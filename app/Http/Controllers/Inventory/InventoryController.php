<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Store\Inventory\Inventory;
use App\Models\Store\Inventory\InventoryMovement;
use Carbon\Carbon;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $inventories = Inventory::ownedByUser($user)
            ->with(['store', 'product']);
        $search = $request->query('query', null);
        if ($search != null && $search !== '') {
            $like = "%{$search}%";
            $inventories = $inventories->whereHas('product', function ($sub) use ($like) {
                $sub->where('name', 'like', $like);
            })
                ->orWhereHas('product', function ($sub) use ($like) {
                    $sub->where('sku', 'like', $like);
                });
        }

        $store = $request->query('store', null);
        if ($store != null && $store !== '') {
            $inventories = $inventories->where('store_id', $store);
        }

        $status = $request->query('status', null);
        if ($status != null && $status !== '') {
            if ($status === 'in_stock') {
                $inventories = $inventories->whereColumn('quantity', '>', 'reorder_level');
            } else if ($status === 'low_stock') {
                $inventories = $inventories->whereColumn('quantity', '<=', 'reorder_level');
            } else if ($status === 'out_of_stock') {
                $inventories = $inventories->where('quantity', '<=', 0);
            }
        }

        $category = $request->query('category', null);
        if ($category != null && $category !== '') {
            $inventories = $inventories->whereHas('product', function ($sub) use ($category) {
                $sub->where('category_id', $category);
            });
        }

        $brand = $request->query('brand', null);
        if ($brand != null && $brand !== '') {
            $like = "%{$brand}%";
            $inventories = $inventories->whereHas('product', function ($sub) use ($like) {
                $sub->where('brand', 'like', $like);
            });
        }

        $expiringSoon = $request->query('has_soon_expiring_products', null);
        if ($expiringSoon != null && $expiringSoon === 'true') {
            $startDate = Carbon::now();
            $endDate = $startDate->copy()->addMonth();
            $inventories = $inventories->whereBetween('expiry_date', [$startDate, $endDate]);
        }

        $hasExpiredProducts = $request->query('has_expired_products', null);
        if ($hasExpiredProducts != null && $hasExpiredProducts === 'true') {
            $startDate = Carbon::now();
            $inventories = $inventories->where('expiry_date', '<=', $startDate);
        }


        $minInventoryValue = $request->query('min_inventory_value', null);
        if ($minInventoryValue != null && $minInventoryValue !== '') {
            $inventories = $inventories->where('quantity', '>=', $minInventoryValue);
        }

        $maxInventoryValue = $request->query('max_inventory_value', null);
        if ($maxInventoryValue != null && $maxInventoryValue !== '') {
            $inventories = $inventories->where('quantity', '<=', $maxInventoryValue);
        }


        $minCreateDate = $request->query('min_create_date', null);
        if ($minCreateDate != null && $minCreateDate !== '') {
            $date = Carbon::parse($minCreateDate);
            $inventories = $inventories->where('created_at', '>=', $date);
        }

        $maxCreateDate = $request->query('max_create_date', null);
        if ($maxCreateDate != null && $maxCreateDate !== '') {
            $date = Carbon::parse($maxCreateDate);
            $inventories = $inventories->where('created_at', '<=', $date);
        }

        $minUpdateDate = $request->query('min_update_date', null);
        if ($minUpdateDate != null && $minUpdateDate !== '') {
            $date = Carbon::parse($minUpdateDate);
            $inventories = $inventories->where('updated_at', '>=', $date);
        }

        $maxUpdateDate = $request->query('max_update_date', null);
        if ($maxUpdateDate != null && $maxUpdateDate !== '') {
            $date = Carbon::parse($maxUpdateDate);
            $inventories = $inventories->where('updated_at', '<=', $date);
        }

        $inventories = $inventories->paginate(10);
        return response()->json($inventories);
    }

    public function movements()
    {
        $user = auth()->user();
        $movements = InventoryMovement::accessibleByUser($user)
            ->with([
                'inventory.store',
                'inventory.product',
                'performedBy',
            ])
            ->paginate(10);
        return response()->json($movements);
    }

    public function adjustment(Request $request)
    {
        $data = $request->validate([
            'product' => 'required|exists:products,id',
            'store' => 'required|exists:stores,id',
            'adjustment_type' => 'required|in:increase,decrease,exact',
            'quantity' => 'required|integer',
            'notes' => 'nullable|string',
        ]);

        $user = auth()->user();

        $inventory = Inventory::where('product_id', $data['product'])->where('store_id', $data['store'])->first();

        if (!$inventory) {
            return response()->json(['message' => 'Inventory not found'], 404);
        }

        switch ($data['adjustment_type']) {
            case 'increase':
                $inventory->quantity += $data['quantity'];
                break;
            case 'decrease':
                $inventory->quantity -= $data['quantity'];
                break;
            case 'exact':
                $inventory->quantity = $data['quantity'];
                break;
        }

        $inventory->save();
        InventoryMovement::create([
            'inventory_id' => $inventory['id'],
            'type' => 'adjustment',
            'quantity' => $data['quantity'] * ($data['adjustment_type'] === 'decrease' ? -1 : 1),
            'reference_type' => 'inventory',
            'reference_id' => $inventory['id'],
            'performed_by_id' => $user->id,
            'note' => $data['notes'] ?? null,
        ]);

        return response()->json(['message' => 'Inventory adjusted successfully']);
    }
}
