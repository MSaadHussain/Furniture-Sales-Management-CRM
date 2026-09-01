<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Colour master (requirements 8.3). Item-level colour demand is a headline
 * report, so colours are a first-class table rather than free text.
 */
class Colour extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'hex', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_colour');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /** Swatch colour for the UI, falling back to a neutral grey. */
    public function swatch(): string
    {
        return $this->hex ?: '#98A2B3';
    }
}
