<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Permission::insert([
            [
                'name' => 'create_sale',
                'group' => 'sales'
            ],
            [
                'name' => 'view_sales',
                'group' => 'sales'
            ],
            [
                'name' => 'view_sale_details',
                'group' => 'sales'
            ],
            [
                'name' => 'cancel_sale',
                'group' => 'sales'
            ],
            [
                'name' => 'refund_sale',
                'group' => 'sales'
            ],
            [
                'name' => 'add_payment',
                'group' => 'sales'
            ],

            /* [
                'name' => 'create_order',
                'group' => 'orders'
            ],
            [
                'name' => 'view_orders',
                'group' => 'orders'
            ],
            [
                'name' => 'view_order_details',
                'group' => 'orders'
            ],
            [
                'name' => 'update_order',
                'group' => 'orders'
            ],
            [
                'name' => 'cancel_order',
                'group' => 'orders'
            ],
            [
                'name' => 'refund_order',
                'group' => 'orders'
            ], */

            [
                'name' => 'view_inventory',
                'group' => 'inventory'
            ],
            [
                'name' => 'adjust_inventory',
                'group' => 'inventory'
            ],
            [
                'name' => 'view_inventory_movements',
                'group' => 'inventory'
            ],

            [
                'name' => 'view_products',
                'group' => 'products'
            ],
            [
                'name' => 'view_product_details',
                'group' => 'products'
            ],
            [
                'name' => 'create_product',
                'group' => 'products'
            ],
            [
                'name' => 'update_product',
                'group' => 'products'
            ],
            [
                'name' => 'delete_product',
                'group' => 'products'
            ],
            [
                'name' => 'manage_variants',
                'group' => 'products'
            ],

            [
                'name' => 'create_transfer',
                'group' => 'transfers'
            ],
            [
                'name' => 'view_transfers',
                'group' => 'transfers'
            ],
            [
                'name' => 'send_transfer',
                'group' => 'transfers'
            ],
            [
                'name' => 'receive_transfer',
                'group' => 'transfers'
            ],
            [
                'name' => 'cancel_transfer',
                'group' => 'transfers'
            ],

            [
                'name' => 'view_customers',
                'group' => 'customers'
            ],
            [
                'name' => 'create_customer',
                'group' => 'customers'
            ],
            [
                'name' => 'update_customer',
                'group' => 'customers'
            ],

            [
                'name' => 'view_reports',
                'group' => 'reports'
            ],
            [
                'name' => 'view_analytics',
                'group' => 'reports'
            ],

            [
                'name' => 'view_users',
                'group' => 'store_users'
            ],
            [
                'name' => 'add_user',
                'group' => 'store_users'
            ],
            [
                'name' => 'update_user_role',
                'group' => 'store_users'
            ],
            [
                'name' => 'remove_user',
                'group' => 'store_users'
            ],

            [
                'name' => 'manage_store_settings',
                'group' => 'settings'
            ],
        ]);
    }
}
