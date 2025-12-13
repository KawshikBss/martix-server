<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $guarded = [];

    protected $casts = [
        'visible_stores' => 'array',
    ];

    // protected $appends = ['visible_to_stores'];
    protected $appends = ['image_url'];

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

    // public function getVisibleToStoresAttribute()
    // {
    //     $stores = $this->visible_stores;
    //     if (empty($stores)) {
    //         return [];
    //     }
    //     return Store::whereIn('id', $stores)->get();
    // }
}
