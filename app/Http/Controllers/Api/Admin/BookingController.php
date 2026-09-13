<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingStaff;
use App\Models\StaffProfile;
use App\Services\PaymentProofService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BookingController extends Controller
{
    public function index(
        Request $request,
        PaymentProofService $paymentService
    ): JsonResponse {
        $query = Booking::query()
            ->with([
                'items',
                'customer:id,name,email,phone',
                'staffAssignments.staff.user:id,name,email,phone',
            ])
            ->withCount('staffAssignments');

        if ($request->filled('search')) {
            $search = trim(
                $request->string('search')->toString()
            );

            $query->where(
                function ($bookingQuery) use ($search) {
                    $bookingQuery
                        ->where(
                            'booking_code',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'customer_name',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'customer_phone',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'customer_email',
                            'like',
                            "%{$search}%"
                        );
                }
            );
        }

        if ($request->filled('status')) {
            $query->where(
                'status',
                $request->string('status')->toString()
            );
        }

        if ($request->filled('payment_status')) {
            $query->where(
                'payment_status',
                $request
                    ->string('payment_status')
                    ->toString()
            );
        }

        if ($request->filled('date')) {
            $query->whereDate(
                'start_at',
                $request->string('date')->toString()
            );
        }

        if ($request->filled('from_date')) {
            $query->whereDate(
                'start_at',
                '>=',
                $request
                    ->string('from_date')
                    ->toString()
            );
        }

        if ($request->filled('to_date')) {
            $query->whereDate(
                'start_at',
                '<=',
                $request
                    ->string('to_date')
                    ->toString()
            );
        }

        $query
            ->orderByDesc('start_at')
            ->orderByDesc('id');

        $bookings = $query->paginate(
            min(
                max(
                    (int) $request->integer(
                        'per_page',
                        15
                    ),
                    1
                ),
                100
            )
        );

        $bookings
            ->getCollection()
            ->transform(
                function (Booking $booking) use ($paymentService) {
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
        Booking $booking,
        PaymentProofService $paymentService
    ): JsonResponse {
        $booking->load([
            'items',
            'customer:id,name,email,phone',
            'coupon:id,code,name,type,value',
            'staffAssignments.staff.user:id,name,email,phone',
        ]);

        $paymentService
            ->attachPaymentData(
                $booking
            );

        return response()->json([
            'success' => true,
            'data' => [
                'booking' => $booking,
            ],
        ]);
    }

    public function confirm(
        Request $request,
        Booking $booking,
        PaymentProofService $paymentService
    ): JsonResponse {
        if ($booking->status === 'cancelled') {
            return response()->json([
                'success' => false,
                'message' =>
                    'Booking đã bị huỷ nên không thể xác nhận.',
            ], 422);
        }

        if ($booking->status === 'completed') {
            return response()->json([
                'success' => false,
                'message' =>
                    'Booking đã hoàn thành.',
            ], 422);
        }

        if ($booking->status === 'no_show') {
            return response()->json([
                'success' => false,
                'message' =>
                    'Booking đã được đánh dấu khách không đến.',
            ], 422);
        }

        if (
            in_array(
                $booking->status,
                [
                    'confirmed',
                    'in_progress',
                ],
                true
            )
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Booking này đã được xác nhận.',
            ], 422);
        }

        if ($booking->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' =>
                    'Trạng thái booking hiện tại không thể xác nhận.',
            ], 422);
        }

        if ($booking->end_at->lte(now())) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Booking đã quá thời gian thực hiện nên không thể xác nhận.',
            ], 422);
        }

        $summary =
            $paymentService->summary(
                $booking
            );

        $needsDeposit =
            $summary['deposit_amount'] > 0 &&
            $summary['deposit_remaining'] > 0;

        if (
            $needsDeposit &&
            !$request->boolean('force')
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Booking chưa hoàn tất tiền cọc.',
                'requires_confirmation' =>
                    true,
                'payment' =>
                    $summary,
            ], 409);
        }

        DB::transaction(
            function () use ($booking, $needsDeposit) {
                $fromStatus =
                    $booking->status;

                $booking->update([
                    'status' =>
                        'confirmed',

                    'confirmed_by' =>
                        auth()->id(),

                    'confirmed_at' =>
                        now(),
                ]);

                DB::table(
                    'booking_status_histories'
                )->insert([
                            'booking_id' =>
                                $booking->id,

                            'from_status' =>
                                $fromStatus,

                            'to_status' =>
                                'confirmed',

                            'changed_by' =>
                                auth()->id(),

                            'note' =>
                                $needsDeposit
                                ? 'Admin xác nhận booking khi tiền cọc chưa hoàn tất.'
                                : 'Admin xác nhận booking.',

                            'created_at' =>
                                now(),
                        ]);
            }
        );

        $booking
            ->refresh()
            ->load([
                'items',
                'customer:id,name,email,phone',
                'coupon:id,code,name,type,value',
                'staffAssignments.staff.user:id,name,email,phone',
            ]);

        $paymentService
            ->attachPaymentData(
                $booking
            );

        return response()->json([
            'success' => true,
            'message' =>
                'Booking đã được xác nhận thành công.',
            'data' => [
                'booking' =>
                    $booking,
            ],
        ]);
    }

    public function updateStatus(
        Request $request,
        Booking $booking,
        PaymentProofService $paymentService
    ): JsonResponse {
        $data = $request->validate([
            'status' => [
                'required',
                'in:confirmed,in_progress,completed,cancelled,no_show',
            ],
            'reason' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ]);

        $target = $data['status'];
        $current = $booking->status;

        if ($target === 'confirmed') {
            return $this->confirm(
                $request,
                $booking,
                $paymentService
            );
        }

        $allowed = [
            'pending' => [
                'cancelled',
            ],

            'confirmed' => [
                'in_progress',
                'cancelled',
                'no_show',
            ],

            'in_progress' => [
                'completed',
                'cancelled',
                'no_show',
            ],

            'completed' => [],
            'cancelled' => [],
            'no_show' => [],
        ];

        if (
            !in_array(
                $target,
                $allowed[$current] ?? [],
                true
            )
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Không thể chuyển từ trạng thái hiện tại sang trạng thái đã chọn.',
            ], 422);
        }

        if (
            $current === 'in_progress'
            &&
            $booking->payment_status === 'paid'
            &&
            $target !== 'completed'
        ) {
            return response()->json([
                'success' => false,

                'message' =>
                    'Booking đã thanh toán đầy đủ. Trạng thái hợp lệ tiếp theo duy nhất là Hoàn thành.',
            ], 422);
        }

        if ($target === 'completed') {
            $paymentService->syncBookingPaymentStatus(
                $booking
            );

            $booking->refresh();

            $summary = $paymentService->summary(
                $booking
            );

            if (
                $booking->payment_status !== 'paid' ||
                (float) $summary['remaining_amount'] > 0
            ) {
                return response()->json([
                    'success' => false,

                    'message' =>
                        'Không thể hoàn thành booking khi khách hàng chưa thanh toán đủ. Vui lòng xác nhận thanh toán trước.',

                    'payment' => $summary,
                ], 422);
            }
        }

        DB::transaction(
            function () use ($booking, $current, $target, $data) {
                $changes = [
                    'status' => $target,
                ];

                if ($target === 'cancelled') {
                    $changes['cancelled_by'] =
                        auth()->id();
                    $changes['cancelled_at'] =
                        now();
                    $changes['cancellation_reason'] =
                        $data['reason'] ??
                        'Admin huỷ booking.';
                }

                $booking->update($changes);

                DB::table(
                    'booking_status_histories'
                )->insert([
                            'booking_id' =>
                                $booking->id,
                            'from_status' =>
                                $current,
                            'to_status' =>
                                $target,
                            'changed_by' =>
                                auth()->id(),
                            'note' =>
                                $data['reason'] ??
                                'Admin cập nhật trạng thái booking.',
                            'created_at' =>
                                now(),
                        ]);
            }
        );

        $booking
            ->refresh()
            ->load([
                'items',
                'customer:id,name,email,phone',
                'coupon:id,code,name,type,value',
                'staffAssignments.staff.user:id,name,email,phone',
            ]);

        $paymentService
            ->attachPaymentData(
                $booking
            );

        return response()->json([
            'success' => true,
            'message' =>
                'Đã cập nhật trạng thái booking.',
            'data' => [
                'booking' => $booking,
            ],
        ]);
    }

    public function eligibleStaff(
        Booking $booking
    ): JsonResponse {
        if (
            !in_array(
                $booking->status,
                [
                    'confirmed',
                    'in_progress',
                ],
                true
            )
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Chỉ có thể tìm nhân viên cho booking đã được xác nhận.',
            ], 422);
        }

        $booking->loadMissing('items');

        $serviceIds = $booking
            ->items
            ->pluck('service_id')
            ->filter()
            ->unique()
            ->values();

        if ($serviceIds->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Booking không có dịch vụ hợp lệ để phân công nhân viên.',
            ], 422);
        }

        $staffList = StaffProfile::query()
            ->with([
                'user:id,name,email,phone,status',
                'services:id,name',
            ])
            ->where('status', 'active')
            ->where('is_bookable', true)
            ->whereHas(
                'user',
                fn($query) =>
                $query->where(
                    'status',
                    'active'
                )
            )
            ->where(
                function ($query) use ($serviceIds) {
                    foreach ($serviceIds as $serviceId) {
                        $query->whereHas(
                            'services',
                            function ($serviceQuery) use ($serviceId) {
                                $serviceQuery
                                    ->where(
                                        'services.id',
                                        $serviceId
                                    )
                                    ->where(
                                        'staff_services.status',
                                        'active'
                                    );
                            }
                        );
                    }
                }
            )
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->filter(
                fn(StaffProfile $staff) =>
                $this->isStaffAvailableForBooking(
                    $staff,
                    $booking
                )
            )
            ->values()
            ->map(
                function (StaffProfile $staff) use ($booking) {
                    $currentAssignment =
                        $booking
                            ->staffAssignments()
                            ->where(
                                'staff_id',
                                $staff->id
                            )
                            ->first();

                    return [
                        'id' => $staff->id,
                        'employee_code' =>
                            $staff->employee_code,
                        'position' =>
                            $staff->position,
                        'experience_years' =>
                            (int) $staff->experience_years,
                        'is_primary' =>
                            (bool) (
                                $currentAssignment
                                        ?->is_primary ??
                                false
                            ),
                        'user' => [
                            'id' =>
                                $staff->user->id,
                            'name' =>
                                $staff->user->name,
                            'email' =>
                                $staff->user->email,
                            'phone' =>
                                $staff->user->phone,
                        ],
                        'services' =>
                            $staff->services
                                ->map(
                                    fn($service) => [
                                        'id' =>
                                            $service->id,
                                        'name' =>
                                            $service->name,
                                    ]
                                )
                                ->values(),
                    ];
                }
            );

        return response()->json([
            'success' => true,
            'data' => [
                'staff' => $staffList,
            ],
        ]);
    }

    public function assignPrimary(
        Request $request,
        Booking $booking
    ): JsonResponse {
        $validated = $request->validate([
            'staff_id' => [
                'required',
                'integer',
                'exists:staff_profiles,id',
            ],
        ]);

        if ($booking->status != 'confirmed') {
            return response()->json([
                'success' => false,
                'message' =>
                    'Không thể đổi phân công Photo sau khi đã xác nhận!',
            ], 422);
        }

        if ($booking->end_at->lte(now())) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Booking đã quá thời gian thực hiện.',
            ], 422);
        }

        $booking->loadMissing('items');

        $staff = StaffProfile::query()
            ->with([
                'user:id,name,email,phone,status',
                'services:id,name',
            ])
            ->findOrFail(
                (int) $validated['staff_id']
            );

        if (
            !$this->isStaffEligibleByProfile(
                $staff,
                $booking
            )
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Nhân viên không đủ điều kiện thực hiện dịch vụ của booking này.',
            ], 422);
        }

        if (
            !$this->isStaffAvailableForBooking(
                $staff,
                $booking
            )
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Nhân viên không còn khả dụng trong khung giờ này. Hãy tải lại danh sách.',
            ], 422);
        }

        DB::transaction(
            function () use ($booking, $staff) {
                $now = now();

                /*
                 * Một booking chỉ có MỘT nhân viên chính.
                 * Khi đổi từ Photo A sang Photo B, xoá hẳn
                 * assignment primary cũ để Photo A được
                 * giải phóng lịch ngay lập tức.
                 *
                 * Điều kiện role=primary cũng dọn luôn
                 * các dòng cũ bị lỗi trước đây: is_primary=0
                 * nhưng role vẫn là primary.
                 */
                DB::table('booking_staff')
                    ->where(
                        'booking_id',
                        $booking->id
                    )
                    ->where(
                        'staff_id',
                        '!=',
                        $staff->id
                    )
                    ->where(
                        function ($query) {
                            $query
                                ->where(
                                    'is_primary',
                                    true
                                )
                                ->orWhere(
                                    'role',
                                    'primary'
                                );
                        }
                    )
                    ->delete();

                $existing = DB::table(
                    'booking_staff'
                )
                    ->where(
                        'booking_id',
                        $booking->id
                    )
                    ->where(
                        'staff_id',
                        $staff->id
                    )
                    ->first();

                if ($existing) {
                    DB::table('booking_staff')
                        ->where(
                            'id',
                            $existing->id
                        )
                        ->update([
                            'role' => 'primary',
                            'is_primary' => true,
                            'assigned_by' =>
                                auth()->id(),
                            'assigned_at' =>
                                $now,
                            'updated_at' =>
                                $now,
                        ]);
                } else {
                    DB::table(
                        'booking_staff'
                    )->insert([
                                'booking_id' =>
                                    $booking->id,

                                'staff_id' =>
                                    $staff->id,

                                'role' =>
                                    'primary',

                                'is_primary' =>
                                    true,

                                'assigned_by' =>
                                    auth()->id(),

                                'assigned_at' =>
                                    $now,

                                'created_at' =>
                                    $now,

                                'updated_at' =>
                                    $now,
                            ]);
                }
            }
        );

        $booking
            ->refresh()
            ->load([
                'items',
                'customer:id,name,email,phone',
                'coupon:id,code,name,type,value',
                'staffAssignments.staff.user:id,name,email,phone',
            ]);

        return response()->json([
            'success' => true,
            'message' =>
                'Đã phân công nhân viên chính.',
            'data' => [
                'booking' => $booking,
            ],
        ]);
    }

    private function isStaffEligibleByProfile(
        StaffProfile $staff,
        Booking $booking
    ): bool {
        if (
            $staff->status !== 'active' ||
            !$staff->is_bookable ||
            !$staff->user ||
            $staff->user->status !== 'active'
        ) {
            return false;
        }

        $serviceIds = $booking
            ->items
            ->pluck('service_id')
            ->filter()
            ->unique()
            ->values();

        if ($serviceIds->isEmpty()) {
            return false;
        }

        foreach ($serviceIds as $serviceId) {
            $canDoService =
                $staff
                    ->services()
                    ->where(
                        'services.id',
                        $serviceId
                    )
                    ->where(
                        'staff_services.status',
                        'active'
                    )
                    ->exists();

            if (!$canDoService) {
                return false;
            }
        }

        return true;
    }

    private function isStaffAvailableForBooking(
        StaffProfile $staff,
        Booking $booking
    ): bool {
        if (
            !$this->isStaffEligibleByProfile(
                $staff,
                $booking
            )
        ) {
            return false;
        }

        $start = Carbon::parse(
            $booking->start_at
        );

        $end = Carbon::parse(
            $booking->end_at
        );

        if (
            !$start->isSameDay($end)
        ) {
            return false;
        }

        $dayOfWeek =
            $start->dayOfWeek;

        $startTime =
            $start->format('H:i:s');

        $endTime =
            $end->format('H:i:s');

        $coveredBySchedule =
            $staff
                ->schedules()
                ->where(
                    'day_of_week',
                    $dayOfWeek
                )
                ->where(
                    'is_working',
                    true
                )
                ->where(
                    'start_time',
                    '<=',
                    $startTime
                )
                ->where(
                    'end_time',
                    '>=',
                    $endTime
                )
                ->exists();

        if (!$coveredBySchedule) {
            return false;
        }

        $hasTimeOff =
            $staff
                ->timeOffs()
                ->where(
                    'start_at',
                    '<',
                    $end->format(
                        'Y-m-d H:i:s'
                    )
                )
                ->where(
                    'end_at',
                    '>',
                    $start->format(
                        'Y-m-d H:i:s'
                    )
                )
                ->where(
                    function ($query) {
                        $query
                            ->whereNull('status')
                            ->orWhereNotIn(
                                'status',
                                [
                                    'cancelled',
                                    'rejected',
                                ]
                            );
                    }
                )
                ->exists();

        if ($hasTimeOff) {
            return false;
        }

        $hasConflict =
            BookingStaff::query()
                ->where(
                    'staff_id',
                    $staff->id
                )
                ->where(
                    'booking_id',
                    '!=',
                    $booking->id
                )
                ->whereHas(
                    'booking',
                    function ($query) use ($start, $end) {
                        $query
                            ->whereIn(
                                'status',
                                [
                                    'confirmed',
                                    'in_progress',
                                ]
                            )
                            ->where(
                                'start_at',
                                '<',
                                $end->format(
                                    'Y-m-d H:i:s'
                                )
                            )
                            ->where(
                                'end_at',
                                '>',
                                $start->format(
                                    'Y-m-d H:i:s'
                                )
                            );
                    }
                )
                ->exists();

        return !$hasConflict;
    }
}
