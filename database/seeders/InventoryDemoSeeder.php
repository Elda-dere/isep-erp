<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Customer;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Seeds sample inventory, warehouses, products, customers and sales for the dashboard demo.
 * Safe to re-run: uses firstOrCreate so duplicates are never inserted.
 */
class InventoryDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new RuntimeException('InventoryDemoSeeder can only run when APP_ENV=local.');
        }

        // ── Units ─────────────────────────────────────────────────────────────
        $units = [];
        foreach (['Piece', 'Pair', 'Box', 'Kg', 'Litre'] as $u) {
            $units[$u] = Unit::firstOrCreate(['name' => $u], ['symbol' => strtolower(substr($u, 0, 3))]);
        }

        // ── Categories ────────────────────────────────────────────────────────
        $categories = [];
        foreach (['Clothing', 'Footwear', 'Accessories', 'Electronics', 'Food & Beverage', 'Office Supplies'] as $c) {
            $categories[$c] = Category::firstOrCreate(['name' => $c]);
        }

        // ── Warehouses ────────────────────────────────────────────────────────
        $warehouses = [];
        foreach ([
            ['Location A', 'Addis Ababa – Main Store'],
            ['Location B', 'Addis Ababa – Warehouse'],
            ['Location C', 'Dire Dawa – Branch'],
            ['Location D', 'Hawassa – Branch'],
        ] as [$name, $location]) {
            $warehouses[$name] = Warehouse::firstOrCreate(['name' => $name], ['location' => $location]);
        }

        // ── Products ──────────────────────────────────────────────────────────
        $productData = [
            ['MRP400', 'Winter Jacket Leather',         'Clothing',        'Piece',  22, 185.00, 223.20, 5,  'Location A'],
            ['MRP405', 'ABCD Western Boot lace up',     'Footwear',        'Pair',   35, 150.00, 223.20, 8,  'Location B'],
            ['MRP620', 'XYZ White Socks',               'Accessories',     'Piece',  23, 5.00,   8.50,   10, 'Location A'],
            ['MRP052', 'ABC Shoes & XYZ Footwear',      'Footwear',        'Pair',   12, 95.00,  120.00, 5,  'Location C'],
            ['MRP310', 'Classic Denim Jacket',          'Clothing',        'Piece',   8, 65.00,   89.00, 10, 'Location B'],
            ['MRP270', 'Leather Briefcase',             'Accessories',     'Piece',  15, 110.00, 145.00, 4,  'Location A'],
            ['ELC100', 'Wireless Headphones Pro',       'Electronics',     'Piece',   6, 55.00,   79.99, 5,  'Location A'],
            ['ELC205', 'USB-C Charging Hub',            'Electronics',     'Piece',  30, 18.00,   29.99, 10, 'Location B'],
            ['OFC001', 'Premium Notebook A5',           'Office Supplies', 'Piece',  50, 2.50,    4.99,  20, 'Location C'],
            ['OFC002', 'Ballpoint Pens (Box of 12)',    'Office Supplies', 'Box',    40, 3.00,    5.50,  15, 'Location D'],
            ['FDB100', 'Ethiopian Coffee Blend 500g',   'Food & Beverage', 'Piece',   3, 8.00,   12.00, 10, 'Location C'],
            ['ACC300', 'Canvas Backpack 30L',           'Accessories',     'Piece',  18, 35.00,  55.00,  6,  'Location D'],
        ];

        $products = [];
        foreach ($productData as [$sku, $name, $cat, $unit, $qty, $cost, $sell, $reorder, $warehouse]) {
            $product = Product::firstOrCreate(
                ['sku' => $sku],
                [
                    'name'          => $name,
                    'category_id'   => $categories[$cat]->id,
                    'unit_id'       => $units[$unit]->id,
                    'cost_price'    => $cost,
                    'selling_price' => $sell,
                    'reorder_level' => $reorder,
                    'is_active'     => true,
                ],
            );

            // Set inventory stock
            InventoryStock::updateOrCreate(
                ['product_id' => $product->id, 'warehouse_id' => $warehouses[$warehouse]->id],
                ['quantity' => $qty],
            );

            // Add a second warehouse entry for some products (simulate "multiple")
            if (in_array($sku, ['MRP400', 'MRP620', 'MRP405', 'ELC100'])) {
                $secondary = $sku === 'MRP405' ? 'Location D' : 'Location B';
                if ($secondary !== $warehouse) {
                    InventoryStock::updateOrCreate(
                        ['product_id' => $product->id, 'warehouse_id' => $warehouses[$secondary]->id],
                        ['quantity' => rand(2, 15)],
                    );
                }
            }

            $products[$sku] = $product;
        }

        // ── Customers ─────────────────────────────────────────────────────────
        $customerData = [
            ['Abebe Girma',    'abebe@example.com',    '+251911000001'],
            ['Sara Kebede',    'sara@example.com',     '+251911000002'],
            ['Mulu Tesfaye',   'mulu@example.com',     '+251911000003'],
            ['Hana Bekele',    'hana@example.com',     '+251911000004'],
            ['Dawit Alemu',    'dawit@example.com',    '+251911000005'],
        ];
        $customers = [];
        foreach ($customerData as [$name, $email, $phone]) {
            $customers[] = Customer::firstOrCreate(
                ['email' => $email],
                ['name' => $name, 'phone' => $phone],
            );
        }

        // ── Sales ─────────────────────────────────────────────────────────────
        $admin = User::where('email', config('erp.admin.email'))->first();
        if (! $admin) {
            return; // cannot create sales without an admin user
        }

        $saleSeeds = [
            ['SAL-0001', $customers[0], $products['MRP400'], 2, 'paid',      now()->subDays(1)],
            ['SAL-0002', $customers[1], $products['MRP405'], 1, 'paid',      now()->subDays(1)],
            ['SAL-0003', $customers[2], $products['MRP620'], 5, 'pending',   now()->subHours(3)],
            ['SAL-0004', $customers[3], $products['ELC100'], 1, 'paid',      now()->subHours(5)],
            ['SAL-0005', $customers[4], $products['OFC001'], 10, 'paid',     now()->subHours(2)],
            ['SAL-0006', $customers[0], $products['ACC300'], 1, 'cancelled', now()->subDays(2)],
            ['SAL-0007', $customers[1], $products['ELC205'], 2, 'paid',      today()],
            ['SAL-0008', $customers[2], $products['MRP310'], 1, 'pending',   today()],
        ];

        foreach ($saleSeeds as [$num, $customer, $product, $qty, $status, $date]) {
            if (Sale::where('sale_number', $num)->exists()) {
                continue;
            }

            $total = round($product->selling_price * $qty, 2);

            $sale = Sale::create([
                'sale_number'  => $num,
                'customer_id'  => $customer->id,
                'status'       => $status,
                'total_amount' => $total,
                'currency'     => Sale::DEFAULT_CURRENCY,
                'created_by'   => $admin->id,
                'created_at'   => $date,
            ]);

            // Find a warehouse that has stock for this product
            $stockRow = InventoryStock::where('product_id', $product->id)->first();
            SaleItem::create([
                'sale_id'       => $sale->id,
                'product_id'    => $product->id,
                'warehouse_id'  => $stockRow?->warehouse_id,
                'quantity'      => $qty,
                'unit_price'    => $product->selling_price,
                'line_total'    => $total,
            ]);
        }
    }
}
