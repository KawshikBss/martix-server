<?php

namespace App\Models\Store\Sale;

use App\Models\Store\Inventory\Inventory;
use App\Models\Store\Product;
use Illuminate\Database\Eloquent\Model;

class SaleItem extends Model
{
    protected $guarded = [];

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function product()
    {
        return $this->hasOne(Product::class);
    }

    public function inventory()
    {
        return $this->hasOne(Inventory::class);
    }
}
