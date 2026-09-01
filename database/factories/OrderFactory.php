<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    public function definition(): array
    {
        $subtotal = $this->faker->numberBetween(20000, 300000);

        return [
            'order_number'            => 'SALE-' . now()->format('Y') . '-' . $this->faker->unique()->numerify('######'),
            'customer_id'             => Customer::factory(),
            'sales_person_id'         => null,
            'order_created_at'        => now(),
            'requested_delivery_date' => today()->addDays(7),
            'actual_delivery_date'    => null,
            'subtotal'                => $subtotal,
            'discount'                => 0,
            'delivery_charge'         => 0,
            'tax'                     => 0,
            'grand_total'             => $subtotal,
            'payment_status'          => PaymentStatus::Pending->value,
            'payment_method'          => PaymentMethod::Cash->value,
            'amount_paid'             => 0,
            'balance_due'             => $subtotal,
            'order_status'            => OrderStatus::New->value,
            'zip_code'                => $this->faker->randomElement(['54000', '54700', '44000']),
        ];
    }

    public function delivered(?int $daysLate = 0): static
    {
        return $this->state(fn (array $attributes) => [
            'order_status'         => OrderStatus::Delivered->value,
            'actual_delivery_date' => \Illuminate\Support\Carbon::parse($attributes['requested_delivery_date'])->addDays($daysLate),
            'payment_status'       => PaymentStatus::Paid->value,
            'amount_paid'          => $attributes['grand_total'],
            'balance_due'          => 0,
        ]);
    }

    public function dueToday(): static
    {
        return $this->state(fn () => ['requested_delivery_date' => today()]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => ['order_status' => OrderStatus::Cancelled->value]);
    }
}
