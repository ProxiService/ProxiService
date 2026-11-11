<?php

use App\Http\Controllers\Admin\ProviderReviewController;
use App\Http\Controllers\Admin\ReviewReportController as AdminReviewReportController;
use App\Http\Controllers\Admin\ServiceCommissionController as AdminServiceCommissionController;
use App\Http\Controllers\Admin\ServiceRequestDisputeController as AdminServiceRequestDisputeController;
use App\Http\Controllers\Client\CategoryController;
use App\Http\Controllers\Client\DashboardController;
use App\Http\Controllers\Client\MyServiceRequestsController;
use App\Http\Controllers\Client\ProviderDetailController;
use App\Http\Controllers\Client\SearchController;
use App\Http\Controllers\Client\ServicePaymentController;
use App\Http\Controllers\Client\ServiceProviderController;
use App\Http\Controllers\Client\ServiceQuoteController;
use App\Http\Controllers\Client\ServiceRequestController;
use App\Http\Controllers\Client\ServiceReviewController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Prestataire\ProviderProfileController;
use App\Http\Controllers\Prestataire\ServiceRequestController as PrestataireServiceRequestController;
use App\Http\Controllers\PublicServiceLocationController;
use App\Http\Controllers\ReviewReportController;
use App\Http\Controllers\ServiceRequestConversationController;
use App\Http\Controllers\ServiceRequestIssueController;
use App\Http\Controllers\Webhooks\GeniusPayWebhookController;
use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/*
|--------------------------------------------------------------------------
| Pages publiques
|--------------------------------------------------------------------------
*/

Route::inertia('/', 'welcome')->name('home');

Route::get('informations/{slug}', function (string $slug): InertiaResponse {
    $pages = [
        'cgu' => ['Conditions d’utilisation', ['Éditeur de la plateforme', 'Création de compte et utilisation', 'Responsabilités et réclamations']],
        'confidentialite' => ['Politique de confidentialité', ['Responsable et contact', 'Données traitées et finalités', 'Durées, destinataires et droits']],
        'mentions-legales' => ['Mentions légales', ['Éditeur', 'Hébergement', 'Contact et propriété intellectuelle']],
        'paiements-remboursements' => ['Paiements et remboursements', ['Moyens de paiement disponibles', 'Frais et commissions', 'Annulations, remboursements et reversements']],
    ];
    abort_unless(isset($pages[$slug]), 404);

    return Inertia::render('legal/show', ['slug' => $slug, 'title' => $pages[$slug][0], 'sections' => $pages[$slug][1]]);
})->name('legal.show');

