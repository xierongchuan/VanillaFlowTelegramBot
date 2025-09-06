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
