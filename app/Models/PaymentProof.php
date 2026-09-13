<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentProof extends Model
{
    protected $fillable = [
        'booking_id',
        'customer_id',
        'amount',
        'approved_amount',
        'image_path',
        'status',
        'customer_note',
        'reviewed_by',
        'reviewed_at',
        'rejection_reason',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'approved_amount' => 'decimal:2',
        'reviewed_at' => 'datetime',
    ];

    protected $appends = [
        'image_url',
        'is_manual',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(
            Booking::class
        );
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'customer_id'
        );
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'reviewed_by'
        );
    }

    public function getIsManualAttribute(): bool
    {
        return str_starts_with(
            (string) $this->image_path,
            'manual://'
        );
    }

    public function getImageUrlAttribute(): ?string
    {
        if (
            !$this->image_path ||
            $this->is_manual
        ) {
            return null;
        }

        return url(
            "/api/v1/payment-proofs/{$this->id}/image"
        );
    }
}
