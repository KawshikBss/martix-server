<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InventoryNotification extends Notification
{
    use Queueable;

    private $type, $data;

    /**
     * Create a new notification instance.
     */
    public function __construct($type, $data)
    {
        $this->type = $type;
        $this->data = $data;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    /* public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->line('The introduction to the notification.')
            ->action('Notification Action', url('/'))
            ->line('Thank you for using our application!');
    } */

    public function toDatabase($notifiable)
    {

        return match ($this->type) {
            'low_stock' => [
                'type' => 'low_stock',
                'title' => 'Low Stock Alert',
                'message' => $this->data['product_name'] . ' is running low',
                'inventory_id' => $this->data['inventory_id'],
            ],

            'out_of_stock' => [
                'type' => 'out_of_stock',
                'title' => 'Out of Stock',
                'message' => $this->data['product_name'] . ' is out of stock',
                'inventory_id' => $this->data['inventory_id'],
            ],

            default => [
                'type' => 'unknown',
                'title' => 'Notification',
                'message' => 'Something happened'
            ]
        };
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
