<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Public\CheckCouponRequest;
use App\Models\Coupon;
use App\Models\ServiceVariant;
use Illuminate\Http\JsonResponse;

class CouponController extends Controller
{
    public function check(
        CheckCouponRequest $request
    ): JsonResponse {
        $variant = ServiceVariant::query()
            ->where('status', 'active')
            ->with('service')
            ->findOrFail(
                $request->integer('variant_id')
            );

        if (
            !$variant->service ||
            $variant->service->status !== 'active'
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Dịch vụ hiện không khả dụng.',
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | Giá thực tế của gói
        |--------------------------------------------------------------------------
        */

        $price = $variant->sale_price !== null
            ? (float) $variant->sale_price
            : (float) $variant->price;

        $coupon = Coupon::query()
            ->where(
                'code',
                $request->string('code')->toString()
            )
            ->where('status', 'active')
            ->first();

        if (!$coupon) {
            return response()->json([
                'success' => false,
                'message' => 'Mã giảm giá không tồn tại hoặc đã ngừng áp dụng.',
            ], 422);
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
            return response()->json([
                'success' => false,
                'message' => 'Mã giảm giá chưa đến thời gian áp dụng.',
            ], 422);
        }

        if (
            $coupon->end_at &&
            now()->gt($coupon->end_at)
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Mã giảm giá đã hết hạn.',
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | Giá trị đơn tối thiểu
        |--------------------------------------------------------------------------
        */

        if (
            $coupon->min_order_amount !== null &&
            $price < (float) $coupon->min_order_amount
        ) {
            return response()->json([
                'success' => false,
                'message' => sprintf(
                    'Đơn hàng cần tối thiểu %s để sử dụng mã này.',
                    number_format(
                        (float) $coupon->min_order_amount,
                        0,
                        ',',
                        '.'
                    ) . ' ₫'
                ),
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | Giới hạn sử dụng toàn hệ thống
        |--------------------------------------------------------------------------
        */

        if (
            $coupon->usage_limit !== null &&
            $coupon->usages()->count() >=
            (int) $coupon->usage_limit
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Mã giảm giá đã hết lượt sử dụng.',
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | Tính giảm giá
        |--------------------------------------------------------------------------
        */

        $discount = 0;

        if ($coupon->type === 'percent') {
            $discount =
                $price *
                ((float) $coupon->value / 100);
        } elseif ($coupon->type === 'fixed') {
            $discount =
                (float) $coupon->value;
        }

        if (
            $coupon->max_discount_amount !== null
        ) {
            $discount = min(
                $discount,
                (float) $coupon->max_discount_amount
            );
        }

        $discount = min(
            $discount,
            $price
        );

        $discount = round(
            $discount,
            2
        );

        $total = max(
            0,
            round(
                $price - $discount,
                2
            )
        );

        return response()->json([
            'success' => true,

            'message' => 'Áp dụng mã giảm giá thành công.',

            'data' => [
                'coupon' => [
                    'id' => $coupon->id,
                    'code' => $coupon->code,
                    'name' => $coupon->name,
                    'type' => $coupon->type,
                    'value' => $coupon->value,
                ],

                'subtotal' => $price,
                'discount' => $discount,
                'total' => $total,
            ],
        ]);
    }
}