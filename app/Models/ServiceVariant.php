<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ServiceVariant extends Model
{
    protected $fillable = [
        'service_id',
        'name',
        'code',
        'description',
        'thumbnail',
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

    public function getThumbnailAttribute(?string $value): ?string
    {
        return $this->resolvePublicImageUrl($value);
    }

    private function resolvePublicImageUrl(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        if (
            str_starts_with($value, 'http://')
            || str_starts_with($value, 'https://')
            || str_starts_with($value, 'data:')
            || str_starts_with($value, 'blob:')
        ) {
            return $value;
        }

        $path = ltrim($value, '/');

        if (str_starts_with($path, 'storage/')) {
            return url('/' . $path);
        }

        return url(Storage::url($path));
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}