<?php

namespace App\Http\Resources\Store;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoreResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'image_url' => $this->image_url,
            'name' => $this->name,
            'unique_id' => $this->unique_id,
            'branch' => $this->branch,
            'type' => $this->type,
            'description' => $this->description,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'address_2' => $this->address_2,
            'owner' => $this->owner,
            'current_inventory_count' => $this->getCurrentInventoryCountAttribute(),
            'current_inventory_value' => $this->getCurrentInventoryValueAttribute(),
            'can_edit' => $this->getCanEditAttribute(),
            'low_stock_items_count' => $this->getLowStockItemsCountAttribute(),
            'inventories' => $this->inventories()->with(['product'])->get(),
            'manager_id' => $this->manager_id,
            'manager' => $this->manager,
            'staff' => $this->staff,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at->diffForHumans(),
            'updated_at' => $this->updated_at->diffForHumans(),
            'today_sales' => $this->sales()->where('created_at', now()->day)->sum('grand_total'),
            'monthly_sales' => $this->sales()->whereBetween('created_at', [now()->copy()->startOfMonth(), now()->copy()->endOfMonth()])->sum('grand_total'),
        ];
    }
}
