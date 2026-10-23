<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\ProviderProfile;
use App\Models\Service;
use Inertia\Inertia;
use Inertia\Response;

class ProviderDetailController extends Controller
{
    public function show(
        Service $service,
        ProviderProfile $providerProfile
    ): Response {
        $category = $service->category;

        // Le service et sa catégorie doivent être actifs.
        abort_unless(
            $service->is_active && $category?->is_active,
            404
        );

        // Seuls les profils approuvés peuvent être consultés ici.
        abort_unless(
            $providerProfile->status === 'approved',
            404
        );

        // Vérifier que le prestataire propose réellement ce service.
        $offeredService = $providerProfile->services()
            ->where('services.id', $service->id)
            ->wherePivot('is_active', true)
            ->first();

        abort_unless($offeredService, 404);

        $providerProfile->load('user:id,name');

        return Inertia::render('client/providers/show', [
            'service' => [
                'id' => $service->id,
                'name' => $service->name,
                'slug' => $service->slug,
                'description' => $service->description,
                'pricing_type' => $service->pricing_type,
                'base_price' => $service->base_price,
                'category' => [
                    'name' => $category->name,
                    'slug' => $category->slug,
                ],
            ],

            'provider' => [
                'id' => $providerProfile->id,
                'name' => $providerProfile->user->name,
                'business_name' => $providerProfile->business_name,
                'bio' => $providerProfile->bio,
                'years_experience' => $providerProfile->years_experience,
                'city' => $providerProfile->city,
                'neighborhood' => $providerProfile->neighborhood,
                'is_available' => $providerProfile->is_available,
                'price' => $offeredService->pivot->price,
            ],
        ]);
    }
}
