<?php

namespace App\Http\Controllers;

use App\Models\Subscription\Plan;
use App\Models\Subscription\Subscription;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function plans()
    {
        $plans = Plan::where('is_active', true)->with('limits')->get();

        return response()->json($plans);
    }

    public function subscribe(Request $request)
    {
        $data = $request->validate([
            'plan_id' => 'required|exists:plans,id',
            'store_id' => 'required|exists:stores,id',
        ]);

        $subscription = Subscription::create(
            [
                'plan_id' => $data['plan_id'],
                'store_id' => $data['store_id'],
                'status' => 'active',
                'starts_at' => now(),
                'ends_at' => now()->addMonth(),
            ]
        );

        return response()->json($subscription, 201);
    }
}
