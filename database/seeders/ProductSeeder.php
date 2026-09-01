<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Colour;
use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /** name => [category, price, colour shortlist] */
    private const CATALOGUE = [
        ['3-Seater Sofa',        'Sofa',            85000, ['Grey', 'Beige', 'Blue', 'Brown']],
        ['2-Seater Sofa',        'Sofa',            62000, ['Grey', 'Beige', 'Black']],
        ['L-Shaped Sofa',        'Sofa',           145000, ['Grey', 'Brown', 'Cream']],
        ['Recliner Chair',       'Sofa',            48000, ['Black', 'Brown', 'Grey']],
        ['King Size Bed',        'Bed',            120000, ['Walnut', 'Oak', 'White']],
        ['Queen Size Bed',       'Bed',             95000, ['Walnut', 'Oak', 'White']],
        ['Single Bed',           'Bed',             45000, ['Oak', 'White']],
        ['6-Seater Dining Table','Dining Table',    80000, ['Walnut', 'Oak', 'Black']],
        ['4-Seater Dining Table','Dining Table',    55000, ['Walnut', 'Oak']],
        ['Dining Chair',         'Dining Chair',     8000, ['Black', 'Brown', 'Beige', 'Grey']],
        ['Bar Stool',            'Dining Chair',     9500, ['Black', 'Walnut']],
        ['Coffee Table',         'Coffee Table',    35000, ['Walnut', 'Black', 'Oak']],
        ['Side Table',           'Coffee Table',    18000, ['Walnut', 'White']],
        ['TV Console 6ft',       'TV Unit',         52000, ['Walnut', 'Black', 'White']],
        ['TV Console 4ft',       'TV Unit',         38000, ['Walnut', 'Black']],
        ['3-Door Wardrobe',      'Wardrobe',       110000, ['Walnut', 'White', 'Oak']],
        ['2-Door Wardrobe',      'Wardrobe',        78000, ['Walnut', 'White']],
        ['Display Cabinet',      'Cabinet',         46000, ['Walnut', 'White', 'Oak']],
        ['Shoe Cabinet',         'Cabinet',         22000, ['White', 'Oak']],
        ['Executive Desk',       'Office Furniture', 68000, ['Walnut', 'Black']],
        ['Office Chair',         'Office Furniture', 26000, ['Black', 'Grey']],
        ['Bookshelf',            'Office Furniture', 32000, ['Oak', 'White', 'Walnut']],
    ];

    public function run(): void
    {
        $categories = Category::pluck('id', 'name');
        $colours    = Colour::pluck('id', 'name');

        foreach (self::CATALOGUE as $i => [$name, $category, $price, $colourNames]) {
            $product = Product::updateOrCreate(
                ['name' => $name],
                [
                    'product_code'  => sprintf('FUR-%04d', $i + 1),
                    'category_id'   => $categories[$category],
                    'default_price' => $price,
                    'is_active'     => true,
                    'description'   => null,
                ],
            );

            $product->colours()->sync(
                collect($colourNames)->map(fn ($c) => $colours[$c] ?? null)->filter()->all()
            );
        }
    }
}
