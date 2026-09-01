<?php

namespace Database\Seeders;

use App\Models\Colour;
use Illuminate\Database\Seeder;

class ColourSeeder extends Seeder
{
    public function run(): void
    {
        $colours = [
            'Grey'   => '#8E8E93',
            'Black'  => '#1C1C1E',
            'Brown'  => '#6F4E37',
            'Walnut' => '#5C4033',
            'Beige'  => '#D9C7A7',
            'White'  => '#F5F5F7',
            'Blue'   => '#2F5D8C',
            'Cream'  => '#EFE4D2',
            'Oak'    => '#B58A4E',
            'Green'  => '#3F6B54',
        ];

        $i = 0;
        foreach ($colours as $name => $hex) {
            Colour::updateOrCreate(
                ['name' => $name],
                ['hex' => $hex, 'is_active' => true, 'sort_order' => $i++],
            );
        }
    }
}
