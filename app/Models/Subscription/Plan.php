<?php

namespace App\Models\Subscription;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $guarded = [];

    public function limits()
    {
        return $this->hasMany(PlanLimit::class);
    }
}
