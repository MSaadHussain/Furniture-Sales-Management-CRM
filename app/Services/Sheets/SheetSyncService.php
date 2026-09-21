<?php

namespace App\Services\Sheets;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Mirrors orders, customers and products into a Google Sheet.
 *
 * Nothing here runs inline with a web request: rows are flagged dirty by the
 * observers (`sheet_synced_at` set back to NULL) and drained by the
 * `sheets:sync` command on the existing cron tick, so a slow or unreachable
 * Google never holds up saving an order (requirements 37: no daemon).
 *
 * The sheet is upserted, never appended blindly. Column A of every tab holds
 * the CRM row id; the script matches on it, so editing an order updates its
 * existing line instead of adding a second one.
 */
class SheetSyncService
{
    /** Entity key => model class. The tab name is the ucfirst of the key. */
    public const ENTITIES = [
        'orders'    => Order::class,
        'customers' => Customer::class,
        'products'  => Product::class,
    ];

    public function __construct(private SheetsWebAppClient $client) {}

    public function configured(): bool
    {
        return $this->client->configured();
    }

    /** Rows still waiting to be written, per entity. */
    public function pendingCounts(): array
    {
        $counts = [];

        foreach (array_keys(self::ENTITIES) as $entity) {
            $counts[$entity] = $this->dirtyQuery($entity)->count();
        }

        $counts['deletions'] = DB::table('sheet_sync_deletions')->count();

        return $counts;
    }

    /**
     * Pushes one batch. Returns how many rows went out, so the command can
     * keep calling until it reports zero.
     */
    public function syncBatch(?int $limit = null): int
    {
        $limit   = $limit ?: (int) config('sheets.batch_size');
        $payload = ['action' => 'sync', 'sheets' => []];
        $written = [];
        $total   = 0;

        foreach (array_keys(self::ENTITIES) as $entity) {
            $models    = $this->dirtyQuery($entity)->limit($limit)->get();
            $deletions = $this->deletionIds($entity);

            if ($models->isEmpty() && $deletions === []) {
                continue;
            }

            $payload['sheets'][$this->tab($entity)] = [
                'headers' => $this->headers($entity),
                'rows'    => $models->map(fn (Model $model) => $this->row($entity, $model))->all(),
                'deletes' => $deletions,
            ];

            $written[$entity] = $models->pluck('id')->all();
            $total += $models->count() + count($deletions);
        }

        if ($total === 0) {
            return 0;
        }

        $this->client->post($payload);

        // Stamped only once Apps Script has confirmed the write, so a failed
        // call simply leaves the rows dirty for the next tick.
        foreach ($written as $entity => $ids) {
            if ($ids !== []) {
                DB::table($entity)->whereIn('id', $ids)->update(['sheet_synced_at' => now()]);
            }

            DB::table('sheet_sync_deletions')->where('entity', $entity)->delete();
        }

        return $total;
    }

    /** Marks everything dirty so the next run rewrites the entire sheet. */
    public function markAllDirty(): void
    {
        foreach (array_keys(self::ENTITIES) as $entity) {
            DB::table($entity)->update(['sheet_synced_at' => null]);
        }
    }

    public function tab(string $entity): string
    {
        return ucfirst($entity);
    }

    /* ---------------------------------------------------------------------
     | Dirty-row selection
     |--------------------------------------------------------------------- */

    private function dirtyQuery(string $entity): Builder
    {
        $class = self::ENTITIES[$entity];

        /** @var Builder $query */
        $query = $class::query()->whereNull('sheet_synced_at')->orderBy('id');

        return match ($entity) {
            'orders'   => $query->with(['customer', 'salesPerson', 'items']),
            'products' => $query->with(['category', 'colours']),
            default    => $query,
        };
    }

    /** @return list<int> */
    private function deletionIds(string $entity): array
    {
        return DB::table('sheet_sync_deletions')
            ->where('entity', $entity)
            ->pluck('entity_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /* ---------------------------------------------------------------------
     | Row mapping. Column A is always the CRM id: it is what the script
     | matches on, so its position must not change.
     |--------------------------------------------------------------------- */

    public function headers(string $entity): array
    {
        return match ($entity) {
            'orders' => [
                'ID', 'Order No', 'Order Date', 'Customer', 'Phone', 'Address', 'ZIP',
                'Items', 'No. of Orders', 'Sales Person', 'Source',
                'Delivery Date', 'Delivered On', 'Order Status', 'Payment Status',
                'Subtotal', 'Discount', 'Delivery Charge', 'Tax', 'Grand Total',
                'Amount Paid', 'Balance Due', 'Notes',
            ],
            'customers' => [
                'ID', 'Name', 'Phone', 'Alt Phone', 'Email',
                'Address', 'City', 'State', 'ZIP', 'Orders', 'Notes', 'Added On',
            ],
            'products' => [
                'ID', 'Code', 'Name', 'Category', 'Colours', 'Default Price', 'Active', 'Added On',
            ],
        };
    }

    private function row(string $entity, Model $model): array
    {
        return match ($entity) {
            'orders'    => $this->orderRow($model),
            'customers' => $this->customerRow($model),
            'products'  => $this->productRow($model),
        };
    }

    private function orderRow(Order $order): array
    {
        $items = $order->items->map(function (OrderItem $item) {
            $colour = $item->item_colour ? ' (' . $item->item_colour . ')' : '';
            $qty    = $item->quantity > 1 ? ' x' . $item->quantity : '';

            return $item->item_name_snapshot . $colour . $qty;
        })->implode(', ');

        return [
            $order->id,
            $order->order_number,
            $order->order_created_at?->toDateString(),
            $order->customer?->name,
            $order->customer?->phone,
            $order->deliveryAddress(),
            $order->zip_code,
            $items,
            $order->number_of_orders,
            $order->salesPerson?->name,
            $order->order_source,
            $order->requested_delivery_date?->toDateString(),
            $order->actual_delivery_date?->toDateString(),
            $order->order_status?->value,
            $order->payment_status?->value,
            // Plain numbers, not formatted money, so the sheet can total them.
            (float) $order->subtotal,
            (float) $order->discount,
            (float) $order->delivery_charge,
            (float) $order->tax,
            (float) $order->grand_total,
            (float) $order->amount_paid,
            (float) $order->balance_due,
            $order->notes,
        ];
    }

    private function customerRow(Customer $customer): array
    {
        return [
            $customer->id,
            $customer->name,
            $customer->phone,
            $customer->phone_alt,
            $customer->email,
            $customer->address,
            $customer->city,
            $customer->state,
            $customer->zip_code,
            $customer->orders()->count(),
            $customer->notes,
            $customer->created_at?->toDateString(),
        ];
    }

    private function productRow(Product $product): array
    {
        return [
            $product->id,
            $product->product_code,
            $product->name,
            $product->category?->name,
            $product->colours->pluck('name')->implode(', '),
            (float) $product->default_price,
            $product->is_active ? 'Yes' : 'No',
            $product->created_at?->toDateString(),
        ];
    }
}
