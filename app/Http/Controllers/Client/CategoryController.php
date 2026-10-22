<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function show(Category $category): Response
    {
        // Une catégorie désactivée ne doit pas être visible.
        abort_unless($category->is_active, 404);

        // Récupérer uniquement les services actifs de cette catégorie.
        $services = $category->services()
            ->where('is_active', true)
            ->orderBy('name')
            ->get([
                'id',
                'category_id',
                'name',
                'slug',
                'description',
                'pricing_type',
                'base_price',
                'estimated_duration_minutes',
            ]);

        return Inertia::render('client/categories/show', [
            'category' => $category->only([
                'id',
                'name',
                'slug',
                'description',
                'icon',
            ]),
            'services' => $services,
        ]);
    }
}
