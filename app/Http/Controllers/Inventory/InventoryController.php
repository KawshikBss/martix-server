<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Store\Inventory\Inventory;
use App\Models\Store\Inventory\InventoryMovement;
use App\Models\Store\Inventory\InventoryTransfer;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class InventoryController extends Controller
{
    private $notificationService;

    public function __construct()
    {
        $this->notificationService = new NotificationService();
    }
    public function index(Request $request)
    {
        $user = Auth::user();
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

    public function find(Request $request)
    {
        $data = $request->validate([
            'product' => 'required|exists:products,id',
            'store' => 'required|exists:stores,id',
        ]);

        $user = Auth::user();
        $inventory = Inventory::where('product_id', $data['product'])
            ->where('store_id', $data['store'])
            ->ownedByUser($user)
            ->with(['store', 'product'])
            ->first();
        if (!$inventory) {
            return response()->json(['message' => 'Inventory not found'], 404);
        }
        return response()->json(['inventory' => $inventory]);
    }

    public function transfer(Request $request)
    {
        $data = $request->validate([
            'inventory' => 'required|exists:inventories,id',
            'receiving_store' => 'required|exists:stores,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $user = Auth::user();

        $inventory = Inventory::where('id', $data['inventory'])
            ->with('store')
            ->first();

        if (!$inventory) {
            return response()->json(['error' => 'Inventory not found'], 404);
        }
        if ($inventory->quantity < $data['quantity']) {
            return response()->json(['error' => 'Insufficient inventory quantity'], 400);
        }

        DB::beginTransaction();
        try {

            $inventory->decrement('quantity', $data['quantity']);
            $inventory->save();

            InventoryMovement::create([
                'inventory_id' => $inventory->id,
                'type' => 'transfer',
                'quantity' => -$data['quantity'],
                'reference_type' => 'transfer',
                'reference_id' => null,
                'performed_by_id' => $user->id,
            ]);

            $receivingInventory = Inventory::firstOrCreate(
                [
                    'product_id' => $inventory->product_id,
                    'store_id' => $data['receiving_store'],
                ],
                [
                    'quantity' => 0,
                ]
            );

            $receivingInventory->increment('quantity', $data['quantity']);
            $receivingInventory->save();
            InventoryMovement::create([
                'inventory_id' => $receivingInventory->id,
                'type' => 'transfer',
                'quantity' => $data['quantity'],
                'reference_type' => 'transfer',
                'reference_id' => null,
                'performed_by_id' => $user->id,
            ]);


            $transfer = InventoryTransfer::create([
                'transfer_number' => 'TR-' . strtoupper(uniqid()),
                'source_inventory_id' => $inventory->id,
                'destination_inventory_id' => $receivingInventory->id,
                'quantity' => $data['quantity'],
                'status' => 'completed',
                'created_by_id' => $user->id,
            ]);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['error' => 'Failed to transfer inventory'], 500);
        }
        $sourceUsers = $inventory->store->staff()->whereHas('role', function ($q) {
            $q->whereIn('name', ['owner', 'manager']);
        })->get()->pluck('user');

        $sourceRecipients = $sourceUsers->where('id', '!=', $user->id);

        $this->notificationService->notifyTransferStatus([
            'type' => 'receive',
            'transfer' => $transfer,
            'source_inventory' => $inventory,
            'destination_inventory' => $receivingInventory,
        ], $sourceRecipients);

        $destinationUsers = $receivingInventory->store->staff()->whereHas('role', function ($q) {
            $q->whereIn('name', ['owner', 'manager']);
        })->get()->pluck('user');

        $destinationRecipients = $destinationUsers->where('id', '!=', $user->id);

        $this->notificationService->notifyTransferStatus([
            'type' => 'create',
            'transfer' => $transfer,
            'source_inventory' => $inventory,
            'destination_inventory' => $receivingInventory,
        ], $destinationRecipients);

        return response()->json(['message' => 'Inventory transferred successfully']);
    }

    public function transfers(Request $request)
    {
        $user = Auth::user();
        $transfers = InventoryTransfer::accessibleByUser($user);

        $search = $request->query('query', null);
        if ($search != null && $search !== '') {
            $like = "%{$search}%";
            $transfers = $transfers->whereHas('sourceInventory.product', function ($sub) use ($like) {
                $sub->where('name', 'like', $like);
            })
                ->orWhereHas('sourceInventory.product', function ($sub) use ($like) {
                    $sub->where('sku', 'like', $like);
                })
                ->orWhereHas('sourceInventory.store', function ($sub) use ($like) {
                    $sub->where('name', 'like', $like);
                })
                ->orWhereHas('destinationInventory.store', function ($sub) use ($like) {
                    $sub->where('name', 'like', $like);
                });
        }

        $store = $request->query('store', null);
        if ($store != null && $store !== '') {
            $transfers = $transfers->whereHas('sourceInventory.store', function ($sub) use ($store) {
                $sub->where('id', $store);
            })->orWhereHas('destinationInventory.store', function ($sub) use ($store) {
                $sub->where('id', $store);
            });
        }

        $product = $request->query('product', null);
        if ($product != null && $product !== '') {
            $transfers = $transfers->whereHas('sourceInventory.product', function ($sub) use ($product) {
                $sub->where('id', $product);
            })->orWhereHas('destinationInventory.product', function ($sub) use ($product) {
                $sub->where('id', $product);
            });
        }

        $user = $request->query('user', null);
        if ($user != null && $user !== '') {
            $transfers = $transfers->where('created_by_id', $user);
        }

        $status = $request->query('status', null);
        if ($status != null && $status !== '') {
            $transfers = $transfers->where('status', $status);
        }

        $minCreateDate = $request->query('min_create_date', null);
        if ($minCreateDate != null && $minCreateDate !== '') {
            $date = Carbon::parse($minCreateDate);
            $transfers = $transfers->where('created_at', '>=', $date);
        }

        $maxCreateDate = $request->query('max_create_date', null);
        if ($maxCreateDate != null && $maxCreateDate !== '') {
            $date = Carbon::parse($maxCreateDate);
            $transfers = $transfers->where('created_at', '<=', $date);
        }

        $minUpdateDate = $request->query('min_update_date', null);
        if ($minUpdateDate != null && $minUpdateDate !== '') {
            $date = Carbon::parse($minUpdateDate);
            $transfers = $transfers->where('updated_at', '>=', $date);
        }

        $maxUpdateDate = $request->query('max_update_date', null);
        if ($maxUpdateDate != null && $maxUpdateDate !== '') {
            $date = Carbon::parse($maxUpdateDate);
            $transfers = $transfers->where('updated_at', '<=', $date);
        }

        $transfers = $transfers->with(['sourceInventory', 'destinationInventory', 'createdBy'])->paginate(10);
        return response()->json($transfers);
    }

    public function movements(Request $request)
    {
        $user = Auth::user();
        $movements = InventoryMovement::accessibleByUser($user);

        $search = $request->query('query', null);
        if ($search != null && $search !== '') {
            $like = "%{$search}%";
            $movements = $movements->whereHas('inventory.product', function ($sub) use ($like) {
                $sub->where('name', 'like', $like);
            })
                ->orWhereHas('inventory.product', function ($sub) use ($like) {
                    $sub->where('sku', 'like', $like);
                });
        }

        $store = $request->query('store', null);
        if ($store != null && $store !== '') {
            $movements = $movements->whereHas('inventory', function ($sub) use ($store) {
                $sub->where('store_id', $store);
            });
        }

        $adjustment_type = $request->query('adjustment_type', null);
        if ($adjustment_type != null && $adjustment_type !== '') {
            $movements = $movements->where('quantity', $adjustment_type === 'decrease' ? '<' : '>', 0);
        }

        $reason = $request->query('reason', null);
        if ($reason != null && $reason !== '') {
            $movements = $movements->where('type', $reason);
        }


        $movements = $movements->with([
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

        $user = Auth::user();

        $inventory = Inventory::where('product_id', $data['product'])->where('store_id', $data['store'])->first();

        if (!$inventory) {
            $inventory = Inventory::create([
                'product_id' => $data['product'],
                'store_id' => $data['store'],
                'quantity' => 0,
            ]);
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
        $inventoryMovement = InventoryMovement::create([
            'inventory_id' => $inventory['id'],
            'type' => 'adjustment',
            'quantity' => $data['quantity'] * ($data['adjustment_type'] === 'decrease' ? -1 : 1),
            'reference_type' => 'inventory',
            'reference_id' => $inventory['id'],
            'performed_by_id' => $user->id,
            'note' => $data['notes'] ?? null,
        ]);
        $users = $inventory->store->staff()->whereHas('role', function ($q) {
            $q->whereIn('name', ['owner', 'manager']);
        })->get()->pluck('user');

        $recipients = $users->where('id', '!=', $user->id);

        $this->notificationService->notifyInventoryAdjustment([
            'type' => $data['adjustment_type'],
            'inventory' => $inventory,
            'inventory_movement' => $inventoryMovement,
            'quantity' => $data['quantity'],
            'performed_by' => $user,
        ], $recipients);

        return response()->json(['message' => 'Inventory adjusted successfully']);
    }

    public function metrics()
    {
        $user = Auth::user();
        $inventories = Inventory::ownedByUser($user);
        $totalActiveItems = (clone $inventories)->where('quantity', '>', 0)->count();
        $totalInventoryValue = (clone $inventories)->where('quantity', '>', 0)->sum(DB::raw('quantity * selling_price'));
        $lowStockItems = (clone $inventories)->whereColumn('quantity', '<=', 'reorder_level')->count();
        $outOfStockItems = (clone $inventories)->where('quantity', '<=', 0)->count();
        $expiringSoonItems = (clone $inventories)->whereBetween('expiry_date', [Carbon::now(), Carbon::now()->addMonth()])->count();
        $expiredItems = (clone $inventories)->where('expiry_date', '<=', Carbon::now())->count();

        return response()->json([
            'total_active_items' => $totalActiveItems,
            'total_inventory_value' => ($totalInventoryValue ?? 0) . '$',
            'low_stock_items' => ($lowStockItems ?? 0) . ' units',
            'out_of_stock_items' => ($outOfStockItems ?? 0) . ' units',
            'expiring_soon_items' => ($expiringSoonItems ?? 0) . ' units',
            'expired_items' => ($expiredItems ?? 0) . ' units',
        ]);
    }

    public function transferMetrics()
    {
        $user = Auth::user();
        $transfers = InventoryTransfer::accessibleByUser($user);
        $totalTransfers = (clone $transfers)->count();
        $totalCompleted = (clone $transfers)->where('status', 'completed')->count();
        $totalPending = (clone $transfers)->where('status', 'pending')->count();
        $totalTransfersQuantity = (clone $transfers)->sum('quantity');
        $totalTransfersValue = (clone $transfers)->where('status', 'completed')
            ->join('inventories as src', 'src.id', '=', 'inventory_transfers.source_inventory_id')
            ->join('products', 'products.id', '=', 'src.product_id')
            ->sum(DB::raw('inventory_transfers.quantity * products.cost_price'));;

        return response()->json([
            'total_transfers' => $totalTransfers,
            'total_completed' => $totalCompleted,
            'total_pending' => $totalPending,
            'total_transfers_quantity' => ($totalTransfersQuantity ?? 0) . ' units',
            'total_transfers_value' => ($totalTransfersValue ?? 0) . '$',
        ]);
    }

    public function movementMetrics()
    {
        $user = Auth::user();
        $movements = InventoryMovement::accessibleByUser($user);
        $totalMovements = (clone $movements)->count();
        $stockAdded = (clone $movements)->where('quantity', '>', 0)->sum('quantity');
        $stockRemoved = (clone $movements)->where('quantity', '<', 0)->sum('quantity');
        $adjustments = (clone $movements)->where('type', 'adjustment')->count();
        $netStockChange = $stockAdded + $stockRemoved;

        return response()->json([
            'total_movements' => ($totalMovements ?? 0) . ' units',
            'stock_added' => ($stockAdded ?? 0) . ' units',
            'stock_removed' => abs($stockRemoved ?? 0) . ' units',
            'adjustments' => ($adjustments ?? 0) . ' units',
            'net_stock_change' => ($netStockChange ?? 0) . ' units',
        ]);
    }

    public function statusGraph()
    {
        $user = Auth::user();
        $inventories = Inventory::ownedByUser($user);
        $totalInStock = (clone $inventories)->whereColumn('quantity', '>', 'reorder_level')->count();
        $totalLowStock = (clone $inventories)->whereColumn('quantity', '<=', 'reorder_level')->count();
        $totalOutStock = (clone $inventories)->where('quantity', '<=', 0)->count();
        return response()->json([
            'in_stock' => $totalInStock,
            'low_stock' => $totalLowStock,
            'out_of_stock' => $totalOutStock,
        ]);
    }

    public function valueByCategoryGraph()
    {
        $user = Auth::user();
        $categories = Inventory::ownedByUser($user)
            ->with('product.category')
            ->get()
            ->groupBy('product.category.name')
            ->map(function ($items) {
                return $items->sum(function ($item) {
                    return $item->quantity * $item->selling_price;
                });
            });

        return response()->json($categories);
    }

    public function movementLevelsGraph()
    {
        $user = Auth::user();
        $movements = InventoryMovement::accessibleByUser($user)->get()
            ->filter(function ($sale) {
                return $sale->created_at->month === now()->month;
            })->groupBy(function ($movement) {
                return $movement->created_at->format('d');
            })->map(function ($group) {
                return [
                    'count' => $group->count(),
                    'stock_in' => $group->where('quantity', '>', 0)->sum('quantity'),
                    'stock_out' => $group->where('quantity', '<', 0)->sum('quantity'),
                ];
            });
        return response()->json($movements);
    }

    public function movementTypesGraph()
    {
        $user = Auth::user();
        $movements = InventoryMovement::accessibleByUser($user)->get()->groupBy(function ($movement) {
            return $movement->type;
        })->map(function ($group) {
            return [
                'count' => $group->count(),
                'total' => $group->sum('quantity'),
            ];
        });
        return response()->json($movements);
    }

    public function transferLevelsGraph()
    {
        $user = Auth::user();
        $transfers = InventoryTransfer::accessibleByUser($user)->get()
            ->filter(function ($sale) {
                return $sale->created_at->month === now()->month;
            })->groupBy(function ($transfer) {
                return $transfer->created_at->format('d');
            })->map(function ($group) {
                return [
                    'count' => $group->sum('quantity'),
                ];
            });
        return response()->json($transfers);
    }

    public function transfersByStoresGraph()
    {
        $user = Auth::user();
        $transfers = InventoryTransfer::accessibleByUser($user)
            ->with('sourceInventory.store')
            ->get()
            ->groupBy('sourceInventory.store.name')
            ->map(function ($group) {
                return [
                    'count' => $group->sum('quantity'),
                ];
            });
        return response()->json($transfers);
    }
}
