<?php

namespace App\Models\Subscription;

use App\Models\Store;
use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    protected $guarded = [];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }
}
