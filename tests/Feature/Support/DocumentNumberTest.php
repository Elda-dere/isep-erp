<?php

namespace Tests\Feature\Support;

use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Support\DocumentNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentNumberTest extends TestCase
{
    use RefreshDatabase;

    private Supplier $supplier;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->supplier = Supplier::create(['name' => 'Acme Trading']);
        $this->user = User::factory()->create();
    }

    // Stores an order with a chosen number, to prepare "the highest existing number".
    private function order(string $number): void
    {
        PurchaseOrder::create([
            'supplier_id' => $this->supplier->id,
            'order_number' => $number,
            'status' => PurchaseOrder::STATUS_DRAFT,
            'created_by' => $this->user->id,
        ]);
    }

    private function next(string $prefix): string
    {
        return DocumentNumber::next(PurchaseOrder::class, 'order_number', $prefix);
    }

    public function test_the_first_number_of_a_prefix_is_0001(): void
    {
        $this->assertSame('PO-2026-0001', $this->next('PO-2026-'));
    }

    public function test_the_next_number_follows_the_highest_existing_one(): void
    {
        $this->order('PO-2026-0001');
        $this->order('PO-2026-0003'); // a gap is fine: the next one follows the highest
        $this->assertSame('PO-2026-0004', $this->next('PO-2026-'));

        // Numbers are compared as numbers: 10000 is higher than 9999.
        $this->order('PO-2026-9999');
        $this->assertSame('PO-2026-10000', $this->next('PO-2026-'));
        $this->order('PO-2026-10000');
        $this->assertSame('PO-2026-10001', $this->next('PO-2026-'));
    }

    public function test_each_prefix_counts_on_its_own(): void
    {
        $this->order('PO-2026-0007');

        $this->assertSame('PO-2026-0008', $this->next('PO-2026-'));
        $this->assertSame('PO-2027-0001', $this->next('PO-2027-')); // a new year starts again
    }
}
