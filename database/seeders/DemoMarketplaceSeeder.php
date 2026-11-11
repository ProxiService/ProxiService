<?php

namespace Database\Seeders;

use App\Models\ProviderProfile;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\ServiceReview;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/** Données fictives de démonstration; jamais à exécuter en production. */
class DemoMarketplaceSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->error('Les données de démonstration sont interdites en production.');

            return;
        }

        $samples = [
            ['id' => 'amina-kone', 'name' => 'Amina Koné', 'business' => 'Atelier Eau Claire', 'category' => 'plomberie', 'service' => 'reparation-robinet', 'neighborhood' => 'Cocody Angré', 'lat' => 5.3912, 'lng' => -3.9674, 'years' => 7],
            ['id' => 'yves-kouassi', 'name' => 'Yves Kouassi', 'business' => 'Élec Ivoire Démo', 'category' => 'electricite', 'service' => 'installation-prise', 'neighborhood' => 'Yopougon Niangon', 'lat' => 5.3368, 'lng' => -4.0912, 'years' => 9],
            ['id' => 'fatou-traore', 'name' => 'Fatou Traoré', 'business' => 'Menuiserie Baobab Démo', 'category' => 'menuiserie', 'service' => 'reparation-meuble', 'neighborhood' => 'Marcory Résidentiel', 'lat' => 5.3021, 'lng' => -3.9898, 'years' => 5],
            ['id' => 'ibrahim-diallo', 'name' => 'Ibrahim Diallo', 'business' => 'Services Lagune Démo', 'category' => 'serrurerie', 'service' => 'remplacement-serrure', 'neighborhood' => 'Treichville', 'lat' => 5.2946, 'lng' => -4.0082, 'years' => 11],
        ];

        $client = User::query()->updateOrCreate(
            ['email' => 'client.demo@example.test'],
            ['name' => 'Client Démo', 'password' => Hash::make('demo-only-password'), 'role' => 'client', 'email_verified_at' => now()],
        );

        $profiles = [];

        foreach ($samples as $sample) {
            $user = User::query()->updateOrCreate(
                ['email' => $sample['id'].'@example.test'],
                ['name' => $sample['name'], 'password' => Hash::make('demo-only-password'), 'role' => 'prestataire', 'email_verified_at' => now()],
            );

            $profile = ProviderProfile::query()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'business_name' => $sample['business'],
                    'bio' => '[PROFIL DE DÉMONSTRATION] Exemple fictif pour présenter le service.',
                    'phone' => null,
                    'years_experience' => $sample['years'],
                    'city' => 'Abidjan',
                    'neighborhood' => $sample['neighborhood'],
                    'latitude' => $sample['lat'],
                    'longitude' => $sample['lng'],
                    'is_available' => true,
                ],
            );
            $profile->status = 'approved';
            $profile->save();

            $service = Service::query()->where('slug', $sample['service'])
                ->whereHas('category', fn ($query) => $query->where('slug', $sample['category']))
                ->first();

            if ($service) {
                $profile->services()->syncWithoutDetaching([$service->id => ['is_active' => true]]);
                $profiles[] = [$profile, $service];
            }
        }

        // Les avis et interventions de ce seeder sont fictifs et étiquetés dans leur contenu.
        foreach ($profiles as $index => [$profile, $service]) {
            $request = ServiceRequest::query()->firstOrCreate(
                ['description' => '[DÉMO] Intervention fictive '.($index + 1), 'client_id' => $client->id],
                [
                    'provider_profile_id' => $profile->id,
                    'service_id' => $service->id,
                    'city' => 'Abidjan',
                    'neighborhood' => $profile->neighborhood,
                    'status' => 'completed',
                    'is_urgent' => false,
                ],
            );

            ServiceReview::query()->updateOrCreate(
                ['service_request_id' => $request->id],
                [
                    'client_id' => $client->id,
                    'provider_profile_id' => $profile->id,
                    'rating' => [5, 4, 5, 4][$index],
                    'comment' => '[AVIS DE DÉMONSTRATION — FICTIF] Exemple neutre, sans expérience client réelle.',
                    'is_visible' => true,
                ],
            );
        }
    }
}
