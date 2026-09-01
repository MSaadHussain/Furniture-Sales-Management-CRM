<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;

/**
 * System settings (requirements 5.12). Admin only. Covers the currency, the
 * order-numbering prefix, dashboard defaults, and the extra permissions an
 * Admin can grant a Manager (requirements 3.2).
 */
class SettingsController extends Controller implements HasMiddleware
{
    /** Optional Manager rights, all off by default except exporting. */
    public const MANAGER_PERMISSIONS = [
        'manager_can_cancel_orders'    => ['label' => 'Cancel orders', 'default' => false, 'hint' => 'Allows a Manager to cancel an order and record a reason.'],
        'manager_can_manage_customers' => ['label' => 'Create and edit customers', 'default' => false, 'hint' => 'Without this a Manager can view customers but not change them.'],
        'manager_can_manage_products'  => ['label' => 'Manage products, categories and colours', 'default' => false, 'hint' => 'Without this a Manager sees the catalogue read-only.'],
        'manager_can_export_data'      => ['label' => 'Export reports and data', 'default' => true,  'hint' => 'Customer personal data is never exported to Sales Persons regardless.'],
    ];

    public function __construct(private AuditService $audit) {}

    public static function middleware(): array
    {
        return ['can:manage-settings'];
    }

    public function index()
    {
        return view('settings.index', [
            'settings'    => $this->values(),
            'permissions' => self::MANAGER_PERMISSIONS,
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'currency_symbol'    => ['nullable', 'string', 'max:8'],
            'currency_decimals'  => ['required', 'integer', 'between:0,4'],
            'currency_position'  => ['required', 'in:before,after'],
            'business_name'      => ['nullable', 'string', 'max:120'],
            'default_date_range' => ['required', 'string', 'in:' . implode(',', array_keys(\App\Services\DateRangeService::presets()))],
            'default_delivery_lead_days' => ['required', 'integer', 'between:0,365'],
        ]);

        $before = $this->values();

        Setting::put('currency_symbol', trim((string) ($data['currency_symbol'] ?? '')));
        Setting::put('currency_decimals', (int) $data['currency_decimals']);
        Setting::put('currency_position', $data['currency_position']);
        Setting::put('business_name', $data['business_name'] ?? null);
        Setting::put('default_date_range', $data['default_date_range']);
        Setting::put('default_delivery_lead_days', (int) $data['default_delivery_lead_days']);

        foreach (array_keys(self::MANAGER_PERMISSIONS) as $key) {
            Setting::put($key, $request->boolean($key));
        }

        $this->audit->log('settings.updated', null, 'Updated system settings', $before, $this->values());

        return back()->with('toast', 'Settings saved.');
    }

    /** Current values with their defaults applied. */
    public static function values(): array
    {
        $values = [
            'currency_symbol'    => (string) Setting::get('currency_symbol', ''),
            'currency_decimals'  => (int) Setting::get('currency_decimals', 0),
            'currency_position'  => (string) Setting::get('currency_position', 'before'),
            'business_name'      => Setting::get('business_name'),
            'default_date_range' => (string) Setting::get('default_date_range', 'this_month'),
            'default_delivery_lead_days' => (int) Setting::get('default_delivery_lead_days', 7),
        ];

        foreach (self::MANAGER_PERMISSIONS as $key => $meta) {
            $values[$key] = (bool) Setting::get($key, $meta['default']);
        }

        return $values;
    }
}
