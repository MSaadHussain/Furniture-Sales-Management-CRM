<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * Baseline settings. Currency is deliberately left blank: an Admin picks it on
 * the Settings screen before amounts start showing a symbol.
 */
class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'currency_symbol'            => '€',
            'currency_decimals'          => 0,
            'currency_position'          => 'before',
            'default_date_range'         => 'this_month',
            'default_delivery_lead_days' => 7,
            'idle_logout_enabled'        => true,
            'idle_timeout_minutes'       => 15,
            'require_password_24h'       => true,
            'manager_can_cancel_orders'    => false,
            'manager_can_manage_customers' => false,
            'manager_can_manage_products'  => false,
            'manager_can_export_data'      => true,
        ];

        foreach ($defaults as $key => $value) {
            if (! Setting::where('key', $key)->exists()) {
                Setting::put($key, $value);
            }
        }
    }
}
