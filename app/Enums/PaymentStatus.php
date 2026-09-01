<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Pending  = 'pending';
    case Partial  = 'partial';
    case Paid     = 'paid';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Pending  => 'Pending',
            self::Partial  => 'Partial',
            self::Paid     => 'Paid',
            self::Refunded => 'Refunded',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending  => '#F79009',
            self::Partial  => '#06AED4',
            self::Paid     => '#12B76A',
            self::Refunded => '#F04438',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Pending  => 'fa-hourglass-half',
            self::Partial  => 'fa-coins',
            self::Paid     => 'fa-circle-check',
            self::Refunded => 'fa-rotate-left',
        };
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
