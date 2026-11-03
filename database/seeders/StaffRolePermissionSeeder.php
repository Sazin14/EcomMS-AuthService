<?php

namespace Database\Seeders;

use App\Models\Staff;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class StaffRolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // Staff
            'staff.view',
            'staff.create',
            'staff.update',
            'staff.delete',
            'staff.assign-role',
            'staff.remove-role',

            // Roles
            'role.view',
            'role.create',
            'role.update',
            'role.delete',

            // Permissions
            'permission.view',
            'permission.assign',
            'permission.revoke',

            // Products
            'product.view',
            'product.create',
            'product.update',
            'product.delete',

            // Categories
            'category.view',
            'category.create',
            'category.update',
            'category.delete',

            // Brands
            'brand.view',
            'brand.create',
            'brand.update',
            'brand.delete',

            // Attributes
            'attribute.view',
            'attribute.create',
            'attribute.update',
            'attribute.delete',

            // Inventory
            'inventory.view',
            'inventory.adjust',
            'inventory.transfer',
            'inventory.receive',
            'inventory.reserve',
            'inventory.manage',      // reorder levels, pre-order / flash-sale policy
            'warehouse.manage',      // create / edit warehouses

            // Purchasing (InventoryService)
            'purchasing.view',
            'purchasing.manage',

            // Orders (OrderService)
            'order.view',
            'order.confirm',
            'order.cancel',
            'fulfillment.manage',
            'payment.manage',
            'return.manage',
            'refund.manage',

            // Notifications (NotificationService)
            'notification.view',
            'notification.manage',

            // Sales
            'sales.view',
            'sales.report',
            'sale.create',
            'sale.update',
            'sale.cancel',

            // Promotions
            'promotion.view',
            'promotion.create',
            'promotion.update',
            'promotion.delete',

            // General
            'dashboard.view',
            'report.view',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'staff',
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Roles
        |--------------------------------------------------------------------------
        */

        $superAdmin = Role::firstOrCreate([
            'name' => 'super-admin',
            'guard_name' => 'staff',
        ]);

        $admin = Role::firstOrCreate([
            'name' => 'admin',
            'guard_name' => 'staff',
        ]);

        $generalManager = Role::firstOrCreate([
            'name' => 'general-manager',
            'guard_name' => 'staff',
        ]);

        $productManager = Role::firstOrCreate([
            'name' => 'product-manager',
            'guard_name' => 'staff',
        ]);

        $inventoryManager = Role::firstOrCreate([
            'name' => 'inventory-manager',
            'guard_name' => 'staff',
        ]);

        $salesManager = Role::firstOrCreate([
            'name' => 'sales-manager',
            'guard_name' => 'staff',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Super Admin
        |--------------------------------------------------------------------------
        */

        $superAdmin->syncPermissions(
            Permission::where('guard_name', 'staff')->get()
        );

        /*
        |--------------------------------------------------------------------------
        | Admin
        |--------------------------------------------------------------------------
        */

        $admin->syncPermissions([
            'staff.view',
            'staff.create',
            'staff.update',
            'staff.delete',
            'staff.assign-role',
            'staff.remove-role',

            'role.view',
            'permission.view',

            'product.view',
            'product.create',
            'product.update',
            'product.delete',

            'category.view',
            'category.create',
            'category.update',
            'category.delete',

            'brand.view',
            'brand.create',
            'brand.update',
            'brand.delete',

            'attribute.view',
            'attribute.create',
            'attribute.update',
            'attribute.delete',

            'inventory.view',
            'inventory.adjust',
            'inventory.receive',

            'sales.view',
            'sales.report',

            'order.view',
            'notification.view',

            'dashboard.view',
            'report.view',
        ]);

        /*
        |--------------------------------------------------------------------------
        | General Manager
        |--------------------------------------------------------------------------
        */

        $generalManager->syncPermissions([
            'dashboard.view',
            'report.view',

            'staff.view',

            'product.view',
            'category.view',
            'brand.view',
            'attribute.view',

            'inventory.view',

            'sales.view',
            'sales.report',

            'promotion.view',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Product Manager
        |--------------------------------------------------------------------------
        */

        $productManager->syncPermissions([
            'dashboard.view',

            'product.view',
            'product.create',
            'product.update',
            'product.delete',

            'category.view',
            'category.create',
            'category.update',
            'category.delete',

            'brand.view',
            'brand.create',
            'brand.update',
            'brand.delete',

            'attribute.view',
            'attribute.create',
            'attribute.update',
            'attribute.delete',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Inventory Manager
        |--------------------------------------------------------------------------
        */

        $inventoryManager->syncPermissions([
            'dashboard.view',

            'product.view',

            'inventory.view',
            'inventory.adjust',
            'inventory.transfer',
            'inventory.receive',
            'inventory.reserve',
            'inventory.manage',
            'warehouse.manage',
            'purchasing.view',
            'purchasing.manage',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Sales Manager
        |--------------------------------------------------------------------------
        */

        $salesManager->syncPermissions([
            'dashboard.view',

            'product.view',
            'inventory.view',

            'sales.view',
            'sales.report',
            'sale.create',
            'sale.update',
            'sale.cancel',

            'order.view',
            'order.confirm',
            'order.cancel',
            'fulfillment.manage',
            'return.manage',

            'promotion.view',
            'promotion.create',
            'promotion.update',
            'promotion.delete',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Create Initial Super Admin
        |--------------------------------------------------------------------------
        */

        $superAdminStaff = Staff::firstOrCreate(
            [
                'email' => 'superadmin@example.com',
            ],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('Password123'),
            ]
        );

        $superAdminStaff->syncRoles([
            'super-admin',
        ]);
    }
}