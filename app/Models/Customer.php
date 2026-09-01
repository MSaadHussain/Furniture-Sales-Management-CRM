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
     * Find an existing customer by phone, tolerating formatting differences and
     * a country-code prefix (requirements 7.3).
     */
    public static function findByPhone(?string $phone): ?self
    {
        $digits = self::normalisePhone($phone);
        if (strlen($digits) < 6) {
            return null;
        }

        // Compare on the last 9 digits so 0300 1234567 matches +92 300 1234567.
        $tail = substr($digits, -9);

        return static::query()
            ->whereRaw("REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '(', ''), ')', '') LIKE ?", ["%{$tail}"])
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
