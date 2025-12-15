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
        $store = $user->stores()->findOrFail($id)->toResource(StoreResource::class);
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
            'description' => 'nullable|string|max:255',
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
                    $store->staff()->create($storeUserData);
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
            'name' => 'required|string|max:255',
            'image' => 'nullable|image|max:2048',
            'unique_id' => 'nullable|string|max:255',
            'branch' => 'nullable|string|max:255',
            'type' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500',
            'address_2' => 'nullable|string|max:500',
            'manager_id' => 'nullable|exists:users,id',
            'staff_list' => 'nullable|array'
        ]);

        $newStaffList = $data['staff_list'] ?? [];
        unset($data['staff_list']);

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('profiles', 'public');
        }

        DB::beginTransaction();
        try {
            $store->update($data);

            // Sync staff: add new, update existing roles, remove missing
            $existing = $store->staff()->get(); // collection of store-staff records
            $existingIds = $existing->pluck('user_id')->toArray();
            $newIds = array_map(fn($s) => $s['staff_id'], $newStaffList ?: []);

            // Remove staff no longer present
            $toRemove = array_diff($existingIds, $newIds);
            if (!empty($toRemove)) {
                $store->staff()->whereIn('user_id', $toRemove)->delete();
            }

            // Add new staff and update roles for existing
            foreach ($newStaffList as $staffRecord) {
                $staffId = $staffRecord['staff_id'];
                $roleId = $staffRecord['role_id'] ?? null;

                if (in_array($staffId, $existingIds)) {
                    $store->staff()->where('user_id', $staffId)->update(['role_id' => $roleId]);
                } else {
                    $store->staff()->create(['user_id' => $staffId, 'role_id' => $roleId]);
                }
            }

            DB::commit();
            return response()->json($store);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
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
