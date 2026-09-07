<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\PaymentProofService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    public function index(
        Request $request,
        PaymentProofService $paymentService
    ): JsonResponse {
        $user = $request->user();

        $query = Booking::query()
            ->with([
                'items',
                'staffAssignments.staff.user:id,name',
            ])
            ->where(
                'customer_id',
                $user->id
            );

        if ($request->filled('status')) {
            $query->where(
                'status',
                $request
                    ->string('status')
                    ->toString()
            );
        }

        if (
            $request->filled(
                'payment_status'
            )
        ) {
            $query->where(
                'payment_status',
                $request
                    ->string(
                        'payment_status'
                    )
                    ->toString()
            );
        }

        if ($request->filled('search')) {
            $search = trim(
                $request
                    ->string('search')
                    ->toString()
            );

            $query->where(
                function ($bookingQuery) use (
                    $search
                ) {
                    $bookingQuery
                        ->where(
                            'booking_code',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhereHas(
                            'items',
                            function (
                                $itemQuery
                            ) use ($search) {
                                $itemQuery
                                    ->where(
                                        'service_name',
                                        'like',
                                        "%{$search}%"
                                    )
                                    ->orWhere(
                                        'variant_name',
                                        'like',
                                        "%{$search}%"
                                    );
                            }
                        );
                }
            );
        }

        $perPage = min(
            max(
                (int) $request->input(
                    'per_page',
                    10
                ),
                1
            ),
            50
        );

        $bookings = $query
            ->orderByDesc('start_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        $bookings
            ->getCollection()
            ->transform(
                function (
                    Booking $booking
                ) use (
                    $paymentService
                ) {
                    return $paymentService
                        ->attachPaymentData(
                            $booking,
                            false
                        );
                }
            );

        return response()->json([
            'success' => true,
            'data' => $bookings,
        ]);
    }

    public function show(
        Request $request,
        Booking $booking,
        PaymentProofService $paymentService
    ): JsonResponse {
        $user = $request->user();

        if (
            (int) $booking->customer_id !==
            (int) $user->id
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Bạn không có quyền xem lịch hẹn này.',
            ], 403);
        }

        $booking->load([
            'items',
            'coupon:id,code,name,type,value',
            'staffAssignments.staff.user:id,name',
        ]);

        $paymentService
            ->attachPaymentData(
                $booking
            );

        $booking->setAttribute(
            'bank_transfer',
            $paymentService
                ->bankTransferData(
                    $booking
                )
        );

        return response()->json([
            'success' => true,
            'data' => [
                'booking' => $booking,
            ],
        ]);
    }
}
