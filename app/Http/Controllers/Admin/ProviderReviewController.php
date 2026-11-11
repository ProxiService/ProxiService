<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProviderProfile;
use App\Services\AdminAuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ProviderReviewController extends Controller
{
    /**
     * Afficher les profils professionnels en attente.
     */
    public function index(): Response
    {
        $profiles = ProviderProfile::query()
            ->where('status', 'pending')
            ->with([
                'user:id,name,email',
                'services:id,name',
            ])
            ->oldest()
            ->paginate(10)
            ->through(fn (ProviderProfile $profile) => [
                'id' => $profile->id,
                'business_name' => $profile->business_name,
                'bio' => $profile->bio,
                'phone' => $profile->phone,
                'years_experience' => $profile->years_experience,
                'city' => $profile->city,
                'neighborhood' => $profile->neighborhood,
                'address' => $profile->address,
                'status' => $profile->status,
                'created_at' => $profile->created_at?->format('d/m/Y H:i'),

                'user' => [
                    'id' => $profile->user->id,
                    'name' => $profile->user->name,
                    'email' => $profile->user->email,
                ],

                'services' => $profile->services
                    ->map(fn ($service) => [
                        'id' => $service->id,
                        'name' => $service->name,
                    ])
                    ->values(),
            ]);

        return Inertia::render('admin/providers/index', [
            'profiles' => $profiles,
        ]);
    }

    /**
     * Approuver un profil professionnel.
     */
    public function approve(
        Request $request,
        ProviderProfile $providerProfile,
        AdminAuditLogger $audit,
    ): RedirectResponse {
        abort_unless(
            $providerProfile->status === 'pending',
            409,
            'Ce profil ne peut plus être approuvé dans son état actuel.'
        );

        DB::transaction(function () use ($request, $providerProfile, $audit): void {
            $before = ['status' => $providerProfile->status];
            $providerProfile->forceFill(['status' => 'approved'])->save();
            $audit->record($request, 'provider_profile.approved', $providerProfile, $before, ['status' => 'approved']);
        });

        return to_route('admin.providers.index')
            ->with('success', 'Le profil a été approuvé.');
    }

    /**
     * Rejeter un profil professionnel.
     */
    public function reject(
        Request $request,
        ProviderProfile $providerProfile,
        AdminAuditLogger $audit,
    ): RedirectResponse {
        abort_unless(
            $providerProfile->status === 'pending',
            409,
            'Ce profil ne peut plus être rejeté dans son état actuel.'
        );

        DB::transaction(function () use ($request, $providerProfile, $audit): void {
            $before = ['status' => $providerProfile->status];
            $providerProfile->forceFill(['status' => 'rejected'])->save();
            $audit->record($request, 'provider_profile.rejected', $providerProfile, $before, ['status' => 'rejected']);
        });

        return to_route('admin.providers.index')
            ->with('success', 'Le profil a été rejeté.');
    }
}
