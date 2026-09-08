<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;

/**
 * Generates the human-readable order number required by section 29:
 * SALE-2026-000001. Unique, sequential per year, and never reused.
 *
 * The sequence is the highest of two sources: the largest number still in the
 * orders table (including soft-deleted rows), and a stored high-water mark.
 * The mark exists because an Admin can permanently delete an order, which
 * removes its row and would otherwise let the table maximum go backwards and
 * hand the same number to a different order.
 */
class OrderNumberService
{
    public const PREFIX = 'SALE';

    public function next(?int $year = null): string
    {
        $year = $year ?: (int) now()->format('Y');

        // Lock the row range while we read the current maximum so two
        // concurrent order saves cannot claim the same number. The unique index
        // on order_number is the final backstop.
        return DB::transaction(function () use ($year) {
            $prefix = self::PREFIX . '-' . $year . '-';

            $last = Order::withTrashed()
                ->where('order_number', 'like', $prefix . '%')
                ->lockForUpdate()
                ->orderByDesc('order_number')
                ->value('order_number');

            $fromTable = $last ? (int) substr((string) $last, strlen($prefix)) : 0;
            $fromMark  = (int) Setting::get(self::watermarkKey($year), 0);

            $sequence = max($fromTable, $fromMark) + 1;

            // Remember it, so the number survives the order being deleted.
            Setting::put(self::watermarkKey($year), $sequence);

            return $prefix . str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
        });
    }

    public static function watermarkKey(int $year): string
    {
        return 'order_number_high_water_' . $year;
    }
}
