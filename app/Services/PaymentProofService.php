<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\PaymentProof;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PaymentProofService
{
    /**
     * Tạo summary thanh toán/cọc của booking.
     *
     * - Tính theo booking_id
     * - Không phụ thuộc customer_id
     * - Bao gồm cả proof khách upload
     * - Bao gồm cả proof admin xác nhận thủ công
     */
    public function summary(
        Booking $booking
    ): array {
        $totalAmount = max(
            (float) $booking->total_amount,
            0
        );

        $depositAmount = max(
            (float) $booking->deposit_amount,
            0
        );

        /*
        |--------------------------------------------------------------------------
        | Tổng tiền đã được xác nhận
        |--------------------------------------------------------------------------
        */

        $approvedAmount = (float) PaymentProof::query()
            ->where(
                'booking_id',
                $booking->id
            )
            ->where(
                'status',
                'approved'
            )
            ->sum('amount');

        /*
        |--------------------------------------------------------------------------
        | Cọc còn thiếu
        |--------------------------------------------------------------------------
        */

        $depositRemaining = max(
            $depositAmount - $approvedAmount,
            0
        );

        /*
        |--------------------------------------------------------------------------
        | Tổng tiền còn phải thanh toán
        |--------------------------------------------------------------------------
        */

        $remainingAmount = max(
            $totalAmount - $approvedAmount,
            0
        );

        /*
        |--------------------------------------------------------------------------
        | Có proof nào đang chờ duyệt không
        |--------------------------------------------------------------------------
        */

        $hasPendingProof = PaymentProof::query()
            ->where(
                'booking_id',
                $booking->id
            )
            ->where(
                'status',
                'pending'
            )
            ->exists();

        /*
        |--------------------------------------------------------------------------
        | Proof gần nhất
        |--------------------------------------------------------------------------
        */

        $latestProof = PaymentProof::query()
            ->where(
                'booking_id',
                $booking->id
            )
            ->latest('id')
            ->first();

        /*
        |--------------------------------------------------------------------------
        | Trạng thái tiền cọc
        |--------------------------------------------------------------------------
        */

        if ($depositAmount <= 0) {
            $depositStatus = 'not_required';
        } elseif ($depositRemaining <= 0) {
            $depositStatus = 'paid';
        } elseif ($hasPendingProof) {
            $depositStatus = 'pending_review';
        } elseif ($approvedAmount > 0) {
            $depositStatus = 'partially_paid';
        } else {
            $depositStatus = 'unpaid';
        }

        return [
            'total_amount' =>
                $totalAmount,

            'deposit_amount' =>
                $depositAmount,

            'approved_amount' =>
                $approvedAmount,

            'deposit_remaining' =>
                $depositRemaining,

            'remaining_amount' =>
                $remainingAmount,

            'deposit_status' =>
                $depositStatus,

            'has_pending_proof' =>
                $hasPendingProof,

            'latest_proof_status' =>
                $latestProof?->status,
        ];
    }

    /**
     * Gắn dữ liệu thanh toán vào Booking để trả API.
     *
     * BookingController hiện tại gọi method này
     * cho cả danh sách và booking detail.
     */
    public function attachPaymentData(
        Booking $booking,
        bool $includeProofs = true
    ): Booking {
        /*
        |--------------------------------------------------------------------------
        | Payment summary
        |--------------------------------------------------------------------------
        */

        $booking->setAttribute(
            'payment_summary',
            $this->summary(
                $booking
            )
        );

        /*
        |--------------------------------------------------------------------------
        | Payment proofs
        |--------------------------------------------------------------------------
        |
        | Danh sách booking không cần proof chi tiết,
        | nên BookingController truyền false.
        |
        | Detail booking cần proof để hiện ảnh/
        | giao dịch thủ công.
        */

        if ($includeProofs) {
            $proofs = PaymentProof::query()
                ->where(
                    'booking_id',
                    $booking->id
                )
                ->orderByDesc('id')
                ->get();

            $booking->setAttribute(
                'payment_proofs',
                $proofs
            );
        }

        return $booking;
    }

    /**
     * Đồng bộ payment_status trong bảng bookings.
     *
     * Lưu ý:
     * "Đã cọc" KHÔNG đồng nghĩa "Đã thanh toán toàn bộ".
     */

    public function bankTransferData(Booking $booking): array
    {
        $summary = $this->summary($booking);

        $amount = (int) round(
            $summary['deposit_remaining']
        );

        $bankCode = (string) config(
            'payment.bank.code',
            ''
        );

        $accountNumber = (string) config(
            'payment.bank.account_number',
            ''
        );

        $accountName = (string) config(
            'payment.bank.account_name',
            ''
        );

        $bankName = (string) config(
            'payment.bank.name',
            ''
        );

        $prefix = (string) config(
            'payment.bank.transfer_prefix',
            'BOOKORA'
        );

        $content = trim(
            $prefix . ' ' . $booking->booking_code
        );

        $qrUrl = null;

        if (
            $amount > 0 &&
            $bankCode !== '' &&
            $accountNumber !== ''
        ) {
            $qrUrl =
                'https://img.vietqr.io/image/' .
                rawurlencode($bankCode) .
                '-' .
                rawurlencode($accountNumber) .
                '-compact2.png' .
                '?amount=' .
                $amount .
                '&addInfo=' .
                rawurlencode($content) .
                '&accountName=' .
                rawurlencode($accountName);
        }

        return [
            'bank_name' => $bankName,
            'bank_code' => $bankCode,
            'account_number' => $accountNumber,
            'account_name' => $accountName,
            'transfer_content' => $content,
            'amount' => $amount,
            'qr_url' => $qrUrl,
        ];
    }


    public function syncBookingPaymentStatus(
        Booking $booking
    ): Booking {
        $summary = $this->summary(
            $booking
        );

        $approvedAmount = (float) 
            $summary['approved_amount'];

        $totalAmount = (float) 
            $summary['total_amount'];

        if (
            $totalAmount > 0 &&
            $approvedAmount >= $totalAmount
        ) {
            $booking->payment_status =
                'paid';
        } elseif ($approvedAmount > 0) {
            $booking->payment_status =
                'partially_paid';
        } else {
            $booking->payment_status =
                'unpaid';
        }

        $booking->save();

        return $booking->fresh();
    }

    /**
     * Admin duyệt proof khách upload.
     */
    public function approve(
        PaymentProof $proof,
        ?User $admin
    ): Booking {
        return DB::transaction(
            function () use ($proof, $admin) {
                $proof->update([
                    'status' =>
                        'approved',

                    'reviewed_by' =>
                        $admin?->id,

                    'reviewed_at' =>
                        now(),

                    'rejection_reason' =>
                        null,
                ]);

                $booking = $this
                    ->syncBookingPaymentStatus(
                        $proof->booking
                    );

                $booking->load([
                    'items',
                    'customer:id,name,email,phone',
                    'coupon:id,code,name,type,value',
                    'staffAssignments.staff.user:id,name,email,phone',
                ]);

                return $this
                    ->attachPaymentData(
                        $booking
                    );
            }
        );
    }

    /**
     * Admin từ chối proof.
     */
    public function reject(
        PaymentProof $proof,
        ?User $admin,
        string $reason
    ): Booking {
        return DB::transaction(
            function () use ($proof, $admin, $reason) {
                $proof->update([
                    'status' =>
                        'rejected',

                    'reviewed_by' =>
                        $admin?->id,

                    'reviewed_at' =>
                        now(),

                    'rejection_reason' =>
                        $reason,
                ]);

                $booking = $this
                    ->syncBookingPaymentStatus(
                        $proof->booking
                    );

                $booking->load([
                    'items',
                    'customer:id,name,email,phone',
                    'coupon:id,code,name,type,value',
                    'staffAssignments.staff.user:id,name,email,phone',
                ]);

                return $this
                    ->attachPaymentData(
                        $booking
                    );
            }
        );
    }
}