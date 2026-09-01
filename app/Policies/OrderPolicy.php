<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

/**
 * Record-level rules for orders. Capability-level checks live in the gates
 * defined by AppServiceProvider; Admins bypass all of this via Gate::before.
 */
class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view-orders');
    }

    public function view(User $user, Order $order): bool
    {
        return $user->can('view-orders');
    }

    public function create(User $user): bool
    {
        return $user->can('manage-orders');
    }

    /** Cancelled and returned orders are frozen for everyone. */
    public function update(User $user, Order $order): bool
    {
        return $user->can('manage-orders') && $order->isEditable();
    }

    public function cancel(User $user, Order $order): bool
    {
        return $user->can('cancel-orders') && $order->isEditable();
    }

    public function delete(User $user, Order $order): bool
    {
        return $user->can('delete-orders');
    }

    public function restore(User $user, Order $order): bool
    {
        return $user->can('delete-orders');
    }

    /** Recording an actual delivery date on a live order. */
    public function recordDelivery(User $user, Order $order): bool
    {
        return $user->can('manage-deliveries') && $order->isEditable();
    }

    public function changeStatus(User $user, Order $order): bool
    {
        return $user->can('manage-orders') && $order->isEditable();
    }
}
