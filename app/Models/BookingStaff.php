<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingStaff extends Model
{
    protected $table = 'booking_staff';

    protected $fillable = [
        'booking_id',
        'staff_id',
        'role',
        'is_primary',
        'assigned_by',
        'assigned_at',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'assigned_at' => 'datetime',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(
            Booking::class,
            'booking_id'
        );
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(
            StaffProfile::class,
            'staff_id'
        );
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'assigned_by'
        );
    }
}