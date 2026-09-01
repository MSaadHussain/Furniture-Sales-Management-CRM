<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'     => $this->faker->name(),
            'phone'    => $this->faker->unique()->numerify('+92 3## #######'),
            'email'    => $this->faker->unique()->safeEmail(),
            'address'  => $this->faker->streetAddress(),
            'city'     => $this->faker->randomElement(['Lahore', 'Karachi', 'Islamabad', 'Rawalpindi', 'Multan']),
            'state'    => 'Punjab',
            'zip_code' => $this->faker->randomElement(['54000', '54700', '44000', '46000', '75500']),
        ];
    }
}
