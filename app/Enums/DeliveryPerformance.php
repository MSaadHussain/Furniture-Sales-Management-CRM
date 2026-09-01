<?php

namespace App\Enums;

/**
 * Derived, never stored: computed by comparing actual_delivery_date against
 * requested_delivery_date. Requirements section 15.
 */
enum DeliveryPerformance: string
{
    case OnTime  = 'on_time';
    case Late    = 'late';
    case Pending = 'pending';

    public function label(): string
    {
        return match ($this) {
            self::OnTime  => 'On Time',
            self::Late    => 'Late',
            self::Pending => 'Pending',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::OnTime  => '#12B76A',
            self::Late    => '#F04438',
            self::Pending => '#98A2B3',
        };
    }
}
