<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'Restaurants',
            'Electronics',
            'Clothing',
            'Home Services',
            'Transportation',
            'Education',
            'Beauty',
            'Groceries',
        ];

        foreach ($categories as $category) {
            Category::create([
                'name' => $category,
            ]);
        }
    }
}