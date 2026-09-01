<?php

namespace Database\Seeders;

use App\Models\Customer;
use Illuminate\Database\Seeder;

/**
 * Sample customers spread across a handful of ZIP codes, weighted so the ZIP
 * report has an obvious leader and a couple of growing areas to find.
 */
class CustomerSeeder extends Seeder
{
    /** zip => [city, state, weight] */
    private const AREAS = [
        '54000' => ['Lahore', 'Punjab', 10],
        '54700' => ['Lahore', 'Punjab', 8],
        '54600' => ['Lahore', 'Punjab', 5],
        '44000' => ['Islamabad', 'Capital Territory', 6],
        '46000' => ['Rawalpindi', 'Punjab', 4],
        '75500' => ['Karachi', 'Sindh', 5],
        '74200' => ['Karachi', 'Sindh', 3],
        '25000' => ['Peshawar', 'Khyber Pakhtunkhwa', 2],
        '38000' => ['Faisalabad', 'Punjab', 3],
        '60000' => ['Multan', 'Punjab', 2],
    ];

    private const FIRST = ['Ahmed', 'Fatima', 'Bilal', 'Ayesha', 'Usman', 'Zainab', 'Hassan', 'Maryam', 'Omar', 'Hira', 'Kamran', 'Nadia', 'Tariq', 'Sana', 'Imran', 'Rabia', 'Faisal', 'Amna', 'Junaid', 'Sadia'];
    private const LAST  = ['Khan', 'Ali', 'Malik', 'Sheikh', 'Butt', 'Chaudhry', 'Qureshi', 'Siddiqui', 'Raza', 'Hussain', 'Aslam', 'Javed'];

    public function run(): void
    {
        // Build a weighted pool so some ZIPs genuinely dominate the ranking.
        $pool = [];
        foreach (self::AREAS as $zip => [$city, $state, $weight]) {
            $pool = array_merge($pool, array_fill(0, $weight, $zip));
        }

        $phone = 3001000000;

        for ($i = 0; $i < 120; $i++) {
            $zip = $pool[array_rand($pool)];
            [$city, $state] = self::AREAS[$zip];

            $name = self::FIRST[array_rand(self::FIRST)] . ' ' . self::LAST[array_rand(self::LAST)];

            Customer::create([
                'name'     => $name,
                'phone'    => '+92 ' . substr((string) ($phone + $i), 0, 3) . ' ' . substr((string) ($phone + $i), 3),
                'email'    => strtolower(str_replace(' ', '.', $name)) . ($i + 1) . '@example.test',
                'address'  => rand(1, 400) . ' Street ' . rand(1, 40) . ', Block ' . chr(65 + rand(0, 7)),
                'city'     => $city,
                'state'    => $state,
                'zip_code' => $zip,
            ]);
        }
    }
}
