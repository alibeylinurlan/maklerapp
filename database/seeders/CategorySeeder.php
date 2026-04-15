<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['bina_id' => '1', 'slug' => 'menziller', 'name_az' => 'Mənzillər'],
            ['bina_id' => '2', 'slug' => 'yeni-tikili', 'name_az' => 'Yeni tikili'],
            ['bina_id' => '3', 'slug' => 'kohne-tikili', 'name_az' => 'Köhnə tikili'],
            ['bina_id' => '5', 'slug' => 'heyet-evleri', 'name_az' => 'Həyət evləri'],
            ['bina_id' => '7', 'slug' => 'ofisler', 'name_az' => 'Ofislər'],
            ['bina_id' => '8', 'slug' => 'qarajlar', 'name_az' => 'Qarajlar'],
            ['bina_id' => '9', 'slug' => 'torpaq', 'name_az' => 'Torpaq'],
            ['bina_id' => '10', 'slug' => 'obyektler', 'name_az' => 'Obyektlər'],
        ];

        foreach ($categories as $cat) {
            Category::updateOrCreate(['bina_id' => $cat['bina_id']], $cat);
        }
    }
}
