<?php

declare(strict_types=1);

namespace App\Services\Contracts;

/**
 * Interface for audit logging operations.
 * Follows Interface Segregation Principle.
 */
interface AuditLogServiceInterface
{
    /**
     * Log an action performed on a record.
     *
     * @param string $tableName The table name where the action occurred
     * @param int $recordId The ID of the record
     * @param int $actorId The ID of the user performing the action
     * @param string $action The action performed (insert, update, delete, etc.)
     * @param array $payload Additional data about the action
     * @return void
     */
    public function logAction(
        string $tableName,
        int $recordId,
        int $actorId,
        string $action,
        array $payload = []
    ): void;

    /**
     * Log expense request creation.
     *
     * @param int $requestId The expense request ID
     * @param int $requesterId The ID of the user creating the request
     * @param float $amount The requested amount
     * @param string $currency The currency code
     * @param string $description The request description
     * @return void
     */
    public function logExpenseRequestCreated(
        int $requestId,
        int $requesterId,
        float $amount,
        string $currency,
        string $description
    ): void;

    /**
     * Log expense request deletion.
     *
     * @param int $requestId The expense request ID
     * @param int $actorId The ID of the user deleting the request
     * @param string|null $reason The reason for deletion
     * @return void
     */
    public function logExpenseRequestDeleted(
        int $requestId,
        int $actorId,
        ?string $reason = null
    ): void;

    /**
     * Log expense approval action.
     *
     * @param int $requestId The expense request ID
     * @param int $directorId The ID of the director
     * @param string $action The action (approved/declined)
     * @param string|null $comment Optional comment
     * @return void
     */
    public function logExpenseApprovalAction(
        int $requestId,
        int $directorId,
        string $action,
        ?string $comment = null
    ): void;

    /**
     * Log expense issuance.
     *
     * @param int $requestId The expense request ID
     * @param int $accountantId The ID of the accountant
     * @param float|null $issuedAmount The amount issued (if different from requested)
     * @return void
     */
    public function logExpenseIssued(
        int $requestId,
        int $accountantId,
        ?float $issuedAmount = null
    ): void;
}
