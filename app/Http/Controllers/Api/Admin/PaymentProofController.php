<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\PaymentProof;
use App\Services\PaymentProofService;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentProofController extends Controller
{
    public function __construct(
        private readonly PaymentProofService $paymentProofService,
        private readonly NotificationService $notifications
    ) {
    }

    /**
     * Admin xác nhận khách đã hoàn tất tiền cọc.
     */
    public function markDepositPaid(
        Request $request,
        Booking $booking
    ): JsonResponse {
        $summary = $this->paymentProofService->summary(
            $booking
        );

        $depositAmount = (float) $summary['deposit_amount'];
        $depositRemaining = (float) $summary['deposit_remaining'];

        if ($depositAmount <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Booking này không yêu cầu tiền cọc.',
            ], 422);
        }

        if ($depositRemaining <= 0) {
            $booking->load([
                'items',
                'customer:id,name,email,phone',
                'coupon:id,code,name,type,value',
                'staffAssignments.staff.user:id,name,email,phone',
            ]);

            $this->paymentProofService->attachPaymentData(
                $booking
            );

            return response()->json([
                'success' => true,
                'message' => 'Booking này đã hoàn tất tiền cọc.',
                'data' => [
                    'booking' => $booking,
                ],
            ]);
        }

        DB::transaction(
            function () use (
                $request,
                $booking,
                $depositRemaining
            ) {
                PaymentProof::query()->create([
                    'booking_id' => $booking->id,
                    'customer_id' => $booking->customer_id,
                    'amount' => $depositRemaining,
                    'image_path' => 'manual://admin-deposit',
                    'status' => 'approved',
                    'customer_note' => 'Admin xác nhận tiền cọc thủ công.',
                    'reviewed_by' => $request->user()?->id,
                    'reviewed_at' => now(),
                ]);

                $this->paymentProofService
                    ->syncBookingPaymentStatus(
                        $booking
                    );
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

        $this->paymentProofService->attachPaymentData(
            $booking
        );

        $this->notifications->sendToCustomer(
            $booking,
            'deposit_approved',
            'Tiền cọc đã được xác nhận',
            'Admin đã xác nhận tiền cọc cho ' . $booking->booking_code . '.',
            'success'
        );

        return response()->json([
            'success' => true,
            'message' => 'Đã xác nhận khách hoàn tất tiền cọc.',
            'data' => [
                'booking' => $booking,
            ],
        ]);
    }

    /**
     * Admin xác nhận khách đã thanh toán TOÀN BỘ
     * số tiền còn lại.
     *
     * Chỉ thực hiện khi booking đang in_progress.
     */
    public function markPaid(
        Request $request,
        Booking $booking
    ): JsonResponse {
        if ($booking->status !== 'in_progress') {
            return response()->json([
                'success' => false,
                'message' =>
                    'Chỉ có thể xác nhận thanh toán đầy đủ khi booking đang thực hiện.',
            ], 422);
        }

        $summary = $this->paymentProofService->summary(
            $booking
        );

        $remainingAmount = (float) $summary['remaining_amount'];

        if ($remainingAmount <= 0) {
            $this->paymentProofService
                ->syncBookingPaymentStatus(
                    $booking
                );

            $booking
                ->refresh()
                ->load([
                    'items',
                    'customer:id,name,email,phone',
                    'coupon:id,code,name,type,value',
                    'staffAssignments.staff.user:id,name,email,phone',
                ]);

            $this->paymentProofService->attachPaymentData(
                $booking
            );

            return response()->json([
                'success' => true,
                'message' => 'Booking này đã được thanh toán đầy đủ.',
                'data' => [
                    'booking' => $booking,
                ],
            ]);
        }

        DB::transaction(
            function () use (
                $request,
                $booking,
                $remainingAmount
            ) {
                PaymentProof::query()->create([
                    'booking_id' => $booking->id,
                    'customer_id' => $booking->customer_id,
                    'amount' => $remainingAmount,
                    'image_path' => 'manual://admin-full-payment',
                    'status' => 'approved',
                    'customer_note' =>
                        'Admin xác nhận thanh toán toàn bộ số tiền còn lại.',
                    'reviewed_by' => $request->user()?->id,
                    'reviewed_at' => now(),
                ]);

                $this->paymentProofService
                    ->syncBookingPaymentStatus(
                        $booking
                    );
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

        $this->paymentProofService->attachPaymentData(
            $booking
        );

        $this->notifications->sendToCustomer(
            $booking,
            'payment_completed',
            'Thanh toán đã được xác nhận',
            'Booking ' . $booking->booking_code . ' đã được xác nhận thanh toán đầy đủ.',
            'success'
        );

        return response()->json([
            'success' => true,
            'message' => 'Đã xác nhận khách thanh toán đầy đủ.',
            'data' => [
                'booking' => $booking,
            ],
        ]);
    }

    public function approve(
        Request $request,
        PaymentProof $proof
    ): JsonResponse {
        $booking = $this->paymentProofService->approve(
            $proof,
            $request->user()
        );

        $this->notifications->sendToCustomer(
            $booking,
            'payment_proof_approved',
            'Ảnh chuyển khoản đã được xác nhận',
            'Giao dịch của booking ' . $booking->booking_code . ' đã được admin xác nhận.',
            'success'
        );

        return response()->json([
            'success' => true,
            'message' => 'Đã xác nhận giao dịch.',
            'data' => [
                'booking' => $booking,
            ],
        ]);
    }

    public function reject(
        Request $request,
        PaymentProof $proof
    ): JsonResponse {
        $data = $request->validate([
            'reason' => [
                'required',
                'string',
                'max:1000',
            ],
        ]);

        $booking = $this->paymentProofService->reject(
            $proof,
            $request->user(),
            $data['reason']
        );

        $this->notifications->sendToCustomer(
            $booking,
            'payment_proof_rejected',
            'Ảnh chuyển khoản chưa được chấp nhận',
            $data['reason'],
            'error'
        );

        return response()->json([
            'success' => true,
            'message' => 'Đã từ chối giao dịch.',
            'data' => [
                'booking' => $booking,
            ],
        ]);
    }
}