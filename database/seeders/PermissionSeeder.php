<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /** Every permission the application checks. PROPOSAL: confirm with the team. */
    public const NAMES = [
        // Access control and audit (M1)
        'users.view',
        'users.manage',
        'roles.manage',
        'audit.view',

        // Employees (M2)
        'departments.manage',
        'positions.manage',
        'employees.view',
        'employees.manage',

        // QR attendance (M3)
        'qr.own',
        'attendance.scan',
        'attendance.view_own',
        'attendance.view_all',
        'attendance.correct',
        'attendance.revoke_qr',
        'networks.manage',

        // Product catalogue (M4)
        'products.view',
        'products.manage',

        // Inventory (M5)
        'warehouses.manage',
        'inventory.view',
        'inventory.adjust',

        // Procurement (M6)
        'suppliers.manage',
        'purchase_orders.view',
        'purchase_orders.manage',
        'goods_receipts.manage',

        // Sales (M7)
        'customers.manage',
        'sales.view',
        'sales.create',
        'sales.cancel',
        'sales.price_override',

        // Payments (M8)
        'payments.view',
        'payments.start',
        'payments.verify',
        'payments.view_payload',

        // Dashboards and reports
        'dashboard.view',
        'reports.view',
    ];

    public function run(): void
    {
        foreach (self::NAMES as $name) {
            Permission::firstOrCreate(['name' => $name]);
        }
    }
}