<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Service;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
{
    public function run(): void
    {
        $catalog = [
            'plomberie' => [
                [
                    'name' => 'Réparation de robinet',
                    'slug' => 'reparation-robinet',
                    'description' => 'Réparation ou remplacement simple de robinet.',
                    'pricing_type' => 'fixed',
                    'base_price' => 5000,
                    'estimated_duration_minutes' => 45,
                ],
                [
                    'name' => 'Réparation de fuite d’eau',
                    'slug' => 'reparation-fuite-eau',
                    'description' => 'Recherche et réparation de fuite.',
                    'pricing_type' => 'quote',
                    'base_price' => null,
                    'estimated_duration_minutes' => null,
                ],
                [
                    'name' => 'Débouchage',
                    'slug' => 'debouchage',
                    'description' => 'Débouchage de canalisations.',
                    'pricing_type' => 'quote',
                    'base_price' => null,
                    'estimated_duration_minutes' => null,
                ],
                [
                    'name' => 'Installation sanitaire',
                    'slug' => 'installation-sanitaire',
                    'description' => 'Installation ou remplacement d’équipements sanitaires.',
                    'pricing_type' => 'quote',
                    'base_price' => null,
                    'estimated_duration_minutes' => null,
                ],
            ],

            'electricite' => [
                [
                    'name' => 'Installation de prise',
                    'slug' => 'installation-prise',
                    'description' => 'Installation ou remplacement de prise électrique.',
                    'pricing_type' => 'quote',
                    'base_price' => null,
                    'estimated_duration_minutes' => null,
                ],
                [
                    'name' => 'Installation de luminaire',
                    'slug' => 'installation-luminaire',
                    'description' => 'Pose de luminaires et éclairages.',
                    'pricing_type' => 'quote',
                    'base_price' => null,
                    'estimated_duration_minutes' => null,
                ],
                [
                    'name' => 'Dépannage électrique',
                    'slug' => 'depannage-electrique',
                    'description' => 'Diagnostic et dépannage électrique.',
                    'pricing_type' => 'quote',
                    'base_price' => null,
                    'estimated_duration_minutes' => null,
                ],
            ],

            'menuiserie' => [
                [
                    'name' => 'Fabrication de table',
                    'slug' => 'fabrication-table',
                    'description' => 'Fabrication de table selon les dimensions demandées.',
                    'pricing_type' => 'quote',
                    'base_price' => null,
                    'estimated_duration_minutes' => null,
                ],
                [
                    'name' => 'Réparation de meuble',
                    'slug' => 'reparation-meuble',
                    'description' => 'Réparation de meubles endommagés.',
                    'pricing_type' => 'quote',
                    'base_price' => null,
                    'estimated_duration_minutes' => null,
                ],
                [
                    'name' => 'Installation de porte',
                    'slug' => 'installation-porte',
                    'description' => 'Installation ou ajustement de porte.',
                    'pricing_type' => 'quote',
                    'base_price' => null,
                    'estimated_duration_minutes' => null,
                ],
            ],

            'peinture' => [
                [
                    'name' => 'Peinture intérieure',
                    'slug' => 'peinture-interieure',
                    'description' => 'Peinture de pièces et surfaces intérieures.',
                    'pricing_type' => 'quote',
                    'base_price' => null,
                    'estimated_duration_minutes' => null,
                ],
                [
                    'name' => 'Rénovation de peinture',
                    'slug' => 'renovation-peinture',
                    'description' => 'Préparation et rénovation des surfaces peintes.',
                    'pricing_type' => 'quote',
                    'base_price' => null,
                    'estimated_duration_minutes' => null,
                ],
            ],

            'maconnerie' => [
                [
                    'name' => 'Réparation de mur',
                    'slug' => 'reparation-mur',
                    'description' => 'Réparation de fissures et de murs endommagés.',
                    'pricing_type' => 'quote',
                    'base_price' => null,
                    'estimated_duration_minutes' => null,
                ],
                [
                    'name' => 'Petits travaux de maçonnerie',
                    'slug' => 'petits-travaux-maconnerie',
                    'description' => 'Petites réparations et travaux de maçonnerie.',
                    'pricing_type' => 'quote',
                    'base_price' => null,
                    'estimated_duration_minutes' => null,
                ],
            ],

            'nettoyage' => [
                [
                    'name' => 'Nettoyage de maison',
                    'slug' => 'nettoyage-maison',
                    'description' => 'Nettoyage courant d’une maison.',
                    'pricing_type' => 'quote',
                    'base_price' => null,
                    'estimated_duration_minutes' => null,
                ],
                [
                    'name' => 'Nettoyage après travaux',
                    'slug' => 'nettoyage-apres-travaux',
                    'description' => 'Nettoyage après rénovation ou chantier.',
                    'pricing_type' => 'quote',
                    'base_price' => null,
                    'estimated_duration_minutes' => null,
                ],
            ],

            'serrurerie' => [
                [
                    'name' => 'Ouverture de porte',
                    'slug' => 'ouverture-porte',
                    'description' => 'Intervention pour une porte bloquée.',
                    'pricing_type' => 'quote',
                    'base_price' => null,
                    'estimated_duration_minutes' => null,
                ],
                [
                    'name' => 'Remplacement de serrure',
                    'slug' => 'remplacement-serrure',
                    'description' => 'Remplacement ou installation de serrure.',
                    'pricing_type' => 'quote',
                    'base_price' => null,
                    'estimated_duration_minutes' => null,
                ],
            ],

            'mecanique' => [
                [
                    'name' => 'Diagnostic automobile',
                    'slug' => 'diagnostic-automobile',
                    'description' => 'Recherche de la cause d’une panne automobile.',
                    'pricing_type' => 'quote',
                    'base_price' => null,
                    'estimated_duration_minutes' => null,
                ],
                [
                    'name' => 'Entretien automobile',
                    'slug' => 'entretien-automobile',
                    'description' => 'Entretien courant du véhicule.',
                    'pricing_type' => 'quote',
                    'base_price' => null,
                    'estimated_duration_minutes' => null,
                ],
            ],

            'jardinage' => [
                [
                    'name' => 'Entretien de jardin',
                    'slug' => 'entretien-jardin',
                    'description' => 'Entretien des plantes et des espaces verts.',
                    'pricing_type' => 'quote',
                    'base_price' => null,
                    'estimated_duration_minutes' => null,
                ],
                [
                    'name' => 'Tonte de pelouse',
                    'slug' => 'tonte-pelouse',
                    'description' => 'Tonte et entretien de pelouse.',
                    'pricing_type' => 'quote',
                    'base_price' => null,
                    'estimated_duration_minutes' => null,
                ],
            ],

            'electromenager' => [
                [
                    'name' => 'Réparation de réfrigérateur',
                    'slug' => 'reparation-refrigerateur',
                    'description' => 'Diagnostic et réparation de réfrigérateur.',
                    'pricing_type' => 'quote',
                    'base_price' => null,
                    'estimated_duration_minutes' => null,
                ],
                [
                    'name' => 'Réparation de machine à laver',
                    'slug' => 'reparation-machine-laver',
                    'description' => 'Diagnostic et réparation de machine à laver.',
                    'pricing_type' => 'quote',
                    'base_price' => null,
                    'estimated_duration_minutes' => null,
                ],
            ],
        ];

        foreach ($catalog as $categorySlug => $services) {
            $category = Category::where('slug', $categorySlug)->firstOrFail();

            foreach ($services as $service) {
                Service::updateOrCreate(
                    [
                        'category_id' => $category->id,
                        'slug' => $service['slug'],
                    ],
                    [
                        'name' => $service['name'],
                        'description' => $service['description'],
                        'pricing_type' => $service['pricing_type'],
                        'base_price' => $service['base_price'],
                        'estimated_duration_minutes' => $service['estimated_duration_minutes'],
                        'is_active' => true,
                    ]
                );
            }
        }
    }
}
