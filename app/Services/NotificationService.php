<?php

namespace App\Services;

use App\Events\AppNotificationCreated;
use App\Models\AppNotification;
use App\Models\Booking;
use App\Models\User;
use Throwable;

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

        $notification = AppNotification::query()->create([
            'user_id' => $userId,
            'type' => $type,
            'level' => $level,
            'title' => $title,
            'message' => $message,
            'booking_id' => $booking?->id,
            'url' => $url,
            'data' => $data ?: null,
        ]);

        // DB là nguồn dữ liệu chính. Nếu Reverb tạm ngắt, notification vẫn được lưu.
        try {
            AppNotificationCreated::dispatch($notification);
        } catch (Throwable $exception) {
            report($exception);
        }

        return $notification;
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

    public function sendToCustomers(
        string $type,
        string $title,
        ?string $message = null,
        string $level = 'info',
        ?string $url = '/',
        array $data = []
    ): void {
        User::query()
            ->where('status', 'active')
            ->whereHas('roles', fn ($query) => $query->where('code', 'customer'))
            ->pluck('id')
            ->each(fn ($userId) => $this->sendToUser((int) $userId, $type, $title, $message, $level, null, $url, $data));
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
