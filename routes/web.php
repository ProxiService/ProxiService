<?php

use App\Http\Controllers\Client\CategoryController;
use App\Http\Controllers\Client\DashboardController;
use App\Http\Controllers\Client\SearchController;
use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use App\Http\Controllers\Prestataire\ProviderProfileController;

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

            Route::get('search', SearchController::class)
                ->name('search');

            Route::get('categories/{category:slug}', [
                CategoryController::class,
                'show',
            ])->name('categories.show');
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

            Route::get('profile', [ProviderProfileController::class, 'edit'])->name('profile.edit');

            Route::put('profile', [ProviderProfileController::class, 'update'])->name('profile.update');
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
        });
});

/*
|--------------------------------------------------------------------------
| Paramètres du compte fournis par le starter kit
|--------------------------------------------------------------------------
*/

require __DIR__.'/settings.php';
