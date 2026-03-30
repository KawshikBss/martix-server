<?php

namespace App\Http\Middleware;

use App\Models\Store;
use App\Services\PermissionService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class PermissionMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, $permission = null): Response
    {
        $user = Auth::user();

        $store = $this->resolveStore($request);

        // If permission requires store but none found
        if ($permission && !$store) {
            return response()->json([
                'error' => 'Store context required'
            ], 400);
        }

        if ($store) {
            $service = new PermissionService();

            if (!$service->hasPermission($user, $store, $permission)) {
                return response()->json([
                    'error' => 'User is not authorized to perform this action.'
                ], 403);
            }
        }

        return $next($request);
    }

    private function resolveStore(Request $request)
    {
        // 1. From route (BEST)
        if ($request->route('store')) {
            return $request->route('store');
        }

        // 2. From store_id (fallback)
        if ($request->store_id) {
            return Store::find($request->store_id);
        }

        // 3. From Sale
        if ($request->route('sale')) {
            return $request->route('sale')->store;
        }

        // 4. From Product
        if ($request->route('product')) {
            return $request->route('product')->store;
        }

        // Add more as needed...

        return null;
    }
}
