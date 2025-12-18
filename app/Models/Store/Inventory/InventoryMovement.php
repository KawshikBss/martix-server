<?php

namespace App\Models\Store\Inventory;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class InventoryMovement extends Model
{
    protected $guarded = [];

    public function inventory()
    {
        return $this->belongsTo(Inventory::class);
    }

    public function performedBy()
    {
        return $this->belongsTo(User::class, 'performed_by_id');
    }

    public function reference()
    {
        return $this->morphTo();
    }
}
