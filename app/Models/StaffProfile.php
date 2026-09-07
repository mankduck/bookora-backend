<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StaffProfile extends Model
{
    protected $fillable = [
        'user_id',
        'employee_code',
        'position',
        'bio',
        'experience_years',
        'is_bookable',
        'status',
        'sort_order',
    ];

    protected $casts = [
        'experience_years' => 'integer',
        'is_bookable' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(
            Service::class,
            'staff_services',
            'staff_id',
            'service_id'
        )
            ->withPivot([
                'custom_duration_minutes',
                'status',
            ])
            ->withTimestamps();
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(
            StaffSchedule::class,
            'staff_id'
        )->orderBy('day_of_week');
    }

    public function timeOffs(): HasMany
    {
        return $this->hasMany(
            StaffTimeOff::class,
            'staff_id'
        );
    }

    public function bookingAssignments(): HasMany
    {
        return $this->hasMany(
            BookingStaff::class,
            'staff_id'
        );
    }
}