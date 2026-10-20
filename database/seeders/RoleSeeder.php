<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /** Default roles from SRS section 2.2 and their permissions. PROPOSAL: confirm with the team. */
    public const MAP = [
        'Administrator' => PermissionSeeder::NAMES,

        'HR Manager' => [
            'employees.view', 'employees.manage', 'departments.manage', 'positions.manage',
            'attendance.view_all', 'attendance.correct', 'attendance.revoke_qr',
            'reports.view',
        ],

        'Employee' => [
            'qr.own', 'attendance.view_own',
        ],

        'Attendance Scanner' => [
            'attendance.scan',
        ],

        'Warehouse Clerk' => [
            'products.view', 'warehouses.manage', 'inventory.view', 'inventory.adjust',
            'goods_receipts.manage', 'purchase_orders.view', 'reports.view',
        ],

        'Procurement Officer' => [
            'products.view', 'suppliers.manage', 'purchase_orders.view',
            'purchase_orders.manage', 'reports.view',
        ],

        'Sales Clerk' => [
            'products.view', 'inventory.view', 'customers.manage',
            'sales.view', 'sales.create', 'sales.cancel',
            'payments.view', 'payments.start', 'reports.view',
        ],

        // Read-only across modules (SRS section 2.2).
        'Manager' => [
            'dashboard.view', 'reports.view', 'employees.view', 'attendance.view_all',
            'products.view', 'inventory.view', 'purchase_orders.view',
            'sales.view', 'payments.view',
        ],
    ];

    public function run(): void
    {
        foreach (self::MAP as $roleName => $permissionNames) {
            $role = Role::firstOrCreate(['name' => $roleName]);

            $ids = Permission::whereIn('name', $permissionNames)->pluck('id');

            // Adds missing links and never removes links an administrator added later.
            $role->permissions()->syncWithoutDetaching($ids);
        }
    }
}