<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Models\ExpenseRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use SergiX44\Nutgram\Nutgram;

/**
 * Interface for expense-related operations.
 * Following Interface Segregation Principle.
 */
interface ExpenseServiceInterface
{
    /**
     * Create a new expense request.
     *
     * @param Nutgram $bot Bot instance
     * @param User $requester User making the request
     * @param string $description Description of the expense
     * @param float $amount Amount requested
     * @param string $currency Currency code
     * @return int|null Request ID if successful, null otherwise
     */
    public function createRequest(
        Nutgram $bot,
        User $requester,
        string $description,
        float $amount,
        string $currency = 'UZS'
    ): ?int;

    /**
     * Create a new expense request and directly issue it (cashier functionality).
     * This allows cashiers to directly issue funds without director approval.
     *
     * @param Nutgram $bot Bot instance
     * @param User $cashier User issuing the request (cashier)
     * @param User $recipient Recipient of the funds
     * @param string $description Description of the expense
     * @param float $amount Amount to be issued
     * @param string $currency Currency code
     * @param string|null $comment Optional comment
     * @return int|null Request ID if successful, null otherwise
     */
    public function createAndIssueRequest(
        Nutgram $bot,
        User $cashier,
        User $recipient,
        string $description,
        float $amount,
        string $currency = 'UZS',
        ?string $comment = null
    ): ?int;

    /**
     * Delete an expense request.
     *
     * @param int $requestId Request ID to delete
     * @param int $actorId ID of user performing the deletion
     * @param string|null $reason Optional reason for deletion
     * @return void
     */
    public function deleteRequest(int $requestId, int $actorId, ?string $reason = null): void;

    /**
     * Get expense request by ID with related data.
     *
     * @param int $requestId Request ID
     * @return ExpenseRequest|null Expense request or null if not found
     */
    public function getExpenseRequestById(int $requestId): ?ExpenseRequest;

    /**
     * Get pending expense requests for a company.
     *
     * @param int $companyId Company ID
     * @return Collection Collection of pending expense requests
     */
    public function getPendingRequestsForCompany(int $companyId): Collection;

    /**
     * Get approved expense requests for a company.
     *
     * @param int $companyId Company ID
     * @return Collection Collection of approved expense requests
     */
    public function getApprovedRequestsForCompany(int $companyId): Collection;
}
