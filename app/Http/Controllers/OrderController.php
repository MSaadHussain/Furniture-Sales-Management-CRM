<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateOrderRequest;
use App\Models\Category;
use App\Models\Colour;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\AuditService;
use App\Services\Export\TabularExport;
use App\Services\OrderService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Carbon;

class OrderController extends Controller implements HasMiddleware
{
    public function __construct(
        private OrderService $orders,
        private AuditService $audit,
    ) {}

    /** Sales Persons never reach any order screen (requirements 3.3). */
    public static function middleware(): array
    {
        return ['can:view-orders'];
    }

    /* ---------------------------------------------------------------------
     | List
     |--------------------------------------------------------------------- */

    public function index(Request $request)
    {
        [$sort, $direction] = $this->resolveSort($request);

        $orders = $this->sorted($this->filtered($request), $sort, $direction)
            ->with(['customer', 'salesPerson', 'items'])
            ->paginate(20)
            ->withQueryString();

        // Totals for the whole filtered set, not just the visible page.
        $totals = (clone $this->filtered($request))
            ->countable()
            ->selectRaw('COUNT(*) as orders, COALESCE(SUM(grand_total), 0) as revenue, COALESCE(SUM(balance_due), 0) as outstanding')
            ->first();

        return view('orders.index', [
            'orders'       => $orders,
            'totals'       => $totals,
            'salesPersons' => User::selectableSalesPersons()->get(['id', 'name']),
            'categories'   => Category::active()->orderBy('name')->get(['id', 'name']),
            'filters'      => $request->only($this->filterKeys()),
        ]);
    }

    /* ---------------------------------------------------------------------
     | Create / edit
     |--------------------------------------------------------------------- */

    public function create(Request $request)
    {
        $this->authorize('create', Order::class);

        $customer = $request->filled('customer')
            ? Customer::find($request->integer('customer'))
            : null;

        return view('orders.create', $this->formData() + [
            'order'    => new Order([
                'order_status'            => OrderStatus::New,
                'payment_status'          => PaymentStatus::Pending,
                'requested_delivery_date' => today()->addDay(),
            ]),
            'customer' => $customer,
        ]);
    }

    public function store(StoreOrderRequest $request)
    {
        $order = $this->orders->create($request->validated());

        return redirect()
            ->route('orders.show', $order)
            ->with('toast', "Order {$order->order_number} created successfully.");
    }

    public function show(Order $order)
    {
        $this->authorize('view', $order);

        $order->load(['items.product', 'items.colour', 'customer', 'salesPerson', 'creator', 'updater']);

        return view('orders.show', [
            'order'   => $order,
            'history' => $order->customer
                ? $order->customer->orders()->whereKeyNot($order->id)->latest('order_created_at')->limit(5)->get()
                : collect(),
        ]);
    }

    public function edit(Order $order)
    {
        $this->authorize('update', $order);

        $order->load(['items.product', 'items.colour', 'customer']);

        return view('orders.edit', $this->formData() + [
            'order'    => $order,
            'customer' => $order->customer,
        ]);
    }

    public function update(UpdateOrderRequest $request, Order $order)
    {
        $order = $this->orders->update($order, $request->validated());

        return redirect()
            ->route('orders.show', $order)
            ->with('toast', "Order {$order->order_number} updated.");
    }

    /**
     * Permanently removes an order and its line items. Admin only, and there is
     * no undo: the row is gone, not soft-deleted.
     *
     * A snapshot goes into the audit log first, so the trail still records who
     * removed what even though the order itself no longer exists (28). The
     * order number is not recycled, thanks to the high-water mark in
     * OrderNumberService.
     */
    public function destroy(Order $order)
    {
        $this->authorize('delete', $order);

        $order->loadMissing(['customer', 'salesPerson', 'items']);

        $snapshot = [
            'order_number'            => $order->order_number,
            'customer'                => $order->customer?->name,
            'customer_phone'          => $order->customer?->phone,
            'zip_code'                => $order->zip_code,
            'sales_person'            => $order->salesPerson?->name,
            'order_created_at'        => $order->order_created_at?->toDateTimeString(),
            'requested_delivery_date' => $order->requested_delivery_date?->toDateString(),
            'actual_delivery_date'    => $order->actual_delivery_date?->toDateString(),
            'order_status'            => $order->order_status?->value,
            'payment_status'          => $order->payment_status?->value,
            'grand_total'             => (string) $order->grand_total,
            'items'                   => $order->items
                ->map(fn ($i) => "{$i->quantity} x {$i->item_name_snapshot}" . ($i->item_colour ? " ({$i->item_colour})" : ''))
                ->implode(', '),
        ];

        $number = $order->order_number;

        $this->audit->log(
            'order.force_deleted',
            null,
            "Permanently deleted order {$number}",
            $snapshot,
            null,
        );

        // Line items go with it: order_items.order_id cascades on delete.
        $order->forceDelete();

        return redirect()
            ->route('orders.index')
            ->with('toast', "Order {$number} was permanently deleted.");
    }

