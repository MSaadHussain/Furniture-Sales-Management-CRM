<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\DB;

/**
 * Generates the human-readable order number required by section 29:
 * SALE-2026-000001. Unique, sequential per year, and never reused - the
 * sequence is derived from the highest number ever issued in that year,
 * including soft-deleted orders.
 */
class OrderNumberService
{
    public const PREFIX = 'SALE';

    public function next(?int $year = null): string
    {
        $year = $year ?: (int) now()->format('Y');

        // Lock the table row range while we read the current maximum so two
        // concurrent order saves cannot claim the same number. The unique index
        // on order_number is the final backstop.
        return DB::transaction(function () use ($year) {
            $prefix = self::PREFIX . '-' . $year . '-';

            $last = Order::withTrashed()
                ->where('order_number', 'like', $prefix . '%')
                ->lockForUpdate()
                ->orderByDesc('order_number')
                ->value('order_number');

            $sequence = $last ? ((int) substr((string) $last, strlen($prefix))) + 1 : 1;

            return $prefix . str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
        });
    }
}
