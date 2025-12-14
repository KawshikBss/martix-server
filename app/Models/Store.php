<?php

namespace App\Models;

use App\Models\Store\StoreUser;
use Illuminate\Database\Eloquent\Model;

class Store extends Model
{
    protected $guarded = [];

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
        return $this->hasMany(StoreUser::class);
    }
}
