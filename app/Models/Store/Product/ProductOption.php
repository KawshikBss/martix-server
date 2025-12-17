<?php

namespace App\Models\Store\Product;

use App\Models\Store\Product;
use Illuminate\Database\Eloquent\Model;

class ProductOption extends Model
{
    public $timestamps = false;
    protected $guarded = [];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function values()
    {
        return $this->hasMany(ProductOptionValue::class);
    }
}
