<?php

namespace App\Support;

class Flag
{
    /** Convert an ISO-3166-1 alpha-2 code to its regional-indicator emoji flag. */
    public static function emoji(?string $code): string
    {
        if (! $code || strlen($code) !== 2) {
            return '';
        }
        $code = strtoupper($code);
        $flag = '';
        foreach (str_split($code) as $ch) {
            $flag .= mb_chr(127397 + ord($ch), 'UTF-8');
        }
        return $flag;
    }
}
