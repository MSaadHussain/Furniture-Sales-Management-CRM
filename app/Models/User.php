<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name', 'email', 'phone', 'password', 'role', 'is_active', 'avatar_color', 'avatar_path',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'role'              => UserRole::class,
            'is_active'         => 'boolean',
        ];
    }

    /** Orders where this user is the assigned Sales Person. */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'sales_person_id');
    }

    /** Orders this user entered into the CRM. */
    public function createdOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'created_by');
    }

    public function hasRole(UserRole ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isSalesPerson(): bool
    {
        return $this->role === UserRole::SalesPerson;
    }

    /** Only active Sales Persons may be selected on an order (requirements 12). */
    public function scopeSelectableSalesPersons(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where('role', UserRole::SalesPerson->value)
            ->orderBy('name');
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
              ->orWhere('email', 'like', "%{$term}%")
              ->orWhere('phone', 'like', "%{$term}%");
        });
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim($this->name));
        $first = $parts[0][0] ?? '';
        $last  = count($parts) > 1 ? end($parts)[0] : '';

        return strtoupper($first . $last);
    }

    public function avatarUrl(): ?string
    {
        return $this->avatar_path
            ? \Illuminate\Support\Facades\Storage::url($this->avatar_path)
            : null;
    }
}
