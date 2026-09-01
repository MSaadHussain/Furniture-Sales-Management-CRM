<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Category;
use App\Models\Colour;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Owns order creation and editing. Every monetary figure is recalculated here
 * from the submitted line items, so a tampered total on the client is ignored
 * (requirements 11).
 */
class OrderService
{
    public function __construct(
        private OrderNumberService $numbers,
        private AuditService $audit,
    ) {}

    /* ---------------------------------------------------------------------
     | Create
     |--------------------------------------------------------------------- */

    public function create(array $data): Order
    {
        return DB::transaction(function () use ($data) {
            $customer = $this->resolveCustomer($data);

            $order = new Order([
                'customer_id'             => $customer->id,
                'sales_person_id'         => $data['sales_person_id'] ?? null,
                'requested_delivery_date' => $data['requested_delivery_date'],
                'order_status'            => $data['order_status'] ?? OrderStatus::New->value,
                'payment_method'          => $data['payment_method'] ?? null,
                'zip_code'                => $customer->zip_code,
                'notes'                   => $data['notes'] ?? null,
                'created_by'              => Auth::id(),
                'updated_by'              => Auth::id(),
            ]);

            $order->order_number = $this->numbers->next();
            // Order creation date is stamped by the system, never by the form.
            $order->order_created_at = now();
            $order->save();

            $this->syncItems($order, $data['items'] ?? []);
            $this->applyTotals($order, $data);

            // A back-dated delivery may be recorded straight away when an order
            // is entered after the goods already went out.
            if (! empty($data['actual_delivery_date'])) {
                $order->actual_delivery_date = Carbon::parse($data['actual_delivery_date']);
                $order->order_status = OrderStatus::Delivered;
            }

            $order->save();

            $this->audit->log(
                'order.created',
                $order,
                "Created order {$order->order_number} for {$customer->name}",
                null,
                [
                    'order_number'            => $order->order_number,
                    'customer'                => $customer->name,
                    'grand_total'             => (string) $order->grand_total,
                    'requested_delivery_date' => $order->requested_delivery_date->toDateString(),
                    'items'                   => $order->items()->count(),
                ],
            );

            return $order->fresh(['items', 'customer', 'salesPerson']);
        });
    }

    /* ---------------------------------------------------------------------
     | Update
     |--------------------------------------------------------------------- */

    public function update(Order $order, array $data): Order
    {
        return DB::transaction(function () use ($order, $data) {
            $before             = $order->getAttributes();
            $previousDeliveryAt = $order->requested_delivery_date?->toDateString();

            $customer = $this->resolveCustomer($data, $order);

            $order->fill([
                'customer_id'             => $customer->id,
                'sales_person_id'         => $data['sales_person_id'] ?? null,
                'requested_delivery_date' => $data['requested_delivery_date'],
                'payment_method'          => $data['payment_method'] ?? null,
                'zip_code'                => $customer->zip_code,
                'notes'                   => $data['notes'] ?? null,
                'updated_by'              => Auth::id(),
            ]);

            if (! empty($data['order_status'])) {
                $order->order_status = OrderStatus::from($data['order_status']);
            }

            $order->save();

            $this->syncItems($order, $data['items'] ?? []);
            $this->applyTotals($order, $data);
            $order->save();

            $this->audit->logChanges('order.updated', $order, $before, "Updated order {$order->order_number}");

            // Requirements 34.3: a delivery-date change is audited in its own right.
            $newDeliveryAt = $order->requested_delivery_date?->toDateString();
            if ($previousDeliveryAt !== $newDeliveryAt) {
                $this->audit->log(
                    'order.delivery_date_changed',
                    $order,
                    "Requested delivery date changed on {$order->order_number}",
                    ['requested_delivery_date' => $previousDeliveryAt],
                    ['requested_delivery_date' => $newDeliveryAt],
                );
            }

            return $order->fresh(['items', 'customer', 'salesPerson']);
        });
    }

    /* ---------------------------------------------------------------------
     | Status, payment and delivery transitions
     |--------------------------------------------------------------------- */

    public function changeStatus(Order $order, OrderStatus $status): Order
    {
        $previous = $order->order_status;

        if ($previous === $status) {
            return $order;
        }

        $order->order_status = $status;
        $order->updated_by   = Auth::id();

        // Marking an order delivered without a date stamps today (requirements 14.4).
        if ($status === OrderStatus::Delivered && ! $order->actual_delivery_date) {
            $order->actual_delivery_date = today();
        }

        $order->save();

        $this->audit->log(
            'order.status_changed',
            $order,
            "Order {$order->order_number}: {$previous->label()} to {$status->label()}",
            ['order_status' => $previous->value],
            ['order_status' => $status->value],
        );

        return $order;
    }

