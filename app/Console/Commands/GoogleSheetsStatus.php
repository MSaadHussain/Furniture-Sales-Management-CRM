<?php

namespace App\Console\Commands;

use App\Services\Sheets\SheetSyncService;
use App\Services\Sheets\SheetsWebAppClient;
use Illuminate\Console\Command;
use Throwable;

/**
 * Tells an operator, in one screen, whether the sheet link actually works and
 * how much is still waiting to go out.
 */
class GoogleSheetsStatus extends Command
{
    protected $signature = 'sheets:status';

    protected $description = 'Check the Google Sheet connection and show what is still pending';

    public function handle(SheetSyncService $sync, SheetsWebAppClient $client): int
    {
        $this->components->twoColumnDetail('Enabled', config('sheets.enabled') ? '<fg=green>yes</>' : '<fg=yellow>no</>');
        $problem = $client->urlProblem();
        $this->components->twoColumnDetail(
            'Web App URL',
            $problem ? '<fg=yellow>' . $problem . '</>' : (string) config('sheets.url')
        );
        $this->components->twoColumnDetail('Shared secret', config('sheets.secret') ? 'set' : '<fg=yellow>not set</>');

        if (! $sync->configured()) {
            $this->newLine();
            $this->components->warn('Sync is off until all three are in place. See GOOGLE_SHEETS.md.');

            return self::SUCCESS;
        }

        $this->newLine();

        foreach ($sync->pendingCounts() as $entity => $count) {
            $this->components->twoColumnDetail('Pending ' . $entity, (string) $count);
        }

        $this->newLine();

        try {
            $response = $client->ping();
            $this->components->info('Connected. Spreadsheet: ' . ($response['spreadsheet'] ?? 'unknown'));
        } catch (Throwable $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
