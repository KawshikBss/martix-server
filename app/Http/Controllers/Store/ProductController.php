<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\Store\Inventory\Inventory;
use App\Models\Store\Inventory\InventoryMovement;
use App\Models\Store\Product;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $storeIds = $user->stores->pluck('id')->toArray();
        $managerStoreIds = Store::where('manager_id', $user->id)->pluck('id')->toArray();
        $storeIds = array_merge($storeIds, $managerStoreIds);
        $productsInStores = DB::table('store_products')->whereIn('store_id', $storeIds)->pluck('product_id')->toArray();
        $products = Product::where(function ($q) use ($productsInStores) {
            foreach ($productsInStores as $productId) {
                $q->where('id', $productId);
            }
        })->orWhere('owner_id', $user->id)->where('is_variation', false);

        $search = $request->query('query', null);
        if ($search != null && $search !== '') {
            $like = "%{$search}%";
            $products = $products->where('name', 'like', $like)->orWhere('sku', $like);
        }

        $category = $request->query('category', null);
        if ($category != null && $category !== '') {
            $products = $products->where('category_id', $category);
        }

        $stockStatus = $request->query('stock_status', null);
        if ($stockStatus != null && $stockStatus !== '') {
            if ($stockStatus === 'in_stock') {
                $products = $products->whereDoesntHave('inventories', function ($q) {
                    $q->whereColumn('quantity', '>', 'reorder_level');
                });
            } else if ($stockStatus === 'low_stock') {
                $products = $products->whereHas('inventories', function ($q) {
                    $q->whereColumn('quantity', '<=', 'reorder_level');
                });
            } else if ($stockStatus === 'out_of_stock') {
                $products = $products->whereHas('inventories', function ($q) {
                    $q->where('quantity', '<=', 0);
                });
            }
        }

        $minPrice = $request->query('min_price', null);
        if ($minPrice != null && $minPrice !== '') {
            $products = $products->where('cost_price', '>=', $minPrice);
        }

        $maxPrice = $request->query('max_price', null);
        if ($maxPrice != null && $maxPrice !== '') {
            $products = $products->where('cost_price', '<=', $maxPrice);
        }

        $products = $products->with(['category', 'variants'])->get();
        return response()->json($products);
    }

    public function show($id)
    {
        $user = auth()->user();
        $product = $user->products()->with(['owner', 'variants', 'variants.inventories', 'inventories'])->findOrFail($id);
        return response()->json($product);
    }

    public function store(Request $request)
    {
        $user = auth()->user();

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'sku' => 'nullable|string|max:100|unique:products,sku',
            'description' => 'nullable|string',
            'image' => 'nullable|image|max:2048',
            'cost_price' => 'required|numeric|min:0',
            'tax_type' => 'nullable|string|max:100',
            'tax_rate' => 'nullable|numeric|min:0',
            'category_id' => 'nullable|exists:categories,id',
            'brand' => 'nullable|string|max:255',
            'tags' => 'nullable|string',
            // 'parent_id' => 'nullable|exists:products,id',
            'is_variation' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
            'variations' => 'nullable|array',
            'product_stocks' => 'nullable|array',
        ]);

        $variations = $data['variations'] ?? [];
        $productStocks = $data['product_stocks'] ?? [];
        unset($data['variations']);
        unset($data['product_stocks']);

        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('products', 'public');
        } else {
            $imagePath = null;
        }

        $data['image'] = $imagePath;

        $data['owner_id'] = $user->id;
        DB::beginTransaction();


        try {
            $product = $user->products()->create($data);
            $newProducts = [];
            if (count($variations) > 0) {
                unset($data['sku']);
                $data['is_variation'] = true;
                foreach ($variations as $variation) {
                    $data['variations'] = json_encode([
                        'option' => $variation['option'],
                        'value' => $variation['value']
                    ]);
                    $newProducts[$data['variations']] = $product->variants()->create($data);
                }
            }

            if (count($productStocks) > 0) {
                foreach ($productStocks as $stock) {
                    $productId = $product['id'];
                    if (array_key_exists('variant', $stock)) {
                        $variant = json_encode([
                            'option' => $stock['variant']['option'],
                            'value' => $stock['variant']['value']
                        ]);
                        if (array_key_exists($variant, $newProducts)) {
                            $productId = $newProducts[$variant]['id'];
                        }
                    }
                    $stockData = [
                        'store_id' => $stock['store'],
                        'product_id' => $productId,
                        'barcode' => $stock['barcode'],
                        'initial_quantity' => $stock['quantity'],
                        'quantity' => $stock['quantity'],
                        'selling_price' => $stock['selling_price'],
                        'reorder_level' => $stock['reorder_level'],
                        'expiry_date' => $stock['expiry_date'],
                    ];
                    $inventory = Inventory::create($stockData);
                    $inventoryMovementData = [
                        'inventory_id' => $inventory['id'],
                        'type' => 'initial',
                        'quantity' => $inventory['initial_quantity'],
                        'reference_type' => 'product_creation',
                        'reference_id' => $inventory['product_id'],
                        'performed_by_id' => $user['id']
                    ];
                    InventoryMovement::create($inventoryMovementData);
                }
            }

            DB::commit();

            return response()->json($product, 201);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $user = auth()->user();
        $product = $user->products()->findOrFail($id);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'sku' => 'nullable|string|max:100|unique:products,sku',
            'description' => 'nullable|string',
            'image' => 'nullable|image|max:2048',
            'cost_price' => 'required|numeric|min:0',
            'tax_type' => 'nullable|string|max:100',
            'tax_rate' => 'nullable|numeric|min:0',
            'category_id' => 'nullable|exists:categories,id',
            'brand' => 'nullable|string|max:255',
            'tags' => 'nullable|string',
            'is_variation' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
            'variations' => 'nullable|array',
            'product_stocks' => 'nullable|array',
        ]);

        $variations = $data['variations'] ?? [];
        $productStocks = $data['product_stocks'] ?? [];
        unset($data['variations']);
        unset($data['product_stocks']);

        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('products', 'public');
        } else {
            $imagePath = $product['image'];
        }

        $data['image'] = $imagePath;

        $product->update($data);
        return response()->json($product);
    }

    public function destroy($id)
    {
        $user = auth()->user();
        $product = $user->products()->findOrFail($id);
        $product->delete();
        return response()->json(null, 204);
    }
}