    public function updatePayment(Order $order, PaymentStatus $status, ?float $amountPaid, ?string $method): Order
    {
        $before = $order->getAttributes();

        $order->payment_status = $status;
        $order->payment_method = $method ?: $order->payment_method;
        $order->amount_paid    = $this->resolveAmountPaid($status, $amountPaid, (float) $order->grand_total);
        $order->balance_due    = max(0, round((float) $order->grand_total - (float) $order->amount_paid, 2));
        $order->updated_by     = Auth::id();
        $order->save();

        $this->audit->logChanges(
            'order.payment_changed',
            $order,
            $before,
            "Payment updated on {$order->order_number}: " . $status->label(),
        );

        return $order;
    }

    /**
     * Records the actual delivery. The customer requested date is never touched
     * (requirements 14.4 and 59).
     */
    public function recordDelivery(Order $order, Carbon $deliveredOn): Order
    {
        $before = $order->getAttributes();

        $order->actual_delivery_date = $deliveredOn;
        $order->order_status         = OrderStatus::Delivered;
        $order->updated_by           = Auth::id();
        $order->save();

        $performance = $order->deliveryPerformance()->label();

        $this->audit->log(
            'order.delivered',
            $order,
            "Order {$order->order_number} delivered on {$deliveredOn->format('d M Y')} ({$performance})",
            [
                'actual_delivery_date' => $before['actual_delivery_date'] ?? null,
                'order_status'         => $before['order_status'] ?? null,
            ],
            [
                'actual_delivery_date' => $deliveredOn->toDateString(),
                'order_status'         => OrderStatus::Delivered->value,
            ],
        );

        return $order;
    }

    public function cancel(Order $order, ?string $reason): Order
    {
        $previous = $order->order_status;

        $order->order_status        = OrderStatus::Cancelled;
        $order->cancellation_reason = $reason;
        $order->updated_by          = Auth::id();
        $order->save();

        $this->audit->log(
            'order.cancelled',
            $order,
            trim("Order {$order->order_number} cancelled. " . (string) $reason),
            ['order_status' => $previous->value],
            ['order_status' => OrderStatus::Cancelled->value, 'cancellation_reason' => $reason],
        );

        return $order;
    }

    /* ---------------------------------------------------------------------
     | Internals
     |--------------------------------------------------------------------- */

    /**
     * Uses the selected existing customer, falls back to a phone match so a
     * repeat buyer never gets a duplicate record (requirements 7.3), and
     * otherwise creates a new customer from the order form fields.
     */
    private function resolveCustomer(array $data, ?Order $order = null): Customer
    {
        $fields = [
            'name'     => $data['customer_name'] ?? null,
            'phone'    => $data['customer_phone'] ?? null,
            'email'    => $data['customer_email'] ?? null,
            'address'  => $data['customer_address'] ?? null,
            'city'     => $data['customer_city'] ?? null,
            'state'    => $data['customer_state'] ?? null,
            'zip_code' => $data['customer_zip_code'] ?? null,
        ];

        $customer = null;

        if (! empty($data['customer_id'])) {
            $customer = Customer::find($data['customer_id']);
        }

        if (! $customer && ! empty($fields['phone'])) {
            $customer = Customer::findByPhone($fields['phone']);
        }

        if (! $customer && $order) {
            $customer = $order->customer;
        }

        if ($customer) {
            // Refresh details from the form, but never blank out stored values.
            $customer->fill(array_filter($fields, fn ($v) => $v !== null && $v !== ''));

            if ($customer->isDirty()) {
                $customer->save();
            }

            return $customer;
        }

        $fields['created_by'] = Auth::id();
        $customer = Customer::create($fields);

        $this->audit->log('customer.created', $customer, "Created customer {$customer->name} from order entry");

        return $customer;
    }

