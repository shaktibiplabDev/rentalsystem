<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class LegalPage extends Model
{
    protected $fillable = [
        'slug', 'title', 'icon', 'content', 'excerpt',
        'meta_title', 'meta_description', 'order', 'is_active',
        'show_in_footer', 'published_at', 'version',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'show_in_footer' => 'boolean',
        'published_at' => 'datetime',
    ];

    public static function getFooterPages(): array
    {
        return Cache::remember('legal_footer_pages', 86400, function () {
            return self::where('is_active', true)
                ->where('show_in_footer', true)
                ->orderBy('order')
                ->get(['slug', 'title'])   // only the needed columns
                ->toArray();
        });
    }

    public static function getBySlug($slug): ?array
    {
        return Cache::remember("legal_page_{$slug}", 86400, function () use ($slug) {
            return self::where('slug', $slug)
                ->where('is_active', true)
                ->first()
                ?->toArray();   // returns array or null
        });
    }

    // Clear cache on save/update
    protected static function booted()
    {
        static::saved(function () {
            Cache::forget('legal_footer_pages');
        });
    }
}
