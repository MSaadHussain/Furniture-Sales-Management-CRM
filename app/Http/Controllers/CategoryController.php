<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCategoryRequest;
use App\Models\Category;
use App\Services\AuditService;
use Illuminate\Routing\Controllers\HasMiddleware;

class CategoryController extends Controller implements HasMiddleware
{
    public function __construct(private AuditService $audit) {}

    public static function middleware(): array
    {
        return ['can:view-products'];
    }

    public function index()
    {
        return view('categories.index', [
            'categories' => Category::withCount('products')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->paginate(30),
        ]);
    }

    public function store(StoreCategoryRequest $request)
    {
        $category = Category::create($request->validated() + ['is_active' => $request->boolean('is_active', true)]);

        $this->audit->log('category.created', $category, "Created category {$category->name}");

        return back()->with('toast', "Category {$category->name} created.");
    }

    public function update(StoreCategoryRequest $request, Category $category)
    {
        $before = $category->getAttributes();
        $category->update($request->validated() + ['is_active' => $request->boolean('is_active')]);

        $this->audit->logChanges('category.updated', $category, $before, "Updated category {$category->name}");

        return back()->with('toast', "Category {$category->name} updated.");
    }

    /** A category holding products is deactivated rather than deleted. */
    public function destroy(Category $category)
    {
        $this->authorize('manage-products');

        if ($category->products()->exists()) {
            $category->update(['is_active' => false]);

            return back()->with('toast', "{$category->name} still has products, so it was deactivated instead of deleted.");
        }

        $name = $category->name;
        $category->delete();

        $this->audit->log('category.deleted', $category, "Deleted category {$name}");

        return back()->with('toast', "Category {$name} deleted.");
    }
}
