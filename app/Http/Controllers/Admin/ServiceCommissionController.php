<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceCommission;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use LogicException;

class ServiceCommissionController extends Controller
{
    /**
     * Afficher les statistiques financières et les commissions.
     */
    public function index(): Response
    {
        $totals = DB::table('service_commissions')
            ->selectRaw('COUNT(*) as total_commissions')
            ->selectRaw('COALESCE(SUM(gross_amount), 0) as gross_total')
            ->selectRaw('COALESCE(SUM(commission_amount), 0) as commission_total')
            ->selectRaw('COALESCE(SUM(provider_amount), 0) as provider_total')
            ->selectRaw(
                "COALESCE(SUM(CASE WHEN collection_status = 'pending' THEN commission_amount ELSE 0 END), 0) as pending_total"
            )
            ->first();

        if ($totals === null) {
            throw new LogicException('La requête de totaux doit toujours retourner une ligne.');
        }

        $commissions = ServiceCommission::query()
            ->with([
                'serviceRequest:id,client_id,provider_profile_id,service_id,status',
                'serviceRequest.client:id,name',
                'serviceRequest.service:id,name',
                'serviceRequest.providerProfile:id,user_id,business_name',
                'serviceRequest.providerProfile.user:id,name',
            ])
            ->latest('calculated_at')
            ->paginate(15)
            ->through(
                fn (ServiceCommission $commission): array => [
                    'id' => $commission->id,
                    'service_request_id' => $commission->service_request_id,
                    'service_name' => $commission->serviceRequest->service->name,
                    'client_name' => $commission->serviceRequest->client->name,
                    'provider_name' => $commission->serviceRequest->providerProfile
                        ->business_name
                        ?: $commission->serviceRequest->providerProfile->user->name,
                    'gross_amount' => (float) $commission->gross_amount,
                    'commission_rate' => (float) $commission->commission_rate,
                    'commission_amount' => (float) $commission->commission_amount,
                    'provider_amount' => (float) $commission->provider_amount,
                    'collection_status' => $commission->collection_status,
                    'collection_status_label' => match ($commission->collection_status) {
                        'pending' => 'À collecter',
                        'collected' => 'Collectée',
                        'paid' => 'Payée',
                        default => $commission->collection_status,
                    },
                    'calculated_at' => $commission->calculated_at
                        ->format('d/m/Y H:i'),
                ],
            );

        return Inertia::render('admin/commissions/index', [
            'summary' => [
                'total_commissions' => (int) $totals->total_commissions,
                'gross_total' => (float) $totals->gross_total,
                'commission_total' => (float) $totals->commission_total,
                'provider_total' => (float) $totals->provider_total,
                'pending_total' => (float) $totals->pending_total,
            ],
            'commissions' => $commissions,
        ]);
    }
}
