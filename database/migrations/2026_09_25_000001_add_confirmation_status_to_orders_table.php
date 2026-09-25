<?php

use App\Enums\ConfirmationStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Confirmed / Not Confirmed / Duplicate, tracked separately from order_status.
 * Existing orders start at the default rather than NULL, so the column never
 * has to be read as "unknown".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('confirmation_status', 20)
                ->default(ConfirmationStatus::DEFAULT->value)
                ->after('order_status')
                ->index();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['confirmation_status']);
            $table->dropColumn('confirmation_status');
        });
    }
};
