<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AllTablesTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_29_business_tables_exist(): void
    {
        $tables = [
            'users', 'roles', 'user_roles', 'permissions', 'role_permissions', 'audit_logs',
            'departments', 'positions', 'employees',
            'qr_codes', 'attendance_records', 'attendance_events', 'approved_networks',
            'categories', 'units', 'products',
            'warehouses', 'inventory_stocks', 'stock_movements',
            'suppliers', 'purchase_orders', 'purchase_order_items',
            'goods_receipts', 'goods_receipt_items',
            'customers', 'sales', 'sale_items',
            'payments', 'payment_events',
        ];

        $this->assertCount(29, $tables);

        foreach ($tables as $table) {
            $this->assertTrue(Schema::hasTable($table), "Missing table: {$table}");
        }
    }

    public function test_money_and_quantity_columns_exist(): void
    {
        $this->assertTrue(Schema::hasColumns('products', ['cost_price', 'selling_price', 'reorder_level']));
        $this->assertTrue(Schema::hasColumns('sale_items', ['quantity', 'unit_price', 'line_total']));
        $this->assertTrue(Schema::hasColumns('payments', ['amount', 'currency', 'tx_ref', 'status']));
        $this->assertTrue(Schema::hasColumns('stock_movements', ['quantity', 'movement_type', 'performed_by']));
    }
}