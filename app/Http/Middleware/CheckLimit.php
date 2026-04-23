<?php

namespace App\Http\Middleware;

use App\Models\Store;
use App\Services\SubscriptionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckLimit
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, $key): Response
    {
        $user = auth()->user();

        // 🔹 Resolve store (IMPORTANT — flexible)
        $storeId = $request->store_id
            ?? $request->route('store_id')
            ?? $request->route('store');

        $store = Store::with('subscription.plan.limits')->find($storeId);

        if (!$store) {
            return response()->json([
                'error' => 'Store not found'
            ], 404);
        }

        // 🔹 Check user belongs to store (security)
        if (!$store->staff()->where('user_id', $user->id)->exists()) {
            return response()->json([
                'error' => 'Unauthorized store access'
            ], 403);
        }

        // 🔹 Check subscription exists
        if (!$store->subscription || $store->subscription->status !== 'active') {
            return response()->json([
                'error' => 'Store is not active. Please subscribe.'
            ], 403);
        }

        $service = new SubscriptionService();

        // 🔹 Check limit
        if (!$service->canUse($store, $key)) {
            return response()->json([
                'error' => 'limit_reached',
                'message' => "Limit reached for {$key}",
                'limit_key' => $key
            ], 403);
        }

        // Attach store to request (VERY USEFUL)
        $request->merge(['resolved_store' => $store]);
        return $next($request);
    }
}
