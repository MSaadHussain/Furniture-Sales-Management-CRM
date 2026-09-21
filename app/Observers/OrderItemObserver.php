<?php

namespace App\Observers;

use Illuminate\Database\Eloquent\Model;

/**
 * A line item has no sheet row of its own: it is part of the Items column on
 * the parent order, so any change makes that order's row stale.
 */
class OrderItemObserver extends SheetSyncObserver
{
    protected function entity(): string
    {
        return 'orders';
    }

    protected function sheetId(Model $model): int
    {
        return (int) $model->order_id;
    }

    /** Adding an item changes an order that may already be in the sheet. */
    public function created(Model $model): void
    {
        $this->markDirty($model);
    }

    /** The order itself lives on; only its row needs rewriting. */
    public function deleted(Model $model): void
    {
        $this->markDirty($model);
    }

    public function forceDeleted(Model $model): void
    {
        $this->markDirty($model);
    }
}
