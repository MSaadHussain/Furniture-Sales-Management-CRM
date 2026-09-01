<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $password = env('SEED_PASSWORD', 'password');

        $demoAccounts = [
            ['name' => 'Sales Manager', 'email' => 'manager@example.test', 'role' => UserRole::Manager,     'phone' => '+92 300 0000002'],
            ['name' => 'Ali Raza',      'email' => 'ali@example.test',     'role' => UserRole::SalesPerson, 'phone' => '+92 300 0000003'],
            ['name' => 'Ahmed Khan',    'email' => 'ahmed@example.test',   'role' => UserRole::SalesPerson, 'phone' => '+92 300 0000004'],
            ['name' => 'Sara Iqbal',    'email' => 'sara@example.test',    'role' => UserRole::SalesPerson, 'phone' => '+92 300 0000005'],
            ['name' => 'Bilal Hussain', 'email' => 'bilal@example.test',   'role' => UserRole::SalesPerson, 'phone' => '+92 300 0000006'],
        ];

        foreach ($demoAccounts as $account) {
            User::updateOrCreate(
                ['email' => $account['email']],
                [
                    'name'              => $account['name'],
                    'phone'             => $account['phone'],
                    'role'              => $account['role'],
                    'password'          => Hash::make($password),
                    'is_active'         => true,
                    'email_verified_at' => now(),
                ],
            );
        }

        $this->call([
            CategorySeeder::class,
            ColourSeeder::class,
            ProductSeeder::class,
            CustomerSeeder::class,
            OrderSeeder::class,
        ]);
    }
}
