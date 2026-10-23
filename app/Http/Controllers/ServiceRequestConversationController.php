<?php

namespace App\Http\Controllers;

use App\Models\ServiceRequest as ServiceRequestModel;
use App\Models\ServiceRequestMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ServiceRequestConversationController extends Controller
{
    /**
     * Afficher la conversation liée à une demande acceptée.
     */
    public function show(
        Request $request,
        ServiceRequestModel $serviceRequest,
    ): Response {
        $this->ensureCanAccess($request, $serviceRequest);

        $serviceRequest->load([
            'service:id,name,slug',
            'client:id,name',
            'providerProfile:id,user_id,business_name,city,neighborhood',
            'providerProfile.user:id,name',
        ]);

        $messages = $serviceRequest->messages()
            ->with('sender:id,name')
            ->orderBy('id')
            ->get()
            ->map(fn (ServiceRequestMessage $message): array => [
                'id' => $message->id,
                'body' => $message->body,
                'created_at' => $message->created_at
                    ?->format('d/m/Y H:i'),
                'sender' => [
                    'id' => $message->sender->id,
                    'name' => $message->sender->name,
                ],
            ])
            ->values();

        $isClient = $request->user()->role === 'client';

        return Inertia::render('conversations/show', [
            'conversation' => [
                'id' => $serviceRequest->id,
                'description' => $serviceRequest->description,
                'city' => $serviceRequest->city,
                'neighborhood' => $serviceRequest->neighborhood,
                'address' => $serviceRequest->address,
                'scheduled_at' => $serviceRequest->scheduled_at
                    ?->format('d/m/Y H:i'),
                'service_name' => $serviceRequest->service->name,
                'client_name' => $serviceRequest->client->name,
                'provider_name' => $serviceRequest->providerProfile
                    ->business_name
                    ?: $serviceRequest->providerProfile->user->name,
            ],
            'messages' => $messages,
            'currentUserId' => (int) $request->user()->id,
            'storeUrl' => $isClient
                ? route('client.requests.messages.store', $serviceRequest)
                : route('prestataire.requests.messages.store', $serviceRequest),
            'backUrl' => $isClient
                ? route('client.requests.index')
                : route('prestataire.requests.index'),
        ]);
    }

    /**
     * Envoyer un message dans la conversation.
     */
    public function store(
        Request $request,
        ServiceRequestModel $serviceRequest,
    ): RedirectResponse {
        $this->ensureCanAccess($request, $serviceRequest);

        $validated = $request->validate([
            'body' => ['required', 'string', 'min:1', 'max:5000'],
        ]);

        $message = new ServiceRequestMessage([
            'body' => $validated['body'],
        ]);

        // L'expéditeur est déterminé par Laravel, jamais par le formulaire.
        $message->sender()->associate($request->user());

        // La demande est également associée côté serveur.
        $serviceRequest->messages()->save($message);

        $routeName = $request->user()->role === 'client'
            ? 'client.requests.messages.show'
            : 'prestataire.requests.messages.show';

        return redirect()
            ->route($routeName, $serviceRequest)
            ->with('success', 'Ton message a été envoyé.');
    }

    /**
     * Autoriser uniquement les participants pendant le cycle actif de la prestation.
     */
    private function ensureCanAccess(
        Request $request,
        ServiceRequestModel $serviceRequest,
    ): void {
        $user = $request->user();

        $isClient = $user->role === 'client'
            && (int) $serviceRequest->client_id === (int) $user->id;

        $providerProfile = $user->role === 'prestataire'
            ? $user->providerProfile
            : null;

        $isProvider = $providerProfile !== null
            && (int) $serviceRequest->provider_profile_id
                === (int) $providerProfile->id;

        $chatAvailable = in_array(
            $serviceRequest->status,
            [
                'accepted',
                'in_progress',
                'awaiting_confirmation',
                'completed',
            ],
            true,
        );

        abort_unless(
            ($isClient || $isProvider) && $chatAvailable,
            404,
            'Conversation introuvable.',
        );
    }
}
