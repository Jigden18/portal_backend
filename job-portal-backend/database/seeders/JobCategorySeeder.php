<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\JobCategory;

class JobCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Content Writer', 'icon' => '/icons/edit.svg'],
            ['name' => 'Art & Design', 'icon' => '/icons/palette.svg'],
            ['name' => 'Human Resources', 'icon' => '/icons/users.svg'],
            ['name' => 'Programmer', 'icon' => '/icons/code.svg'],
            ['name' => 'Finance', 'icon' => '/icons/wallet-minimal.svg'],
            ['name' => 'Customer Service', 'icon' => '/icons/headphones.svg'],
            ['name' => 'Food & Restaurant', 'icon' => '/icons/utensils-crossed.svg'],
            ['name' => 'Music Producer', 'icon' => '/icons/music.svg'],
        ];

        foreach ($categories as $category) {
            JobCategory::create($category);
        }
    }
}