    /**
     * Replaces the order line items. Product name, colour and category are
     * snapshotted so later catalogue edits cannot rewrite history
     * (requirements 31.6 and 59).
     *
     * @param array<int,array<string,mixed>> $rows
     */
    private function syncItems(Order $order, array $rows): void
    {
        $order->items()->delete();

        $products = Product::with('category')
            ->whereIn('id', array_filter(array_column($rows, 'product_id')))
            ->get()
            ->keyBy('id');

        $colours = Colour::whereIn('id', array_filter(array_column($rows, 'colour_id')))
            ->get()
            ->keyBy('id');

        foreach ($rows as $row) {
            $quantity = (int) ($row['quantity'] ?? 1);
            if ($quantity < 1) {
                continue;
            }

            $product = isset($row['product_id']) && $row['product_id'] !== '' ? $products->get((int) $row['product_id']) : null;
            $colour  = isset($row['colour_id']) && $row['colour_id'] !== '' ? $colours->get((int) $row['colour_id']) : null;

            $name = trim((string) ($row['item_name'] ?? '')) ?: (string) ($product->name ?? '');
            if ($name === '') {
                continue;
            }

            $unitPrice = max(0, Money::parse($row['unit_price'] ?? ($product->default_price ?? 0)));
            $discount  = max(0, Money::parse($row['discount'] ?? 0));
            // A line discount can never exceed the line value.
            $discount  = min($discount, $quantity * $unitPrice);

            // If product does not exist by ID, search by name or create it on the spot
            if (! $product) {
                $product = Product::whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();

                if (! $product) {
                    $category = Category::active()->first() ?? Category::firstOrCreate(
                        ['name' => 'General'],
                        ['slug' => 'general', 'is_active' => true]
                    );

                    $product = Product::create([
                        'name'          => $name,
                        'category_id'   => $category->id,
                        'product_code'  => Product::nextProductCode('FUR'),
                        'default_price' => $unitPrice,
                        'is_active'     => true,
                    ]);
                }
            }

            // If colour is specified as text or not resolved by ID, search by name or create on the spot
            $colourName = trim((string) ($row['item_colour'] ?? ''));
            if (! $colour && $colourName !== '') {
                $colour = Colour::whereRaw('LOWER(name) = ?', [mb_strtolower($colourName)])->first();

                if (! $colour) {
                    $colour = Colour::create([
                        'name'       => $colourName,
                        'is_active'  => true,
                        'sort_order' => 0,
                    ]);
                }
            }

            // Link colour to product shortlist if both exist
            if ($product && $colour) {
                $product->colours()->syncWithoutDetaching([$colour->id]);
            }

            $finalColourName = $colour?->name ?: ($colourName ?: null);

            OrderItem::create([
                'order_id'               => $order->id,
                'product_id'             => $product?->id,
                'colour_id'              => $colour?->id,
                'item_name_snapshot'     => $name,
                'item_colour'            => $finalColourName,
                'category_name_snapshot' => $product?->category?->name,
                'quantity'               => $quantity,
                'unit_price'             => $unitPrice,
                'discount'               => $discount,
                'line_total'             => OrderItem::computeLineTotal($quantity, $unitPrice, $discount),
                'notes'                  => $row['notes'] ?? null,
            ]);
        }
    }

    /**
     * Recomputes every total from the persisted line items.
     * Grand Total = Subtotal - Discount + Delivery Charge + Tax.
     */
    private function applyTotals(Order $order, array $data): void
    {
        $subtotal = (float) $order->items()->sum('line_total');

        $orderDiscount = max(0, Money::parse($data['discount'] ?? 0));
        // An order-level discount cannot push the order below zero.
        $orderDiscount = min($orderDiscount, $subtotal);

        $deliveryCharge = max(0, Money::parse($data['delivery_charge'] ?? 0));
        $tax            = max(0, Money::parse($data['tax'] ?? 0));

        $grandTotal = round($subtotal - $orderDiscount + $deliveryCharge + $tax, 2);

        $paymentStatus = ! empty($data['payment_status'])
            ? PaymentStatus::from($data['payment_status'])
            : ($order->payment_status ?? PaymentStatus::Pending);

        $amountPaid = $this->resolveAmountPaid(
            $paymentStatus,
            isset($data['amount_paid']) ? Money::parse($data['amount_paid']) : null,
            $grandTotal,
        );

        $order->subtotal        = $subtotal;
        $order->discount        = $orderDiscount;
        $order->delivery_charge = $deliveryCharge;
        $order->tax             = $tax;
        $order->grand_total     = $grandTotal;
        $order->payment_status  = $paymentStatus;
        $order->amount_paid     = $amountPaid;
        $order->balance_due     = max(0, round($grandTotal - $amountPaid, 2));
    }

    /**
     * Keeps amount paid consistent with the chosen payment status so the two
     * can never contradict each other in reports.
     */
    private function resolveAmountPaid(PaymentStatus $status, ?float $amountPaid, float $grandTotal): float
    {
        return match ($status) {
            PaymentStatus::Paid     => $grandTotal,
            PaymentStatus::Pending  => 0.0,
            PaymentStatus::Refunded => 0.0,
            PaymentStatus::Partial  => max(0, min(round((float) $amountPaid, 2), $grandTotal)),
        };
    }
}
