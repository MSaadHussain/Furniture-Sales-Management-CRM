<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'product_code', 'name', 'category_id', 'default_price', 'description', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'default_price' => 'decimal:2',
            'is_active'     => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function colours(): BelongsToMany
    {
        return $this->belongsToMany(Colour::class, 'product_colour');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);
        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
              ->orWhere('product_code', 'like', "%{$term}%");
        });
    }

    /**
     * Colours offered for this product: its own shortlist when one exists,
     * otherwise the full active colour master.
     */
    public function availableColours()
    {
        $own = $this->relationLoaded('colours') ? $this->colours : $this->colours()->get();

        return $own->isNotEmpty() ? $own : Colour::active()->orderBy('sort_order')->orderBy('name')->get();
    }

    /** Next auto-generated SKU, e.g. FUR-0042. */
    public static function nextProductCode(string $prefix = 'FUR'): string
    {
        $last = static::withTrashed()
            ->where('product_code', 'like', $prefix . '-%')
            ->orderByRaw('LENGTH(product_code) DESC, product_code DESC')
            ->value('product_code');

        $n = $last ? ((int) substr((string) $last, strlen($prefix) + 1)) + 1 : 1;

        return sprintf('%s-%04d', $prefix, $n);
    }
}
