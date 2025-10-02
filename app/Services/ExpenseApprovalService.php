<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ExpenseStatus;
use App\Enums\Role;
use App\Models\ExpenseApproval;
use App\Models\ExpenseRequest;
use App\Models\User;
use App\Services\Contracts\AuditLogServiceInterface;
use App\Services\Contracts\ExpenseApprovalServiceInterface;
use App\Services\Contracts\NotificationServiceInterface;
use App\Services\Contracts\UserFinderServiceInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;
use Throwable;

/**
 * Service for expense approval operations.
 * Follows Single Responsibility Principle - only handles approvals, declines, and issuance.
 * Follows Dependency Inversion Principle - depends on abstractions, not concretions.
 */
class ExpenseApprovalService implements ExpenseApprovalServiceInterface
{
    public function __construct(
        private NotificationServiceInterface $notificationService,
        private AuditLogServiceInterface $auditLogService,
        private UserFinderServiceInterface $userFinderService
    ) {
    }

    /**
     * Approve expense request.
     */
    public function approveRequest(
        Nutgram $bot,
        int $requestId,
        User $director,
        ?string $comment = null
    ): array {
        try {
            return DB::transaction(function () use ($bot, $requestId, $director, $comment) {
                // Find and lock the request
                $request = ExpenseRequest::with('requester')
                    ->where('id', $requestId)
                    ->lockForUpdate()
                    ->first();

                if (!$request) {
                    return ['success' => false, 'message' => 'Expense request not found'];
                }

                if ($request->status !== ExpenseStatus::PENDING->value) {
                    return ['success' => false, 'message' => 'Request already processed'];
                }

                // Update request status
                $request->update([
                    'status' => ExpenseStatus::APPROVED->value,
                    'approved_at' => now(),
                    'director_id' => $director->id,
                    'director_comment' => $comment,
                ]);

                // Create approval record
                ExpenseApproval::create([
                    'expense_request_id' => $requestId,
                    'actor_id' => $director->id,
                    'actor_role' => Role::DIRECTOR->value,
                    'action' => ExpenseStatus::APPROVED->value,
                    'comment' => $comment,
                    'created_at' => now(),
                ]);

                // Log the approval
                $this->auditLogService->logExpenseApprovalAction(
                    $requestId,
                    $director->id,
                    ExpenseStatus::APPROVED->value,
                    $comment
                );

                // Notify requester about approval
                $this->notificationService->notifyExpenseStatus(
                    $bot,
                    $request->requester,
                    $request,
                    ExpenseStatus::APPROVED->value,
                    $comment
                );

                // Find and notify cashier
                $cashier = $this->userFinderService->findCashierForCompany($request->company_id);
                if ($cashier) {
                    $this->notificationService->notifyCashierApproved(
                        $bot,
                        $cashier,
                        $request,
                        $comment
                    );
                }

                Log::info("Expense request approved", [
                    'request_id' => $requestId,
                    'director_id' => $director->id,
                    'comment' => $comment
                ]);

                return ['success' => true, 'request' => $request];
            });
        } catch (Throwable $e) {
            Log::error("Failed to approve expense request", [
                'request_id' => $requestId,
                'director_id' => $director->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return ['success' => false, 'message' => 'Internal error occurred'];
        }
    }

    /**
     * Decline expense request.
     */
    public function declineRequest(
        Nutgram $bot,
        int $requestId,
        User $director,
        ?string $reason = null
    ): array {
        try {
            return DB::transaction(function () use ($bot, $requestId, $director, $reason) {
                // Find and lock the request
                $request = ExpenseRequest::with('requester')
                    ->where('id', $requestId)
                    ->lockForUpdate()
                    ->first();

                if (!$request) {
                    return ['success' => false, 'message' => 'Expense request not found'];
                }

                if ($request->status !== ExpenseStatus::PENDING->value) {
                    return ['success' => false, 'message' => 'Request already processed'];
                }

                // Update request status
                $request->update(['status' => ExpenseStatus::DECLINED->value]);

                // Create approval record (declined)
                ExpenseApproval::create([
                    'expense_request_id' => $requestId,
                    'actor_id' => $director->id,
                    'actor_role' => Role::DIRECTOR->value,
                    'action' => 'declined',
                    'comment' => $reason,
                    'created_at' => now(),
                ]);

                // Log the decline
                $this->auditLogService->logExpenseApprovalAction(
                    $requestId,
                    $director->id,
                    'declined',
                    $reason
                );

                // Notify requester about decline
                $this->notificationService->notifyExpenseStatus(
                    $bot,
                    $request->requester,
                    $request,
                    ExpenseStatus::DECLINED->value,
                    $reason
                );

                Log::info("Expense request declined", [
                    'request_id' => $requestId,
                    'director_id' => $director->id,
                    'reason' => $reason
                ]);

                return ['success' => true, 'request' => $request];
            });
        } catch (Throwable $e) {
            Log::error("Failed to decline expense request", [
                'request_id' => $requestId,
                'director_id' => $director->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return ['success' => false, 'message' => 'Internal error occurred'];
        }
    }

    /**
     * Mark expense as issued.
     */
    public function issueExpense(
        Nutgram $bot,
        int $requestId,
        User $cashier
    ): array {
        try {
            return DB::transaction(function () use ($bot, $requestId, $cashier) {
                // Find and lock the request
                $request = ExpenseRequest::with('requester')
                    ->where('id', $requestId)
                    ->lockForUpdate()
                    ->first();

                if (!$request) {
                    return ['success' => false, 'message' => 'Expense request not found'];
                }

                if ($request->status !== ExpenseStatus::APPROVED->value) {
                    return ['success' => false, 'message' => 'Request must be approved first'];
                }

                // Update request status
                $request->update(['status' => ExpenseStatus::ISSUED->value]);

                // Create approval record for issuance
                ExpenseApproval::create([
                    'expense_request_id' => $requestId,
                    'actor_id' => $cashier->id,
                    'actor_role' => Role::CASHIER->value,
                    'action' => 'issued',
                    'comment' => null,
                    'created_at' => now(),
                ]);

                // Log the issuance
                $this->auditLogService->logExpenseIssued(
                    $requestId,
                    $cashier->id
                );

                // Notify requester about issuance
                // $this->notificationService->notifyExpenseStatus(
                //     $bot,
                //     $request->requester,
                //     $request,
                //     ExpenseStatus::ISSUED->value
                // );

                Log::info("Expense issued", [
                    'request_id' => $requestId,
                    'cashier_id' => $cashier->id
                ]);

                return ['success' => true, 'request' => $request];
            });
        } catch (Throwable $e) {
            Log::error("Failed to issue expense", [
                'request_id' => $requestId,
                'cashier_id' => $cashier->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return ['success' => false, 'message' => 'Internal error occurred'];
        }
    }
}
