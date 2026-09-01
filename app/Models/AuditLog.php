<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AuditLog extends Model
{
    protected $fillable = [
        'user_id', 'action', 'auditable_type', 'auditable_id',
        'description', 'old_values', 'new_values', 'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Short record label, e.g. Order 128. */
    public function recordLabel(): string
    {
        if (! $this->auditable_type) {
            return 'System';
        }

        return class_basename($this->auditable_type) . ' #' . $this->auditable_id;
    }

    /** Field-level diff of old vs new values, for the audit detail view. */
    public function changes(): array
    {
        $old = $this->old_values ?? [];
        $new = $this->new_values ?? [];
        $out = [];

        foreach (array_unique(array_merge(array_keys($old), array_keys($new))) as $key) {
            $before = $old[$key] ?? null;
            $after  = $new[$key] ?? null;

            if ($before === $after) {
                continue;
            }

            $out[] = [
                'field'  => Str::headline((string) $key),
                'before' => is_scalar($before) || $before === null ? $before : json_encode($before),
                'after'  => is_scalar($after) || $after === null ? $after : json_encode($after),
            ];
        }

        return $out;
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);
        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            $q->where('action', 'like', "%{$term}%")
              ->orWhere('description', 'like', "%{$term}%")
              ->orWhere('ip_address', 'like', "%{$term}%");
        });
    }
}
