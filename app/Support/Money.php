<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Currency formatting. The symbol, decimal places and symbol position are all
 * configured from Settings and have no built-in default, so the CRM shows bare
 * numbers until an Admin picks a currency.
 */
class Money
{
    public static function symbol(): string
    {
        $symbol = trim((string) Setting::get('currency_symbol', '€'));
        return $symbol !== '' ? $symbol : '€';
    }

    public static function decimals(): int
    {
        return max(0, min(4, (int) Setting::get('currency_decimals', 0)));
    }

    /** Whether the symbol is rendered before or after the amount. */
    public static function symbolAfter(): bool
    {
        return Setting::get('currency_position', 'before') === 'after';
    }

    public static function isConfigured(): bool
    {
        return self::symbol() !== '';
    }

    /** Full amount, e.g. Rs. 85,000 or 85,000.00 when no symbol is set. */
    public static function format(float|int|string|null $amount): string
    {
        $number = number_format((float) $amount, self::decimals(), '.', ',');
        $symbol = self::symbol();

        if ($symbol === '') {
            return $number;
        }

        return self::symbolAfter() ? $number . ' ' . $symbol : $symbol . ' ' . $number;
    }

    /**
     * Abbreviated amount for KPI cards, e.g. 82.4M / 7.85M / 940K.
     * Falls back to the full format below 1,000.
     */
    public static function compact(float|int|string|null $amount): string
    {
        $value  = (float) $amount;
        $sign   = $value < 0 ? '-' : '';
        $value  = abs($value);
        $symbol = self::symbol();

        [$scaled, $suffix] = match (true) {
            $value >= 1_000_000_000 => [$value / 1_000_000_000, 'B'],
            $value >= 1_000_000     => [$value / 1_000_000, 'M'],
            $value >= 1_000         => [$value / 1_000, 'K'],
            default                 => [$value, ''],
        };

        $number = $suffix === ''
            ? number_format($scaled, self::decimals(), '.', ',')
            : rtrim(rtrim(number_format($scaled, 1, '.', ''), '0'), '.') . $suffix;

        $number = $sign . $number;

        if ($symbol === '') {
            return $number;
        }

        return self::symbolAfter() ? $number . ' ' . $symbol : $symbol . ' ' . $number;
    }

    /** Parses a user-entered amount such as "$50,000" or "50 000" into a float. */
    public static function parse(mixed $value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        $clean = preg_replace('/[^0-9.\-]/', '', (string) $value);

        return $clean === '' || $clean === '-' ? 0.0 : (float) $clean;
    }
}
