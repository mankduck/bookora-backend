<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\PaymentProof;
use App\Services\PaymentProofService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentProofController extends Controller
{
    public function approve(
        Request $request,
        PaymentProof $proof,
        PaymentProofService $paymentService
    ): JsonResponse {
        if ($proof->status === 'approved') {
            return response()->json([
                'success' => false,
                'message' =>
                    'Ảnh chuyển khoản này đã được duyệt.',
            ], 422);
        }

        $booking =
            $proof->booking()
                ->firstOrFail();

        DB::transaction(
            function () use ($proof, $request) {
                $proof->update([
                    'status' => 'approved',
                    'approved_amount' =>
                        $proof->amount,
                    'reviewed_by' =>
                        $request->user()->id,
                    'reviewed_at' => now(),
                    'rejection_reason' => null,
                ]);
            }
        );

        $booking->refresh();
        $paymentService->attachPaymentData(
            $booking
        );

        return response()->json([
            'success' => true,
            'message' =>
                'Đã xác nhận giao dịch.',
            'data' => [
                'booking' => $booking,
            ],
        ]);
    }

    public function reject(
        Request $request,
        PaymentProof $proof,
        PaymentProofService $paymentService
    ): JsonResponse {
        $data = $request->validate([
            'reason' => [
                'required',
                'string',
                'max:1000',
            ],
        ]);

        if ($proof->status === 'approved') {
            return response()->json([
                'success' => false,
                'message' =>
                    'Giao dịch đã được duyệt nên không thể từ chối.',
            ], 422);
        }

        $booking =
            $proof->booking()
                ->firstOrFail();

        $proof->update([
            'status' => 'rejected',
            'approved_amount' => 0,
            'reviewed_by' =>
                $request->user()->id,
            'reviewed_at' => now(),
            'rejection_reason' =>
                $data['reason'],
        ]);

        $booking->refresh();
        $paymentService->attachPaymentData(
            $booking
        );

        return response()->json([
            'success' => true,
            'message' =>
                'Đã từ chối ảnh chuyển khoản.',
            'data' => [
                'booking' => $booking,
            ],
        ]);
    }

    public function markDepositPaid(
        Request $request,
        Booking $booking,
        PaymentProofService $paymentService
    ): JsonResponse {
        $summary =
            $paymentService->summary(
                $booking
            );

        if (
            $summary['deposit_amount'] <= 0
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Booking này không yêu cầu tiền cọc.',
            ], 422);
        }

        if (
            $summary['deposit_remaining'] <= 0
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Booking này đã được xác nhận đủ tiền cọc.',
            ], 422);
        }

        if (!$booking->customer_id) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Booking cũ này không có customer_id nên chưa thể ghi nhận cọc theo cơ chế mới.',
            ], 422);
        }

        PaymentProof::create([
            'booking_id' =>
                $booking->id,

            'customer_id' =>
                $booking->customer_id,

            /*
             * Admin bấm "Đã cọc"
             * => tự ghi nhận đúng phần cọc còn thiếu.
             */
            'amount' =>
                $summary[
                    'deposit_remaining'
                ],

            'approved_amount' =>
                $summary[
                    'deposit_remaining'
                ],

            'image_path' =>
                'manual://admin-deposit',

            'status' =>
                'approved',

            'customer_note' =>
                'Admin xác nhận khách đã hoàn tất tiền cọc.',

            'reviewed_by' =>
                $request->user()->id,

            'reviewed_at' =>
                now(),

            'rejection_reason' =>
                null,
        ]);

        $booking->refresh();

        $paymentService
            ->attachPaymentData(
                $booking
            );

        return response()->json([
            'success' => true,

            'message' =>
                'Đã đánh dấu booking là đã cọc.',

            'data' => [
                'booking' =>
                    $booking,
            ],
        ]);
    }

    public function manualPayment(
        Request $request,
        Booking $booking,
        PaymentProofService $paymentService
    ): JsonResponse {
        $summary =
            $paymentService->summary(
                $booking
            );

        if (
            $summary['remaining_amount'] <= 0
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Booking đã được thanh toán đủ.',
            ], 422);
        }

        $data = $request->validate([
            'amount' => [
                'required',
                'numeric',
                'min:1',
                'max:' .
                $summary['remaining_amount'],
            ],
            'method' => [
                'required',
                'in:cash,bank_transfer,other',
            ],
            'note' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ]);

        $methodLabel = match (
        $data['method']
        ) {
            'cash' => 'Tiền mặt',
            'bank_transfer' =>
            'Chuyển khoản',
            default => 'Khác',
        };

        PaymentProof::create([
            'booking_id' => $booking->id,
            'customer_id' =>
                $booking->customer_id ??
                $request->user()->id,
            'amount' => $data['amount'],
            'approved_amount' =>
                $data['amount'],
            'image_path' =>
                'manual://' .
                $data['method'],
            'status' => 'approved',
            'customer_note' => trim(
                'Admin ghi nhận ' .
                $methodLabel .
                ($data['note'] ?? ''
                    ? ': ' . $data['note']
                    : '')
            ),
            'reviewed_by' =>
                $request->user()->id,
            'reviewed_at' => now(),
            'rejection_reason' => null,
        ]);

        $booking->refresh();
        $paymentService->attachPaymentData(
            $booking
        );

        return response()->json([
            'success' => true,
            'message' =>
                'Đã ghi nhận khoản thanh toán thủ công.',
            'data' => [
                'booking' => $booking,
            ],
        ], 201);
    }
}
