<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_code'  => strtoupper($this->faker->unique()->bothify('FUR-####')),
            'name'          => $this->faker->words(2, true),
            'category_id'   => Category::factory(),
            'default_price' => $this->faker->numberBetween(5000, 150000),
            'is_active'     => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
