<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ColourController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SecurityController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('dashboard'));

Route::middleware(['auth', 'verified', 'password.24h', 'track.activity'])->group(function () {

    /*
    |----------------------------------------------------------------------
    | Dashboard — the only screen a Sales Person can reach
    |----------------------------------------------------------------------
    */
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    /*
    |----------------------------------------------------------------------
    | Sales / Orders
    |----------------------------------------------------------------------
    | Fixed paths are declared before the {order} wildcard so /orders/export
    | is not swallowed by the resource route.
    */
    Route::get('orders/export', [OrderController::class, 'export'])->name('orders.export');
    Route::get('orders/lookup/customer', [OrderController::class, 'lookupCustomer'])->name('orders.lookup.customer');
    Route::get('orders/lookup/customers', [OrderController::class, 'searchCustomers'])->name('orders.lookup.customers');
    Route::get('orders/lookup/product/{product}', [OrderController::class, 'productDetails'])->name('orders.lookup.product');
    Route::resource('orders', OrderController::class);
    Route::match(['post', 'patch'], 'orders/{order}/status', [OrderController::class, 'updateStatus'])->name('orders.status');
    Route::match(['post', 'patch'], 'orders/{order}/payment', [OrderController::class, 'updatePayment'])->name('orders.payment');
    Route::match(['post', 'patch'], 'orders/{order}/deliver', [OrderController::class, 'recordDelivery'])->name('orders.deliver');
    Route::match(['post', 'patch'], 'orders/{order}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');

    /*
    |----------------------------------------------------------------------
    | Customers
    |----------------------------------------------------------------------
    */
    Route::get('customers/export', [CustomerController::class, 'export'])->name('customers.export');
    Route::resource('customers', CustomerController::class);

    /*
    |----------------------------------------------------------------------
    | Products, categories and colours
    |----------------------------------------------------------------------
    */
    Route::get('products/export', [ProductController::class, 'export'])->name('products.export');
    Route::resource('products', ProductController::class);
    Route::patch('products/{product}/toggle', [ProductController::class, 'toggleActive'])->name('products.toggle');

    Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::post('categories', [CategoryController::class, 'store'])->name('categories.store');
    Route::patch('categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
    Route::delete('categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');

    Route::get('colours', [ColourController::class, 'index'])->name('colours.index');
    Route::post('colours', [ColourController::class, 'store'])->name('colours.store');
    Route::patch('colours/{colour}', [ColourController::class, 'update'])->name('colours.update');
    Route::delete('colours/{colour}', [ColourController::class, 'destroy'])->name('colours.destroy');

    /*
    |----------------------------------------------------------------------
    | Deliveries
    |----------------------------------------------------------------------
    */
    Route::get('deliveries', [DeliveryController::class, 'index'])->name('deliveries.index');
    Route::get('deliveries/calendar', [DeliveryController::class, 'calendar'])->name('deliveries.calendar');
    Route::get('deliveries/export', [DeliveryController::class, 'export'])->name('deliveries.export');

    /*
    |----------------------------------------------------------------------
    | Reports
    |----------------------------------------------------------------------
    */
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('sales', [ReportController::class, 'sales'])->name('sales');
        Route::get('products', [ReportController::class, 'products'])->name('products');
        Route::get('customers', [ReportController::class, 'customers'])->name('customers');
        Route::get('zip', [ReportController::class, 'zip'])->name('zip');
        Route::get('sales-persons', [ReportController::class, 'salesPersons'])->name('sales-persons');
        Route::get('deliveries', [ReportController::class, 'deliveries'])->name('deliveries');
        Route::get('{report}/export', [ReportController::class, 'export'])
            ->whereIn('report', ['sales', 'products', 'customers', 'zip', 'sales-persons', 'deliveries'])
            ->name('export');
    });

    /*
    |----------------------------------------------------------------------
    | Administration (Admin only, enforced by controller middleware)
    |----------------------------------------------------------------------
    */
    Route::resource('users', UserController::class)->except(['show']);
    Route::patch('users/{user}/toggle', [UserController::class, 'toggleActive'])->name('users.toggle');

    Route::get('audit-logs/export', [AuditLogController::class, 'export'])->name('audit.export');
    Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit.index');
    Route::get('audit-logs/{auditLog}', [AuditLogController::class, 'show'])->name('audit.show');

    Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::patch('settings', [SettingsController::class, 'update'])->name('settings.update');
    Route::get('settings/security', [SecurityController::class, 'index'])->name('settings.security');
    Route::patch('settings/security', [SecurityController::class, 'update'])->name('settings.security.update');
    Route::get('settings/reset-data', [\App\Http\Controllers\SystemResetController::class, 'index'])->name('settings.reset');
    Route::post('settings/reset-data', [\App\Http\Controllers\SystemResetController::class, 'reset'])->name('settings.reset.execute');

    Route::get('activity/logs', [ActivityController::class, 'logs'])->name('activity.logs');
    Route::get('activity/sessions', [ActivityController::class, 'sessions'])->name('activity.sessions');

    /*
    |----------------------------------------------------------------------
    | Profile (every signed-in user)
    |----------------------------------------------------------------------
    */
    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('profile/avatar', [ProfileController::class, 'removeAvatar'])->name('profile.avatar.remove');
    Route::put('profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
});

require __DIR__ . '/auth.php';
