<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffSchedule extends Model
{
    protected $fillable = [
        'staff_id',
        'day_of_week',
        'start_time',
        'end_time',
        'is_working',
    ];

    protected $casts = [
        'day_of_week' => 'integer',
        'is_working' => 'boolean',
    ];

    public function staff(): BelongsTo
    {
        return $this->belongsTo(
            StaffProfile::class,
            'staff_id'
        );
    }
}