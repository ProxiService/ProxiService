<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        $categories = Category::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'slug',
                'description',
                'icon',
            ]);

        return Inertia::render('client/dashboard', [
            'categories' => $categories,
        ]);
    }
}
