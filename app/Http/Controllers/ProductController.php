<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
use App\Models\Category;
use App\Models\Colour;
use App\Models\Product;
use App\Services\AuditService;
use App\Services\Export\TabularExport;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;

class ProductController extends Controller implements HasMiddleware
{
    public function __construct(private AuditService $audit) {}

    public static function middleware(): array
    {
        return ['can:view-products'];
    }

    public function index(Request $request)
    {
        $products = Product::query()
            ->with('category')
            ->search($request->query('search'))
            ->when($request->filled('category_id'), fn ($q) => $q->where('category_id', $request->integer('category_id')))
            ->when($request->query('status') === 'active', fn ($q) => $q->where('is_active', true))
            ->when($request->query('status') === 'inactive', fn ($q) => $q->where('is_active', false))
            ->withCount('orderItems')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('products.index', [
            'products'   => $products,
            'categories' => Category::orderBy('name')->get(),
            'filters'    => $request->only(['search', 'category_id', 'status']),
        ]);
    }

    public function create()
    {
        $this->authorize('create', Product::class);

        return view('products.create', $this->formData() + [
            'product' => new Product(['is_active' => true, 'product_code' => Product::nextProductCode()]),
        ]);
    }

    public function store(StoreProductRequest $request)
    {
        $data = $request->validated();
        $data['product_code'] = $data['product_code'] ?? null ?: Product::nextProductCode();
        $data['is_active'] = $request->boolean('is_active');

        $product = Product::create($data);
        $product->colours()->sync($request->input('colours', []));

        $this->audit->log('product.created', $product, "Created product {$product->name}", null, $data);

        return redirect()->route('products.index')->with('toast', "Product {$product->name} created.");
    }

    public function show(Product $product)
    {
        $this->authorize('view', $product);

        $product->load('category', 'colours');

        // How this product has actually sold, grouped by the snapshotted name.
        $performance = $product->orderItems()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereNull('orders.deleted_at')
            ->selectRaw('COUNT(DISTINCT order_items.order_id) as orders')
            ->selectRaw('COALESCE(SUM(order_items.quantity), 0) as quantity')
            ->selectRaw('COALESCE(SUM(order_items.line_total), 0) as revenue')
            ->selectRaw('COALESCE(AVG(order_items.unit_price), 0) as avg_price')
            ->first();

        $colourMix = $product->orderItems()
            ->whereNotNull('item_colour')
            ->selectRaw('item_colour as name, SUM(quantity) as quantity')
            ->groupBy('item_colour')
            ->orderByDesc('quantity')
            ->limit(10)
            ->get();

        return view('products.show', [
            'product'     => $product,
            'performance' => $performance,
            'colourMix'   => $colourMix,
        ]);
    }

    public function edit(Product $product)
    {
        $this->authorize('update', $product);

        return view('products.edit', $this->formData() + ['product' => $product->load('colours')]);
    }

    public function update(StoreProductRequest $request, Product $product)
    {
        $before = $product->getAttributes();

        $data = $request->validated();
        $data['product_code'] = $data['product_code'] ?? null ?: $product->product_code;
        $data['is_active'] = $request->boolean('is_active');

        $product->update($data);
        $product->colours()->sync($request->input('colours', []));

        $this->audit->logChanges('product.updated', $product, $before, "Updated product {$product->name}");

        return redirect()->route('products.index')->with('toast', "Product {$product->name} updated.");
    }

    /**
     * Products are deactivated rather than removed once they have sold, so
     * historical orders keep a live product link.
     */
    public function destroy(Product $product)
    {
        $this->authorize('delete', $product);

        if ($product->orderItems()->exists()) {
            $product->update(['is_active' => false]);
            $this->audit->log('product.deactivated', $product, "Deactivated product {$product->name} (has order history)");

            return back()->with('toast', "{$product->name} has order history, so it was deactivated instead of deleted.");
        }

        $name = $product->name;
        $product->delete();

        $this->audit->log('product.deleted', $product, "Deleted product {$name}");

        return redirect()->route('products.index')->with('toast', "Product {$name} deleted.");
    }

    public function toggleActive(Product $product)
    {
        $this->authorize('update', $product);

        $product->update(['is_active' => ! $product->is_active]);

        $this->audit->log(
            $product->is_active ? 'product.activated' : 'product.deactivated',
            $product,
            ($product->is_active ? 'Activated' : 'Deactivated') . " product {$product->name}",
        );

        return back()->with('toast', "{$product->name} is now " . ($product->is_active ? 'active' : 'inactive') . '.');
    }

    public function export(Request $request)
    {
        $this->authorize('export-data');

        $query = Product::query()->with('category')->withCount('orderItems')->orderBy('name');

        $headers = ['Product Code', 'Name', 'Category', 'Default Price', 'Status', 'Times Ordered', 'Created'];

        $rows = function () use ($query) {
            foreach ($query->cursor() as $p) {
                yield [
                    $p->product_code, $p->name, $p->category?->name, $p->default_price,
                    $p->is_active ? 'Active' : 'Inactive', $p->order_items_count, $p->created_at?->format('Y-m-d'),
                ];
            }
        };

        return TabularExport::download('products-' . now()->format('Y-m-d'), $headers, $rows(), $request->query('format', 'csv'));
    }

    private function formData(): array
    {
        return [
            'categories' => Category::active()->orderBy('sort_order')->orderBy('name')->get(),
            'colours'    => Colour::active()->orderBy('sort_order')->orderBy('name')->get(),
        ];
    }
}
