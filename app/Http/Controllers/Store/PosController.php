<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Store\Inventory\Inventory;
use App\Models\Store\Inventory\InventoryMovement;
use App\Models\Store\Product;
use App\Models\Store\Sale\Sale;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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

    public function getSales(Request $request)
    {
        $user = auth()->user();

        $sales = Sale::where('user_id', $user->id);

        $search = $request->query('query', null);
        if ($search != null && $search !== '') {
            $like = "%{$search}%";
            $sales = $sales->where('sale_number', 'like', $like);
        }

        $store = $request->query('store', null);
        if ($store != null && $store !== '') {
            $sales = $sales->where('store_id', $store);
        }

        $user = $request->query('user', null);
        if ($user != null && $user !== '') {
            $sales = $sales->where('user_id', $user);
        }

        $status = $request->query('status', null);
        if ($status != null && $status !== '') {
            $sales = $sales->where('status', $status);
        }

        $payment_status = $request->query('payment_status', null);
        if ($payment_status != null && $payment_status !== '') {
            $sales = $sales->where('payment_status', $payment_status);
        }

        $min_order_value = $request->query('min_order_value', null);
        if ($min_order_value != null && $min_order_value !== '') {
            $sales = $sales->where('grand_total', '>=', $min_order_value);
        }

        $max_order_value = $request->query('max_order_value', null);
        if ($max_order_value != null && $max_order_value !== '') {
            $sales = $sales->where('grand_total', '<=', $max_order_value);
        }

        $minCreateDate = $request->query('min_create_date', null);
        if ($minCreateDate != null && $minCreateDate !== '') {
            $date = Carbon::parse($minCreateDate);
            $sales = $sales->where('created_at', '>=', $date);
        }

        $maxCreateDate = $request->query('max_create_date', null);
        if ($maxCreateDate != null && $maxCreateDate !== '') {
            $date = Carbon::parse($maxCreateDate);
            $sales = $sales->where('created_at', '<=', $date);
        }

        $minUpdateDate = $request->query('min_update_date', null);
        if ($minUpdateDate != null && $minUpdateDate !== '') {
            $date = Carbon::parse($minUpdateDate);
            $sales = $sales->where('updated_at', '>=', $date);
        }

        $maxUpdateDate = $request->query('max_update_date', null);
        if ($maxUpdateDate != null && $maxUpdateDate !== '') {
            $date = Carbon::parse($maxUpdateDate);
            $sales = $sales->where('updated_at', '<=', $date);
        }

        $sales = $sales->with(['user', 'store', 'customer', 'items'])->paginate(10);

        return response()->json($sales);
    }

    public function getSale(Request $request, Sale $sale)
    {
        $sale->load(['user', 'store', 'customer', 'items.product', 'items.product.category', 'items.inventory']);

        return response()->json($sale);
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

            $inventoryMovementData = [
                // 'inventory_id' => $inventory['id'],
                'type' => 'sale',
                // 'quantity' => $inventory['initial_quantity'],
                'reference_type' => 'sale',
                'reference_id' => $sale->id,
                'performed_by_id' => $user->id
            ];

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
                $inventoryMovementData['inventory_id'] = $item['inventory_id'];
                $inventoryMovementData['quantity'] = -$item['quantity'];
                InventoryMovement::create($inventoryMovementData);
                $inventory = Inventory::find($item['inventory_id']);
                $inventory->decrement('quantity', $item['quantity']);
            }
            DB::commit();
            return response()->json($sale, 201);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function refundSale(Request $request, Sale $sale)
    {
        $sale->update([
            'status' => 'refunded'
        ]);

        return response()->json($sale);
    }
    public function cancelSale(Request $request, Sale $sale)
    {
        $sale->update([
            'status' => 'canceled'
        ]);

        return response()->json($sale);
    }
}
