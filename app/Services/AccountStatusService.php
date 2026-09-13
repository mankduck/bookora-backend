<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\StaffProfile;

class AccountStatusService
{
    /**
     * Kiểm tra customer có thể bị khóa hay không.
     *
     * Customer chỉ được khóa khi:
     *
     * - Không còn booking pending / confirmed / in_progress
     * - Booking completed / no_show phải thanh toán đủ
     * - Booking cancelled được xem là đã kết thúc
     */
    public function validateCustomerCanDeactivate(
        int $customerId
    ): void {
        $openBookings = Booking::query()
            ->where(
                'customer_id',
                $customerId
            )
            ->whereIn(
                'status',
                [
                    'pending',
                    'confirmed',
                    'in_progress',
                ]
            )
            ->orderBy('start_at')
            ->get([
                'id',
                'booking_code',
                'status',
                'payment_status',
                'start_at',
            ]);

        if ($openBookings->isNotEmpty()) {
            $codes = $openBookings
                ->pluck('booking_code')
                ->filter()
                ->implode(', ');

            abort(
                response()->json([
                    'message' =>
                        'Không thể khóa khách hàng vì vẫn còn booking chưa hoàn tất.',

                    'reason' =>
                        'active_bookings',

                    'bookings_count' =>
                        $openBookings->count(),

                    'booking_codes' =>
                        $openBookings
                            ->pluck('booking_code')
                            ->values(),

                    'detail' =>
                        $codes !== ''
                            ? "Các booking chưa hoàn tất: {$codes}."
                            : 'Khách hàng vẫn còn booking chưa hoàn tất.',
                ], 422)
            );
        }

        /**
         * cancelled không cần kiểm tra thanh toán.
         *
         * completed / no_show phải paid.
         */
        $unpaidFinishedBookings =
            Booking::query()
                ->where(
                    'customer_id',
                    $customerId
                )
                ->whereIn(
                    'status',
                    [
                        'completed',
                        'no_show',
                    ]
                )
                ->where(
                    'payment_status',
                    '!=',
                    'paid'
                )
                ->orderBy('start_at')
                ->get([
                    'id',
                    'booking_code',
                    'status',
                    'payment_status',
                ]);

        if (
            $unpaidFinishedBookings
                ->isNotEmpty()
        ) {
            $codes =
                $unpaidFinishedBookings
                    ->pluck(
                        'booking_code'
                    )
                    ->filter()
                    ->implode(', ');

            abort(
                response()->json([
                    'message' =>
                        'Không thể khóa khách hàng vì vẫn còn booking chưa thanh toán đủ.',

                    'reason' =>
                        'unpaid_bookings',

                    'bookings_count' =>
                        $unpaidFinishedBookings
                            ->count(),

                    'booking_codes' =>
                        $unpaidFinishedBookings
                            ->pluck(
                                'booking_code'
                            )
                            ->values(),

                    'detail' =>
                        $codes !== ''
                            ? "Các booking chưa thanh toán đủ: {$codes}."
                            : 'Khách hàng vẫn còn công nợ chưa xử lý.',
                ], 422)
            );
        }
    }

    /**
     * Kiểm tra staff có thể bị khóa hay không.
     *
     * Staff chỉ được khóa khi tất cả booking
     * mà staff được phân công đều đã kết thúc.
     */
    public function validateStaffCanDeactivate(
        StaffProfile $staff
    ): void {
        $activeAssignments =
            Booking::query()
                ->whereHas(
                    'staffAssignments',
                    function ($query) use ($staff) {
                        $query->where(
                            'staff_id',
                            $staff->id
                        );
                    }
                )
                ->whereIn(
                    'status',
                    [
                        'pending',
                        'confirmed',
                        'in_progress',
                    ]
                )
                ->orderBy('start_at')
                ->get([
                    'id',
                    'booking_code',
                    'status',
                    'start_at',
                ]);

        if (
            $activeAssignments
                ->isEmpty()
        ) {
            return;
        }

        $codes =
            $activeAssignments
                ->pluck(
                    'booking_code'
                )
                ->filter()
                ->implode(', ');

        abort(
            response()->json([
                'message' =>
                    'Không thể khóa nhân viên vì vẫn đang phụ trách booking chưa hoàn tất.',

                'reason' =>
                    'active_assignments',

                'bookings_count' =>
                    $activeAssignments
                        ->count(),

                'booking_codes' =>
                    $activeAssignments
                        ->pluck(
                            'booking_code'
                        )
                        ->values(),

                'detail' =>
                    $codes !== ''
                        ? "Nhân viên đang phụ trách: {$codes}."
                        : 'Nhân viên vẫn đang phụ trách booking chưa hoàn tất.',
            ], 422)
        );
    }
}