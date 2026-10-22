<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SearchController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $term = trim($validated['q'] ?? '');

        $services = collect();

        if ($term !== '') {
            $search = "%{$term}%";

            $services = Service::query()
                ->with('category:id,name,slug')
                ->where('is_active', true)
                ->whereHas('category', function (Builder $query) {
                    $query->where('is_active', true);
                })
                ->where(function (Builder $query) use ($search) {
                    $query->where('name', 'like', $search)
                        ->orWhere('description', 'like', $search)
                        ->orWhereHas(
                            'category',
                            function (Builder $categoryQuery) use ($search) {
                                $categoryQuery->where('name', 'like', $search);
                            }
                        );
                })
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
        }

        return Inertia::render('client/search/index', [
            'searchTerm' => $term,
            'services' => $services,
        ]);
    }
}
