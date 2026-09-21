<?php

namespace App\Console\Commands;

use App\Services\Sheets\SheetSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Drains everything the observers have flagged into the Google Sheet.
 *
 * Runs on the ordinary cron tick. When nothing has changed it makes no HTTP
 * call at all, so an idle CRM costs Google nothing.
 */
class SyncGoogleSheets extends Command
{
    protected $signature = 'sheets:sync
                            {--all : Re-push every row, not just the changed ones}
                            {--limit= : Rows per batch (defaults to sheets.batch_size)}
                            {--max-batches=20 : Safety stop so one tick cannot run forever}';

    protected $description = 'Push changed orders, customers and products to the Google Sheet';

    public function handle(SheetSyncService $sync): int
    {
        if (! $sync->configured()) {
            $this->components->warn('Google Sheets sync is off. Set GOOGLE_SHEETS_ENABLED, GOOGLE_SHEETS_WEBAPP_URL and GOOGLE_SHEETS_SECRET.');

            return self::SUCCESS;
        }

        if ($this->option('all')) {
            $sync->markAllDirty();
            $this->components->info('Every row flagged for a full re-push.');
        }

        $limit   = $this->option('limit') ? (int) $this->option('limit') : null;
        $max     = max(1, (int) $this->option('max-batches'));
        $total   = 0;

        for ($batch = 0; $batch < $max; $batch++) {
            try {
                $written = $sync->syncBatch($limit);
            } catch (Throwable $e) {
                // Rows stay dirty, so the next tick simply tries again.
                Log::error('Google Sheets sync failed: ' . $e->getMessage());
                $this->components->error($e->getMessage());

                return self::FAILURE;
            }

            if ($written === 0) {
                break;
            }

            $total += $written;
        }

        $this->components->info($total === 0 ? 'Sheet already up to date.' : "Wrote {$total} row(s) to the sheet.");

        return self::SUCCESS;
    }
}
