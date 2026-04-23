<?php

namespace App\Services;

use App\Models\Subscription\UsageLog;

class SubscriptionService
{
    public function getLimit($store, $key)
    {
        if (!$store->subscription || !$store->subscription->plan) {
            return 0; // or throw exception depending on your logic
        }

        return $store->subscription
            ->plan
            ->limits
            ->where('key', $key)
            ->first()?->value;
    }

    public function getUsage($store, $key)
    {
        $today = now();

        $usage = UsageLog::where([
            'store_id' => $store->id,
            'key' => $key,
            'period_start' => $today->startOfMonth(),
        ])->first();

        return $usage?->used ?? 0;
    }

    public function canUse($store, $key, $currentUsage = null)
    {
        $limit = $this->getLimit($store, $key);

        if ($limit === null) return true;

        if ($key === 'products_limit') {
            return $store->products()->count() < $limit;
        }

        $usage = $currentUsage ?? $this->getUsage($store, $key);

        return $usage < $limit;
    }

    public function incrementUsage($store, $key)
    {
        if (!$this->canUse($store, $key)) {
            throw new \Exception("Limit exceeded for {$key}");
        }

        $start = now()->startOfMonth();
        $end = now()->endOfMonth();

        $usage = UsageLog::firstOrCreate([
            'store_id' => $store->id,
            'key' => $key,
            'period_start' => $start,
        ], [
            'period_end' => $end,
            'used' => 0,
        ]);

        $usage->increment('used');
    }
}
