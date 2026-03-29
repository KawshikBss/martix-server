<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Fetch roles
        $owner = Role::where('name', 'owner')->first();
        $manager = Role::where('name', 'manager')->first();
        $staff = Role::where('name', 'staff')->first();

        // Fetch all permissions
        $allPermissions = Permission::pluck('id');

        // =========================
        // OWNER → ALL PERMISSIONS
        // =========================
        $owner->permissions()->sync($allPermissions);

        // =========================
        // MANAGER PERMISSIONS
        // =========================
        $managerPermissions = [
            // Sales
            'create_sale',
            'view_sales',
            'view_sale_details',
            'cancel_sale',
            'refund_sale',
            'add_payment',

            // Orders
            'create_order',
            'view_orders',
            'view_order_details',
            'update_order',
            'cancel_order',
            'refund_order',

            // Inventory
            'view_inventory',
            'adjust_inventory',
            'view_inventory_movements',

            // Products
            'view_products',
            'view_product_details',
            'create_product',
            'update_product',
            'manage_variants',
            // 'delete_product', // optional (keep restricted)

            // Transfers
            'create_transfer',
            'view_transfers',
            'send_transfer',
            'receive_transfer',
            'cancel_transfer',

            // Customers
            'view_customers',
            'create_customer',
            'update_customer',

            // Reports
            'view_reports',
            // 'view_analytics', // optional restriction

            // Users (LIMITED)
            'view_users',
            // 'add_user', // optional (owner only)
            // 'update_user_role',
            // 'remove_user',

            // Settings (optional)
            // 'manage_store_settings',
        ];

        $managerPermissionIds = Permission::whereIn('name', $managerPermissions)->pluck('id');
        $manager->permissions()->sync($managerPermissionIds);

        // =========================
        // STAFF PERMISSIONS
        // =========================
        $staffPermissions = [
            // Sales
            'create_sale',
            'view_sales',
            'view_sale_details',
            'add_payment',

            // Orders (limited)
            'view_orders',
            'view_order_details',
            'update_order',

            // Inventory
            'view_inventory',

            // Products
            'view_products',
            'view_product_details',

            // Transfers (view only)
            'view_transfers',

            // Customers
            'view_customers',
            'create_customer',

            // NO reports
            // NO user management
            // NO settings
        ];

        $staffPermissionIds = Permission::whereIn('name', $staffPermissions)->pluck('id');
        $staff->permissions()->sync($staffPermissionIds);
    }
}
