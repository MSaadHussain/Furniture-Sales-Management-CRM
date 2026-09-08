<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCustomerRequest;
use App\Models\Customer;
use App\Services\AuditService;
use App\Services\Export\TabularExport;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;

class CustomerController extends Controller implements HasMiddleware
{
    public function __construct(private AuditService $audit) {}

    /**
     * Customer records carry personal data, so the whole module is closed to
     * Sales Persons (requirements 3.3 / 45).
     */
    public static function middleware(): array
    {
        return ['can:view-customers'];
    }

    public function index(Request $request)
    {
        $customers = Customer::query()
            ->search($request->query('search'))
            ->when($request->filled('zip_code'), fn ($q) => $q->where('zip_code', 'like', $request->query('zip_code') . '%'))
            ->when($request->filled('city'), fn ($q) => $q->where('city', $request->query('city')))
            ->when($request->query('type') === 'returning', fn ($q) => $q->has('orders', '>', 1))
            ->when($request->query('type') === 'new', fn ($q) => $q->has('orders', '<=', 1))
            // Aggregates come from the database so the list never loads orders.
            ->withCount('orders')
            ->withSum('orders as orders_value', 'grand_total')
            ->withMax('orders as last_order_at', 'order_created_at')
            ->orderBy($this->sortColumn($request), $request->query('direction') === 'asc' ? 'asc' : 'desc')
            ->paginate(20)
            ->withQueryString();

        return view('customers.index', [
            'customers' => $customers,
            'cities'    => Customer::query()->whereNotNull('city')->where('city', '<>', '')
                ->distinct()->orderBy('city')->limit(200)->pluck('city'),
            'filters'   => $request->only(['search', 'zip_code', 'city', 'type', 'sort', 'direction']),
        ]);
    }

    public function create()
    {
        $this->authorize('create', Customer::class);

        return view('customers.create', ['customer' => new Customer()]);
    }

    public function store(StoreCustomerRequest $request)
    {
        $customer = Customer::create($request->validated() + ['created_by' => $request->user()->id]);

        $this->audit->log('customer.created', $customer, "Created customer {$customer->name}", null, $request->validated());

        return redirect()->route('customers.show', $customer)->with('toast', 'Customer created.');
    }

    public function show(Customer $customer)
    {
        $this->authorize('view', $customer);

        $orders = $customer->orders()
            ->with('items')
            ->latest('order_created_at')
            ->paginate(10);

        // Products this customer has bought before (requirements 7.4).
        $products = $customer->orders()
            ->join('order_items', 'order_items.order_id', '=', 'orders.id')
            ->selectRaw('order_items.item_name_snapshot as name, order_items.item_colour as colour')
            ->selectRaw('SUM(order_items.quantity) as quantity')
            ->selectRaw('MAX(orders.order_created_at) as last_bought')
            ->groupBy('order_items.item_name_snapshot', 'order_items.item_colour')
            ->orderByDesc('quantity')
            ->limit(20)
            ->get();

        return view('customers.show', [
            'customer' => $customer,
            'orders'   => $orders,
            'products' => $products,
            'stats'    => [
                'orders'      => $customer->orderCount(),
                'total_spent' => $customer->totalSpent(),
                'first_order' => $customer->firstOrderAt(),
                'last_order'  => $customer->lastOrderAt(),
                'delivered'   => $customer->orders()->whereNotNull('actual_delivery_date')->count(),
            ],
        ]);
    }

    public function edit(Customer $customer)
    {
        $this->authorize('update', $customer);

        return view('customers.edit', ['customer' => $customer]);
    }

    public function update(StoreCustomerRequest $request, Customer $customer)
    {
        $before = $customer->getAttributes();
        $customer->update($request->validated());

        $this->audit->logChanges('customer.updated', $customer, $before, "Updated customer {$customer->name}");

        return redirect()->route('customers.show', $customer)->with('toast', 'Customer updated.');
    }

    public function destroy(Customer $customer)
    {
        $this->authorize('delete', $customer);

        if ($customer->orders()->exists()) {
            return back()->with('error', 'This customer has orders and cannot be deleted. Historical sales data must stay intact.');
        }

        $name = $customer->name;
        $customer->delete();

        $this->audit->log('customer.deleted', $customer, "Deleted customer {$name}");

        return redirect()->route('customers.index')->with('toast', "Customer {$name} deleted.");
    }

    public function export(Request $request)
    {
        $this->authorize('export-data');

        $query = Customer::query()
            ->search($request->query('search'))
            ->withCount('orders')
            ->withSum('orders as orders_value', 'grand_total')
            ->withMax('orders as last_order_at', 'order_created_at')
            ->orderBy('name');

        $headers = ['Customer ID', 'Name', 'Phone', 'Additional Phone', 'Email', 'Address', 'City', 'State', 'ZIP', 'Orders', 'Total Spent', 'Last Order', 'Created'];

        $rows = function () use ($query) {
            foreach ($query->cursor() as $c) {
                yield [
                    $c->id, $c->name, $c->phone, $c->phone_alt, $c->email, $c->address, $c->city, $c->state, $c->zip_code,
                    $c->orders_count, $c->orders_value ?? 0,
                    $c->last_order_at ? \Illuminate\Support\Carbon::parse($c->last_order_at)->format('Y-m-d') : null,
                    $c->created_at?->format('Y-m-d'),
                ];
            }
        };

        return TabularExport::download('customers-' . now()->format('Y-m-d'), $headers, $rows(), $request->query('format', 'csv'));
    }

    /** Whitelisted sort columns, so the query string cannot inject SQL. */
    private function sortColumn(Request $request): string
    {
        return match ($request->query('sort')) {
            'name'          => 'name',
            'orders'        => 'orders_count',
            'value'         => 'orders_value',
            'last_order'    => 'last_order_at',
            default         => 'created_at',
        };
    }
}
