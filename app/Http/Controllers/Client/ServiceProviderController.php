<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ServiceProviderController extends Controller
{
    public function index(Request $request, Service $service): Response
    {
        $validated = $request->validate([
            'city' => ['nullable', 'string', 'max:100'],
            'neighborhood' => ['nullable', 'string', 'max:100'],
        ]);

        $category = $service->category;

        // Masquer les services désactivés et ceux de catégories inactives.
        abort_unless(
            $service->is_active && $category?->is_active,
            404
        );

        $city = trim($validated['city'] ?? '');
        $neighborhood = trim($validated['neighborhood'] ?? '');

        // Ne montrer que les prestataires approuvés qui proposent
        // activement ce service.
        $providers = $service->providerProfiles()
            ->where('provider_profiles.status', 'approved')
            ->wherePivot('is_active', true)
            ->with('user:id,name,email')
            ->when(
                $city !== '',
                fn ($query) => $query->where(
                    'provider_profiles.city',
                    'like',
                    "%{$city}%"
                )
            )
            ->when(
                $neighborhood !== '',
                fn ($query) => $query->where(
                    'provider_profiles.neighborhood',
                    'like',
                    "%{$neighborhood}%"
                )
            )
            ->orderByDesc('provider_profiles.is_available')
            ->orderBy('provider_profiles.city')
            ->get()
            ->map(fn ($profile) => [
                'id' => $profile->id,
                'business_name' => $profile->business_name,
                'bio' => $profile->bio,
                'years_experience' => $profile->years_experience,
                'city' => $profile->city,
                'neighborhood' => $profile->neighborhood,
                'is_available' => $profile->is_available,
                'price' => $profile->pivot->price,

                'user' => [
                    'name' => $profile->user->name,
                ],
            ])
            ->values();

        return Inertia::render('client/services/providers', [
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
            'providers' => $providers,
            'filters' => [
                'city' => $city,
                'neighborhood' => $neighborhood,
            ],
        ]);
    }
}
