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

    public function getImageUrl()
    {
        return $this->image ? env('APP_URL') . '/storage/' . $this->image : null;
    }

    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id');
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
