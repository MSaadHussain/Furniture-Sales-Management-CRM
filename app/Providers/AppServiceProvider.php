<?php

namespace App\Providers;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Policies\CustomerPolicy;
use App\Policies\OrderPolicy;
use App\Policies\ProductPolicy;
use App\Models\Setting;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Gate::policy(Order::class, OrderPolicy::class);
        Gate::policy(Customer::class, CustomerPolicy::class);
        Gate::policy(Product::class, ProductPolicy::class);

        $this->defineGates();

        // The header shows how many deliveries are scheduled for tomorrow. Cached
        // briefly so it costs one indexed count per minute, not one per page.
        View::composer('layouts.partials.header', function ($view) {
            $view->with('tomorrowDeliveryCount', Cache::remember(
                'deliveries.tomorrow.count.' . today()->addDay()->toDateString(),
                now()->addMinute(),
                fn () => Order::query()->forDeliveryDate(today()->addDay())->open()->count(),
            ));
            $view->with('todayDeliveryCount', Cache::remember(
                'deliveries.today.count.' . today()->toDateString(),
                now()->addMinute(),
                fn () => Order::query()->forDeliveryDate(today())->open()->count(),
            ));
        });

        // Login throttling (requirements 4.4). Keyed on email plus IP so one
        // attacker cannot lock every account from a single address.
        RateLimiter::for('login', function (Request $request) {
            $key = strtolower((string) $request->input('email')) . '|' . $request->ip();

            return [Limit::perMinute(5)->by($key), Limit::perMinute(30)->by($request->ip())];
        });
    }

    /**
     * Capability gates. Admin has everything. Manager runs day-to-day
     * operations but is denied destructive/system-level actions unless an Admin
     * turns them on in Settings (requirements 3.2). Sales Persons reach nothing
     * beyond the dashboard (requirements 3.3).
     */
    private function defineGates(): void
    {
        // Admin bypasses the capability gates below. It deliberately does NOT
        // bypass a check about a specific record, so record-level rules (for
        // example: a cancelled order is frozen) still apply to Admins.
        Gate::before(function (User $user, string $ability, array $arguments = []) {
            if (! $user->isAdmin() || ! $user->is_active) {
                return null;
            }

            return isset($arguments[0]) && is_object($arguments[0]) ? null : true;
        });

        $management = fn (User $user) => $user->is_active && $user->role->isManagement();

        // Managers get these as standard.
        Gate::define('view-orders', $management);
        Gate::define('manage-orders', $management);
        Gate::define('view-customers', $management);
        Gate::define('view-products', $management);
        Gate::define('manage-deliveries', $management);
        Gate::define('view-reports', $management);

        // Optional manager rights, off unless an Admin enables them.
        Gate::define('cancel-orders', fn (User $u) => $management($u) && self::managerMay('cancel_orders'));
        Gate::define('delete-orders', fn (User $u) => false);
        Gate::define('manage-customers', fn (User $u) => $management($u) && self::managerMay('manage_customers'));
        Gate::define('manage-products', fn (User $u) => $management($u) && self::managerMay('manage_products'));
        Gate::define('export-data', fn (User $u) => false);

        // Admin-only capabilities. The Gate::before above lets Admins through;
        // everyone else is refused here.
        Gate::define('manage-users', fn (User $u) => false);
        Gate::define('manage-settings', fn (User $u) => false);
        Gate::define('view-audit', fn (User $u) => false);
        Gate::define('view-activity', fn (User $u) => false);
    }

    /** Reads an Admin-granted manager permission from Settings. */
    private static function managerMay(string $permission, bool $default = false): bool
    {
        return (bool) Setting::get('manager_can_' . $permission, $default);
    }
}
