<?php

namespace App\Models;

use App\Enums\DeliveryPerformance;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class Order extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'order_number', 'customer_id', 'sales_person_id',
        'order_created_at', 'requested_delivery_date', 'actual_delivery_date',
        'subtotal', 'discount', 'delivery_charge', 'tax', 'grand_total',
        'payment_status', 'payment_method', 'amount_paid', 'balance_due',
        'order_status', 'zip_code', 'notes', 'cancellation_reason',
        'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'order_created_at'        => 'datetime',
            'requested_delivery_date' => 'date',
            'actual_delivery_date'    => 'date',
            'subtotal'                => 'decimal:2',
            'discount'                => 'decimal:2',
            'delivery_charge'         => 'decimal:2',
            'tax'                     => 'decimal:2',
            'grand_total'             => 'decimal:2',
            'amount_paid'             => 'decimal:2',
            'balance_due'             => 'decimal:2',
            'order_status'            => OrderStatus::class,
            'payment_status'          => PaymentStatus::class,
            'payment_method'          => PaymentMethod::class,
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function salesPerson(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sales_person_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /** Orders that count towards revenue: everything but cancelled and returned. */
    public function scopeCountable(Builder $query): Builder
    {
        return $query->whereIn('order_status', OrderStatus::revenueValues());
    }

    /** Orders still awaiting delivery. */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('order_status', OrderStatus::openValues());
    }

    public function scopeForDeliveryDate(Builder $query, Carbon|string $date): Builder
    {
        return $query->whereDate('requested_delivery_date', Carbon::parse($date)->toDateString());
    }

    public function scopeCreatedBetween(Builder $query, $from, $to): Builder
    {
        return $query->whereBetween('order_created_at', [$from, $to]);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);
        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            $q->where('order_number', 'like', "%{$term}%")
              ->orWhere('zip_code', 'like', "%{$term}%")
              ->orWhereHas('customer', function (Builder $c) use ($term) {
                  $c->where('name', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%");
              })
              ->orWhereHas('items', function (Builder $i) use ($term) {
                  $i->where('item_name_snapshot', 'like', "%{$term}%");
              });
        });
    }

    public function deliveryPerformance(): DeliveryPerformance
    {
        if (! $this->actual_delivery_date) {
            return DeliveryPerformance::Pending;
        }

        return $this->actual_delivery_date->lte($this->requested_delivery_date)
            ? DeliveryPerformance::OnTime
            : DeliveryPerformance::Late;
    }

    /** Days late, or 0 when on time or not yet delivered. */
    public function daysLate(): int
    {
        if (! $this->actual_delivery_date || $this->actual_delivery_date->lte($this->requested_delivery_date)) {
            return 0;
        }

        return (int) $this->requested_delivery_date->diffInDays($this->actual_delivery_date);
    }

    /** Open order whose requested delivery date has already passed. */
    public function isOverdue(): bool
    {
        return ! $this->actual_delivery_date
            && in_array($this->order_status, OrderStatus::openCases(), true)
            && $this->requested_delivery_date->isBefore(today());
    }

    public function isDueToday(): bool
    {
        return $this->requested_delivery_date->isToday();
    }

    /** Cancelled and returned orders are frozen. */
    public function isEditable(): bool
    {
        return ! $this->order_status->isLocked();
    }

    public function isCancelled(): bool
    {
        return $this->order_status === \App\Enums\OrderStatus::Cancelled;
    }

    public function itemSummary(int $limit = 2): string
    {
        $items = $this->relationLoaded('items') ? $this->items : $this->items()->get();
        $names = $items->take($limit)->map(
            fn (OrderItem $i) => $i->quantity > 1 ? "{$i->item_name_snapshot} x{$i->quantity}" : $i->item_name_snapshot
        );

        $extra = $items->count() - $limit;

        return $names->implode(', ') . ($extra > 0 ? " +{$extra} more" : '');
    }

    public function totalQuantity(): int
    {
        $items = $this->relationLoaded('items') ? $this->items : $this->items()->get();

        return (int) $items->sum('quantity');
    }

    /**
     * Formats order details for quick one-click clipboard copying.
     */
    public function copyDetailsText(): string
    {
        $customer = $this->relationLoaded('customer') ? $this->customer : $this->customer()->first();
        $items    = $this->relationLoaded('items') ? $this->items : $this->items()->get();

        $name  = $customer?->name ?? 'N/A';
        $phone = $customer?->phone ?? 'N/A';

        $addrParts = array_filter([
            $customer?->address,
            $customer?->city,
            $customer?->state,
            $customer?->zip_code ?: $this->zip_code,
        ]);
        $address = ! empty($addrParts) ? implode(', ', $addrParts) : ($this->zip_code ?: 'N/A');

        $productList = $items->map(function (OrderItem $item) {
            $col = $item->item_colour ? " ({$item->item_colour})" : '';
            return "{$item->quantity}x {$item->item_name_snapshot}{$col}";
        })->implode(', ');

        $total = \App\Support\Money::format($this->grand_total);

        return "Name: {$name}\nNumber: {$phone}\nAddress: {$address}\nProduct: {$productList}\nPrice Total: {$total}";
    }
}
