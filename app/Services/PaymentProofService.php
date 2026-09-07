<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\PaymentProof;
use Illuminate\Support\Facades\DB;

class PaymentProofService
{
    public function summary(
        Booking $booking
    ): array {
        $approvedAmount = (float) PaymentProof::query()
            ->where(
                'booking_id',
                $booking->id
            )
            ->where(
                'status',
                'approved'
            )
            ->sum('approved_amount');

        $depositAmount = max(
            (float) $booking->deposit_amount,
            0
        );

        $totalAmount = max(
            (float) $booking->total_amount,
            0
        );

        $depositRemaining = max(
            $depositAmount - $approvedAmount,
            0
        );

        $remainingAmount = max(
            $totalAmount - $approvedAmount,
            0
        );

        $pendingProof = PaymentProof::query()
            ->where(
                'booking_id',
                $booking->id
            )
            ->where(
                'status',
                'pending'
            )
            ->latest('id')
            ->first();

        $latestProof = PaymentProof::query()
            ->where(
                'booking_id',
                $booking->id
            )
            ->latest('id')
            ->first();

        if ($depositAmount <= 0) {
            $depositStatus = 'not_required';
        } elseif (
            $approvedAmount >=
            $depositAmount
        ) {
            $depositStatus = 'paid';
        } elseif ($pendingProof) {
            $depositStatus =
                'pending_review';
        } elseif ($approvedAmount > 0) {
            $depositStatus =
                'partially_paid';
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
                (bool) $pendingProof,

            'latest_proof_status' =>
                $latestProof?->status,
        ];
    }

    public function syncBookingPaymentStatus(
        Booking $booking
    ): array {
        $summary =
            $this->summary($booking);

        $approvedAmount =
            $summary['approved_amount'];

        $totalAmount =
            $summary['total_amount'];

        if (
            $totalAmount > 0 &&
            $approvedAmount >= $totalAmount
        ) {
            $status = 'paid';
        } elseif ($approvedAmount > 0) {
            $status = 'partially_paid';
        } else {
            $status = 'unpaid';
        }

        if (
            $booking->payment_status !==
            'refunded' &&
            $booking->payment_status !==
            $status
        ) {
            $booking->update([
                'payment_status' =>
                    $status,
            ]);

            $booking->refresh();
        }

        return $this->summary(
            $booking
        );
    }

    public function attachPaymentData(
        Booking $booking,
        bool $includeProofs = true
    ): Booking {
        $summary =
            $this->syncBookingPaymentStatus(
                $booking
            );

        $booking->setAttribute(
            'payment_summary',
            $summary
        );

        if ($includeProofs) {
            $proofs = PaymentProof::query()
                ->with([
                    'reviewer:id,name',
                ])
                ->where(
                    'booking_id',
                    $booking->id
                )
                ->latest('id')
                ->get();

            $booking->setAttribute(
                'payment_proofs',
                $proofs
            );
        }

        return $booking;
    }

    public function bankTransferData(
        Booking $booking
    ): array {
        $summary =
            $this->summary($booking);

        $amount = (int) round(
            $summary[
                'deposit_remaining'
            ]
        );

        $bankCode =
            (string) config(
                'payment.bank.code',
                ''
            );

        $accountNumber =
            (string) config(
                'payment.bank.account_number',
                ''
            );

        $accountName =
            (string) config(
                'payment.bank.account_name',
                ''
            );

        $bankName =
            (string) config(
                'payment.bank.name',
                ''
            );

        $prefix =
            (string) config(
                'payment.bank.transfer_prefix',
                'BOOKORA'
            );

        $content = trim(
            $prefix . ' ' .
            $booking->booking_code
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
                rawurlencode(
                    $accountNumber
                ) .
                '-compact2.png' .
                '?amount=' .
                $amount .
                '&addInfo=' .
                rawurlencode($content) .
                '&accountName=' .
                rawurlencode(
                    $accountName
                );
        }

        return [
            'bank_name' =>
                $bankName,

            'bank_code' =>
                $bankCode,

            'account_number' =>
                $accountNumber,

            'account_name' =>
                $accountName,

            'transfer_content' =>
                $content,

            'amount' =>
                $amount,

            'qr_url' =>
                $qrUrl,
        ];
    }
}
