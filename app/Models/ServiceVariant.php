<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceVariant extends Model
{
    protected $fillable = [
        'service_id',
        'name',
        'code',
        'description',
        'price',
        'sale_price',
        'duration_minutes',
        'deposit_type',
        'deposit_value',
        'status',
        'sort_order',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'deposit_value' => 'decimal:2',
        'duration_minutes' => 'integer',
        'sort_order' => 'integer',
    ];

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}