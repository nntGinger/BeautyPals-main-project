<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class VendorStatusService
{
    /**
     * Valid status transitions: currentStatus => [allowedNextStatuses]
     */
    private const VALID_TRANSITIONS = [
        'pending'   => ['approved', 'rejected'],
        'approved'  => ['suspended'],
        'suspended' => ['approved'],
        'rejected'  => ['pending'],
    ];

    /**
     * Statuses that REQUIRE a reason from the actor.
     */
    private const REQUIRES_REASON = ['rejected', 'suspended'];

    /**
     * Transition a vendor to a new status, writing an audit log in the same transaction.
     *
     * @throws InvalidArgumentException  When the transition is not allowed.
     * @throws \RuntimeException         When a reason is required but not provided.
     */
    public function transition(
        Vendor $vendor,
        string $newStatus,
        User   $actor,
        ?string $reason = null,
    ): void {
        $oldStatus = $vendor->status;

        // 1. Validate transition
        $allowed = self::VALID_TRANSITIONS[$oldStatus] ?? [];
        if (! in_array($newStatus, $allowed, true)) {
            throw new InvalidArgumentException(
                "Cannot transition vendor [{$vendor->id}] from '{$oldStatus}' to '{$newStatus}'."
            );
        }

        // 2. Validate reason
        if (in_array($newStatus, self::REQUIRES_REASON, true) && empty(trim((string) $reason))) {
            throw new \RuntimeException(
                "A reason is required when setting vendor status to '{$newStatus}'."
            );
        }

        // 3. Build update payload
        $updates = ['status' => $newStatus];

        match ($newStatus) {
            'approved' => $updates += [
                'approved_by'       => $actor->id,
                'approved_at'       => now(),
                'reject_reason'     => null,
                'suspended_reason'  => null,
            ],
            'rejected' => $updates += [
                'reject_reason'    => $reason,
                'approved_by'      => null,
                'approved_at'      => null,
                'suspended_reason' => null,
            ],
            'suspended' => $updates += [
                'suspended_reason' => $reason,
            ],
            'pending' => $updates += [
                'reject_reason'    => null,
                'suspended_reason' => null,
            ],
            default => null,
        };

        // 4. Persist inside a transaction (rollback if audit write fails)
        DB::transaction(function () use ($vendor, $updates, $oldStatus, $newStatus, $actor, $reason) {
            $vendor->update($updates);
            $this->writeAudit($vendor, $oldStatus, $newStatus, $actor, $reason);
        });
    }

    /**
     * Map (oldStatus → newStatus) to a named audit action.
     */
    public function auditAction(string $oldStatus, string $newStatus): string
    {
        return match ("{$oldStatus}->{$newStatus}") {
            'pending->approved'   => 'approve_vendor',
            'pending->rejected'   => 'reject_vendor',
            'approved->suspended' => 'suspend_vendor',
            'suspended->approved' => 'unsuspend_vendor',
            'rejected->pending'   => 'resubmit_vendor',
            default               => "transition_{$oldStatus}_to_{$newStatus}",
        };
    }

    /**
     * Write an audit log entry. Extracted to a protected method so tests can override it.
     */
    protected function writeAudit(
        Vendor  $vendor,
        string  $oldStatus,
        string  $newStatus,
        User    $actor,
        ?string $reason,
    ): void {
        AuditLog::create([
            'actor_id'    => $actor->id,
            'action'      => $this->auditAction($oldStatus, $newStatus),
            'entity_type' => 'vendor',
            'entity_id'   => $vendor->id,
            'old_values'  => [
                'status'           => $oldStatus,
                'reject_reason'    => $vendor->getOriginal('reject_reason'),
                'suspended_reason' => $vendor->getOriginal('suspended_reason'),
            ],
            'new_values'  => [
                'status' => $newStatus,
                'reason' => $reason,
            ],
            'ip'          => request()->ip(),
        ]);
    }
}

