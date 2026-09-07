<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coupon extends Model
{
    protected $fillable = [
        'code',
        'name',
        'type',
        'value',
        'min_order_amount',
        'max_discount_amount',
        'usage_limit',
        'usage_per_customer',
        'start_at',
        'end_at',
        'status',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'min_order_amount' => 'decimal:2',
        'max_discount_amount' => 'decimal:2',
        'usage_limit' => 'integer',
        'usage_per_customer' => 'integer',
        'start_at' => 'datetime',
        'end_at' => 'datetime',
    ];

    public function usages(): HasMany
    {
        return $this->hasMany(
            CouponUsage::class,
            'coupon_id'
        );
    }
}