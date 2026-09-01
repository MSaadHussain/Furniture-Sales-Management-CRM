<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CategoryFactory extends Factory
{
    public function definition(): array
    {
        $name = $this->faker->unique()->randomElement([
            'Sofa', 'Bed', 'Dining Table', 'Dining Chair', 'Coffee Table',
            'TV Unit', 'Wardrobe', 'Cabinet', 'Office Furniture', 'Other',
        ]);

        return [
            'name'      => $name,
            'slug'      => Str::slug($name),
            'is_active' => true,
        ];
    }
}
