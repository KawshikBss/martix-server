<?php

namespace App\Models;

use App\Models\Store\StoreUser;
use Illuminate\Database\Eloquent\Model;

class Store extends Model
{
    protected $guarded = [];
    
    protected $appends = ['image_url'];

    public function getImageUrlAttribute(): string
    {
        return  env('APP_URL') . '/storage/' . ($this->image ? $this->image : 'profiles/default-user.png');
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function staff()
    {
        return $this->hasMany(StoreUser::class)->with(['role', 'user']);
    }
}
