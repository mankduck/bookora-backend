<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Coupon;
use App\Models\ServiceVariant;
use App\Models\User;
use App\Services\AvailabilityService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AdminBookingCreateController extends Controller
{
    public function store(
        Request $request,
        AvailabilityService $availabilityService
    ): JsonResponse {
        $data = $request->validate([
            'variant_id' => [
                'required',
                'integer',
                'exists:service_variants,id',
            ],

            'start_at' => [
                'required',
                'date_format:Y-m-d H:i:s',
            ],

            'customer_name' => [
                'required',
                'string',
                'max:150',
            ],

            'customer_phone' => [
                'required',
                'string',
                'max:30',
            ],

            'customer_email' => [
                'nullable',
                'email',
                'max:190',
            ],

            'notes' => [
                'nullable',
                'string',
                'max:2000',
            ],

            'coupon_code' => [
                'nullable',
                'string',
                'max:100',
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | 1. Lấy gói dịch vụ
        |--------------------------------------------------------------------------
        */

        $variant = ServiceVariant::query()
            ->with([
                'service',
            ])
            ->findOrFail(
                $data['variant_id']
            );

        if (!$variant->service) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Không tìm thấy dịch vụ của gói này.',
            ], 422);
        }

        if (
            $variant->status !== 'active' ||
            $variant->service->status !== 'active'
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Dịch vụ hoặc gói hiện không hoạt động.',
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | 2. Thời lượng
        |--------------------------------------------------------------------------
        */

        $durationMinutes =
            (int) $variant->duration_minutes;

        if ($durationMinutes <= 0) {
            $durationMinutes =
                (int) $variant
                    ->service
                    ->default_duration_minutes;
        }

        if ($durationMinutes <= 0) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Gói dịch vụ chưa có thời lượng hợp lệ.',
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | 3. Parse ngày giờ
        |--------------------------------------------------------------------------
        */

        $startAt =
            Carbon::createFromFormat(
                'Y-m-d H:i:s',
                $data['start_at']
            );

        $endAt =
            $startAt
                ->copy()
                ->addMinutes(
                    $durationMinutes
                );

        if ($startAt->lte(now())) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Không thể tạo booking ở thời gian đã qua.',
                'errors' => [
                    'start_at' => [
                        'Khung giờ đã qua.',
                    ],
                ],
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | 4. Kiểm tra availability lần cuối
        |--------------------------------------------------------------------------
        |
        | Không tin hoàn toàn slot frontend.
        | Khi Admin bấm tạo booking, backend kiểm tra lại.
        |
        */

        $availableSlots =
            $availabilityService
                ->getAvailableSlots(
                    $variant,
                    $startAt->format(
                        'Y-m-d'
                    )
                );

        $normalizedStart =
            $startAt->format(
                'Y-m-d H:i:s'
            );

        $slotExists =
            collect(
                $availableSlots
            )->contains(
                function ($slot) use (
                    $normalizedStart
                ) {
                    return
                        ($slot['start_at'] ?? null)
                        ===
                        $normalizedStart;
                }
            );

        if (!$slotExists) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Khung giờ vừa chọn không còn khả dụng.',
                'errors' => [
                    'start_at' => [
                        'Khung giờ vừa chọn không còn khả dụng.',
                    ],
                ],
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | 5. Giá gốc
        |--------------------------------------------------------------------------
        */

        $price =
            $variant->sale_price !== null
                ? (float) $variant->sale_price
                : (float) $variant->price;

        $subtotal =
            max(
                $price,
                0
            );

        /*
        |--------------------------------------------------------------------------
        | 6. Coupon
        |--------------------------------------------------------------------------
        */

        $coupon = null;
        $discountAmount = 0;

        if (
            !empty(
                $data['coupon_code']
            )
        ) {
            $couponCode =
                strtoupper(
                    trim(
                        $data['coupon_code']
                    )
                );

            $coupon = Coupon::query()
                ->where(
                    'code',
                    $couponCode
                )
                ->where(
                    'status',
                    'active'
                )
                ->first();

            if (!$coupon) {
                return response()->json([
                    'success' => false,
                    'message' =>
                        'Mã ưu đãi không hợp lệ.',
                ], 422);
            }

            if (
                $coupon->start_at &&
                now()->lt(
                    $coupon->start_at
                )
            ) {
                return response()->json([
                    'success' => false,
                    'message' =>
                        'Mã ưu đãi chưa đến thời gian sử dụng.',
                ], 422);
            }

            if (
                $coupon->end_at &&
                now()->gt(
                    $coupon->end_at
                )
            ) {
                return response()->json([
                    'success' => false,
                    'message' =>
                        'Mã ưu đãi đã hết hạn.',
                ], 422);
            }

            if (
                $coupon->min_order_amount !== null &&
                $subtotal <
                    (float) $coupon
                        ->min_order_amount
            ) {
                return response()->json([
                    'success' => false,
                    'message' =>
                        'Booking chưa đạt giá trị tối thiểu để sử dụng mã ưu đãi.',
                ], 422);
            }

            /*
             * Với booking Admin tạo không nhất thiết
             * có customer account.
             *
             * Global usage_limit được đếm trực tiếp
             * từ bookings đã sử dụng coupon.
             */
            if (
                $coupon->usage_limit !== null
            ) {
                $usedCount =
                    Booking::query()
                        ->where(
                            'coupon_id',
                            $coupon->id
                        )
                        ->count();

                if (
                    $usedCount >=
                    (int) $coupon
                        ->usage_limit
                ) {
                    return response()->json([
                        'success' => false,
                        'message' =>
                            'Mã ưu đãi đã hết lượt sử dụng.',
                    ], 422);
                }
            }

            /*
             * Chấp nhận cả:
             * - percent
             * - percentage
             * để code chịu được dữ liệu cũ.
             */
            if (
                in_array(
                    $coupon->type,
                    [
                        'percent',
                        'percentage',
                    ],
                    true
                )
            ) {
                $discountAmount =
                    $subtotal *
                    (
                        (float) $coupon->value
                        / 100
                    );

                if (
                    $coupon
                        ->max_discount_amount !==
                    null
                ) {
                    $discountAmount =
                        min(
                            $discountAmount,
                            (float) $coupon
                                ->max_discount_amount
                        );
                }
            } else {
                /*
                 * fixed / amount
                 */
                $discountAmount =
                    (float) $coupon->value;
            }

            $discountAmount =
                min(
                    max(
                        $discountAmount,
                        0
                    ),
                    $subtotal
                );
        }

        /*
        |--------------------------------------------------------------------------
        | 7. Tổng tiền
        |--------------------------------------------------------------------------
        */

        $totalAmount =
            max(
                $subtotal -
                $discountAmount,
                0
            );

        /*
        |--------------------------------------------------------------------------
        | 8. Tính tiền cọc
        |--------------------------------------------------------------------------
        */

        $depositAmount = 0;

        if (
            $variant->deposit_type ===
            'fixed'
        ) {
            $depositAmount =
                min(
                    max(
                        (float)
                        $variant->deposit_value,
                        0
                    ),
                    $totalAmount
                );
        }

        if (
            in_array(
                $variant->deposit_type,
                [
                    'percent',
                    'percentage',
                ],
                true
            )
        ) {
            $depositAmount =
                $totalAmount *
                (
                    max(
                        (float)
                        $variant->deposit_value,
                        0
                    )
                    / 100
                );

            $depositAmount =
                min(
                    $depositAmount,
                    $totalAmount
                );
        }

        /*
        |--------------------------------------------------------------------------
        | 9. Tìm customer account nếu đã tồn tại
        |--------------------------------------------------------------------------
        |
        | Nếu số điện thoại/email trùng customer hiện có:
        | booking sẽ xuất hiện trong tài khoản khách.
        |
        | Nếu không có:
        | customer_id = null
        | nhưng snapshot khách vẫn được lưu đầy đủ.
        |
        */

        $customerId = null;

        $phone =
            trim(
                $data['customer_phone']
            );

        $email =
            !empty(
                $data['customer_email']
            )
                ? trim(
                    $data['customer_email']
                )
                : null;

        $customerQuery =
            User::query()
                ->whereHas(
                    'roles',
                    function ($query) {
                        $query->where(
                            'code',
                            'customer'
                        );
                    }
                );

        $existingCustomer =
            $customerQuery
                ->where(
                    function ($query) use (
                        $phone,
                        $email
                    ) {
                        $query->where(
                            'phone',
                            $phone
                        );

                        if ($email) {
                            $query->orWhere(
                                'email',
                                $email
                            );
                        }
                    }
                )
                ->first();

        if ($existingCustomer) {
            $customerId =
                $existingCustomer->id;
        }

        /*
        |--------------------------------------------------------------------------
        | 10. Tạo booking
        |--------------------------------------------------------------------------
        */

        $booking =
            DB::transaction(
                function () use (
                    $data,
                    $variant,
                    $customerId,
                    $phone,
                    $email,
                    $startAt,
                    $endAt,
                    $subtotal,
                    $discountAmount,
                    $totalAmount,
                    $depositAmount,
                    $coupon,
                    $durationMinutes
                ) {
                    $bookingCode =
                        $this
                            ->generateBookingCode();

                    $booking =
                        Booking::create([
                            'booking_code' =>
                                $bookingCode,

                            'customer_id' =>
                                $customerId,

                            'start_at' =>
                                $startAt,

                            'end_at' =>
                                $endAt,

                            'customer_name' =>
                                trim(
                                    $data[
                                        'customer_name'
                                    ]
                                ),

                            'customer_phone' =>
                                $phone,

                            'customer_email' =>
                                $email,

                            'subtotal' =>
                                $subtotal,

                            'discount_amount' =>
                                $discountAmount,

                            'total_amount' =>
                                $totalAmount,

                            'deposit_amount' =>
                                $depositAmount,

                            'coupon_id' =>
                                $coupon?->id,

                            'customer_note' =>
                                !empty(
                                    $data['notes']
                                )
                                    ? trim(
                                        $data['notes']
                                    )
                                    : null,

                            'internal_note' =>
                                null,

                            'status' =>
                                'pending',

                            'payment_status' =>
                                'unpaid',

                            'source' =>
                                'admin',

                            'confirmed_by' =>
                                null,

                            'confirmed_at' =>
                                null,

                            'cancelled_by' =>
                                null,

                            'cancelled_at' =>
                                null,

                            'cancellation_reason' =>
                                null,
                        ]);

                    BookingItem::create([
                        'booking_id' =>
                            $booking->id,

                        'service_id' =>
                            $variant
                                ->service
                                ->id,

                        'service_variant_id' =>
                            $variant->id,

                        'service_name' =>
                            $variant
                                ->service
                                ->name,

                        'variant_name' =>
                            $variant->name,

                        'price' =>
                            $subtotal,

                        'quantity' =>
                            1,

                        'duration_minutes' =>
                            $durationMinutes,

                        'subtotal' =>
                            $subtotal,
                    ]);

                    DB::table(
                        'booking_status_histories'
                    )->insert([
                        'booking_id' =>
                            $booking->id,

                        'from_status' =>
                            null,

                        'to_status' =>
                            'pending',

                        'changed_by' =>
                            auth()->id(),

                        'note' =>
                            'Admin tạo booking thủ công.',

                        'created_at' =>
                            now(),
                    ]);

                    return $booking;
                }
            );

        /*
        |--------------------------------------------------------------------------
        | 11. Response
        |--------------------------------------------------------------------------
        */

        $booking->load([
            'items',
            'customer:id,name,email,phone',
            'coupon:id,code,name,type,value',
        ]);

        return response()->json([
            'success' => true,

            'message' =>
                'Đã tạo booking thành công.',

            'data' => [
                'booking' => [
                    'id' =>
                        $booking->id,

                    'booking_code' =>
                        $booking
                            ->booking_code,

                    'customer_id' =>
                        $booking
                            ->customer_id,

                    'start_at' =>
                        $booking
                            ->start_at
                            ->format(
                                'Y-m-d H:i:s'
                            ),

                    'end_at' =>
                        $booking
                            ->end_at
                            ->format(
                                'Y-m-d H:i:s'
                            ),

                    'customer_name' =>
                        $booking
                            ->customer_name,

                    'customer_phone' =>
                        $booking
                            ->customer_phone,

                    'customer_email' =>
                        $booking
                            ->customer_email,

                    'subtotal' =>
                        $booking
                            ->subtotal,

                    'discount_amount' =>
                        $booking
                            ->discount_amount,

                    'total_amount' =>
                        $booking
                            ->total_amount,

                    'deposit_amount' =>
                        $booking
                            ->deposit_amount,

                    'status' =>
                        $booking
                            ->status,

                    'payment_status' =>
                        $booking
                            ->payment_status,

                    'source' =>
                        $booking
                            ->source,

                    'items' =>
                        $booking
                            ->items,
                ],
            ],
        ], 201);
    }

    private function generateBookingCode(): string
    {
        do {
            $code =
                'BK' .
                now()->format(
                    'Ymd'
                ) .
                strtoupper(
                    Str::random(6)
                );
        } while (
            Booking::query()
                ->where(
                    'booking_code',
                    $code
                )
                ->exists()
        );

        return $code;
    }
}