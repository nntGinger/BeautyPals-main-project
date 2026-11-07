<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Models\Vendor;
use App\Services\VendorStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VendorStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function postStatus(User $actor, Vendor $vendor, array $data): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($actor)
            ->post(route('admin.vendors.status', $vendor), $data);
    }

    /* ------------------------------------------------------------------
     * Approve: status, approved_by/at, vendor can enter dashboard
     * ----------------------------------------------------------------- */

    public function test_admin_approves_pending_vendor_and_vendor_can_enter_dashboard(): void
    {
        $admin  = $this->admin();
        $vendor = Vendor::factory()->pending()->create();

        $this->postStatus($admin, $vendor, ['action' => 'approve'])
            ->assertRedirect(route('admin.vendors.show', $vendor));

        $vendor->refresh();
        $this->assertSame('approved', $vendor->status);
        $this->assertSame($admin->id, $vendor->approved_by);
        $this->assertNotNull($vendor->approved_at);

        // Approved vendor can now enter vendor area
        $this->actingAs($vendor->owner)->get('/vendor')->assertOk();
    }

    /* ------------------------------------------------------------------
     * Reject: requires reason, sets reject_reason, clears approval
     * ----------------------------------------------------------------- */

    public function test_admin_rejects_vendor_with_reason(): void
    {
        $admin  = $this->admin();
        $vendor = Vendor::factory()->pending()->create();

        $this->postStatus($admin, $vendor, ['action' => 'reject', 'reason' => 'Hồ sơ thiếu thông tin.'])
            ->assertRedirect(route('admin.vendors.show', $vendor));

        $vendor->refresh();
        $this->assertSame('rejected', $vendor->status);
        $this->assertSame('Hồ sơ thiếu thông tin.', $vendor->reject_reason);
    }

    public function test_reject_without_reason_fails_validation_and_no_audit(): void
    {
        $admin  = $this->admin();
        $vendor = Vendor::factory()->pending()->create();

        $this->postStatus($admin, $vendor, ['action' => 'reject'])
            ->assertSessionHasErrors('reason');

        $vendor->refresh();
        $this->assertSame('pending', $vendor->status);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    /* ------------------------------------------------------------------
     * Suspend: requires reason, uses suspended_reason column
     * ----------------------------------------------------------------- */

    public function test_admin_suspends_approved_vendor_with_reason(): void
    {
        $admin  = $this->admin();
        $vendor = Vendor::factory()->approved()->create();

        $this->postStatus($admin, $vendor, ['action' => 'suspend', 'reason' => 'Vi phạm điều khoản.'])
            ->assertRedirect(route('admin.vendors.show', $vendor));

        $vendor->refresh();
        $this->assertSame('suspended', $vendor->status);
        $this->assertSame('Vi phạm điều khoản.', $vendor->suspended_reason);
    }

    public function test_suspend_without_reason_fails_and_no_audit(): void
    {
        $admin  = $this->admin();
        $vendor = Vendor::factory()->approved()->create();

        $this->postStatus($admin, $vendor, ['action' => 'suspend'])
            ->assertSessionHasErrors('reason');

        $vendor->refresh();
        $this->assertSame('approved', $vendor->status);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    /* ------------------------------------------------------------------
     * Unsuspend
     * ----------------------------------------------------------------- */

    public function test_admin_unsuspends_vendor(): void
    {
        $admin  = $this->admin();
        $vendor = Vendor::factory()->suspended()->create();

        $this->postStatus($admin, $vendor, ['action' => 'unsuspend'])
            ->assertRedirect(route('admin.vendors.show', $vendor));

        $vendor->refresh();
        $this->assertSame('approved', $vendor->status);
        $this->assertNull($vendor->suspended_reason);
    }

    /* ------------------------------------------------------------------
     * Invalid transition: pending → suspended
     * ----------------------------------------------------------------- */

    public function test_invalid_transition_is_blocked(): void
    {
        $admin  = $this->admin();
        $vendor = Vendor::factory()->pending()->create();

        // 'suspend' is only valid from 'approved'; pending→suspended is invalid
        $this->postStatus($admin, $vendor, ['action' => 'suspend', 'reason' => 'test'])
            ->assertRedirect(route('admin.vendors.show', $vendor))
            ->assertSessionHasErrors('action');

        $vendor->refresh();
        $this->assertSame('pending', $vendor->status);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    /* ------------------------------------------------------------------
     * Non-admin → 403
     * ----------------------------------------------------------------- */

    public function test_customer_cannot_change_vendor_status(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $vendor   = Vendor::factory()->pending()->create();

        $this->actingAs($customer)
            ->post(route('admin.vendors.status', $vendor), ['action' => 'approve'])
            ->assertForbidden();

        $vendor->refresh();
        $this->assertSame('pending', $vendor->status);
    }

    public function test_vendor_owner_cannot_change_own_vendor_status(): void
    {
        $vendor = Vendor::factory()->pending()->create();

        $this->actingAs($vendor->owner)
            ->post(route('admin.vendors.status', $vendor), ['action' => 'approve'])
            ->assertForbidden();
    }

    /* ------------------------------------------------------------------
     * Audit log: exactly one row per transition
     * ----------------------------------------------------------------- */

    public function test_each_successful_transition_writes_exactly_one_audit_log(): void
    {
        $admin  = $this->admin();
        $vendor = Vendor::factory()->pending()->create();

        $this->postStatus($admin, $vendor, ['action' => 'approve']);
        $this->assertDatabaseCount('audit_logs', 1);

        $log = AuditLog::first();
        $this->assertSame($admin->id, $log->actor_id);
        $this->assertSame('approve_vendor', $log->action);
        $this->assertSame('vendor', $log->entity_type);
        $this->assertSame($vendor->id, $log->entity_id);
        $this->assertSame('pending', $log->old_values['status']);
        $this->assertSame('approved', $log->new_values['status']);

        // Suspend
        $vendor->refresh();
        $this->postStatus($admin, $vendor, ['action' => 'suspend', 'reason' => 'Test']);
        $this->assertDatabaseCount('audit_logs', 2);
    }

    public function test_reject_audit_contains_reason_in_new_values(): void
    {
        $admin  = $this->admin();
        $vendor = Vendor::factory()->pending()->create();

        $this->postStatus($admin, $vendor, ['action' => 'reject', 'reason' => 'Lý do từ chối cụ thể.']);

        $log = AuditLog::first();
        $this->assertSame('reject_vendor', $log->action);
        $this->assertSame('Lý do từ chối cụ thể.', $log->new_values['reason']);
    }

    /* ------------------------------------------------------------------
     * Rollback: audit write failure rolls back vendor status
     * ----------------------------------------------------------------- */

    public function test_audit_write_failure_rolls_back_vendor_status(): void
    {
        $admin  = $this->admin();
        $vendor = Vendor::factory()->pending()->create();

        // Create a subclass of VendorStatusService whose writeAudit throws
        $failingService = new class extends VendorStatusService {
            protected function writeAudit(...$args): void
            {
                throw new \RuntimeException('Simulated audit failure');
            }
        };

        try {
            $failingService->transition($vendor, 'approved', $admin);
            $this->fail('Expected RuntimeException was not thrown.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Simulated audit failure', $e->getMessage());
        }

        // Vendor status must be rolled back
        $vendor->refresh();
        $this->assertSame('pending', $vendor->status);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    /* ------------------------------------------------------------------
     * Vendor self-resubmit (rejected → pending)
     * ----------------------------------------------------------------- */

    public function test_rejected_vendor_can_resubmit(): void
    {
        $vendor = Vendor::factory()->rejected('Hồ sơ cũ sai.')->create();

        $this->actingAs($vendor->owner)
            ->post(route('vendor.resubmit'))
            ->assertRedirect(route('vendor.pending'));

        $vendor->refresh();
        $this->assertSame('pending', $vendor->status);
        $this->assertNull($vendor->reject_reason);

        // Audit log created by service
        $this->assertDatabaseCount('audit_logs', 1);
        $this->assertSame('resubmit_vendor', AuditLog::first()->action);
    }

    public function test_pending_vendor_cannot_resubmit(): void
    {
        $vendor = Vendor::factory()->pending()->create();

        $this->actingAs($vendor->owner)
            ->post(route('vendor.resubmit'))
            ->assertRedirect(route('vendor.pending'))
            ->assertSessionHasErrors('status');

        // Status unchanged, no audit
        $vendor->refresh();
        $this->assertSame('pending', $vendor->status);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    /* ------------------------------------------------------------------
     * Admin vendor list
     * ----------------------------------------------------------------- */

    public function test_admin_can_view_vendor_list_filtered_by_status(): void
    {
        $admin = $this->admin();
        Vendor::factory()->pending()->count(2)->create();
        Vendor::factory()->approved()->create();

        $this->actingAs($admin)
            ->get(route('admin.vendors.index', ['status' => 'pending']))
            ->assertOk()
            ->assertViewHas('vendors');
    }
}
