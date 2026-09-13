<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\StaffProfile;
use App\Models\StaffReview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = StaffReview::query()->with([
            'customer:id,name,email,phone',
            'staff.user:id,name,email,phone',
            'booking:id,booking_code,start_at,status',
        ]);
        if ($request->filled('staff_id')) $query->where('staff_id', (int)$request->integer('staff_id'));
        if ($request->filled('rating')) $query->where('rating', (int)$request->integer('rating'));
        if ($request->filled('search')) {
            $search = trim($request->string('search')->toString());
            $query->where(function ($q) use ($search) {
                $q->where('comment', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn($u) => $u->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('booking', fn($b) => $b->where('booking_code', 'like', "%{$search}%"));
            });
        }
        $reviews = $query->orderByDesc('id')->paginate(20);
        return response()->json(['success' => true, 'data' => $reviews]);
    }

    public function stats(): JsonResponse
    {
        $total = StaffReview::query()->where('status', 'published')->count();
        $average = (float)(StaffReview::query()->where('status', 'published')->avg('rating') ?? 0);
        $distribution = [];
        for ($i = 5; $i >= 1; $i--) $distribution[$i] = StaffReview::query()->where('status', 'published')->where('rating', $i)->count();

        $staff = StaffProfile::query()
            ->whereHas('reviews', fn($q) => $q->where('status', 'published'))
            ->with('user:id,name,avatar')
            ->withAvg(['reviews as rating_avg' => fn($q) => $q->where('status', 'published')], 'rating')
            ->withCount(['reviews as reviews_count' => fn($q) => $q->where('status', 'published')])
            ->orderByDesc('rating_avg')->orderByDesc('reviews_count')->get();

        return response()->json([
            'success' => true,
            'data' => [
                'total_reviews' => $total,
                'average_rating' => round($average, 2),
                'distribution' => $distribution,
                'staff' => $staff,
            ],
        ]);
    }
}
