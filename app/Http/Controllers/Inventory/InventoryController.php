<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Store\Inventory\Inventory;
use App\Models\Store\Inventory\InventoryMovement;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $inventories = Inventory::ownedByUser($user)
            ->with(['store', 'product'])
            ->get();
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
            ->get();
        return response()->json($movements);
    }
}
