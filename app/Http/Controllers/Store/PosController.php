<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Store\Inventory\Inventory;
use App\Models\Store\Product;
use Illuminate\Http\Request;

class PosController extends Controller
{
    public function getProducts(Request $request)
    {
        $storeId = $request->query('store');

        if (!$storeId) {
            return response()->json([
                'message' => 'Store is required'
            ], 422);
        }

        $products = Product::query()
            ->whereNull('parent_id')
            ->whereHas('inventories', function ($q) use ($storeId) {
                $q->where('store_id', $storeId)
                    ->where('quantity', '>', 0);
            })
            ->paginate(10);
        $productIds = $products->getCollection()->pluck('id');

        $inventories = Inventory::with('product')
            ->where('store_id', $storeId)
            ->where('quantity', '>', 0)
            ->whereIn('product_id', function ($query) use ($productIds) {
                $query->select('id')
                    ->from('products')
                    ->whereIn('parent_id', $productIds)
                    ->orWhereIn('id', $productIds);
            })
            ->get();
        $groupedInventories = $inventories->groupBy(function ($inventory) {
            return $inventory->product->parent_id ?? $inventory->product->id;
        });
        $data = $products->getCollection()->map(function ($product) use ($groupedInventories) {

            $items = $groupedInventories->get($product->id, collect());

            return [
                'product_id' => $product->id,
                'name' => $product->name,
                'category' => $product->category,
                'image' => $product->image_url,
                'tax' => $product->tax_rate,
                'total_stock' => $items->sum('quantity'),
                'price_range' => [
                    'min' => $items->min('selling_price'),
                    'max' => $items->max('selling_price'),
                ],
                'variants' => $items->map(function ($inv) {
                    return [
                        'inventory_id' => $inv->id,
                        'product_id' => $inv->product_id,
                        'variation' => $inv->product->variation_meta,
                        'price' => $inv->selling_price,
                        'stock' => $inv->quantity,
                        'stock_status' => $inv->stock_status,
                    ];
                })->values(),
            ];
        });
        return response()->json([
            'data' => $data,
            'current_page' => $products->currentPage(),
            'per_page' => $products->perPage(),
            'total' => $products->total(),
            'last_page' => $products->lastPage(),
            'links' => [
                'next' => $products->nextPageUrl(),
                'prev' => $products->previousPageUrl(),
            ]
        ]);
    }
}
