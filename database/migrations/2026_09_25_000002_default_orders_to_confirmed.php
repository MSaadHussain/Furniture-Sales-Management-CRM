<?php

use App\Enums\ConfirmationStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Confirmed is the normal state: an order is real unless someone says
 * otherwise. Everything already on file counts as confirmed, so the flag only
 * ever marks the exceptions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('confirmation_status', 20)
                ->default(ConfirmationStatus::Confirmed->value)
                ->change();
        });

        DB::table('orders')->update(['confirmation_status' => ConfirmationStatus::Confirmed->value]);
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('confirmation_status', 20)
                ->default(ConfirmationStatus::NotConfirmed->value)
                ->change();
        });
    }
};
