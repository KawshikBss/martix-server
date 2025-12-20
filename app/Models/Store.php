<?php

namespace App\Models;

use App\Models\Store\Inventory\Inventory;
use App\Models\Store\StoreUser;
use Illuminate\Database\Eloquent\Model;

class Store extends Model
{
    protected $guarded = [];

    protected $appends = ['image_url', 'current_inventory_value'];

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

    public function inventories()
    {
        return $this->hasMany(Inventory::class);
    }

    public function getCurrentInventoryCountAttribute()
    {
        return $this->inventories()->sum('quantity');
    }

    public function getCurrentInventoryValueAttribute()
    {
        return $this->inventories->sum(function ($inventory) {
            return $inventory->quantity * $inventory->selling_price;
        });
    }

    public function getLowStockItemsCountAttribute()
    {
        return $this->inventories()->whereColumn('quantity', '<=', 'reorder_level')->count();
    }
}
