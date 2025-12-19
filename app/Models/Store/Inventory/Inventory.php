<?php

namespace App\Models\Store\Inventory;

use App\Models\Store;
use App\Models\Store\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class Inventory extends Model
{
    protected $guarded = [];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function movements()
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function scopeOwnedByUser($query, User $user)
    {
        return $query->where(function ($q) use ($user) {
            $q->whereHas(
                'store',
                fn($s) =>
                $s->where('owner_id', $user->id)
            )
                ->orWhereHas(
                    'product',
                    fn($p) =>
                    $p->where('owner_id', $user->id)
                );
        });
    }
}
