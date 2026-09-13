<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Coupon\StoreCouponRequest;
use App\Http\Requests\Admin\Coupon\UpdateCouponRequest;
use App\Http\Resources\Admin\CouponResource;
use App\Models\Coupon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CouponController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->input('per_page', 15), 1), 100);

        $query = Coupon::query()
            ->select('coupons.*')
            ->selectSub(
                DB::table('coupon_usages')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('coupon_usages.coupon_id', 'coupons.id'),
                'usages_count'
            );

        if ($request->filled('search')) {
            $search = trim($request->string('search')->toString());

            $query->where(function ($couponQuery) use ($search) {
                $couponQuery
                    ->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        if ($request->filled('type')) {
            $query->where('type', $request->string('type')->toString());
        }

        if ($request->filled('active')) {
            $query->where('is_active', $request->boolean('active'));
        }

        $status = $request->string('status')->toString();
        $now = now();

        if ($status === 'active') {
            $query
                ->where('is_active', true)
                ->where(function ($q) use ($now) {
                    $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
                })
                ->where(function ($q) use ($now) {
                    $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
                });
        } elseif ($status === 'scheduled') {
            $query->where('is_active', true)->where('starts_at', '>', $now);
        } elseif ($status === 'expired') {
            $query->whereNotNull('ends_at')->where('ends_at', '<', $now);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        $coupons = $query
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json([
            'data' => [
                'coupons' => CouponResource::collection($coupons->items()),
                'pagination' => [
                    'current_page' => $coupons->currentPage(),
                    'last_page' => $coupons->lastPage(),
                    'per_page' => $coupons->perPage(),
                    'total' => $coupons->total(),
                    'from' => $coupons->firstItem(),
                    'to' => $coupons->lastItem(),
                ],
            ],
        ]);
    }

    public function store(StoreCouponRequest $request): JsonResponse
    {
        $coupon = Coupon::query()->create($request->validated());
        $coupon->setAttribute('usages_count', 0);

        return response()->json([
            'message' => 'Tạo mã giảm giá thành công.',
            'data' => [
                'coupon' => new CouponResource($coupon),
            ],
        ], 201);
    }

    public function show(Coupon $coupon): JsonResponse
    {
        $coupon->setAttribute(
            'usages_count',
            DB::table('coupon_usages')->where('coupon_id', $coupon->id)->count()
        );

        return response()->json([
            'data' => [
                'coupon' => new CouponResource($coupon),
            ],
        ]);
    }

    public function update(UpdateCouponRequest $request, Coupon $coupon): JsonResponse
    {
        $coupon->update($request->validated());
        $coupon->refresh();
        $coupon->setAttribute(
            'usages_count',
            DB::table('coupon_usages')->where('coupon_id', $coupon->id)->count()
        );

        return response()->json([
            'message' => 'Cập nhật mã giảm giá thành công.',
            'data' => [
                'coupon' => new CouponResource($coupon),
            ],
        ]);
    }

    public function destroy(Coupon $coupon): JsonResponse
    {
        $usageCount = DB::table('coupon_usages')
            ->where('coupon_id', $coupon->id)
            ->count();

        $bookingCount = DB::table('bookings')
            ->where('coupon_id', $coupon->id)
            ->count();

        if ($usageCount > 0 || $bookingCount > 0) {
            return response()->json([
                'message' => 'Mã này đã được sử dụng. Hãy tắt mã thay vì xoá để giữ lịch sử booking.',
            ], 409);
        }

        $coupon->delete();

        return response()->json([
            'message' => 'Đã xoá mã giảm giá.',
        ]);
    }
}
