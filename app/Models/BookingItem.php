<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingItem extends Model
{
    protected $fillable = [
        'booking_id',
        'service_id',
        'service_variant_id',
        'service_name',
        'variant_name',
        'price',
        'quantity',
        'duration_minutes',
        'subtotal',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'quantity' => 'integer',
        'duration_minutes' => 'integer',
        'subtotal' => 'decimal:2',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(
            Booking::class,
            'booking_id'
        );
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(
            Service::class,
            'service_id'
        );
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(
            ServiceVariant::class,
            'service_variant_id'
        );
    }
}