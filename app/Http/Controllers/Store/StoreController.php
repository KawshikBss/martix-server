<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Http\Resources\Store\StoreCollection;
use App\Http\Resources\Store\StoreResource;
use App\Models\Store;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class StoreController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $stores = $user->stores();
        $search = $request->query('query', null);
        if ($search != null && $search !== '') {
            $like = "%{$search}%";
            $stores = $stores->where('name', 'like', $like)->orWhere('unique_id', $like);
        }

        $manager = $request->query('manager', null);
        if ($manager != null && $manager !== '') {
            $stores = $stores->where('manager_id', $manager);
        }

        $branch = $request->query('branch', null);
        if ($branch != null && $branch !== '') {
            $like = "%{$branch}%";
            $stores = $stores->where('branch', 'like', $like);
        }

        $location = $request->query('location', null);
        if ($location != null && $location !== '') {
            $like = "%{$location}%";
            $stores = $stores->where('address', 'like', $like)->orWhere('address_2', 'like', $like);
        }

        $status = $request->query('status', null);
        if ($status != null && $status !== '') {
            $stores = $stores->where('is_active', $status === 'active' ? true : false);
        }

        $type = $request->query('type', null);
        if ($type != null && $type !== '') {
            $like = "%{$type}%";
            $stores = $stores->where('type', 'like', $like);
        }

        $minInventoryValue = $request->query('min_inventory_value', null);
        $maxInventoryValue = $request->query('max_inventory_value', null);

        if ($minInventoryValue !== null || $maxInventoryValue !== null) {

            $stores = $stores->withSum([
                'inventories as inventory_value' => function ($q) {
                    $q->select(DB::raw('SUM(quantity * selling_price)'));
                }
            ], 'quantity');

            if ($minInventoryValue !== null && $minInventoryValue !== '') {
                $stores->having('inventory_value', '>=', $minInventoryValue);
            }

            if ($maxInventoryValue !== null && $maxInventoryValue !== '') {
                $stores->having('inventory_value', '<=', $maxInventoryValue);
            }
        }

        $hasStaff = $request->query('has_staff', null);
        if ($hasStaff != null && $hasStaff === 'true') {
            $stores = $stores->whereHas('staff');
        }

        $hasLowStock = $request->query('has_low_stock', null);
        if ($hasLowStock != null && $hasLowStock === 'true') {
            $stores = $stores->whereHas('inventories', function ($q) {
                $q->whereColumn('quantity', '<=', 'reorder_level');
            });
        }

        $hasExpiredProducts = $request->query('has_expired_products', null);
        if ($hasExpiredProducts != null && $hasExpiredProducts === 'true') {
            $stores = $stores->whereHas('inventories', function ($q) {
                $q->where('expiry_date', '<', Carbon::now());
            });
        }

        $hasProductsExpiringSoon = $request->query('has_soon_expiring_products', null);
        if ($hasProductsExpiringSoon != null && $hasProductsExpiringSoon === 'true') {
            $stores = $stores->whereHas('inventories', function ($q) {
                $q->where('expiry_date', '<', Carbon::now()->addDays(10));
            });
        }

        $minCreateDate = $request->query('min_create_date', null);
        if ($minCreateDate != null && $minCreateDate !== '') {
            $date = Carbon::parse($minCreateDate);
            $stores = $stores->where('created_at', '>=', $date);
        }

        $maxCreateDate = $request->query('max_create_date', null);
        if ($maxCreateDate != null && $maxCreateDate !== '') {
            $date = Carbon::parse($maxCreateDate);
            $stores = $stores->where('created_at', '<=', $date);
        }

        $minUpdateDate = $request->query('min_update_date', null);
        if ($minUpdateDate != null && $minUpdateDate !== '') {
            $date = Carbon::parse($minUpdateDate);
            $stores = $stores->where('updated_at', '>=', $date);
        }

        $maxUpdateDate = $request->query('max_update_date', null);
        if ($maxUpdateDate != null && $maxUpdateDate !== '') {
            $date = Carbon::parse($maxUpdateDate);
            $stores = $stores->where('updated_at', '<=', $date);
        }

        $stores = $stores->paginate(2);

        return new StoreCollection($stores);
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

    public function toggleStatus($id)
    {
        $user = auth()->user();
        $store = $user->stores()->findOrFail($id);
        $res = $store->update(['is_active' => !$store->is_active]);
        if (!$res) {
            return response()->json(['error' => 'Failed to update status!'], 500);
        }
        return response()->json(['message' => 'Status updated successfully!'], 200);
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
