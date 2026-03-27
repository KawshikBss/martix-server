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
        $notificationData['action_url'] = '/dashboard/inventory/' . $data['inventory']['id'];
        $notificationData['reference_type'] = "inventory";
        $notificationData['reference_id'] = $data['inventory']['id'];
        $notificationData['type'] = $data['type'];

        switch ($data['type']) {
            case 'low_stock':
                $notificationData['title'] = 'Low Stock';
                $notificationData['messge'] = $productName . ' is running low.';
                $notificationData['priority'] = 'medium';
                break;
            case 'out_of_stock':
                $notificationData['title'] = 'Out of Stock';
                $notificationData['messge'] = $productName . ' is out of stock.';
                $notificationData['priority'] = 'high';
                break;
        }
        foreach ($audience as $recipient) {
            $recipient->notify(new InventoryNotification($notificationData));
        }
    }
}
