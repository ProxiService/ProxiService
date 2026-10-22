<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Plomberie',
                'slug' => 'plomberie',
                'description' => 'Réparation et installation de plomberie.',
                'icon' => 'wrench',
            ],
            [
                'name' => 'Électricité',
                'slug' => 'electricite',
                'description' => 'Dépannage et installations électriques.',
                'icon' => 'zap',
            ],
            [
                'name' => 'Menuiserie',
                'slug' => 'menuiserie',
                'description' => 'Fabrication et réparation de meubles.',
                'icon' => 'hammer',
            ],
            [
                'name' => 'Peinture',
                'slug' => 'peinture',
                'description' => 'Travaux de peinture et finitions.',
                'icon' => 'paint-roller',
            ],
            [
                'name' => 'Maçonnerie',
                'slug' => 'maconnerie',
                'description' => 'Construction et rénovation.',
                'icon' => 'brick-wall',
            ],
            [
                'name' => 'Nettoyage',
                'slug' => 'nettoyage',
                'description' => 'Nettoyage et entretien des espaces.',
                'icon' => 'sparkles',
            ],
            [
                'name' => 'Serrurerie',
                'slug' => 'serrurerie',
                'description' => 'Installation et réparation de serrures.',
                'icon' => 'key-round',
            ],
            [
                'name' => 'Mécanique',
                'slug' => 'mecanique',
                'description' => 'Entretien et réparation automobile.',
                'icon' => 'car-front',
            ],
            [
                'name' => 'Jardinage',
                'slug' => 'jardinage',
                'description' => 'Entretien des jardins et espaces verts.',
                'icon' => 'sprout',
            ],
            [
                'name' => 'Électroménager',
                'slug' => 'electromenager',
                'description' => 'Réparation des appareils ménagers.',
                'icon' => 'plug',
            ],
        ];

        foreach ($categories as $category) {
            Category::updateOrCreate(
                ['slug' => $category['slug']],
                [
                    'name' => $category['name'],
                    'description' => $category['description'],
                    'icon' => $category['icon'],
                    'is_active' => true,
                ]
            );
        }
    }
}
