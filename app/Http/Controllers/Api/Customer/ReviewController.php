<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\StaffReview;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function store(Request $request, Booking $booking, NotificationService $notifications): JsonResponse
    {
        $user = $request->user();
        if ((int)$booking->customer_id !== (int)$user->id) {
            return response()->json(['success' => false, 'message' => 'Bạn không có quyền đánh giá booking này.'], 403);
        }
        if ($booking->status !== 'completed') {
            return response()->json(['success' => false, 'message' => 'Chỉ có thể đánh giá sau khi lịch đặt đã hoàn thành.'], 422);
        }

        $primary = $booking->staffAssignments()
            ->where(fn($q) => $q->where('is_primary', true)->orWhere('role', 'primary'))
            ->with('staff.user:id,name')
            ->first();

        if (!$primary) {
            return response()->json(['success' => false, 'message' => 'Booking chưa có nhân viên chính để đánh giá.'], 422);
        }

        $data = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:3000'],
        ]);

        $review = StaffReview::query()->updateOrCreate(
            ['booking_id' => $booking->id, 'staff_id' => $primary->staff_id],
            [
                'customer_id' => $user->id,
                'rating' => $data['rating'],
                'comment' => $data['comment'] ?? null,
                'status' => 'published',
            ]
        );

        $notifications->sendToAdmins(
            'staff_review_received',
            'Có đánh giá Photo mới',
            $user->name . ' vừa đánh giá ' . ($primary->staff?->user?->name ?? 'nhân viên') . ' ' . $review->rating . '/5 sao.',
            'info',
            $booking,
            ['review_id' => $review->id]
        );

        return response()->json([
            'success' => true,
            'message' => 'Cảm ơn bạn đã gửi đánh giá.',
            'data' => ['review' => $review->load('staff.user:id,name')],
        ]);
    }
}
