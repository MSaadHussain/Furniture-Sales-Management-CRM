<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Optional second contact number. Nullable and additive, so it is safe to
     * run against a database that already holds live customers.
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('phone_alt', 40)->nullable()->after('phone');
            // Indexed because the duplicate-customer lookup searches it too.
            $table->index('phone_alt');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex(['phone_alt']);
            $table->dropColumn('phone_alt');
        });
    }
};
