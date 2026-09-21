<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Purely informational counter typed in by the user. It is not a
            // quantity and takes no part in totals, revenue or any report.
            $table->unsignedInteger('number_of_orders')->nullable()->after('order_source');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('number_of_orders');
        });
    }
};
