<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Pizza', 'image' => 'pizza.png', 'status' => true],
            ['name' => 'Burgers', 'image' => 'burger.png', 'status' => true],
            ['name' => 'Drinks', 'image' => 'drinks.png', 'status' => true],
            ['name' => 'Desserts', 'image' => 'dessert.png', 'status' => true],
        ];

        foreach ($categories as $category) {
            Category::create($category);
        }
    }
}
