<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\Store\Product;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    public function index()
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
        })->orWhere('owner_id', $user->id)->where('is_variation', false)->with(['category', 'variants'])->get();
        return response()->json($products);
    }

    public function show($id)
    {
        $user = auth()->user();
        $product = $user->products()->findOrFail($id);
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
            'variations' => 'nullable|array'
        ]);

        $variations = $data['variations'];
        unset($data['variations']);

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
            if (count($variations) > 0) {
                unset($data['sku']);
                $data['is_variation'] = true;
                foreach ($variations as $variation) {
                    $newProduct = $product->variants()->create($data);
                    $productOption = $newProduct->options()->create([
                        'name' => $variation['option']
                    ]);
                    foreach ($variation['values'] as $value) {
                        $productOption->values()->create([
                            'value' => $value
                        ]);
                    }
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
            'name' => 'sometimes|required|string|max:255',
            'sku' => 'nullable|string|max:100|unique:products,sku,' . $product->id,
            'description' => 'nullable|string',
            'image' => 'nullable|image|max:2048',
            'cost_price' => 'sometimes|required|numeric|min:0',
            'tax_type' => 'nullable|string|max:100',
            'tax_rate' => 'nullable|numeric|min:0',
            'category_id' => 'nullable|exists:categories,id',
            'brand' => 'nullable|string|max:255',
            'tags' => 'nullable|string',
            'parent_id' => 'nullable|exists:products,id',
            'is_variation' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('products', 'public');
            $data['image'] = $imagePath;
        }

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
