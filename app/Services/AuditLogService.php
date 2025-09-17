<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use App\Services\Contracts\AuditLogServiceInterface;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Service for centralized audit logging.
 * Follows Single Responsibility Principle - only handles audit logging.
 * Eliminates code duplication across services.
 */
class AuditLogService implements AuditLogServiceInterface
{
    /**
     * Log an action performed on a record.
     */
    public function logAction(
        string $tableName,
        int $recordId,
        int $actorId,
        string $action,
        array $payload = []
    ): void {
        try {
            AuditLog::create([
                'table_name' => $tableName,
                'record_id' => $recordId,
                'actor_id' => $actorId,
                'action' => $action,
                'payload' => $payload,
                'created_at' => now(),
            ]);

            Log::info("Audit log created", [
                'table' => $tableName,
                'record_id' => $recordId,
                'actor_id' => $actorId,
                'action' => $action
            ]);
        } catch (Throwable $e) {
            Log::error("Failed to create audit log", [
                'table' => $tableName,
                'record_id' => $recordId,
                'actor_id' => $actorId,
                'action' => $action,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Log expense request creation.
     * Convenience method for expense request creation logging.
     */
    public function logExpenseRequestCreated(
        int $requestId,
        int $requesterId,
        float $amount,
        string $currency,
        string $description
    ): void {
        $this->logAction(
            'expense_requests',
            $requestId,
            $requesterId,
            'insert',
            [
                'amount' => $amount,
                'currency' => $currency,
                'description' => $description
            ]
        );
    }

    /**
     * Log expense request deletion.
     * Convenience method for expense request deletion logging.
     */
    public function logExpenseRequestDeleted(
        int $requestId,
        int $actorId,
        ?string $reason = null
    ): void {
        $this->logExpenseAction($requestId, $actorId, 'delete', ['reason' => $reason]);
    }

    /**
     * Log expense approval action.
     * Convenience method for expense approval logging.
     */
    public function logExpenseApprovalAction(
        int $requestId,
        int $directorId,
        string $action,
        ?string $comment = null
    ): void {
        $payload = ['action' => $action];
        if ($comment !== null) {
            $payload['comment'] = $comment;
        }

        $this->logAction(
            'expense_approvals',
            $requestId,
            $directorId,
            $action,
            $payload
        );
    }

    /**
     * Log expense issuance.
     * Convenience method for expense issuance logging.
     */
    public function logExpenseIssued(
        int $requestId,
        int $cashierId,
        ?float $issuedAmount = null,
        ?string $comment = null
    ): void {
        $payload = ['issued_amount' => $issuedAmount];
        if ($comment !== null) {
            $payload['comment'] = $comment;
        }
        $this->logExpenseAction($requestId, $cashierId, 'issued', $payload);
    }

    /**
     * Helper method to log expense-related actions.
     * Consolidates common expense logging patterns.
     */
    private function logExpenseAction(
        int $requestId,
        int $actorId,
        string $action,
        array $additionalPayload = []
    ): void {
        $payload = array_filter($additionalPayload, fn ($value) => $value !== null);

        $this->logAction(
            'expense_requests',
            $requestId,
            $actorId,
            $action,
            $payload
        );
    }
}
