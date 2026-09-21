<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Google Sheets mirror (see GOOGLE_SHEETS.md).
 *
 * Every synced table gets a nullable `sheet_synced_at`. NULL means "this row
 * has changed since it was last written to the sheet", so the very first run
 * after this migration backfills the whole history for free.
 */
return new class extends Migration
{
    private array $tables = ['orders', 'customers', 'products'];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->timestamp('sheet_synced_at')->nullable()->index();
            });
        }

        // Hard-deleted rows leave nothing behind to mark dirty, so the id is
        // parked here until the sheet row has actually been removed.
        Schema::create('sheet_sync_deletions', function (Blueprint $table) {
            $table->id();
            $table->string('entity', 30);
            $table->unsignedBigInteger('entity_id');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['entity', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sheet_sync_deletions');

        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn('sheet_synced_at');
            });
        }
    }
};
