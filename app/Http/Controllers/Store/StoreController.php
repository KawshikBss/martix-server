<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Http\Resources\Store\StoreResource;
use App\Models\Store;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StoreController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $stores = StoreResource::collection($user->stores);
        return response()->json($stores);
    }

    public function show($id)
    {
        $user = auth()->user();
        $store = $user->stores()->findOrFail($id);
        return response()->json($store);
    }

    public function store(Request $request)
    {
        $user = auth()->user();

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'image' => 'nullable|image|max:2048',
            'unique_id' => 'nullable|string|max:255',
            'branch' => 'nullable|string|max:255',
            'type' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500',
            'address_2' => 'nullable|string|max:500',
            'manager_id' => 'nullable|exists:users,id',
            'staff_list' => 'nullable|array'
        ]);

        $staffList = $data['staff_list'] ?? [];
        unset($data['staff_list']);

        $data['owner_id'] = $user->id;

        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('profiles', 'public');
        } else {
            $imagePath = null;
        }

        $data['image'] = $imagePath;

        DB::beginTransaction();

        try {
            $store = $user->stores()->create($data);

            if (count($staffList) > 0) {
                foreach ($staffList as $staffRecord) {
                    $staffId = $staffRecord['staff_id'];
                    $roleId = $staffRecord['role_id'];
                    $storeUserData = ['store_id' => $store['id'], 'user_id' => $staffId, 'role_id' => $roleId];
                    $storeUser = $store->staff()->create($storeUserData);
                }
            }

            DB::commit();

            return response()->json($store, 201);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $user = auth()->user();
        $store = $user->stores()->findOrFail($id);

        $data = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'branch' => 'nullable|string|max:255',
            'type' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500',
            'manager_id' => 'nullable|exists:users,id',
        ]);

        $store->update($data);
        return response()->json($store);
    }

    public function destroy($id)
    {
        $user = auth()->user();
        $store = $user->stores()->findOrFail($id);
        $store->delete();
        return response()->json(null, 204);
    }

    public function addProduct(Request $request, Store $store)
    {
        $user = auth()->user();

        $data = $request->validate([
            'product_id' => 'required|exists:products,id',
            'stock_quantity' => 'required|integer|min:0',
            'low_stock_level' => 'required|integer|min:0',
            'selling_price' => 'required|numeric|min:0',
            'tax_type' => 'nullable|string',
            'tax_value' => 'nullable|numeric|min:0',
            'expiry_date' => 'nullable|date',
            'is_active' => 'sometimes|boolean',
        ]);

        $data['store_id'] = $store->id;

        DB::table('store_products')->insert($data);

        return response()->json(['message' => 'Product added to store inventory', 'data' => $data], 200);
    }
}
