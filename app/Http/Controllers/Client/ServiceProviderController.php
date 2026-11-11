<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Services\ProviderDistance;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ServiceProviderController extends Controller
{
    public function index(Request $request, Service $service, ProviderDistance $distanceCalculator): Response
    {
        $rules = [
            'city' => ['nullable', 'string', 'max:100'],
            'neighborhood' => ['nullable', 'string', 'max:100'],
        ];
        if ($request->isMethod('post')) {
            $rules['latitude'] = ['required', 'numeric', 'between:-90,90'];
            $rules['longitude'] = ['required', 'numeric', 'between:-180,180'];
        }
        $validated = $request->validate($rules);

        $category = $service->category;

        // Masquer les services désactivés et ceux de catégories inactives.
        abort_unless(
            $service->is_active && $category?->is_active,
            404
        );

        $city = trim($validated['city'] ?? '');
        $neighborhood = trim($validated['neighborhood'] ?? '');
        $hasLocation = $request->isMethod('post');

        $providers = collect();

        if ($hasLocation) {
            $providers = $service->providerProfiles()
                ->where('provider_profiles.status', 'approved')
                ->wherePivot('is_active', true)
                ->whereNotNull('provider_profiles.latitude')
                ->whereNotNull('provider_profiles.longitude')
                ->with('user:id,name,email')
                ->withCount(['reviews as visible_reviews_count' => fn ($query) => $query->where('is_visible', true)])
                ->withAvg(['reviews as visible_reviews_avg_rating' => fn ($query) => $query->where('is_visible', true)], 'rating')
                ->when($city !== '', fn ($query) => $query->where('provider_profiles.city', 'like', "%{$city}%"))
                ->when($neighborhood !== '', fn ($query) => $query->where('provider_profiles.neighborhood', 'like', "%{$neighborhood}%"))
                ->get()
                ->map(function ($profile) use ($validated, $distanceCalculator): array {
                    $distanceKm = $distanceCalculator->kilometers(
                        (float) $validated['latitude'],
                        (float) $validated['longitude'],
                        (float) $profile->latitude,
                        (float) $profile->longitude,
                    );
                    $reviewCount = (int) $profile->visible_reviews_count;

                    return [
                        'id' => $profile->id,
                        'business_name' => $profile->business_name,
                        'bio' => $profile->bio,
                        'years_experience' => $profile->years_experience,
                        'city' => $profile->city,
                        'neighborhood' => $profile->neighborhood,
                        'is_available' => $profile->is_available,
                        'price' => $profile->pivot->price,
                        'distance_km' => round($distanceKm, 1),
                        'rating' => $reviewCount >= 3 && $profile->visible_reviews_avg_rating !== null
                            ? round((float) $profile->visible_reviews_avg_rating, 1)
                            : null,
                        'review_count' => $reviewCount,

                        'user' => [
                            'name' => $profile->user->name,
                        ],
                        'sort' => [
                            'available' => $profile->is_available ? 0 : 1,
                            'reliable_rating' => $reviewCount >= 3 ? 0 : 1,
                            'rating' => $profile->visible_reviews_avg_rating !== null
                                ? -((float) $profile->visible_reviews_avg_rating)
                                : 0,
                            'distance' => $distanceKm,
                            'name' => mb_strtolower((string) ($profile->business_name ?: $profile->user->name)),
                        ],
                    ];
                })
                ->sortBy(fn (array $provider): array => array_values($provider['sort']))
                ->take(50)
                ->map(function (array $provider): array {
                    unset($provider['sort']);

                    return $provider;
                })
                ->values();
        }

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
            'locationRequired' => ! $hasLocation,
            'filters' => [
                'city' => $city,
                'neighborhood' => $neighborhood,
            ],
        ]);
    }
}
