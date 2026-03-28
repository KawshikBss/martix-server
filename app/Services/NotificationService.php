<?php

namespace App\Services;

use App\Notifications\InventoryNotification;
use App\Notifications\SaleNotification;
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
                $notificationData['message'] = $productName . ' is running low in ' . $storeName;
                $notificationData['priority'] = 'medium';
                break;
            case 'out_of_stock':
                $notificationData['title'] = 'Out of Stock';
                $notificationData['message'] = $productName . ' is out of stock in ' . $storeName;
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

    public function notifySaleStatus($data, $audience)
    {
        if (count($audience) === 0) {
            $audience[] = Auth::user();
        }

        $notificationData = [
            'type' => 'order_' . $data['type'],
            'action_url' => '/dashboard/sales/' . $data['sale']['id'],
            'reference_type' => "sale",
            'reference_id' => $data['sale']['id']
        ];

        switch ($data['type']) {
            case 'create':
                $notificationData['title'] = 'New Order Created';
                $notificationData['message'] = 'Order #' . $data['sale_number'] . ' created by ' . $data['performed_by']['name'];
                $notificationData['priority'] = 'medium';
                break;
            case 'complete':
                $notificationData['title'] = 'Order Completed';
                $notificationData['message'] = 'Order #' . $data['sale_number'] . ' completed by ' . $data['performed_by']['name'];
                $notificationData['priority'] = 'low';
                break;
            case 'cancel':
                $notificationData['title'] = 'Order Cancelled';
                $notificationData['message'] = 'Order #' . $data['sale_number'] . ' was cancelled by ' . $data['performed_by']['name'];
                $notificationData['priority'] = 'high';
                break;
            case 'refund':
                $notificationData['title'] = 'Order Refunded';
                $notificationData['message'] = 'Refund issued for Order #' . $data['sale_number'] . ' by ' . $data['performed_by']['name'];
                $notificationData['priority'] = 'high';
                break;
            case 'partial_payment':
                $notificationData['title'] = 'Partial Payment Received';
                $notificationData['message'] = 'Partial payment received for Order #' . $data['sale_number'];
                $notificationData['priority'] = 'medium';
                break;
            case 'payment_complete':
                $notificationData['title'] = 'Payment Completed';
                $notificationData['message'] = 'Order #' . $data['sale_number'] . ' fully paid';
                $notificationData['priority'] = 'medium';
                break;
        }

        foreach ($audience as $recipient) {
            $recipient->notify(new SaleNotification($notificationData));
        }
    }

    public function notifyTransferStatus($data, $audience)
    {
        if (count($audience) === 0) {
            $audience[] = Auth::user();
        }

        $notificationData = [
            'type' => 'transfer_' . $data['type'],
            'action_url' => '/dashboard/stores/transfer/history/' . $data['transfer']['id'],
            'reference_type' => "transfer",
            'reference_id' => $data['transfer']['id']
        ];

        $sourceStoreName = $data['source_inventory']['store']['name'];
        $destinationStoreName = $data['destination_inventory']['store']['name'];

        switch ($data['type']) {
            case 'create':
                $notificationData['title'] = 'Stock Transfer Incoming';
                $notificationData['message'] = 'Transfer from ' . $sourceStoreName . ' to ' . $destinationStoreName;
                $notificationData['priority'] = 'medium';
                break;
            case 'receive':
                $notificationData['title'] = 'Transfer Received';
                $notificationData['message'] = 'Transfer received from ' . $sourceStoreName;
                $notificationData['priority'] = 'low';
                break;
            case 'cancel':
                $notificationData['title'] = 'Transfer Cancelled';
                $notificationData['message'] = 'Transfer #' . $data['transfer']['id'] . ' was cancelled by ' . $data['performed_by']['name'];
                $notificationData['priority'] = 'high';
                break;
        }

        foreach ($audience as $recipient) {
            $recipient->notify(new InventoryNotification($notificationData));
        }
    }
}
