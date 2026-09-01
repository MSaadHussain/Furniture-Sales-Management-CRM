<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'Sofa', 'Bed', 'Dining Table', 'Dining Chair', 'Coffee Table',
            'TV Unit', 'Wardrobe', 'Cabinet', 'Office Furniture', 'Other',
        ];

        foreach ($categories as $i => $name) {
            Category::updateOrCreate(
                ['name' => $name],
                ['slug' => Category::uniqueSlug($name), 'is_active' => true, 'sort_order' => $i],
            );
        }
    }
}
