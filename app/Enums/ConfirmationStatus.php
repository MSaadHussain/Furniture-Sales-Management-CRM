<?php

namespace App\Enums;

/**
 * Whether the order has been confirmed with the customer.
 *
 * Deliberately separate from OrderStatus: that one tracks where the goods are,
 * this one tracks whether the sale is real. Nothing here feeds revenue or any
 * report aggregate — a Duplicate still counts until someone says otherwise.
 */
enum ConfirmationStatus: string
{
    case Confirmed    = 'confirmed';
    case NotConfirmed = 'not_confirmed';
    case Duplicate    = 'duplicate';

    /** An order is real unless someone marks it otherwise. */
    public const DEFAULT = self::Confirmed;

    public function label(): string
    {
        return match ($this) {
            self::Confirmed    => 'Confirm',
            self::NotConfirmed => 'Not Confirm',
            self::Duplicate    => 'Duplicate',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Confirmed    => '#12B76A',
            self::NotConfirmed => '#F79009',
            self::Duplicate    => '#667085',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Confirmed    => 'fa-circle-check',
            self::NotConfirmed => 'fa-circle-question',
            self::Duplicate    => 'fa-clone',
        };
    }

    /** Tailwind classes for the inline select on the order list. */
    public function selectClasses(): string
    {
        return match ($this) {
            self::Confirmed    => '!bg-emerald-600 !text-white !border-emerald-600',
            self::NotConfirmed => '!bg-amber-100 !text-amber-800 !border-amber-300 dark:!bg-amber-900/50 dark:!text-amber-200',
            self::Duplicate    => '!bg-slate-500 !text-white !border-slate-500',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
