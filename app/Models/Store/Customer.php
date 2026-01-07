<?php

namespace App\Models\Store;

use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $guarded = [];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
