<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\ServiceRequest as ServiceRequestModel;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MyServiceRequestsController extends Controller
{
    /**
     * Afficher uniquement les demandes du client connecté.
     */
    public function __invoke(Request $request): Response
    {
        $serviceRequests = ServiceRequestModel::query()
            ->where('client_id', $request->user()->id)
            ->with([
                'service:id,name,slug',
                'providerProfile:id,user_id,business_name,city,neighborhood',
                'providerProfile.user:id,name',
            ])
            ->latest()
            ->paginate(10)
            ->through(
                fn (ServiceRequestModel $serviceRequest): array => [
                    'id' => $serviceRequest->id,
                    'description' => $serviceRequest->description,
                    'city' => $serviceRequest->city,
                    'neighborhood' => $serviceRequest->neighborhood,
                    'address' => $serviceRequest->address,
                    'scheduled_at' => $serviceRequest->scheduled_at
                        ?->format('d/m/Y H:i'),
                    'is_urgent' => $serviceRequest->is_urgent,
                    'status' => $serviceRequest->status,
                    'created_at' => $serviceRequest->created_at
                        ?->format('d/m/Y H:i'),

                    'service' => [
                        'name' => $serviceRequest->service->name,
                        'slug' => $serviceRequest->service->slug,
                    ],

                    'provider' => [
                        'name' => $serviceRequest->providerProfile->business_name
                            ?: $serviceRequest->providerProfile->user->name,
                        'city' => $serviceRequest->providerProfile->city,
                        'neighborhood' => $serviceRequest->providerProfile->neighborhood,
                    ],
                ],
            );

        return Inertia::render('client/requests/index', [
            'requests' => $serviceRequests,
        ]);
    }
}
