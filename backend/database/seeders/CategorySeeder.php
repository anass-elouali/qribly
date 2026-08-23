<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        foreach (DemoCatalog::CATEGORIES as $legacyName => $frenchName) {
            $category = Category::query()->where('name', $frenchName)->first();

            if (! $category) {
                $category = Category::query()->where('name', $legacyName)->first();
            }

            if ($category) {
                $category->update(['name' => $frenchName]);
            } else {
                Category::create(['name' => $frenchName]);
            }
        }
    }
}
