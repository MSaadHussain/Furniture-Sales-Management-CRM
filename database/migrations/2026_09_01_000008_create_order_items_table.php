<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            // A product may be deactivated or removed later; the snapshot columns
            // keep historical orders and reports accurate regardless.
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('colour_id')->nullable()->constrained('colours')->nullOnDelete();

            $table->string('item_name_snapshot');
            $table->string('item_colour', 80)->nullable();
            $table->string('category_name_snapshot', 120)->nullable();

            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('line_total', 14, 2)->default(0);
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->index('order_id');
            $table->index('product_id');
            $table->index('item_colour');
            $table->index('colour_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
