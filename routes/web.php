<?php

use App\Http\Controllers\Admin\ProviderReviewController;
use App\Http\Controllers\Admin\ReviewReportController as AdminReviewReportController;
use App\Http\Controllers\Client\CategoryController;
use App\Http\Controllers\Client\DashboardController;
use App\Http\Controllers\Client\MyServiceRequestsController;
use App\Http\Controllers\Client\ProviderDetailController;
use App\Http\Controllers\Client\SearchController;
use App\Http\Controllers\Client\ServiceProviderController;
use App\Http\Controllers\Client\ServiceRequestController;
use App\Http\Controllers\Client\ServiceReviewController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Prestataire\ProviderProfileController;
use App\Http\Controllers\Prestataire\ServiceRequestController as PrestataireServiceRequestController;
use App\Http\Controllers\ReviewReportController;
use App\Http\Controllers\ServiceRequestConversationController;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/*
|--------------------------------------------------------------------------
| Pages publiques
|--------------------------------------------------------------------------
*/

Route::inertia('/', 'welcome')->name('home');

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
    )->name('reviews.report.store');

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
            )->name('requests.messages.store');

            Route::post(
                'services/{service:slug}/providers/{providerProfile}/request',
                [ServiceRequestController::class, 'store']
            )->name('services.providers.request.store');

            Route::post(
                'requests/{serviceRequest}/review',
                [ServiceReviewController::class, 'store'],
            )->name('requests.review.store');

            Route::patch(
                'requests/{serviceRequest}/confirm-completion',
                [ServiceRequestController::class, 'confirmCompletion'],
            )->name('requests.confirm-completion');
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

            Route::get(
                'requests/{serviceRequest}/messages',
                [ServiceRequestConversationController::class, 'show'],
            )->name('requests.messages.show');

            Route::post(
                'requests/{serviceRequest}/messages',
                [ServiceRequestConversationController::class, 'store'],
            )->name('requests.messages.store');

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
        });

    /*
    |--------------------------------------------------------------------------
    | Espace administrateur
    |--------------------------------------------------------------------------
    */

    Route::middleware('role:admin')
        ->prefix('admin')
        ->name('admin.')
        ->group(function () {
            Route::inertia('dashboard', 'admin/dashboard')
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
        });
});

/*
|--------------------------------------------------------------------------
| Paramètres du compte fournis par le starter kit
|--------------------------------------------------------------------------
*/

require __DIR__.'/settings.php';
