<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Service extends Model
{
    protected $fillable = [
        'category_id',
        'name',
        'slug',
        'short_description',
        'description',
        'thumbnail',
        'base_price',
        'default_duration_minutes',
        'status',
        'is_featured',
        'sort_order',
    ];

    protected $casts = [
        'base_price' => 'decimal:2',
        'default_duration_minutes' => 'integer',
        'is_featured' => 'boolean',
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

    public function category(): BelongsTo
    {
        return $this->belongsTo(
            ServiceCategory::class,
            'category_id'
        );
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ServiceVariant::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function staff(): BelongsToMany
    {
        return $this->belongsToMany(
            StaffProfile::class,
            'staff_services',
            'service_id',
            'staff_id'
        )
            ->withPivot([
                'custom_duration_minutes',
                'status',
            ])
            ->withTimestamps();
    }
}