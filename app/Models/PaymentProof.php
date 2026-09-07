<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

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
        'manual_method',
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

    public function getImageUrlAttribute(): ?string
    {
        if (
            !$this->image_path ||
            str_starts_with(
                $this->image_path,
                'manual://'
            )
        ) {
            return null;
        }

        return url(
            Storage::url(
                $this->image_path
            )
        );
    }

    public function getIsManualAttribute(): bool
    {
        return str_starts_with(
            (string) $this->image_path,
            'manual://'
        );
    }

    public function getManualMethodAttribute(): ?string
    {
        if (!$this->is_manual) {
            return null;
        }

        return str_replace(
            'manual://',
            '',
            (string) $this->image_path
        );
    }
}
