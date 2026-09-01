<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Optional per-product colour shortlist. A product with no rows here
        // offers the full active colour master on the order form.
        Schema::create('product_colour', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('colour_id')->constrained('colours')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['product_id', 'colour_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_colour');
    }
};
