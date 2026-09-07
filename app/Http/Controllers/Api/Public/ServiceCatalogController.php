<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceCatalogController extends Controller
{
    /**
     * Danh sách danh mục dịch vụ public.
     */
    public function categories(): JsonResponse
    {
        $categories = ServiceCategory::query()
            ->where('status', 'active')
            ->whereHas('services', function ($query) {
                $query->where('status', 'active');
            })
            ->withCount([
                'services' => function ($query) {
                    $query->where('status', 'active');
                },
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get([
                'id',
                'parent_id',
                'name',
                'slug',
                'description',
                'thumbnail',
                'sort_order',
            ]);

        return response()->json([
            'success' => true,
            'data' => [
                'categories' => $categories,
            ],
        ]);
    }

    /**
     * Danh sách dịch vụ public.
     *
     * Có thể filter:
     * ?category=chup-anh
     * ?featured=1
     * ?search=cuoi
     */
    public function services(
        Request $request
    ): JsonResponse {
        $query = Service::query()
            ->where('status', 'active')
            ->with([
                'category:id,name,slug',
                'variants' => function ($query) {
                    $query
                        ->where('status', 'active')
                        ->orderBy('sort_order')
                        ->orderBy('id');
                },
            ]);

        if ($request->filled('category')) {
            $categorySlug = $request
                ->string('category')
                ->toString();

            $query->whereHas(
                'category',
                function ($categoryQuery) use ($categorySlug) {
                    $categoryQuery
                        ->where('slug', $categorySlug)
                        ->where('status', 'active');
                }
            );
        }

        if ($request->boolean('featured')) {
            $query->where(
                'is_featured',
                true
            );
        }

        if ($request->filled('search')) {
            $search = $request
                ->string('search')
                ->toString();

            $query->where(function ($serviceQuery) use ($search) {
                $serviceQuery
                    ->where(
                        'name',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'short_description',
                        'like',
                        "%{$search}%"
                    );
            });
        }

        $services = $query
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'services' => $services,
            ],
        ]);
    }

    /**
     * Chi tiết một dịch vụ theo slug.
     */
    public function show(
        string $slug
    ): JsonResponse {
        $service = Service::query()
            ->where('slug', $slug)
            ->where('status', 'active')
            ->with([
                'category:id,name,slug',
                'variants' => function ($query) {
                    $query
                        ->where('status', 'active')
                        ->orderBy('sort_order')
                        ->orderBy('id');
                },
            ])
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => [
                'service' => $service,
            ],
        ]);
    }
}