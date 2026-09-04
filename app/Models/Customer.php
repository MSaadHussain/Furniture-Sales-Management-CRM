<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'phone', 'email', 'address', 'city', 'state', 'zip_code', 'notes', 'created_by',
    ];

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);
        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
              ->orWhere('email', 'like', "%{$term}%")
              ->orWhere('zip_code', 'like', "%{$term}%")
              ->orWhere('phone', 'like', '%' . self::normalisePhone($term) . '%');

            if (ctype_digit($term)) {
                $q->orWhere('id', (int) $term);
            }
        });
    }

    /**
     * Digits only, so "+92 300-1234567" and "03001234567" compare sensibly.
     * Used by the duplicate-customer check on the order form.
     */
    public static function normalisePhone(?string $phone): string
    {
        return preg_replace('/\D+/', '', (string) $phone) ?? '';
    }

    /**
     * Find an existing customer by phone, tolerating formatting differences,
     * country codes (+92, +33, 00, etc.), dashes, dots and spaces.
     */
    public static function findByPhone(?string $phone): ?self
    {
        $raw = trim((string) $phone);
        $digits = self::normalisePhone($phone);
        if (strlen($digits) < 4) {
            return null;
        }

        // 1. Direct indexed match on raw input or stripped digits
        $direct = static::query()
            ->where('phone', $raw)
            ->orWhere('phone', $digits)
            ->first();

        if ($direct) {
            return $direct;
        }

        // 2. Fast prefix / suffix indexed LIKE match (last 7 or 8 digits)
        $tail = strlen($digits) >= 8 ? substr($digits, -8) : (strlen($digits) >= 7 ? substr($digits, -7) : $digits);
        $candidate = static::query()
            ->where('phone', 'like', "%{$tail}")
            ->first();

        if ($candidate) {
            return $candidate;
        }

        // 3. Fallback only if needed (for oddly formatted phone strings in DB)
        $cleanSql = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '(', ''), ')', ''), '+', ''), '.', ''), '/', '')";

        return static::query()
            ->whereRaw("{$cleanSql} = ?", [$digits])
            ->orWhereRaw("{$cleanSql} LIKE ?", ["%{$tail}"])
            ->first();
    }

    /* ---------------------------------------------------------------------
     | Customer history (requirements 7.4)
     |--------------------------------------------------------------------- */

    public function orderCount(): int
    {
        return $this->orders()->countable()->count();
    }

    public function totalSpent(): float
    {
        return (float) $this->orders()->countable()->sum('grand_total');
    }

    public function firstOrderAt(): ?\Illuminate\Support\Carbon
    {
        $v = $this->orders()->countable()->min('order_created_at');

        return $v ? \Illuminate\Support\Carbon::parse($v) : null;
    }

    public function lastOrderAt(): ?\Illuminate\Support\Carbon
    {
        $v = $this->orders()->countable()->max('order_created_at');

        return $v ? \Illuminate\Support\Carbon::parse($v) : null;
    }

    /** A customer with more than one countable order is a repeat customer. */
    public function isReturning(): bool
    {
        return $this->orderCount() > 1;
    }

    public function fullAddress(): string
    {
        return collect([$this->address, $this->city, $this->state, $this->zip_code])
            ->filter()
            ->implode(', ');
    }
}
