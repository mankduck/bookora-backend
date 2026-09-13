<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Post extends Model
{
    protected $fillable = [
        'author_id', 'title', 'slug', 'excerpt', 'content', 'featured_image',
        'status', 'published_at', 'meta_title', 'meta_description',
    ];

    protected $casts = ['published_at' => 'datetime'];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
