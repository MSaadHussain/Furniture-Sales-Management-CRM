<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ColourFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'      => $this->faker->unique()->colorName(),
            'hex'       => $this->faker->hexColor(),
            'is_active' => true,
        ];
    }
}
