<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 40)->nullable()->after('email');
            $table->string('role', 30)->default('sales_person')->after('password');
            $table->boolean('is_active')->default(true)->after('role');
            $table->string('avatar_color', 20)->nullable()->after('is_active');
            $table->string('avatar_path')->nullable()->after('avatar_color');
            $table->softDeletes();

            $table->index('role');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropIndex(['is_active']);
            $table->dropSoftDeletes();
            $table->dropColumn(['phone', 'role', 'is_active', 'avatar_color', 'avatar_path']);
        });
    }
};
