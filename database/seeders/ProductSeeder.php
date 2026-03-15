<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Category;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $pizza = Category::where('name', 'Pizza')->first();
        $burger = Category::where('name', 'Burgers')->first();

        Product::create([
            'category_id' => $pizza->id,
            'name' => 'Cheese Margherita',
            'description' => 'Classic pizza with tomato sauce and mozzarella',
            'price' => 12.99,
            'discount_price' => 10.99,
            'image' => 'margherita.png',
            'stock' => 50,
            'is_available' => true
        ]);

        Product::create([
            'category_id' => $burger->id,
            'name' => 'Classic Beef Burger',
            'description' => 'Juicy beef patty with lettuce and cheese',
            'price' => 8.99,
            'image' => 'beef-burger.png',
            'stock' => 100,
            'is_available' => true
        ]);
    }
}
