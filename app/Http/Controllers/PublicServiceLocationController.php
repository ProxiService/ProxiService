<?php

namespace App\Http\Controllers;

use App\Models\ProviderProfile;
use App\Models\Service;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PublicServiceLocationController extends Controller
{
    public function show(Service $service, string $zone): Response
    {
        abort_unless($service->is_active && $service->category()->where('is_active', true)->exists(), 404);

        $city = ProviderProfile::query()
            ->where('status', 'approved')
            ->where('is_available', true)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->whereHas('services', fn ($query) => $query->where('services.id', $service->id))
            ->pluck('city')
            ->filter()
            ->unique()
            ->first(fn (string $candidate): bool => Str::slug($candidate) === $zone);

        abort_unless($city !== null, 404);

        return Inertia::render('public/service-location', [
            'service' => ['name' => $service->name, 'slug' => $service->slug],
            'zone' => $city,
            'zoneSlug' => $zone,
        ]);
    }
}
