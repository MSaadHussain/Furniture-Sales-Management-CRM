<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone', 40);
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->string('city', 120)->nullable();
            $table->string('state', 120)->nullable();
            // ZIP/postal code is the backbone of the location analytics, so it is
            // required at the application layer and indexed here.
            $table->string('zip_code', 20);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('phone');
            $table->index('zip_code');
            $table->index('email');
            $table->index('city');
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
