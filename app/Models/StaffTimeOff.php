<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffTimeOff extends Model
{
    protected $fillable = [
        'staff_id',
        'start_at',
        'end_at',
        'reason',
        'status',
        'created_by',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
    ];

    public function staff(): BelongsTo
    {
        return $this->belongsTo(
            StaffProfile::class,
            'staff_id'
        );
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by'
        );
    }
}