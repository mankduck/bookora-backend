<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Models\HomepageModule;
use App\Models\Post;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Services\SiteSettingService;
use Illuminate\Http\JsonResponse;

class SiteController extends Controller
{
    public function config(SiteSettingService $settings): JsonResponse
    {
        $modules = HomepageModule::query()
            ->where('is_enabled', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'settings' => $settings->all(),
                'modules' => $modules,
            ],
        ]);
    }

    public function homeData(): JsonResponse
    {
        $posts = Post::query()
            ->where('status', 'published')
            ->where(function ($query) {
                $query->whereNull('published_at')
                    ->orWhere('published_at', '<=', now());
            })
            ->with('author:id,name')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit(8)
            ->get();

        $staff = StaffProfile::query()
            ->where('status', 'active')
            ->whereHas('reviews', fn ($query) => $query->where('status', 'published'))
            ->with('user:id,name,avatar')
            ->withAvg(
                ['reviews as rating_avg' => fn ($query) => $query->where('status', 'published')],
                'rating',
            )
            ->withCount(
                ['reviews as reviews_count' => fn ($query) => $query->where('status', 'published')],
            )
            ->orderByDesc('rating_avg')
            ->orderByDesc('reviews_count')
            ->limit(8)
            ->get();

        $servicesModule = HomepageModule::query()
            ->where('type', 'services')
            ->first();

        $configuredServiceIds = $servicesModule?->settings['service_ids'] ?? null;

        if (is_array($configuredServiceIds)) {
            $serviceIds = array_slice(
                array_values(array_unique(array_map('intval', $configuredServiceIds))),
                0,
                6,
            );

            $servicesById = Service::query()
                ->where('status', 'active')
                ->whereIn('id', $serviceIds)
                ->with('category:id,name')
                ->get()
                ->keyBy('id');

            $services = collect($serviceIds)
                ->map(fn ($id) => $servicesById->get($id))
                ->filter()
                ->values();
        } else {
            // Legacy fallback: projects created before service selection existed keep their current homepage content.
            $services = Service::query()
                ->where('status', 'active')
                ->where('is_featured', true)
                ->with('category:id,name')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->limit(6)
                ->get();

            if ($services->isEmpty()) {
                $services = Service::query()
                    ->where('status', 'active')
                    ->with('category:id,name')
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->limit(6)
                    ->get();
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'services' => $services,
                'posts' => $posts,
                'top_staff' => $staff,
            ],
        ]);
    }

    public function staffReviews(StaffProfile $staff): JsonResponse
    {
        $staff->load('user:id,name,avatar');

        $reviews = $staff->reviews()
            ->where('status', 'published')
            ->with([
                'customer:id,name',
                'booking:id,booking_code,start_at,status',
            ])
            ->orderByDesc('rating')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'staff' => [
                    'id' => $staff->id,
                    'position' => $staff->position,
                    'user' => $staff->user,
                    'average_rating' => round((float) ($reviews->avg('rating') ?? 0), 2),
                    'reviews_count' => $reviews->count(),
                ],
                'reviews' => $reviews,
            ],
        ]);
    }
}
