<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\Booking;
use App\Models\User;

class NotificationService
{
    public function sendToUser(
        ?int $userId,
        string $type,
        string $title,
        ?string $message = null,
        string $level = 'info',
        ?Booking $booking = null,
        ?string $url = null,
        array $data = []
    ): ?AppNotification {
        if (!$userId) {
            return null;
        }

        return AppNotification::query()->create([
            'user_id' => $userId,
            'type' => $type,
            'level' => $level,
            'title' => $title,
            'message' => $message,
            'booking_id' => $booking?->id,
            'url' => $url,
            'data' => $data ?: null,
        ]);
    }

    public function sendToCustomer(
        Booking $booking,
        string $type,
        string $title,
        ?string $message = null,
        string $level = 'info',
        array $data = []
    ): ?AppNotification {
        return $this->sendToUser(
            $booking->customer_id,
            $type,
            $title,
            $message,
            $level,
            $booking,
            '/account/bookings/' . $booking->id,
            $data
        );
    }

    public function sendToAdmins(
        string $type,
        string $title,
        ?string $message = null,
        string $level = 'info',
        ?Booking $booking = null,
        array $data = []
    ): void {
        User::query()
            ->where('status', 'active')
            ->whereHas('roles', fn ($query) => $query->where('code', 'admin'))
            ->pluck('id')
            ->each(function ($userId) use ($type, $title, $message, $level, $booking, $data) {
                $this->sendToUser(
                    (int) $userId,
                    $type,
                    $title,
                    $message,
                    $level,
                    $booking,
                    $booking ? '/admin/bookings?booking=' . $booking->id : null,
                    $data
                );
            });
    }
}
