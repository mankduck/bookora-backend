<?php

namespace App\Services;

use App\Models\BookingStaff;
use App\Models\ServiceVariant;
use App\Models\StaffProfile;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AvailabilityService
{
    /**
     * Khoảng cách giữa hai slot.
     *
     * Ví dụ:
     * 08:00
     * 08:30
     * 09:00
     */
    private int $slotIntervalMinutes = 30;

    public function getAvailableSlots(
        ServiceVariant $variant,
        string $date
    ): array {
        $service = $variant->service;

        if (!$service) {
            return [];
        }

        /*
        |--------------------------------------------------------------------------
        | Thời lượng của booking
        |--------------------------------------------------------------------------
        */

        $durationMinutes = (int) $variant->duration_minutes;

        if ($durationMinutes <= 0) {
            $durationMinutes = (int) $service->default_duration_minutes;
        }

        if ($durationMinutes <= 0) {
            return [];
        }

        /*
        |--------------------------------------------------------------------------
        | Carbon date
        |--------------------------------------------------------------------------
        */

        $bookingDate = Carbon::createFromFormat(
            'Y-m-d',
            $date
        );

        /*
         * Carbon:
         *
         * 0 = Chủ nhật
         * 1 = Thứ hai
         * ...
         * 6 = Thứ bảy
         *
         * Khớp với staff_schedules của chúng ta.
         */
        $dayOfWeek = $bookingDate->dayOfWeek;

        /*
        |--------------------------------------------------------------------------
        | Tìm nhân viên có thể làm dịch vụ
        |--------------------------------------------------------------------------
        */

        $staffList = StaffProfile::query()
            ->where('status', 'active')
            ->where('is_bookable', true)
            ->whereHas('user', function ($query) {
                $query->where('status', 'active');
            })
            ->whereHas('services', function ($query) use ($service) {
                $query
                    ->where(
                        'services.id',
                        $service->id
                    )
                    ->where(
                        'staff_services.status',
                        'active'
                    );
            })
            ->with([
                'schedules' => function ($query) use ($dayOfWeek) {
                    $query
                        ->where(
                            'day_of_week',
                            $dayOfWeek
                        )
                        ->where(
                            'is_working',
                            true
                        )
                        ->orderBy('start_time');
                },
            ])
            ->get();

        if ($staffList->isEmpty()) {
            return [];
        }

        /*
        |--------------------------------------------------------------------------
        | Gom tất cả slot khả dụng
        |--------------------------------------------------------------------------
        |
        | Một slot chỉ cần có ÍT NHẤT 1 nhân viên rảnh
        | thì khách hàng được phép đặt.
        |
        */

        $availableSlots = collect();

        foreach ($staffList as $staff) {
            $staffSlots = $this->getStaffAvailableSlots(
                staff: $staff,
                date: $date,
                durationMinutes: $durationMinutes
            );

            foreach ($staffSlots as $slot) {
                $availableSlots->put(
                    $slot['start_time'],
                    $slot
                );
            }
        }

        return $availableSlots
            ->sortKeys()
            ->values()
            ->all();
    }

    private function getStaffAvailableSlots(
        StaffProfile $staff,
        string $date,
        int $durationMinutes
    ): array {
        $slots = [];

        foreach ($staff->schedules as $schedule) {
            /*
            |--------------------------------------------------------------------------
            | Khung giờ làm việc
            |--------------------------------------------------------------------------
            */

            $workStart = Carbon::parse(
                "{$date} {$schedule->start_time}"
            );

            $workEnd = Carbon::parse(
                "{$date} {$schedule->end_time}"
            );

            /*
            |--------------------------------------------------------------------------
            | Tạo từng slot
            |--------------------------------------------------------------------------
            */

            $cursor = $workStart->copy();

            while (true) {
                $slotStart = $cursor->copy();

                $slotEnd = $slotStart
                    ->copy()
                    ->addMinutes($durationMinutes);

                /*
                 * Nếu booking kết thúc vượt quá giờ làm việc
                 * thì dừng.
                 */
                if ($slotEnd->gt($workEnd)) {
                    break;
                }

                /*
                 * Nếu là ngày hôm nay,
                 * không trả về slot đã qua.
                 */
                if (
                    $slotStart->isToday() &&
                    $slotStart->lte(now())
                ) {
                    $cursor->addMinutes(
                        $this->slotIntervalMinutes
                    );

                    continue;
                }

                /*
                 * Kiểm tra nhân viên nghỉ phép/nghỉ việc riêng.
                 */
                if (
                    $this->hasTimeOff(
                        $staff,
                        $slotStart,
                        $slotEnd
                    )
                ) {
                    $cursor->addMinutes(
                        $this->slotIntervalMinutes
                    );

                    continue;
                }

                /*
                 * Kiểm tra booking bị trùng.
                 */
                if (
                    $this->hasBookingConflict(
                        $staff,
                        $slotStart,
                        $slotEnd
                    )
                ) {
                    $cursor->addMinutes(
                        $this->slotIntervalMinutes
                    );

                    continue;
                }

                $slots[] = [
                    'start_time' =>
                        $slotStart->format('H:i'),

                    'end_time' =>
                        $slotEnd->format('H:i'),

                    'start_at' =>
                        $slotStart->format('Y-m-d H:i:s'),

                    'end_at' =>
                        $slotEnd->format('Y-m-d H:i:s'),
                ];

                $cursor->addMinutes(
                    $this->slotIntervalMinutes
                );
            }
        }

        return $slots;
    }

    private function hasTimeOff(
        StaffProfile $staff,
        Carbon $start,
        Carbon $end
    ): bool {
        return $staff
            ->timeOffs()
            ->where(function ($query) {
                /*
                 * Không tính những đơn nghỉ đã bị từ chối
                 * hoặc hủy.
                 *
                 * Cách này cũng chịu được trường hợp
                 * chúng ta thay đổi trạng thái time-off sau này.
                 */
                $query
                    ->whereNull('status')
                    ->orWhereNotIn(
                        'status',
                        [
                            'cancelled',
                            'rejected',
                        ]
                    );
            })
            ->where(
                'start_at',
                '<',
                $end->format('Y-m-d H:i:s')
            )
            ->where(
                'end_at',
                '>',
                $start->format('Y-m-d H:i:s')
            )
            ->exists();
    }

    private function hasBookingConflict(
        StaffProfile $staff,
        Carbon $start,
        Carbon $end
    ): bool {
        /*
        |--------------------------------------------------------------------------
        | Công thức overlap
        |--------------------------------------------------------------------------
        |
        | new_start < existing_end
        | &&
        | new_end > existing_start
        |
        */

        return BookingStaff::query()
            ->where(
                'staff_id',
                $staff->id
            )
            ->whereHas(
                'booking',
                function ($query) use ($start, $end) {
                    $query
                        ->whereIn(
                            'status',
                            [
                                'pending',
                                'confirmed',
                                'in_progress',
                            ]
                        )
                        ->where(
                            'start_at',
                            '<',
                            $end->format(
                                'Y-m-d H:i:s'
                            )
                        )
                        ->where(
                            'end_at',
                            '>',
                            $start->format(
                                'Y-m-d H:i:s'
                            )
                        );
                }
            )
            ->exists();
    }
}