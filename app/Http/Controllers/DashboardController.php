<?php

namespace App\Http\Controllers;

use App\Models\Store\Inventory\Inventory;
use App\Models\Store\Product;
use App\Models\Store\Sale\Sale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

use function Symfony\Component\Clock\now;

class DashboardController extends Controller
{
    public function metrics()
    {
        $user = Auth::user();
        $today = now();

        $sales = Sale::where('user_id', $user->id)->whereDate('created_at', $today);
        $inventories = Inventory::ownedByUser($user);

        $salesToday =  (clone $sales)->where('status', 'completed')->sum('grand_total');
        $ordersToday = (clone $sales)->count();
        $pendingOrders = (clone $sales)->where('status', 'pending')->count();
        $totalDueToday = (clone $sales)->whereNot('payment_status', 'paid')->sum('due_amount');
        $lowStockItems = (clone $inventories)->whereColumn('quantity', '<=', 'reorder_level')->count();

        $mostSoldProduct = Product::withSum('saleItems', 'quantity')
            ->orderByDesc('sale_items_sum_quantity')
            ->first();

        return response()->json([
            'sales_today' => $salesToday,
            'orders_today' => $ordersToday,
            'pending_orders' => $pendingOrders,
            'total_due_today' => $totalDueToday,
            'low_stock_items' => $lowStockItems,
            'most_sold_product' => $mostSoldProduct ? [
                'id' => $mostSoldProduct->id,
                'name' => $mostSoldProduct->name,
                'quantity_sold' => $mostSoldProduct->sale_items_sum_quantity,
            ] : null,
        ]);
    }
}
