<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Colour;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Models\UserActivityLog;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * System Data Reset (Admin only).
 * Allows an authorized administrator to permanently wipe all operational data
 * (orders, customers, logs, and optionally catalogue items) following a strict
 * multi-step confirmation flow and password re-authentication.
 */
class SystemResetController extends Controller implements HasMiddleware
{
    public function __construct(private AuditService $audit) {}

    public static function middleware(): array
    {
        return ['can:manage-settings'];
    }

    /**
     * Display the System Reset screen with live counts of data subject to wipe.
     */
    public function index(Request $request)
    {
        if (! $request->user()->isAdmin()) {
            abort(403, 'Only administrators can perform a system data reset.');
        }

        $stats = [
            'orders'     => Order::count(),
            'items'      => OrderItem::count(),
            'customers'  => Customer::count(),
            'products'   => Product::count(),
            'categories' => Category::count(),
            'colours'    => Colour::count(),
            'staff'      => User::where('role', '!=', UserRole::Admin->value)->count(),
            'logs'       => AuditLog::count() + UserActivityLog::count(),
        ];

        return view('settings.reset', [
            'stats' => $stats,
        ]);
    }

    /**
     * Execute the system reset after validating confirmation phrase and admin password re-login.
     */
    public function reset(Request $request)
    {
        $user = $request->user();

        if (! $user->isAdmin()) {
            abort(403, 'Only administrators can perform a system data reset.');
        }

        $request->validate([
            'confirm_understanding' => ['required', 'accepted'],
            'confirm_irreversible'  => ['required', 'accepted'],
            'confirm_phrase'        => ['required', 'string'],
            'password'              => ['required', 'string'],
            'wipe_catalogue'        => ['nullable', 'boolean'],
            'wipe_staff'            => ['nullable', 'boolean'],
        ], [
            'confirm_understanding.accepted' => 'You must check the confirmation acknowledgment.',
            'confirm_irreversible.accepted'  => 'You must acknowledge that this action cannot be undone.',
            'confirm_phrase.required'        => 'Please type the confirmation phrase DELETE ALL DATA.',
            'password.required'              => 'Your admin password is required to re-authenticate.',
        ]);

        // Confirmation Step 2: Validate exact phrase
        $typedPhrase = trim(strtoupper((string) $request->input('confirm_phrase')));
        if ($typedPhrase !== 'DELETE ALL DATA') {
            return back()
                ->withInput()
                ->withErrors(['confirm_phrase' => 'The confirmation phrase does not match. You must type DELETE ALL DATA exactly.']);
        }

        // Confirmation Step 3: Re-authenticate with current admin password
        if (! Hash::check($request->input('password'), $user->password)) {
            return back()
                ->withInput()
                ->withErrors(['password' => 'Incorrect password. Re-authentication failed.']);
        }

        $wipeCatalogue = $request->boolean('wipe_catalogue');
        $wipeStaff     = $request->boolean('wipe_staff');

        DB::transaction(function () use ($wipeCatalogue, $wipeStaff, $user) {
            // 1. Delete all order items & orders permanently
            DB::table('order_items')->delete();
            DB::table('orders')->delete();

            // 2. Delete all customer records permanently
            DB::table('customers')->delete();

            // 3. Optional: Wipe catalogue (products, colours, categories)
            if ($wipeCatalogue) {
                DB::table('product_colour')->delete();
                DB::table('products')->delete();
                DB::table('colours')->delete();
                DB::table('categories')->delete();
            }

            // 4. Optional: Wipe non-admin staff accounts
            if ($wipeStaff) {
                DB::table('users')
                    ->where('role', '!=', UserRole::Admin->value)
                    ->where('id', '!=', $user->id)
                    ->delete();
            }

            // 5. Clear logs and caches
            DB::table('audit_logs')->delete();
            DB::table('user_activity_logs')->delete();
            Cache::flush();

            // 6. Record fresh audit record of system wipe
            $this->audit->log(
                'system.reset',
                null,
                "Full system data reset executed by Admin {$user->name} ({$user->email}). Operational data wiped.",
                [],
                [
                    'wipe_catalogue' => $wipeCatalogue,
                    'wipe_staff'     => $wipeStaff,
                    'executed_by'    => $user->id,
                    'executed_at'    => now()->toIso8601String(),
                ]
            );
        });

        return redirect()->route('dashboard')->with('toast', 'System reset complete. All operational data has been successfully wiped.');
    }
}
