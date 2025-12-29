<?php

namespace App\Models\Store;

use App\Models\Category;
use App\Models\Store\Inventory\Inventory;
use App\Models\Store\Product\ProductOption;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $guarded = [];

    protected $appends = ['image_url', 'variation_meta', 'current_stock_quantity', 'min_selling_price', 'max_selling_price', 'stock_status'];

    protected $casts = [
        'created_at' => 'datetime:d-m-Y',
        'updated_at' => 'datetime:d-m-Y',
    ];

    public function getImageUrlAttribute(): string
    {
        return  env('APP_URL') . '/storage/' . ($this->image ? $this->image : 'products/default-product.jpg');
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function parent()
    {
        return $this->belongsTo(Product::class, 'parent_id');
    }

    public function variants()
    {
        return $this->hasMany(Product::class, 'parent_id')->with(['category']);
    }

    public function inventories()
    {
        return $this->hasMany(Inventory::class)->with('store');
    }

    public function getCurrentStockQuantityAttribute()
    {
        return $this->inventories()->sum('quantity');
    }

    public function getVariationMetaAttribute()
    {
        $variation = $this->variations ? json_decode($this->variations, true) : null;
        return $variation;
    }

    public function getMinSellingPriceAttribute()
    {
        return $this->inventories()->min('selling_price');
    }

    public function getMaxSellingPriceAttribute()
    {
        return $this->inventories()->max('selling_price');
    }

    public function getStockStatusAttribute()
    {
        if ($this->inventories()->where('quantity', '<=', 0)->exists()) {
            return 'Out Of Stock';
        }

        if ($this->inventories()->whereColumn('quantity', '<=', 'reorder_level')->exists()) {
            return 'Low Stock';
        }

        return 'In Stock';
    }
}
