<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Coupon;
use App\Models\ServiceVariant;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BookingService
{
    public function __construct(
        private AvailabilityService $availabilityService
    ) {
    }

    public function create(array $data): Booking
    {
        /*
        |--------------------------------------------------------------------------
        | 1. Load variant + service trực tiếp từ database
        |--------------------------------------------------------------------------
        */

        $variant = ServiceVariant::query()
            ->with('service')
            ->where('status', 'active')
            ->findOrFail($data['variant_id']);

        $service = $variant->service;

        if (
            !$service ||
            $service->status !== 'active'
        ) {
            throw ValidationException::withMessages([
                'variant_id' =>
                    'Dịch vụ hiện không khả dụng.',
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | 2. Xác định thời lượng thật
        |--------------------------------------------------------------------------
        */

        $durationMinutes =
            (int) $variant->duration_minutes;

        if ($durationMinutes <= 0) {
            $durationMinutes =
                (int) $service->default_duration_minutes;
        }

        if ($durationMinutes <= 0) {
            throw ValidationException::withMessages([
                'variant_id' =>
                    'Gói dịch vụ chưa được cấu hình thời lượng.',
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | 3. Start / End
        |--------------------------------------------------------------------------
        */

        $startAt = Carbon::createFromFormat(
            'Y-m-d H:i:s',
            $data['start_at']
        );

        $endAt = $startAt
            ->copy()
            ->addMinutes($durationMinutes);

        if ($startAt->lte(now())) {
            throw ValidationException::withMessages([
                'start_at' =>
                    'Thời gian đặt lịch phải ở tương lai.',
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | 4. Kiểm tra availability lần cuối
        |--------------------------------------------------------------------------
        |
        | Không bao giờ tin slot frontend gửi lên.
        |
        */

        $availableSlots =
            $this->availabilityService
                ->getAvailableSlots(
                    $variant,
                    $startAt->format('Y-m-d')
                );

        $slotAvailable = collect(
            $availableSlots
        )->contains(
            function (array $slot) use ($startAt) {
                return $slot['start_at'] ===
                    $startAt->format(
                        'Y-m-d H:i:s'
                    );
            }
        );

        if (!$slotAvailable) {
            throw ValidationException::withMessages([
                'start_at' =>
                    'Khung giờ này không còn khả dụng. Vui lòng chọn thời gian khác.',
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | 5. Giá thật
        |--------------------------------------------------------------------------
        */

        $subtotal =
            $variant->sale_price !== null
                ? (float) $variant->sale_price
                : (float) $variant->price;

        $discountAmount = 0;
        $coupon = null;

        /*
        |--------------------------------------------------------------------------
        | 6. Coupon
        |--------------------------------------------------------------------------
        */

        if (!empty($data['coupon_code'])) {
            [
                $coupon,
                $discountAmount,
            ] = $this->calculateCoupon(
                code: $data['coupon_code'],
                subtotal: $subtotal
            );
        }

        $discountAmount = min(
            $discountAmount,
            $subtotal
        );

        $totalAmount = max(
            0,
            round(
                $subtotal - $discountAmount,
                2
            )
        );

        /*
        |--------------------------------------------------------------------------
        | 7. Deposit
        |--------------------------------------------------------------------------
        */

        $depositAmount =
            $this->calculateDeposit(
                variant: $variant,
                total: $totalAmount
            );

        /*
        |--------------------------------------------------------------------------
        | 8. Customer ID
        |--------------------------------------------------------------------------
        |
        | Chỉ user có role CUSTOMER mới được liên kết customer_id.
        |
        | Admin đang login rồi mở trang public để test booking
        | sẽ KHÔNG bị gắn customer_id của Admin nữa.
        |
        */

        $customerId = null;

        $authenticatedUser =
            Auth::user();

        if (
            $authenticatedUser &&
            $authenticatedUser->hasRole('customer')
        ) {
            $customerId =
                $authenticatedUser->id;
        }

        /*
        |--------------------------------------------------------------------------
        | 9. Transaction
        |--------------------------------------------------------------------------
        */

        return DB::transaction(
            function () use (
                $data,
                $service,
                $variant,
                $startAt,
                $endAt,
                $durationMinutes,
                $subtotal,
                $discountAmount,
                $totalAmount,
                $depositAmount,
                $coupon,
                $customerId
            ) {
                /*
                |--------------------------------------------------------------------------
                | Booking
                |--------------------------------------------------------------------------
                */

                $booking = Booking::query()
                    ->create([
                        'booking_code' =>
                            $this->generateBookingCode(),

                        'customer_id' =>
                            $customerId,

                        'start_at' =>
                            $startAt,

                        'end_at' =>
                            $endAt,

                        'customer_name' =>
                            $data['customer_name'],

                        'customer_phone' =>
                            $data['customer_phone'],

                        'customer_email' =>
                            $data['customer_email']
                            ?? null,

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
                            $data['notes']
                            ?? null,

                        'internal_note' =>
                            null,

                        'status' =>
                            'pending',

                        'payment_status' =>
                            'unpaid',

                        'source' =>
                            'website',
                    ]);

                /*
                |--------------------------------------------------------------------------
                | Booking Item Snapshot
                |--------------------------------------------------------------------------
                */

                BookingItem::query()
                    ->create([
                        'booking_id' =>
                            $booking->id,

                        'service_id' =>
                            $service->id,

                        'service_variant_id' =>
                            $variant->id,

                        'service_name' =>
                            $service->name,

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

                return $booking->load([
                    'items',
                ]);
            }
        );
    }

    private function calculateCoupon(
        string $code,
        float $subtotal
    ): array {
        $coupon = Coupon::query()
            ->where('code', $code)
            ->where('status', 'active')
            ->first();

        if (!$coupon) {
            throw ValidationException::withMessages([
                'coupon_code' =>
                    'Mã giảm giá không tồn tại hoặc đã ngừng áp dụng.',
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Thời gian áp dụng
        |--------------------------------------------------------------------------
        */

        if (
            $coupon->start_at &&
            now()->lt($coupon->start_at)
        ) {
            throw ValidationException::withMessages([
                'coupon_code' =>
                    'Mã giảm giá chưa đến thời gian áp dụng.',
            ]);
        }

        if (
            $coupon->end_at &&
            now()->gt($coupon->end_at)
        ) {
            throw ValidationException::withMessages([
                'coupon_code' =>
                    'Mã giảm giá đã hết hạn.',
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Giá trị tối thiểu
        |--------------------------------------------------------------------------
        */

        if (
            $coupon->min_order_amount !== null &&
            $subtotal <
            (float) $coupon->min_order_amount
        ) {
            throw ValidationException::withMessages([
                'coupon_code' =>
                    'Đơn hàng chưa đạt giá trị tối thiểu để sử dụng mã này.',
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Usage limit
        |--------------------------------------------------------------------------
        */

        if (
            $coupon->usage_limit !== null &&
            $coupon->usages()->count() >=
            (int) $coupon->usage_limit
        ) {
            throw ValidationException::withMessages([
                'coupon_code' =>
                    'Mã giảm giá đã hết lượt sử dụng.',
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Tính discount
        |--------------------------------------------------------------------------
        */

        $discount = 0;

        if (
            $coupon->type === 'percent'
        ) {
            $discount =
                $subtotal *
                (
                    (float) $coupon->value
                    / 100
                );
        } elseif (
            $coupon->type === 'fixed'
        ) {
            $discount =
                (float) $coupon->value;
        }

        /*
        |--------------------------------------------------------------------------
        | Maximum discount
        |--------------------------------------------------------------------------
        */

        if (
            $coupon->max_discount_amount !==
            null
        ) {
            $discount = min(
                $discount,
                (float)
                    $coupon->max_discount_amount
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Không cho discount vượt subtotal
        |--------------------------------------------------------------------------
        */

        $discount = min(
            $discount,
            $subtotal
        );

        return [
            $coupon,
            round(
                $discount,
                2
            ),
        ];
    }

    private function calculateDeposit(
        ServiceVariant $variant,
        float $total
    ): float {
        if (
            $variant->deposit_type ===
            'none'
        ) {
            return 0;
        }

        if (
            $variant->deposit_type ===
            'fixed'
        ) {
            return round(
                min(
                    (float)
                        $variant->deposit_value,
                    $total
                ),
                2
            );
        }

        if (
            $variant->deposit_type ===
            'percent'
        ) {
            return round(
                $total *
                (
                    (float)
                        $variant->deposit_value
                    / 100
                ),
                2
            );
        }

        return 0;
    }

    private function generateBookingCode(): string
    {
        do {
            $code =
                'BK' .
                now()->format('Ymd') .
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