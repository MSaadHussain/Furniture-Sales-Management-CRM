<?php

namespace App\Enums;

/**
 * The three roles of the Furniture Sales CRM.
 *
 * Admin      — full system control.
 * Manager    — operational/management role; no destructive or system-level rights
 *              unless an Admin explicitly grants them in Settings.
 * SalesPerson— read-only dashboard role. Registered by an Admin and selected on
 *              orders, but never allowed into orders, customers or reports.
 */
enum UserRole: string
{
    case Admin       = 'admin';
    case Manager     = 'manager';
    case SalesPerson = 'sales_person';

    public function label(): string
    {
        return match ($this) {
            self::Admin       => 'Admin',
            self::Manager     => 'Manager',
            self::SalesPerson => 'Sales Person',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Admin       => '#F04438',
            self::Manager     => '#465FFF',
            self::SalesPerson => '#12B76A',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Admin       => 'Full system control, including users, settings and audit logs.',
            self::Manager     => 'Day-to-day sales and delivery operations, customers and reports.',
            self::SalesPerson => 'Read-only dashboard. Selectable on orders as the seller.',
        };
    }

    /** Roles that may reach anything beyond the dashboard. */
    public function isManagement(): bool
    {
        return $this !== self::SalesPerson;
    }

    public function isAdmin(): bool
    {
        return $this === self::Admin;
    }

    /** Sales Persons see the restricted dashboard only. */
    public function isDashboardOnly(): bool
    {
        return $this === self::SalesPerson;
    }

    /** @return array<int,array{value:string,label:string}> */
    public static function options(): array
    {
        return array_map(
            fn (self $r) => ['value' => $r->value, 'label' => $r->label()],
            self::cases()
        );
    }
}
