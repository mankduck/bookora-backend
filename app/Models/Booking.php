<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Booking extends Model
{
    protected $fillable = [
        'booking_code',
        'customer_id',

        'start_at',
        'end_at',

        'customer_name',
        'customer_phone',
        'customer_email',

        'subtotal',
        'discount_amount',
        'total_amount',
        'deposit_amount',

        'coupon_id',

        'customer_note',
        'internal_note',

        'status',
        'payment_status',
        'source',

        'confirmed_by',
        'confirmed_at',

        'cancelled_by',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',

        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'deposit_amount' => 'decimal:2',

        'confirmed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'customer_id'
        );
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(
            Coupon::class,
            'coupon_id'
        );
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'confirmed_by'
        );
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'cancelled_by'
        );
    }

    public function items(): HasMany
    {
        return $this->hasMany(
            BookingItem::class,
            'booking_id'
        );
    }

    public function staffAssignments(): HasMany
    {
        return $this->hasMany(
            BookingStaff::class,
            'booking_id'
        );
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(
            StaffReview::class,
            'booking_id'
        );
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(
            BookingStatusHistory::class,
            'booking_id'
        );
    }
}