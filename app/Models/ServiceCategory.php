<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceCategory extends Model
{
    protected $fillable = [
        'parent_id',
        'name',
        'slug',
        'description',
        'thumbnail',
        'sort_order',
        'status',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(
            ServiceCategory::class,
            'parent_id'
        );
    }

    public function children(): HasMany
    {
        return $this->hasMany(
            ServiceCategory::class,
            'parent_id'
        );
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class, 'category_id');
    }
}