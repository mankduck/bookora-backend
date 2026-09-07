<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Public\AvailabilityRequest;
use App\Models\ServiceVariant;
use App\Services\AvailabilityService;
use Illuminate\Http\JsonResponse;

class AvailabilityController extends Controller
{
    public function __invoke(
        AvailabilityRequest $request,
        AvailabilityService $availabilityService
    ): JsonResponse {
        $variant = ServiceVariant::query()
            ->with('service')
            ->where('status', 'active')
            ->findOrFail(
                $request->integer('variant_id')
            );

        if (
            !$variant->service ||
            $variant->service->status !== 'active'
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Dịch vụ hiện không khả dụng.',
            ], 422);
        }

        $date = $request
            ->string('date')
            ->toString();

        $slots = $availabilityService
            ->getAvailableSlots(
                $variant,
                $date
            );

        return response()->json([
            'success' => true,

            'data' => [
                'date' => $date,

                'service' => [
                    'id' =>
                        $variant->service->id,

                    'name' =>
                        $variant->service->name,
                ],

                'variant' => [
                    'id' =>
                        $variant->id,

                    'name' =>
                        $variant->name,

                    'duration_minutes' =>
                        $variant->duration_minutes,

                    'price' =>
                        $variant->price,

                    'sale_price' =>
                        $variant->sale_price,
                ],

                'slots' => $slots,
            ],
        ]);
    }
}