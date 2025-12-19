<?php

namespace App\Models\Store\Inventory;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class InventoryMovement extends Model
{
    protected $guarded = [];

    protected $casts = [
        'updated_at' => 'datetime:Y-m-d'
    ];

    protected $appends = ['current_stock_value', 'reference_text'];

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

    public function getCurrentStockValueAttribute()
    {
        return $this->quantity * $this->inventory->selling_price;
    }

    public function getReferenceTextAttribute()
    {
        switch ($this->reference_type) {
            case 'product_creation':
                return 'Product Creation';
            default:
                return null;
        }
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
