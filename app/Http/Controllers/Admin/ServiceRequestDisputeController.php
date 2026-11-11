<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceRequest;
use Inertia\Inertia;
use Inertia\Response;

class ServiceRequestDisputeController extends Controller
{
    public function index(): Response
    {
        $disputes = ServiceRequest::query()
            ->where('dispute_status', 'open')
            ->with([
                'client:id,name',
                'providerProfile.user:id,name',
                'service:id,name',
            ])
            ->oldest('disputed_at')
            ->paginate(20)
            ->through(fn (ServiceRequest $serviceRequest): array => [
                'id' => $serviceRequest->id,
                'status' => $serviceRequest->status,
                'reason' => $serviceRequest->dispute_reason,
                'disputed_at' => $serviceRequest->disputed_at?->format('d/m/Y H:i'),
                'client' => $serviceRequest->client->name,
                'provider' => $serviceRequest->providerProfile->business_name
                    ?: $serviceRequest->providerProfile->user->name,
                'service' => $serviceRequest->service->name,
            ]);

        return Inertia::render('admin/requests/disputes/index', [
            'disputes' => $disputes,
        ]);
    }
}
