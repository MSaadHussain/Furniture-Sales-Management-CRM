<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreColourRequest;
use App\Models\Colour;
use App\Services\AuditService;
use App\Models\OrderItem;
use Illuminate\Routing\Controllers\HasMiddleware;

class ColourController extends Controller implements HasMiddleware
{
    public function __construct(private AuditService $audit) {}

    public static function middleware(): array
    {
        return ['can:view-products'];
    }

    public function index()
    {
        // Lifetime demand per colour, so the master list doubles as a quick
        // read on what actually sells (requirements 23).
        $demand = OrderItem::query()
            ->whereNotNull('colour_id')
            ->selectRaw('colour_id, SUM(quantity) as quantity')
            ->groupBy('colour_id')
            ->pluck('quantity', 'colour_id');

        return view('colours.index', [
            'colours' => Colour::withCount('products')->orderBy('sort_order')->orderBy('name')->get(),
            'demand'  => $demand,
        ]);
    }

    public function store(StoreColourRequest $request)
    {
        $colour = Colour::create($request->validated() + ['is_active' => $request->boolean('is_active', true)]);

        $this->audit->log('colour.created', $colour, "Created colour {$colour->name}");

        return back()->with('toast', "Colour {$colour->name} added.");
    }

    public function update(StoreColourRequest $request, Colour $colour)
    {
        $before = $colour->getAttributes();
        $colour->update($request->validated() + ['is_active' => $request->boolean('is_active')]);

        $this->audit->logChanges('colour.updated', $colour, $before, "Updated colour {$colour->name}");

        return back()->with('toast', "Colour {$colour->name} updated.");
    }

    /**
     * Order items keep a text snapshot of the colour name, so removing a colour
     * here never breaks historical colour reporting.
     */
    public function destroy(Colour $colour)
    {
        $this->authorize('manage-products');

        $name = $colour->name;
        $colour->delete();

        $this->audit->log('colour.deleted', $colour, "Deleted colour {$name}");

        return back()->with('toast', "Colour {$name} removed.");
    }
}
