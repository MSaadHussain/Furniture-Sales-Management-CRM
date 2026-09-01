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
            self::New              => 'New',
            self::Confirmed        => 'Confirmed',
            self::Processing       => 'Processing',
            self::ReadyForDelivery => 'Ready for Delivery',
            self::OutForDelivery   => 'Out for Delivery',
            self::Delivered        => 'Delivered',
            self::Cancelled        => 'Cancelled',
            self::Delayed          => 'Delayed',
            self::Returned         => 'Returned',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::New              => '#667085',
            self::Confirmed        => '#465FFF',
            self::Processing       => '#7A5AF8',
            self::ReadyForDelivery => '#F79009',
            self::OutForDelivery   => '#06AED4',
            self::Delivered        => '#12B76A',
            self::Cancelled        => '#98A2B3',
            self::Delayed          => '#F04438',
            self::Returned         => '#DC6803',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::New              => 'fa-file-circle-plus',
            self::Confirmed        => 'fa-circle-check',
            self::Processing       => 'fa-screwdriver-wrench',
            self::ReadyForDelivery => 'fa-box-open',
            self::OutForDelivery   => 'fa-truck-fast',
            self::Delivered        => 'fa-house-circle-check',
            self::Cancelled        => 'fa-ban',
            self::Delayed          => 'fa-clock',
            self::Returned         => 'fa-rotate-left',
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

    /** Cancelled and Returned orders are excluded from revenue reporting. */
    public static function revenueValues(): array
    {
        return array_values(array_diff(
            array_map(fn (self $s) => $s->value, self::cases()),
            [self::Cancelled->value, self::Returned->value]
        ));
    }

    public function countsAsRevenue(): bool
    {
        return ! in_array($this, [self::Cancelled, self::Returned], true);
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
