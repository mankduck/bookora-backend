<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\PaymentProof;
use App\Services\PaymentProofService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PaymentProofController extends Controller
{
    public function store(
        Request $request,
        Booking $booking,
        PaymentProofService $paymentService
    ): JsonResponse {
        $this->assertOwner(
            $request,
            $booking
        );

        $summary =
            $paymentService->summary(
                $booking
            );

        if (
            $summary['deposit_amount'] <=
            0
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Booking này không yêu cầu đặt cọc.',
            ], 422);
        }

        if (
            $summary['deposit_remaining'] <=
            0
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Booking đã hoàn tất tiền cọc.',
            ], 422);
        }

        $data = $request->validate([
            'image' => [
                'required',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],

            'note' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ]);

        $path = $request
            ->file('image')
            ->store(
                'payment-proofs',
                'public'
            );

        DB::transaction(
            function () use (
                $booking,
                $request,
                $summary,
                $data,
                $path
            ) {
                $replaceable =
                    PaymentProof::query()
                        ->where(
                            'booking_id',
                            $booking->id
                        )
                        ->where(
                            'customer_id',
                            $request->user()->id
                        )
                        ->whereIn(
                            'status',
                            [
                                'pending',
                                'rejected',
                            ]
                        )
                        ->get();

                foreach (
                    $replaceable as
                    $oldProof
                ) {
                    if (
                        $oldProof->image_path
                    ) {
                        Storage::disk(
                            'public'
                        )->delete(
                            $oldProof
                                ->image_path
                        );
                    }

                    $oldProof->delete();
                }

                PaymentProof::create([
                    'booking_id' =>
                        $booking->id,

                    'customer_id' =>
                        $request->user()->id,

                    /*
                     * Proof hiện tại dùng cho
                     * phần tiền cọc còn thiếu.
                     */
                    'amount' =>
                        $summary[
                            'deposit_remaining'
                        ],

                    'approved_amount' =>
                        0,

                    'image_path' =>
                        $path,

                    'status' =>
                        'pending',

                    'customer_note' =>
                        $data['note'] ??
                        null,
                ]);
            }
        );

        $booking->refresh();

        $paymentService
            ->attachPaymentData(
                $booking
            );

        return response()->json([
            'success' => true,
            'message' =>
                'Đã tải ảnh chuyển khoản. Vui lòng chờ admin kiểm tra.',
            'data' => [
                'booking' =>
                    $booking,
            ],
        ], 201);
    }

    public function destroy(
        Request $request,
        Booking $booking,
        PaymentProof $proof,
        PaymentProofService $paymentService
    ): JsonResponse {
        $this->assertOwner(
            $request,
            $booking
        );

        if (
            (int) $proof->booking_id !==
            (int) $booking->id ||
            (int) $proof->customer_id !==
            (int) $request->user()->id
        ) {
            abort(404);
        }

        if (
            $proof->status ===
            'approved'
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Ảnh đã được admin duyệt nên không thể xoá.',
            ], 422);
        }

        if ($proof->image_path) {
            Storage::disk('public')
                ->delete(
                    $proof->image_path
                );
        }

        $proof->delete();

        $booking->refresh();

        $paymentService
            ->attachPaymentData(
                $booking
            );

        return response()->json([
            'success' => true,
            'message' =>
                'Đã xoá ảnh chuyển khoản.',
            'data' => [
                'booking' =>
                    $booking,
            ],
        ]);
    }

    private function assertOwner(
        Request $request,
        Booking $booking
    ): void {
        if (
            (int) $booking->customer_id !==
            (int) $request->user()->id
        ) {
            abort(403);
        }
    }
}
