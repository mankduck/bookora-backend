<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ServiceVariant\StoreServiceVariantRequest;
use App\Http\Requests\Admin\ServiceVariant\UpdateServiceVariantRequest;
use App\Models\Service;
use App\Models\ServiceVariant;
use Illuminate\Http\JsonResponse;

class ServiceVariantController extends Controller
{
    public function store(
        StoreServiceVariantRequest $request,
        Service $service
    ): JsonResponse {
        $data = $request->validated();

        $this->normalizeDeposit($data);

        $data['sort_order'] =
            $data['sort_order'] ?? 0;

        $variant = $service
            ->variants()
            ->create($data);

        return response()->json([
            'success' => true,
            'message' =>
                'Tạo gói dịch vụ thành công.',
            'data' => [
                'variant' => $variant,
            ],
        ], 201);
    }

    public function update(
        UpdateServiceVariantRequest $request,
        Service $service,
        ServiceVariant $variant
    ): JsonResponse {
        if (
            $variant->service_id !==
            $service->id
        ) {
            abort(404);
        }

        $data = $request->validated();

        $this->normalizeDeposit($data);

        $data['sort_order'] =
            $data['sort_order'] ?? 0;

        $variant->update($data);

        return response()->json([
            'success' => true,
            'message' =>
                'Cập nhật gói dịch vụ thành công.',
            'data' => [
                'variant' => $variant->fresh(),
            ],
        ]);
    }

    public function destroy(
        Service $service,
        ServiceVariant $variant
    ): JsonResponse {
        if (
            $variant->service_id !==
            $service->id
        ) {
            abort(404);
        }

        /*
         * Sau này khi booking_items hoạt động,
         * ta sẽ bổ sung kiểm tra không xóa variant
         * đã từng được sử dụng.
         */

        $variant->delete();

        return response()->json([
            'success' => true,
            'message' =>
                'Xóa gói dịch vụ thành công.',
        ]);
    }

    private function normalizeDeposit(
        array &$data
    ): void {
        if (
            ($data['deposit_type'] ?? 'none')
            === 'none'
        ) {
            $data['deposit_value'] = 0;
        }

        if (
            ($data['deposit_type'] ?? null)
            === 'percent'
            &&
            ($data['deposit_value'] ?? 0) > 100
        ) {
            abort(
                response()->json([
                    'success' => false,
                    'message' =>
                        'Tiền cọc theo phần trăm không được lớn hơn 100%.',
                ], 422)
            );
        }
    }
}