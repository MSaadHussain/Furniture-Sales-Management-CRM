<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash         = 'cash';
    case BankTransfer = 'bank_transfer';
    case Card         = 'card';
    case Online       = 'online';
    case Other        = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Cash         => 'Cash',
            self::BankTransfer => 'Bank Transfer',
            self::Card         => 'Card',
            self::Online       => 'Online',
            self::Other        => 'Other',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Cash         => 'fa-money-bill-wave',
            self::BankTransfer => 'fa-building-columns',
            self::Card         => 'fa-credit-card',
            self::Online       => 'fa-globe',
            self::Other        => 'fa-ellipsis',
        };
    }

    /** @return array<int,array{value:string,label:string}> */
    public static function options(): array
    {
        return array_map(
            fn (self $m) => ['value' => $m->value, 'label' => $m->label()],
            self::cases()
        );
    }
}
