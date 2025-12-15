<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $guarded = [];

    protected $casts = [
        'visible_stores' => 'array',
    ];

    protected $appends = ['visible_to_stores', 'image_url'];

    public function getImageUrlAttribute(): string
    {
        return  env('APP_URL') . '/storage/' . ($this->image ? $this->image : 'profiles/default-user.png');
    }

    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id')->with('children');
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function getVisibleToStoresAttribute()
    {
        $raw = $this->visible_stores;

        if (is_null($raw) || $raw === '') {
            return [];
        }

        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $stores = $decoded;
            } else {
                preg_match_all('/\d+/', $raw, $matches);
                $stores = $matches[0] ?? [];
            }
        } else {
            $stores = (array) $raw;
        }

        // normalize to ints and remove empties
        $stores = array_values(array_filter(array_map(fn($v) => is_numeric($v) ? (int) $v : null, $stores)));

        if (empty($stores)) {
            return [];
        }

        return Store::whereIn('id', $stores)->get();
    }
}
