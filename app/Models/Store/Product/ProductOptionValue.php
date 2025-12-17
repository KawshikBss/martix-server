<?php

namespace App\Models\Store\Product;

use Illuminate\Database\Eloquent\Model;

class ProductOptionValue extends Model
{
    public $timestamps = false;
    protected $guarded = [];

    public function option()
    {
        return $this->belongsTo(ProductOption::class);
    }
}
