<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Store\Inventory\Inventory;
use App\Models\Store\Product;
use App\Models\Store\Sale\Sale;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

    public function createSale(Request $request)
    {

        $user = auth()->user();

        $data = $request->validate([
            'store_id' => 'required',
            'customer_id' => 'nullable',
            'sub_total' => 'required|numeric|min:0',
            'tax_total' => 'required|numeric|min:0',
            'discount_total' => 'required|numeric|min:0',
            'grand_total' => 'required|numeric|min:0',
            'paid_amount' => 'required|numeric|min:0',
            'due_amount' => 'required|numeric|min:0',
            'payment_method' => 'nullable|string|max:255',
            'payment_details' => 'nullable|array',
            'items' => 'array'
        ]);

        $saleItems = $data['items'];
        unset($data['items']);
        $data['user_id'] = $user->id;

        $saleNumber = 'POS-' . strtoupper(uniqid());
        $data['sale_number'] = $saleNumber;

        if ($data['due_amount'] > 0) {
            if ($data['paid_amount'] == 0) {
                $data['payment_status'] = 'unpaid';
            } else {
                $data['payment_status'] = 'partial';
            }
        } else {
            $data['payment_status'] = 'paid';
        }

        if ($data['payment_details'] && count($data['payment_details'])) {
            $data['payment_details'] = json_encode($data['payment_details']);
        }

        DB::beginTransaction();

        try {

            $sale = Sale::create($data);

            foreach ($saleItems as $item) {
                $sale->items()->create([
                    'product_id' => $item['product_id'],
                    'inventory_id' => $item['inventory_id'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'tax' => $item['tax'],
                    'discount' => 0,
                    'tax' => $item['tax'],
                    'total' => $item['itemTotal'],
                ]);
            }
            DB::commit();
            return response()->json($sale, 201);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