Route::get('sitemap.xml', function () {
    $urls = [url('/')];
    foreach (['cgu', 'confidentialite', 'mentions-legales', 'paiements-remboursements'] as $slug) {
        $urls[] = url("/informations/{$slug}");
    }
    $profiles = ProviderProfile::query()
        ->where('status', 'approved')
        ->where('is_available', true)
        ->whereNotNull('latitude')
        ->whereNotNull('longitude')
        ->whereNotNull('city')
        ->with(['services' => fn ($query) => $query->where('services.is_active', true)->select('services.id', 'services.slug')])
        ->get(['id', 'city']);
    foreach ($profiles as $profile) {
        foreach ($profile->services as $service) {
            $urls[] = url("/services/{$service->slug}/a/".Str::slug($profile->city));
        }
    }
    $urls = array_values(array_unique($urls));
    $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
    foreach ($urls as $pageUrl) {
        $xml .= '<url><loc>'.e($pageUrl).'</loc></url>';
    }

    return response($xml.'</urlset>', 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
})->name('sitemap');

Route::get('robots.txt', function () {
    $robots = "User-agent: *\nAllow: /\nDisallow: /client/\nDisallow: /prestataire/\nDisallow: /admin/\nDisallow: /settings/\n\nSitemap: ".url('/sitemap.xml')."\n";

    return response($robots, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
})->name('robots');

Route::get('services/{service:slug}/a/{zone}', [PublicServiceLocationController::class, 'show'])
    ->name('public.services.location');

Route::post(
    'webhooks/geniuspay',
    GeniusPayWebhookController::class,
)->name('webhooks.geniuspay');

/*
|--------------------------------------------------------------------------
| Pages accessibles aux utilisateurs connectés
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'verified'])->group(function () {

    Route::get('dashboard', function (Request $request): RedirectResponse|InertiaResponse {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        if ($user->isClient()) {
            return to_route('client.dashboard');
        }

        if ($user->isPrestataire()) {
            return to_route('prestataire.dashboard');
        }

        if ($user->isAdmin()) {
            return to_route('admin.dashboard');
        }

        return Inertia::render('dashboard');
    })->name('dashboard');

    Route::post(
        'reviews/{serviceReview}/report',
        [ReviewReportController::class, 'store'],
    )->middleware('throttle:reports')->name('reviews.report.store');

    /*
    |--------------------------------------------------------------------------
    | Espace client
    |--------------------------------------------------------------------------
    */

    Route::middleware('role:client')
        ->prefix('client')
        ->name('client.')
        ->group(function () {
            Route::get('dashboard', DashboardController::class)
                ->name('dashboard');

            Route::get('notifications', [
                NotificationController::class,
                'index',
            ])->name('notifications.index');

            Route::patch('notifications/read-all', [
                NotificationController::class,
                'markAllAsRead',
            ])->name('notifications.read-all');

            Route::patch('notifications/{notification}/read', [
                NotificationController::class,
                'markAsRead',
            ])->name('notifications.read');

            Route::get('requests', MyServiceRequestsController::class)
                ->name('requests.index');
            Route::get('requests/urgent', [ServiceRequestController::class, 'urgentCreate'])
                ->name('requests.urgent.create');
            Route::post('requests/urgent', [ServiceRequestController::class, 'storeUrgent'])
                ->middleware('throttle:requests')->name('requests.urgent.store');

            Route::get('search', SearchController::class)
                ->name('search');

            Route::get('categories/{category:slug}', [
                CategoryController::class,
                'show',
            ])->name('categories.show');

            Route::get('services/{service:slug}/providers', [
                ServiceProviderController::class,
                'index',
            ])->name('services.providers');
            Route::post('services/{service:slug}/providers', [
                ServiceProviderController::class,
                'index',
            ])->middleware('throttle:30,1')->name('services.providers.search');

            Route::get(
                'services/{service:slug}/providers/{providerProfile}',
                [ProviderDetailController::class, 'show']
            )->name('services.providers.show');

            Route::get(
                'services/{service:slug}/providers/{providerProfile}/request',
                [ServiceRequestController::class, 'create']
            )->name('services.providers.request.create');

            Route::get(
                'requests/{serviceRequest}/messages',
                [ServiceRequestConversationController::class, 'show'],
            )->name('requests.messages.show');

            Route::post(
                'requests/{serviceRequest}/messages',
                [ServiceRequestConversationController::class, 'store'],
            )->middleware('throttle:messages')->name('requests.messages.store');

            Route::post(
                'services/{service:slug}/providers/{providerProfile}/request',
                [ServiceRequestController::class, 'store']
            )->middleware('throttle:requests')->name('services.providers.request.store');

            Route::post(
                'requests/{serviceRequest}/review',
                [ServiceReviewController::class, 'store'],
            )->name('requests.review.store');

            Route::patch(
                'requests/{serviceRequest}/confirm-completion',
                [ServiceRequestController::class, 'confirmCompletion'],
            )->name('requests.confirm-completion');

            Route::post('requests/{serviceRequest}/cancel', [
                ServiceRequestIssueController::class, 'cancel',
            ])->name('requests.cancel');
            Route::post('requests/{serviceRequest}/dispute', [
                ServiceRequestIssueController::class, 'dispute',
            ])->name('requests.dispute');

            Route::patch(
                'requests/{serviceRequest}/quotes/{serviceQuote}/accept',
                [ServiceQuoteController::class, 'accept'],
            )->name('requests.quotes.accept');

            Route::patch(
                'requests/{serviceRequest}/quotes/{serviceQuote}/reject',
                [ServiceQuoteController::class, 'reject'],
            )->name('requests.quotes.reject');

            Route::post(
                'requests/{serviceRequest}/payments',
                [ServicePaymentController::class, 'store'],
            )->middleware('throttle:payments')->name('requests.payments.store');

            Route::get('payments/return', [
                ServicePaymentController::class,
                'returned',
            ])->name('payments.return');
        });
    /*
    |--------------------------------------------------------------------------
    | Espace prestataire
    |--------------------------------------------------------------------------
    */

    Route::middleware('role:prestataire')
        ->prefix('prestataire')
        ->name('prestataire.')
        ->group(function () {
            Route::inertia('dashboard', 'prestataire/dashboard')
                ->name('dashboard');

            Route::get('notifications', [
                NotificationController::class,
                'index',
            ])->name('notifications.index');

            Route::patch('notifications/read-all', [
                NotificationController::class,
                'markAllAsRead',
            ])->name('notifications.read-all');

            Route::patch('notifications/{notification}/read', [
                NotificationController::class,
                'markAsRead',
            ])->name('notifications.read');

            Route::get('profile', [ProviderProfileController::class, 'edit'])->name('profile.edit');

            Route::put('profile', [ProviderProfileController::class, 'update'])->name('profile.update');

            Route::get('requests', [
                PrestataireServiceRequestController::class,
                'index',
            ])->name('requests.index');

            Route::patch('requests/{serviceRequest}/accept', [
                PrestataireServiceRequestController::class,
                'accept',
            ])->name('requests.accept');
            Route::post('requests/{serviceRequest}/accept-urgent', [
                PrestataireServiceRequestController::class, 'acceptUrgent',
            ])->middleware('throttle:requests')->name('requests.accept-urgent');

            Route::get(
                'requests/{serviceRequest}/messages',
                [ServiceRequestConversationController::class, 'show'],
            )->name('requests.messages.show');

            Route::post(
                'requests/{serviceRequest}/messages',
                [ServiceRequestConversationController::class, 'store'],
            )->middleware('throttle:messages')->name('requests.messages.store');

            Route::patch('requests/{serviceRequest}/reject', [
                PrestataireServiceRequestController::class,
                'reject',
            ])->name('requests.reject');

            Route::patch(
                'requests/{serviceRequest}/start',
                [PrestataireServiceRequestController::class, 'start'],
            )->name('requests.start');

            Route::patch(
                'requests/{serviceRequest}/finish',
                [PrestataireServiceRequestController::class, 'finish'],
            )->name('requests.finish');

            Route::post('requests/{serviceRequest}/cancel', [
                ServiceRequestIssueController::class, 'cancel',
            ])->name('requests.cancel');
            Route::post('requests/{serviceRequest}/dispute', [
                ServiceRequestIssueController::class, 'dispute',
            ])->name('requests.dispute');

            Route::post(
                'requests/{serviceRequest}/quotes',
                [App\Http\Controllers\Prestataire\ServiceQuoteController::class, 'store'],
            )->name('requests.quotes.store');
        });

    /*
    |--------------------------------------------------------------------------
    | Espace administrateur
    |--------------------------------------------------------------------------
    */

    Route::middleware(['role:admin', 'admin.2fa'])
        ->prefix('admin')
        ->name('admin.')
        ->group(function () {
            Route::inertia('dashboard', 'admin/dashboard')
                ->name('dashboard');

            Route::post('requests/{serviceRequest}/dispute/resolve', [
                ServiceRequestIssueController::class, 'resolve',
            ])->name('requests.disputes.resolve');
            Route::get('disputes', [AdminServiceRequestDisputeController::class, 'index'])
                ->name('disputes.index');

            Route::get('notifications', [
                NotificationController::class,
                'index',
            ])->name('notifications.index');

            Route::patch('notifications/read-all', [
                NotificationController::class,
                'markAllAsRead',
            ])->name('notifications.read-all');

            Route::patch('notifications/{notification}/read', [
                NotificationController::class,
                'markAsRead',
            ])->name('notifications.read');

            Route::get('providers', [
                ProviderReviewController::class,
                'index',
            ])->name('providers.index');

            Route::patch('providers/{providerProfile}/approve', [
                ProviderReviewController::class,
                'approve',
            ])->name('providers.approve');

            Route::patch('providers/{providerProfile}/reject', [
                ProviderReviewController::class,
                'reject',
            ])->name('providers.reject');

            Route::get('review-reports', [
                AdminReviewReportController::class,
                'index',
            ])->name('review-reports.index');

            Route::patch('review-reports/{serviceReviewReport}/dismiss', [
                AdminReviewReportController::class,
                'dismiss',
            ])->name('review-reports.dismiss');

            Route::patch('review-reports/{serviceReviewReport}/hide-review', [
                AdminReviewReportController::class,
                'hideReview',
            ])->name('review-reports.hide-review');

            Route::get('commissions', [
                AdminServiceCommissionController::class,
                'index',
            ])->name('commissions.index');
        });
});

/*
|--------------------------------------------------------------------------
| Paramètres du compte fournis par le starter kit
|--------------------------------------------------------------------------
*/

require __DIR__.'/settings.php';
