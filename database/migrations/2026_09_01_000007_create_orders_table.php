<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            // Business-facing identifier, e.g. SALE-2026-000001. Never reused.
            $table->string('order_number', 32)->unique();

            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('sales_person_id')->nullable()->constrained('users')->nullOnDelete();

            // Three independent dates. order_created_at records when the business
            // received the order; requested_delivery_date is what the customer
            // asked for; actual_delivery_date is filled in on completion and must
            // never overwrite the requested date.
            $table->timestamp('order_created_at')->useCurrent();
            $table->date('requested_delivery_date');
            $table->date('actual_delivery_date')->nullable();

            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('discount', 14, 2)->default(0);
            $table->decimal('delivery_charge', 14, 2)->default(0);
            $table->decimal('tax', 14, 2)->default(0);
            $table->decimal('grand_total', 14, 2)->default(0);

            $table->string('payment_status', 20)->default('pending');
            $table->string('payment_method', 30)->nullable();
            $table->decimal('amount_paid', 14, 2)->default(0);
            $table->decimal('balance_due', 14, 2)->default(0);

            $table->string('order_status', 30)->default('new');

            // Denormalised copy of the customer ZIP at order time. Keeps location
            // analytics stable if the customer later moves, and lets the ZIP
            // reports aggregate without joining customers on every query.
            $table->string('zip_code', 20)->nullable();

            $table->text('notes')->nullable();
            $table->text('cancellation_reason')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('customer_id');
            $table->index('sales_person_id');
            $table->index('order_created_at');
            $table->index('requested_delivery_date');
            $table->index('actual_delivery_date');
            $table->index('order_status');
            $table->index('payment_status');
            $table->index('zip_code');
            // Serves the daily delivery board: deliveries for a date, by status.
            $table->index(['requested_delivery_date', 'order_status'], 'orders_delivery_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
