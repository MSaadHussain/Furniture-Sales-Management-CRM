<?php

namespace App\Enums;

/**
 * Operational lifecycle of an order. Deliberately separate from PaymentStatus:
 * an order can be "Ready for Delivery" while still "Pending" payment.
 *
 * Happy path: New -> Confirmed -> Processing -> Ready for Delivery
 *             -> Out for Delivery -> Delivered
 *
 * Cancelled / Delayed / Returned are exception states reachable from anywhere.
 */
enum OrderStatus: string
{
    case New              = 'new';
    case Confirmed        = 'confirmed';
    case Processing       = 'processing';
    case ReadyForDelivery = 'ready_for_delivery';
    case OutForDelivery   = 'out_for_delivery';
    case Delivered        = 'delivered';
    case Cancelled        = 'cancelled';
    case Delayed          = 'delayed';
    case Returned         = 'returned';

    public function label(): string
    {
        return match ($this) {
            self::Delivered                 => 'Delivered',
            self::Cancelled, self::Returned => 'Cancelled',
            default                         => 'Pending',
        };
    }

    /**
     * The three choices a user actually sees, each mapped to the raw statuses
     * behind it. label() collapses nine cases into three, so a filter built by
     * looping the cases listed "Pending" six times and, worse, matched only one
     * of them. Keyed by the lower-cased label.
     *
     * @return array<string,array{label:string,values:list<string>}>
     */
    public static function filterGroups(): array
    {
        $groups = [];

        foreach (self::cases() as $case) {
            $key = strtolower($case->label());
            $groups[$key]['label']    = $case->label();
            $groups[$key]['values'][] = $case->value;
        }

        return $groups;
    }

    /**
     * Raw statuses a filter choice stands for. Accepts a group key ('pending')
     * or a single raw status ('new'), so older links keep working.
     *
     * @return list<string>
     */
    public static function valuesForFilter(?string $choice): array
    {
        $choice = (string) $choice;
        $groups = self::filterGroups();

        if (isset($groups[$choice])) {
            return $groups[$choice]['values'];
        }

        return self::tryFrom($choice) ? [$choice] : [];
    }

    /**
     * Statuses that mean the sale did not happen. Distinct from
     * revenueValues(), which is only Delivered.
     *
     * @return list<string>
     */
    public static function lostValues(): array
    {
        return [self::Cancelled->value, self::Returned->value];
    }

    public function color(): string
    {
        return match ($this) {
            self::Delivered                 => '#12B76A',
            self::Cancelled, self::Returned => '#F04438',
            default                         => '#F79009',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Delivered                 => '!bg-emerald-600 !text-white font-bold !border-emerald-600 shadow-xs',
            self::Cancelled, self::Returned => '!bg-red-600 !text-white font-bold !border-red-600 shadow-xs',
            default                         => '!bg-amber-100 !text-amber-800 font-bold !border-amber-300 dark:!bg-amber-900/50 dark:!text-amber-200 dark:!border-amber-700',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Delivered                 => 'fa-house-circle-check',
            self::Cancelled, self::Returned => 'fa-ban',
            default                         => 'fa-clock',
        };
    }

    /** The ordered workflow, excluding exception states. */
    public static function workflow(): array
    {
        return [
            self::New, self::Confirmed, self::Processing,
            self::ReadyForDelivery, self::OutForDelivery, self::Delivered,
        ];
    }

    /** Exception / terminal states outside the happy path. */
    public static function exceptions(): array
    {
        return [self::Cancelled, self::Delayed, self::Returned];
    }

    /** Statuses that still expect a delivery to happen. */
    public static function openCases(): array
    {
        return [
            self::New, self::Confirmed, self::Processing,
            self::ReadyForDelivery, self::OutForDelivery, self::Delayed,
        ];
    }

    /** @return array<int,string> */
    public static function openValues(): array
    {
        return array_map(fn (self $s) => $s->value, self::openCases());
    }

    /** Only Delivered orders count towards realized revenue and sales reporting. */
    public static function revenueValues(): array
    {
        return [self::Delivered->value];
    }

    public function countsAsRevenue(): bool
    {
        return $this === self::Delivered;
    }

    public function isDelivered(): bool
    {
        return $this === self::Delivered;
    }

    /** Cancelled orders are frozen — no edits, no delivery recording. */
    public function isLocked(): bool
    {
        return in_array($this, [self::Cancelled, self::Returned], true);
    }

    /** @return array<int,array{value:string,label:string,color:string}> */
    public static function options(): array
    {
        return array_map(
            fn (self $s) => ['value' => $s->value, 'label' => $s->label(), 'color' => $s->color()],
            self::cases()
        );
    }
}
