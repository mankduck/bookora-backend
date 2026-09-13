<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\PaymentProof;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $today = now()->toDateString();

        $todayBookings = Booking::query()->whereDate('start_at', $today)->count();
        $pending = Booking::query()->where('status', 'pending')->count();
        $customers = User::query()->whereHas('roles', fn($q) => $q->where('code', 'customer'))->count();
        $todayRevenue = (float) PaymentProof::query()
            ->where('status', 'approved')
            ->whereDate('reviewed_at', $today)
            ->sum('approved_amount');

        $statusCounts = [];
        foreach (['confirmed', 'in_progress', 'completed', 'cancelled'] as $status) {
            $statusCounts[$status] = Booking::query()->whereDate('start_at', $today)->where('status', $status)->count();
        }

        $recent = Booking::query()
            ->with(['items:id,booking_id,service_name,variant_name', 'staffAssignments.staff.user:id,name'])
            ->orderByDesc('created_at')->orderByDesc('id')->limit(6)->get();

        return response()->json([
            'success' => true,
            'data' => [
                'stats' => [
                    'today_bookings' => $todayBookings,
                    'pending_bookings' => $pending,
                    'customers' => $customers,
                    'today_revenue' => $todayRevenue,
                ],
                'today_status' => $statusCounts,
                'recent_bookings' => $recent,
            ],
        ]);
    }
}
