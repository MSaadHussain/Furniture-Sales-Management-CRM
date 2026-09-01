<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Simple key/value application settings, editable from the Settings screen.
 * Values are JSON-wrapped so booleans, numbers and arrays round-trip cleanly.
 */
class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    /** Request-lifetime memo so a dashboard render hits the table once per key. */
    private static array $memo = [];

    protected function casts(): array
    {
        return ['value' => 'array'];
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, self::$memo)) {
            return self::$memo[$key] ?? $default;
        }

        try {
            $row = static::where('key', $key)->first();
        } catch (\Throwable $e) {
            // Table may not exist yet (pre-migration console commands).
            return $default;
        }

        $value = $row ? ($row->value['data'] ?? null) : null;
        self::$memo[$key] = $value;

        return $value ?? $default;
    }

    public static function put(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => ['data' => $value]]);
        self::$memo[$key] = $value;
    }

    public static function forget(string $key): void
    {
        unset(self::$memo[$key]);
    }

    public static function flushMemo(): void
    {
        self::$memo = [];
    }
}
