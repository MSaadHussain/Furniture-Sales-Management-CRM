<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id', 'product_id', 'colour_id',
        'item_name_snapshot', 'item_colour', 'category_name_snapshot',
        'quantity', 'unit_price', 'discount', 'line_total', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity'   => 'integer',
            'unit_price' => 'decimal:2',
            'discount'   => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function colour(): BelongsTo
    {
        return $this->belongsTo(Colour::class);
    }

    /** Line total is always recomputed server-side; never trusted from the form. */
    public static function computeLineTotal(int $quantity, float $unitPrice, float $discount): float
    {
        return max(0, round($quantity * $unitPrice - $discount, 2));
    }
}
