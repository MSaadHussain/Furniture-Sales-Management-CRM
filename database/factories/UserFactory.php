<?php

namespace Database\Factories;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected static ?string $password = null;

    public function definition(): array
    {
        return [
            'name'              => $this->faker->name(),
            'email'             => $this->faker->unique()->safeEmail(),
            'phone'             => $this->faker->numerify('+92 3## #######'),
            'email_verified_at' => now(),
            'password'          => static::$password ??= Hash::make('password'),
            'role'              => UserRole::SalesPerson->value,
            'is_active'         => true,
            'avatar_color'      => $this->faker->randomElement(['#465FFF', '#12B76A', '#F79009', '#7A5AF8', '#F04438']),
            'remember_token'    => Str::random(10),
        ];
    }

    public function admin(): static
    {
        return $this->state(fn () => ['role' => UserRole::Admin->value]);
    }

    public function manager(): static
    {
        return $this->state(fn () => ['role' => UserRole::Manager->value]);
    }

    public function salesPerson(): static
    {
        return $this->state(fn () => ['role' => UserRole::SalesPerson->value]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function unverified(): static
    {
        return $this->state(fn () => ['email_verified_at' => null]);
    }
}
