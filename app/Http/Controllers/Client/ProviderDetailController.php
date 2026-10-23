<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\ProviderProfile;
use App\Models\Service;
use App\Models\ServiceReview;
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

        abort_unless($offeredService !== null, 404);

        $providerProfile->load('user:id,name');

        $reviewCount = $providerProfile->reviews()->count();
        $averageRating = $providerProfile->reviews()->avg('rating');

        $reviews = $providerProfile->reviews()
            ->with([
                'client:id,name',
                'serviceRequest:id,service_id',
                'serviceRequest.service:id,name',
            ])
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (ServiceReview $review): array => [
                'id' => $review->id,
                'rating' => (int) $review->rating,
                'comment' => $review->comment,
                'created_at' => $review->created_at?->format('d/m/Y'),
                'client_name' => $review->client->name,
                'service_name' => $review->serviceRequest->service->name,
            ])
            ->values();

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

            'reviewSummary' => [
                'average' => $averageRating !== null
                    ? round((float) $averageRating, 1)
                    : null,
                'count' => $reviewCount,
            ],

            'reviews' => $reviews,
        ]);
    }
}
