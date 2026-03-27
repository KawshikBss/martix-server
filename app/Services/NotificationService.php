<?php

namespace App\Services;

use App\Notifications\InventoryNotification;
use Illuminate\Support\Facades\Auth;

class NotificationService
{
    public function notifyInventoryStatus($data, $audience = [])
    {
        if (count($audience) === 0) {
            $audience[] = Auth::user();
        }

        $notificationData = [];
        $productName = $data['inventory']['product']['name'];
        $storeName = $data['inventory']['store']['name'];
        $notificationData['action_url'] = '/dashboard/inventory/' . $data['inventory']['id'];
        $notificationData['reference_type'] = "inventory";
        $notificationData['reference_id'] = $data['inventory']['id'];
        $notificationData['type'] = $data['type'];

        switch ($data['type']) {
            case 'low_stock':
                $notificationData['title'] = 'Low Stock';
                $notificationData['messge'] = $productName . ' is running low in ' . $storeName;
                $notificationData['priority'] = 'medium';
                break;
            case 'out_of_stock':
                $notificationData['title'] = 'Out of Stock';
                $notificationData['messge'] = $productName . ' is out of stock in ' . $storeName;
                $notificationData['priority'] = 'high';
                break;
        }
        foreach ($audience as $recipient) {
            $recipient->notify(new InventoryNotification($notificationData));
        }
    }

    public function notifyInventoryAdjustment($data, $audience = [])
    {
        if (count($audience) === 0) {
            $audience[] = Auth::user();
        }

        $productName = $data['inventory']['product']['name'];
        $storeName = $data['inventory']['store']['name'];

        $notificationData = [
            'title' => 'Stock Adjusted',
            'message' => 'Stock ' . ($data['type'] === 'exact' ? 'exacted' : $data['type'] . 'd') . ' for ' . $productName . ' in ' . $storeName . ' by ' . $data['quantity'] . ' units by ' . $data['performed_by']['name'],
            'type' => $data['type'],
            'priority' => 'medium',
            'action_url' => '/dashboard/inventory/movements/' . $data['inventory_movement']['id'],
            'reference_type' => "inventory_movement",
            'reference_id' => $data['inventory_movement']['id']
        ];
        foreach ($audience as $recipient) {
            $recipient->notify(new InventoryNotification($notificationData));
        }
    }
}
