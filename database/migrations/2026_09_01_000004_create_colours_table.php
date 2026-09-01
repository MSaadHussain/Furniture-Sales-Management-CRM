<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Colour master (requirements 8.3). Order items snapshot the colour name
        // so historical reporting survives a rename or deletion here.
        Schema::create('colours', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80)->unique();
            $table->string('hex', 7)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('colours');
    }
};
