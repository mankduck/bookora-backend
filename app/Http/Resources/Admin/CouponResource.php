<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CouponResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $now = now();
        $startsAt = $this->starts_at;
        $endsAt = $this->ends_at;
        $usageCount = (int) ($this->usages_count ?? 0);

        $runtimeStatus = 'active';

        if (! $this->is_active) {
            $runtimeStatus = 'inactive';
        } elseif ($startsAt && $startsAt->isFuture()) {
            $runtimeStatus = 'scheduled';
        } elseif ($endsAt && $endsAt->isPast()) {
            $runtimeStatus = 'expired';
        } elseif ($this->usage_limit !== null && $usageCount >= (int) $this->usage_limit) {
            $runtimeStatus = 'exhausted';
        }

        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'type' => $this->type,
            'value' => (float) $this->value,
            'min_order_amount' => (float) ($this->min_order_amount ?? 0),
            'max_discount_amount' => $this->max_discount_amount !== null
                ? (float) $this->max_discount_amount
                : null,
            'usage_limit' => $this->usage_limit !== null ? (int) $this->usage_limit : null,
            'usage_limit_per_customer' => $this->usage_limit_per_customer !== null
                ? (int) $this->usage_limit_per_customer
                : null,
            'usage_count' => $usageCount,
            'starts_at' => $startsAt?->toDateTimeString(),
            'ends_at' => $endsAt?->toDateTimeString(),
            'is_active' => (bool) $this->is_active,
            'runtime_status' => $runtimeStatus,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
