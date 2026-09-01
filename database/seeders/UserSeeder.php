<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Development accounts. In any non-local environment the password is taken from
 * SEED_PASSWORD, or a random one is generated and printed once, so a production
 * deployment can never inherit a known default (requirements 51).
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $isLocal  = app()->environment(['local', 'testing']);
        $password = env('SEED_PASSWORD');

        if (blank($password)) {
            $password = $isLocal ? 'password' : Str::password(16);
        }

        User::updateOrCreate(
            ['email' => 'admin@example.test'],
            [
                'name'              => 'System Admin',
                'phone'             => '+92 300 0000001',
                'role'              => UserRole::Admin,
                'password'          => Hash::make($password),
                'is_active'         => true,
                'email_verified_at' => now(),
            ],
        );

        if (! $isLocal) {
            $this->command?->warn("Admin account password: {$password}");
            $this->command?->warn('Change it immediately after first login.');
        }
    }
}
