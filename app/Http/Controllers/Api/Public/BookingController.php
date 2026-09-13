<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Public\StoreBookingRequest;
use App\Services\BookingService;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;

class BookingController extends Controller
{
    public function store(
        StoreBookingRequest $request,
        BookingService $bookingService,
        NotificationService $notifications
    ): JsonResponse {
        $booking =
            $bookingService->create(
                $request->validated()
            );

        $notifications->sendToAdmins(
            'booking_created',
            'Có đơn đặt lịch mới',
            $booking->customer_name . ' vừa tạo booking ' . $booking->booking_code . '.',
            'info',
            $booking
        );

        return response()->json([
            'success' => true,

            'message' =>
                'Yêu cầu đặt lịch đã được tạo thành công.',

            'data' => [
                'booking' => [
                    'id' =>
                        $booking->id,

                    'booking_code' =>
                        $booking->booking_code,

                    'start_at' =>
                        $booking->start_at
                            ->format(
                                'Y-m-d H:i:s'
                            ),

                    'end_at' =>
                        $booking->end_at
                            ->format(
                                'Y-m-d H:i:s'
                            ),

                    'customer_name' =>
                        $booking->customer_name,

                    'customer_phone' =>
                        $booking->customer_phone,

                    'customer_email' =>
                        $booking->customer_email,

                    /*
                     * API alias.
                     *
                     * Database:
                     * discount_amount
                     * total_amount
                     * deposit_amount
                     *
                     * Frontend:
                     * discount
                     * total
                     * deposit
                     */

                    'subtotal' =>
                        $booking->subtotal,

                    'discount' =>
                        $booking->discount_amount,

                    'total' =>
                        $booking->total_amount,

                    'deposit' =>
                        $booking->deposit_amount,

                    'status' =>
                        $booking->status,

                    'payment_status' =>
                        $booking->payment_status,

                    'items' =>
                        $booking->items,
                ],
            ],
        ], 201);
    }
}