<?php

namespace App\Observers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Flags a row as needing to be rewritten to the Google Sheet.
 *
 * Deliberately cheap: a single UPDATE that sets `sheet_synced_at` back to
 * NULL, with no events of its own. The actual HTTP call to Apps Script
 * happens later, on the cron tick, so saving an order never waits on Google.
 *
 * A freshly created row already has a NULL stamp, so `created` needs no work.
 */
abstract class SheetSyncObserver
{
    /** Table name, which doubles as the entity key in `sheet_sync_deletions`. */
    abstract protected function entity(): string;

    /** The id whose sheet row this model owns. Usually its own. */
    protected function sheetId(Model $model): int
    {
        return (int) $model->getKey();
    }

    public function updated(Model $model): void
    {
        $this->markDirty($model);
    }

    public function restored(Model $model): void
    {
        $this->markDirty($model);
    }

    /** Fires for a soft delete too: either way the row leaves the CRM. */
    public function deleted(Model $model): void
    {
        $this->markDeleted($model);
    }

    public function forceDeleted(Model $model): void
    {
        $this->markDeleted($model);
    }

    protected function markDirty(Model $model): void
    {
        DB::table($this->entity())
            ->where('id', $this->sheetId($model))
            ->update(['sheet_synced_at' => null]);
    }

    protected function markDeleted(Model $model): void
    {
        DB::table('sheet_sync_deletions')->insertOrIgnore([
            'entity'     => $this->entity(),
            'entity_id'  => $this->sheetId($model),
            'created_at' => now(),
        ]);
    }
}
