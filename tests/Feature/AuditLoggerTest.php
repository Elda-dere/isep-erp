<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class AuditLoggerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_records_who_what_and_which_values(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        AuditLogger::log('product.price_changed', 'product', 7,
            ['selling_price' => '10.00'], ['selling_price' => '12.50']);

        $row = AuditLog::firstOrFail();

        $this->assertEquals($user->id, $row->user_id);
        $this->assertSame('product.price_changed', $row->action);
        $this->assertSame('product', $row->entity_type);
        $this->assertEquals(7, $row->entity_id);
        $this->assertSame(['selling_price' => '10.00'], $row->old_values);
        $this->assertSame(['selling_price' => '12.50'], $row->new_values);
        $this->assertNotNull($row->created_at);
    }

    public function test_system_actions_have_no_user(): void
    {
        AuditLogger::log('payment.expired', 'payment', 3);

        $this->assertNull(AuditLog::firstOrFail()->user_id);
    }

    public function test_secrets_are_masked_even_when_nested(): void
    {
        AuditLogger::log('user.created', 'user', 1, null, [
            'name' => 'Ana',
            'password' => 'plain-secret-1',
            'nested' => ['token_hash' => 'abc123', 'visible' => 'yes'],
        ]);

        $values = AuditLog::firstOrFail()->new_values;

        $this->assertSame('Ana', $values['name']);
        $this->assertSame('********', $values['password']);
        $this->assertSame('********', $values['nested']['token_hash']);
        $this->assertSame('yes', $values['nested']['visible']);
    }

    public function test_a_row_cannot_be_edited(): void
    {
        AuditLogger::log('x.test');
        $row = AuditLog::firstOrFail();

        $this->expectException(LogicException::class);

        $row->update(['action' => 'changed']);
    }

    public function test_a_row_cannot_be_deleted(): void
    {
        AuditLogger::log('x.test');
        $row = AuditLog::firstOrFail();

        $this->expectException(LogicException::class);

        $row->delete();
    }
}