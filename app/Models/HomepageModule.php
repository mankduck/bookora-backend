<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HomepageModule extends Model
{
    protected $fillable = [
        'type', 'name', 'slug', 'title', 'content', 'custom_css', 'settings', 'sort_order',
        'is_enabled', 'is_locked', 'show_in_nav', 'nav_label',
    ];

    protected $casts = [
        'settings' => 'array',
        'sort_order' => 'integer',
        'is_enabled' => 'boolean',
        'is_locked' => 'boolean',
        'show_in_nav' => 'boolean',
    ];
}
