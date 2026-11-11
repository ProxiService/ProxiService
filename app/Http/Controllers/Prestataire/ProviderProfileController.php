<?php

namespace App\Http\Controllers\Prestataire;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProviderProfileController extends Controller
{
    /**
     * Afficher le formulaire du profil professionnel.
     */
    public function edit(Request $request): Response
    {
        $profile = $request->user()->providerProfile;

        if ($profile !== null) {
            $this->authorize('view', $profile);
        }

        $services = Service::query()
            ->where('is_active', true)
            ->whereHas('category', function (Builder $query) {
                $query->where('is_active', true);
            })
            ->with('category:id,name')
            ->orderBy('name')
            ->get([
                'id',
                'category_id',
                'name',
                'description',
                'pricing_type',
                'base_price',
                'estimated_duration_minutes',
            ]);

        $selectedServiceIds = $profile
            ? $profile->services()
                ->pluck('services.id')
                ->map(fn ($id) => (int) $id)
                ->all()
            : [];
        $selectedServicePrices = $profile
            ? $profile->services()
                ->get()
                ->mapWithKeys(fn (Service $service): array => [
                    (int) $service->id => $service->pivot->price !== null
                        ? (float) $service->pivot->price
                        : null,
                ])
                ->all()
            : [];
        $hasLocation = $profile !== null
            && $profile->latitude !== null
            && $profile->longitude !== null;

        return Inertia::render('prestataire/profile/edit', [
            'profile' => $profile?->only([
                'business_name',
                'bio',
                'phone',
                'years_experience',
                'city',
                'neighborhood',
                'address',
                'status',
            ]),
            'services' => $services,
            'selectedServiceIds' => $selectedServiceIds,
            'selectedServicePrices' => $selectedServicePrices,
            'commissionRate' => (float) config('proxiservices.commission_rate', 10.0),
            'hasLocation' => $hasLocation,
        ]);
    }

    /**
     * Créer ou modifier le profil professionnel et ses services.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'business_name' => ['nullable', 'string', 'max:150'],
            'bio' => ['nullable', 'string', 'max:3000'],
            'phone' => ['nullable', 'string', 'max:30'],
            'years_experience' => [
                'nullable',
                'integer',
                'min:0',
                'max:80',
            ],
            'city' => ['required', 'string', 'max:100'],
            'neighborhood' => ['required', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:1000'],
            'latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],

            'services' => ['sometimes', 'array'],
            'services.*' => [
                'integer',
                'distinct',
                Rule::exists('services', 'id')
                    ->where('is_active', true),
            ],
        ]);

        // Conserver la sélection séparément des champs du profil.
        $serviceIds = array_key_exists('services', $validated)
            ? $validated['services']
            : null;

        unset($validated['services']);

        $profile = $request->user()
            ->providerProfile()
            ->firstOrNew();

        $this->authorize('update', $profile);

        $profile->fill($validated);
        $profile->save();

        // Synchroniser les services uniquement si le formulaire
        // a transmis le champ services.
        if ($serviceIds !== null) {
            $profile->services()->sync($serviceIds);
        }

        return to_route('prestataire.profile.edit');
    }
}