    /* ---------------------------------------------------------------------
     | Transitions
     |--------------------------------------------------------------------- */

    public function updateStatus(Request $request, Order $order)
    {
        $this->authorize('changeStatus', $order);

        $data = $request->validate([
            'order_status' => ['required', 'string', 'in:' . implode(',', array_column(OrderStatus::cases(), 'value'))],
        ]);

        $status = OrderStatus::from($data['order_status']);

        // Cancelling has its own audit trail and reason, so route it there.
        if ($status === OrderStatus::Cancelled) {
            $this->orders->cancel($order, $request->input('cancellation_reason'));

            return back()->with('toast', "Order {$order->order_number} cancelled.");
        }

        $this->orders->changeStatus($order, $status);

        return back()->with('toast', "Order status set to {$status->label()}.");
    }

    public function updatePayment(Request $request, Order $order)
    {
        $this->authorize('update', $order);

        $data = $request->validate([
            'payment_status' => ['required', 'in:' . implode(',', array_column(PaymentStatus::cases(), 'value'))],
            'payment_method' => ['nullable', 'in:' . implode(',', array_column(PaymentMethod::cases(), 'value'))],
            'amount_paid'    => ['nullable', 'numeric', 'min:0'],
        ]);

        $this->orders->updatePayment(
            $order,
            PaymentStatus::from($data['payment_status']),
            isset($data['amount_paid']) ? (float) $data['amount_paid'] : null,
            $data['payment_method'] ?? null,
        );

        return back()->with('toast', 'Payment details updated.');
    }

    /** Records the actual delivery date without touching the requested date. */
    public function recordDelivery(Request $request, Order $order)
    {
        $this->authorize('recordDelivery', $order);

        $data = $request->validate([
            'actual_delivery_date' => ['required', 'date', 'before_or_equal:today'],
        ], [
            'actual_delivery_date.before_or_equal' => 'The actual delivery date cannot be in the future.',
        ]);

        $order = $this->orders->recordDelivery($order, Carbon::parse($data['actual_delivery_date']));

        return back()->with('toast', "Delivery recorded ({$order->deliveryPerformance()->label()}).");
    }

