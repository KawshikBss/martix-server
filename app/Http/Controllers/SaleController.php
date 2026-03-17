<?php

namespace App\Http\Controllers;

use App\Models\Store\Inventory\Inventory;
use App\Models\Store\Inventory\InventoryMovement;
use App\Models\Store\Product;
use App\Models\Store\Sale\Sale;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SaleController extends Controller
{

    public function index(Request $request)
    {
        $user = Auth::user();

        $sale_type = $request->query('sale_type', 'order');

        $sales = Sale::where('user_id', $user->id)->where('sale_type', $sale_type);

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

    public function show(Request $request, Sale $sale)
    {
        $sale->load(['user', 'store', 'customer', 'items.product', 'items.product.category', 'items.inventory']);

        return response()->json($sale);
    }

    public function store(Request $request)
    {

        $user = Auth::user();

        $data = $request->validate([
            'store_id' => 'required',
            'customer_id' => 'nullable',
            'sub_total' => 'required|numeric|min:0',
            'tax_total' => 'required|numeric|min:0',
            'discount_total' => 'required|numeric|min:0',
            'grand_total' => 'required|numeric|min:0',
            'paid_amount' => 'required|numeric|min:0',
            'due_amount' => 'required|numeric|min:0',
            'sale_type' => 'required|string|in:order,pos',
            'payment_method' => 'nullable|string|max:255',
            'payment_details' => 'nullable|array',
            'items' => 'array'
        ]);

        $saleItems = $data['items'];
        unset($data['items']);
        $data['user_id'] = $user->id;

        $isPosSale = $data['sale_type'] === 'pos';

        $saleNumber = ($isPosSale ? 'POS-' : 'ORD-') . strtoupper(uniqid());
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

        if ($isPosSale) {
            $data['status'] = 'completed';
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
                if ($isPosSale) {
                    $inventoryMovementData['inventory_id'] = $item['inventory_id'];
                    $inventoryMovementData['quantity'] = -$item['quantity'];
                    InventoryMovement::create($inventoryMovementData);
                    $inventory = Inventory::find($item['inventory_id']);
                    $inventory->decrement('quantity', $item['quantity']);
                }
            }
            DB::commit();
            return response()->json($sale, 201);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function getProductsForSale(Request $request)
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

    public function complete(Request $request, Sale $sale)
    {
        if ($sale->status === 'cancelled') {
            abort(400, 'Cannot complete cancelled order');
        }
        if ($sale->status === 'refunded') {
            abort(400, 'Cannot complete refunded order');
        }
        if ($sale->status === 'completed') {
            abort(400, 'Order already completed');
        }

        $user = Auth::user();

        $inventoryMovementData = [
            'type' => 'sale',
            'reference_type' => 'sale',
            'reference_id' => $sale->id,
            'performed_by_id' => $user->id
        ];

        try {
            foreach ($sale->items as $item) {
                $inventoryMovementData['inventory_id'] = $item['inventory_id'];
                $inventoryMovementData['quantity'] = -$item['quantity'];
                InventoryMovement::create($inventoryMovementData);
                $inventory = Inventory::find($item['inventory_id']);
                $inventory->decrement('quantity', $item['quantity']);
            }
            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
        $sale->update([
            'status' => 'completed'
        ]);

        return response()->json($sale);
    }

    public function cancel(Request $request, Sale $sale)
    {
        if ($sale->status === 'cancelled') {
            abort(400, 'Order already cancelled');
        }
        if ($sale->status === 'refunded') {
            abort(400, 'Cannot cancel refunded order');
        }
        if ($sale->status === 'completed') {
            abort(400, 'Cannot cancel completed order');
        }
        $sale->update([
            'status' => 'canceled'
        ]);

        return response()->json($sale);
    }

    public function refund(Request $request, Sale $sale)
    {
        if ($sale->status === 'cancelled') {
            abort(400, 'Cannot refund cancelled order');
        }
        if ($sale->status === 'refunded') {
            abort(400, 'Order already refunded');
        }
        if ($sale->status === 'completed') {
            abort(400, 'Cannot refund completed order');
        }
        $items = $sale->items;
        DB::beginTransaction();
        try {
            foreach ($items as $item) {
                $inventory = Inventory::find($item->inventory_id);
                $quantity = abs($item->quantity);
                $inventory->increment('quantity', $quantity);
                InventoryMovement::create([
                    'inventory_id' => $item->inventory_id,
                    'type' => 'sale_cancellation',
                    'quantity' => $quantity,
                    'reference_type' => 'sale',
                    'reference_id' => $sale->id,
                    'performed_by_id' => Auth::id()
                ]);
                DB::commit();
            }
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
        $sale->update([
            'status' => 'refunded'
        ]);

        return response()->json($sale);
    }

    public function posMetrics(Request $request)
    {
        $user = Auth::user();
        $sales = Sale::where('user_id', $user->id)->where('sale_type', 'pos');
        $completedSales = (clone $sales)->where('status', 'completed')->get();
        $totalSales = $completedSales->sum('grand_total');
        $totalTransactions = $completedSales->count();
        $avgTicketValue = $totalTransactions > 0 ? $totalSales / $totalTransactions : 0;
        $totalRefunded = (clone $sales)->where('status', 'refunded')->sum('grand_total');
        $netRevenue = $totalSales - $totalRefunded;
        return response()->json([
            'total_sales' => number_format((float) ($totalSales ?? 0), 2, '.', '') . '$',
            'total_transactions' => $totalTransactions ?? 0,
            'avg_ticket_value' => number_format((float) ($avgTicketValue ?? 0), 2, '.', '') . '$',
            'total_refunded' => number_format((float) ($totalRefunded ?? 0), 2, '.', '') . '$',
            'net_revenue' => number_format((float) ($netRevenue ?? 0), 2, '.', '') . '$'
        ]);
    }

    public function orderMetrics(Request $request)
    {
        $user = Auth::user();
        $sales = Sale::where('user_id', $user->id)->where('sale_type', 'order');
        $totalSales = (clone $sales)->count();
        $totalCompleted = (clone $sales)->where('status', 'completed')->count();
        $totalPending = (clone $sales)->where('status', 'pending')->count();
        $totalCancelled = (clone $sales)->where('status', 'canceled')->count();
        $totalDue = (clone $sales)->whereNot('payment_status', 'paid')->count();
        $totalDueAmount = (clone $sales)->whereNot('payment_status', 'paid')->sum('due_amount');
        return response()->json([
            'total_sales' => $totalSales,
            'total_pending' => $totalPending,
            'total_completed' => $totalCompleted,
            'total_cancelled' => $totalCancelled,
            'total_due' => $totalDue,
            'total_due_amount' => number_format((float) ($totalDueAmount ?? 0), 2, '.', '') . '$',
        ]);
    }

    public function graph(Request $request)
    {
        $user = Auth::user();

        $sale_type = $request->query('sale_type', 'order');

        $sales = Sale::where('user_id', $user->id)->where('sale_type', $sale_type);
        $data = $sales->get()
            ->filter(function ($sale) {
                return $sale->created_at->month === now()->month;
            })
            ->groupBy(function ($sale) {
                return $sale->created_at->format('D');
            })
            ->sortBy(function ($group, $key) {
                return Carbon::parse($key)->timestamp;
            })
            ->map(function ($group) {
                return [
                    'count' => $group->count(),
                    'total' => $group->sum('grand_total'),
                ];
            });
        return response()->json($data);
    }

    public function revenueGraph(Request $request)
    {
        $user = Auth::user();

        $sales = Sale::where('user_id', $user->id);
        $totalRevenue = (clone $sales)->where('status', 'completed')->sum('grand_total');
        $totalRefunded = (clone $sales)->where('status', 'refunded')->sum('grand_total');
        return response()->json([
            'revenue' => $totalRevenue,
            'refunded' => $totalRefunded,
        ]);
    }

    public function statusGraph(Request $request)
    {
        $user = Auth::user();

        $sales = Sale::where('user_id', $user->id);
        $totalCompleted = (clone $sales)->where('status', 'completed')->count();
        $totalPending = (clone $sales)->where('status', 'pending')->count();
        $totalCancelled = (clone $sales)->where('status', 'cancelled')->count();
        $totalRefunded = (clone $sales)->where('status', 'refunded')->count();
        return response()->json([
            'completed' => $totalCompleted,
            'pending' => $totalPending,
            'cancelled' => $totalCancelled,
            'refunded' => $totalRefunded,
        ]);
    }

    public function paymentStatusGraph(Request $request)
    {
        $user = Auth::user();

        $sales = Sale::where('user_id', $user->id);
        $totalPaid = (clone $sales)->where('payment_status', 'paid')->count();
        $totalUnpaid = (clone $sales)->where('payment_status', 'unpaid')->count();
        $totalPartial = (clone $sales)->where('payment_status', 'partial')->count();
        return response()->json([
            'paid' => $totalPaid,
            'unpaid' => $totalUnpaid,
            'partial' => $totalPartial,
        ]);
    }

    public function paymentMethodGraph(Request $request)
    {
        $user = Auth::user();

        $sales = Sale::where('user_id', $user->id);
        $totalCash = (clone $sales)->where('payment_method', 'Cash')->count();
        $totalCard = (clone $sales)->where('payment_method', 'Card')->count();
        $totalMixed = (clone $sales)->where('payment_method', 'Mixed')->count();
        return response()->json([
            'cash' => $totalCash,
            'card' => $totalCard,
            'mixed' => $totalMixed,
        ]);
    }
}
