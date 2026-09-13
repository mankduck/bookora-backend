<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffReview extends Model
{
    protected $fillable = ['booking_id', 'customer_id', 'staff_id', 'rating', 'comment', 'status'];
    protected $casts = ['rating' => 'integer'];

    public function booking(): BelongsTo { return $this->belongsTo(Booking::class); }
    public function customer(): BelongsTo { return $this->belongsTo(User::class, 'customer_id'); }
    public function staff(): BelongsTo { return $this->belongsTo(StaffProfile::class, 'staff_id'); }
}
