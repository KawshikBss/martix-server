<?php

namespace App\Models\Store\Inventory;

use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class InventoryTransfer extends Model
{
    protected $guarded = [];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d',
        'updated_at' => 'datetime:Y-m-d',
    ];

    // protected $appends = ['current_stock_value', 'reference_text'];

    public function sourceStore()
    {
        return $this->belongsTo(Store::class, 'source_store_id');
    }

    public function destinationStore()
    {
        return $this->belongsTo(Store::class, 'destination_store_id');
    }

    public function inventory()
    {
        return $this->belongsTo(Inventory::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function scopeAccessibleByUser($query, User $user)
    {
        return $query->whereHas('inventory', function ($q) use ($user) {
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
        })->orWhere('performed_by_id', $user->id);
    }
}