    public function cancel(Request $request, Order $order)
    {
        $this->authorize('cancel', $order);

        $data = $request->validate([
            'cancellation_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $this->orders->cancel($order, $data['cancellation_reason'] ?? null);

        return back()->with('toast', "Order {$order->order_number} cancelled.");
    }

    /* ---------------------------------------------------------------------
     | Order-form helpers (JSON)
     |--------------------------------------------------------------------- */

    /**
     * Duplicate-customer lookup for the order form (requirements 7.3).
     * Returns the matched customer plus a short order history so the operator
     * can confirm before reusing the record.
     */
    public function lookupCustomer(Request $request)
    {
        // Release session lock immediately so concurrent browser requests don't block
        session()->save();

        $customer = Customer::findByPhone($request->query('phone'));

        if (! $customer) {
            return response()->json(['found' => false]);
        }

        $orders = $customer->orders()
            ->with(['items'])
            ->latest('order_created_at')
            ->limit(10)
            ->get()
            ->map(fn ($o) => [
                'id'                      => $o->id,
                'order_number'            => $o->display_number,
                'raw_order_number'        => $o->order_number,
                'created_at'              => $o->order_created_at?->format('d M Y h:i A') ?? $o->created_at?->format('d M Y h:i A'),
                'requested_delivery_date' => $o->requested_delivery_date?->format('d M Y'),
                'order_status'            => $o->order_status->label(),
                'payment_status'          => $o->payment_status->label(),
                'grand_total'             => Money::format($o->grand_total),
                'total_quantity'          => $o->totalQuantity(),
                'items'                   => $o->items->map(fn ($item) => [
                    'name'        => $item->item_name_snapshot,
                    'colour'      => $item->item_colour,
                    'quantity'    => $item->quantity,
                    'unit_price'  => Money::format($item->unit_price),
                    'line_total'  => Money::format($item->line_total),
                ]),
            ]);

        $stats = $customer->orders()
            ->selectRaw('COUNT(*) as total_orders, COALESCE(SUM(grand_total), 0) as total_spent, MAX(order_created_at) as last_order')
            ->first();

        return response()->json([
            'found'    => true,
            'customer' => [
                'id'       => $customer->id,
                'name'     => $customer->name,
                'phone'    => $customer->phone,
                'phone_alt' => $customer->phone_alt,
                'email'    => $customer->email,
                'address'  => $customer->address,
                'city'     => $customer->city,
                'state'    => $customer->state,
                'zip_code' => $customer->zip_code,
            ],
            'orders'       => (int) ($stats->total_orders ?? 0),
            'total_spent'  => Money::format((float) ($stats->total_spent ?? 0)),
            'last_order'   => !empty($stats->last_order) ? Carbon::parse($stats->last_order)->format('d M Y') : null,
            'orders_list'  => $orders,
            'url'          => route('customers.show', $customer),
        ]);
    }

    /** Typeahead over the customer list for the order form. */
    public function searchCustomers(Request $request)
    {
        // Release session lock immediately so concurrent browser requests don't block
        session()->save();

        $customers = Customer::search($request->query('q'))
            ->orderBy('name')
            ->limit(15)
            ->get(['id', 'name', 'phone', 'address', 'city', 'state', 'zip_code', 'email']);

        return response()->json($customers);
    }

    /** Product picker data: price and the colours offered for one product. */
    public function productDetails(Product $product)
    {
        $product->load('colours', 'category');

        return response()->json([
            'id'            => $product->id,
            'name'          => $product->name,
            'product_code'  => $product->product_code,
            'default_price' => (float) $product->default_price,
            'category'      => $product->category?->name,
            'colours'       => $product->availableColours()->map(fn (Colour $c) => [
                'id'   => $c->id,
                'name' => $c->name,
                'hex'  => $c->swatch(),
            ])->values(),
        ]);
    }

    /* ---------------------------------------------------------------------
     | Export
     |--------------------------------------------------------------------- */

    public function export(Request $request)
    {
        $this->authorize('export-data');

        $query = $this->filtered($request)->with(['customer', 'salesPerson'])->latest('order_created_at');

        $headers = [
            'Order Number', 'Created', 'Requested Delivery', 'Actual Delivery', 'Delivery Performance',
            'Customer', 'Phone', 'ZIP', 'City', 'Sales Person',
            'Items', 'Subtotal', 'Discount', 'Delivery Charge', 'Tax', 'Grand Total',
            'Amount Paid', 'Balance Due', 'Payment Status', 'Payment Method', 'Order Status',
        ];

        $rows = function () use ($query) {
            foreach ($query->cursor() as $order) {
                yield [
                    $order->order_number,
                    $order->order_created_at?->format('Y-m-d H:i'),
                    $order->requested_delivery_date?->format('Y-m-d'),
                    $order->actual_delivery_date?->format('Y-m-d'),
                    $order->deliveryPerformance()->label(),
                    $order->customer?->name,
                    $order->customer?->phone,
                    $order->zip_code,
                    $order->customer?->city,
                    $order->salesPerson?->name,
                    $order->totalQuantity(),
                    $order->subtotal,
                    $order->discount,
                    $order->delivery_charge,
                    $order->tax,
                    $order->grand_total,
                    $order->amount_paid,
                    $order->balance_due,
                    $order->payment_status->label(),
                    $order->payment_method?->label(),
                    $order->order_status->label(),
                ];
            }
        };

        return TabularExport::download('orders-' . now()->format('Y-m-d'), $headers, $rows(), $request->query('format', 'csv'));
    }

    /* ---------------------------------------------------------------------
     | Filtering
     |--------------------------------------------------------------------- */

    private function filterKeys(): array
    {
        return [
            'search', 'order_status', 'payment_status', 'sales_person_id',
            'zip_code', 'category_id', 'from', 'to', 'delivery_from', 'delivery_to', 'performance',
            'sort', 'direction',
        ];
    }

    /* ---------------------------------------------------------------------
     | Sorting
     |--------------------------------------------------------------------- */

    /**
     * Sortable columns, mapped to what they actually order by. Whitelisted so
     * the query string can never reach raw SQL.
     *
     * Customer and Sales Person sort on a correlated subquery rather than a
     * join, so the row count and the pagination totals stay correct.
     */
    public const SORTS = [
        'order_number'  => 'orders.order_number',
        'created'       => 'orders.order_created_at',
        'customer'      => 'customer_name',
        'zip_code'      => 'orders.zip_code',
        'sales_person'  => 'sales_person_name',
        'requested'     => 'orders.requested_delivery_date',
        'delivered'     => 'orders.actual_delivery_date',
        'order_status'  => 'orders.order_status',
        'payment_status'=> 'orders.payment_status',
        'total'         => 'orders.grand_total',
        'balance'       => 'orders.balance_due',
    ];

    /** @return array{0:string,1:string} the active column key and direction */
    private function resolveSort(Request $request): array
    {
        $sort = (string) $request->query('sort', 'created');

        if (! array_key_exists($sort, self::SORTS)) {
            $sort = 'created';
        }

        $direction = strtolower((string) $request->query('direction')) === 'asc' ? 'asc' : 'desc';

        return [$sort, $direction];
    }

    private function sorted($query, string $sort, string $direction)
    {
        $column = self::SORTS[$sort];

        // The two name columns are not on the orders table.
        if ($column === 'customer_name') {
            $query->orderBy(
                Customer::select('name')->whereColumn('customers.id', 'orders.customer_id'),
                $direction,
            );
        } elseif ($column === 'sales_person_name') {
            $query->orderBy(
                User::select('name')->whereColumn('users.id', 'orders.sales_person_id'),
                $direction,
            );
        } else {
            $query->orderBy($column, $direction);
        }

        // Stable tiebreak so paging never repeats or drops a row when the
        // sorted column holds duplicates.
        return $query->orderBy('orders.id', 'desc');
    }

    /** Server-side filtering only; nothing is filtered in the browser (30). */
    private function filtered(Request $request)
    {
        return Order::query()
            ->search($request->query('search'))
            ->when($request->filled('order_status'), fn ($q) => $q->where('order_status', $request->query('order_status')))
            ->when($request->filled('payment_status'), fn ($q) => $q->where('payment_status', $request->query('payment_status')))
            ->when($request->filled('sales_person_id'), fn ($q) => $q->where('sales_person_id', $request->integer('sales_person_id')))
            ->when($request->filled('zip_code'), fn ($q) => $q->where('zip_code', 'like', $request->query('zip_code') . '%'))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('order_created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('order_created_at', '<=', $request->date('to')))
            ->when($request->filled('delivery_from'), fn ($q) => $q->whereDate('requested_delivery_date', '>=', $request->date('delivery_from')))
            ->when($request->filled('delivery_to'), fn ($q) => $q->whereDate('requested_delivery_date', '<=', $request->date('delivery_to')))
            ->when($request->filled('category_id'), fn ($q) => $q->whereHas('items.product', fn ($p) => $p->where('category_id', $request->integer('category_id'))))
            ->when($request->query('performance') === 'on_time', fn ($q) => $q->whereNotNull('actual_delivery_date')->whereColumn('actual_delivery_date', '<=', 'requested_delivery_date'))
            ->when($request->query('performance') === 'late', fn ($q) => $q->whereNotNull('actual_delivery_date')->whereColumn('actual_delivery_date', '>', 'requested_delivery_date'))
            ->when($request->query('performance') === 'pending', fn ($q) => $q->whereNull('actual_delivery_date'));
    }

    /** Shared select options for the create/edit order form. */
    private function formData(): array
    {
        return [
            'products'     => Product::active()->with(['category', 'colours'])->orderBy('name')->get(),
            'colours'      => Colour::active()->orderBy('sort_order')->orderBy('name')->get(),
            'salesPersons' => User::selectableSalesPersons()->get(['id', 'name', 'role']),
            'statuses'     => OrderStatus::cases(),
            'paymentStatuses' => PaymentStatus::cases(),
            'paymentMethods'  => PaymentMethod::cases(),
        ];
    }
}
