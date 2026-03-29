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

        // You MUST decide how store is resolved
        $store_id = $request->store_id; // or from route
        $store = Store::find($store_id);
        if (!$store) {
            return response()->json([
                'error' => 'Store not found'
            ], 404);
        }

        $service = new PermissionService();

        if (!$service->hasPermission($user, $store, $permission)) {
            return response()->json([
                'error' => 'User is not authorized to perform this action'
            ], 403);
        }

        return $next($request);
    }
}
