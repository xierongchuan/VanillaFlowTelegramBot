<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ExpenseStatus;
use App\Models\ExpenseRequest;
use App\Models\User;
use App\Services\Contracts\AuditLogServiceInterface;
use App\Services\Contracts\ExpenseServiceInterface;
use App\Services\Contracts\NotificationServiceInterface;
use App\Services\Contracts\UserFinderServiceInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;
use Throwable;

/**
 * Consolidated service for expense request lifecycle management.
 * Handles creation, deletion, and basic operations for expense requests.
 * Follows Single Responsibility Principle - manages complete expense request lifecycle.
 * Follows Dependency Inversion Principle - depends on abstractions.
 * Eliminates code duplication by consolidating ExpenseRequestService functionality.
 */
class ExpenseService implements ExpenseServiceInterface
{
    public function __construct(
        private NotificationServiceInterface $notificationService,
        private AuditLogServiceInterface $auditLogService,
        private UserFinderServiceInterface $userFinderService
    ) {
    }

    /**
     * Create a new expense request.
     * Consolidated from ExpenseRequestService to eliminate duplication.
     */
    public function createRequest(
        Nutgram $bot,
        User $requester,
        string $description,
        float $amount,
        string $currency = 'UZS'
    ): ?int {
        try {
            // Create request in transaction
            $requestId = DB::transaction(function () use ($requester, $description, $amount, $currency) {
                // Create expense request
                $request = ExpenseRequest::create([
                    'requester_id' => $requester->id,
                    'description' => $description,
                    'amount' => $amount,
                    'currency' => $currency,
                    'status' => ExpenseStatus::PENDING->value,
                    'company_id' => $requester->company_id,
                ]);

                // Log the creation
                $this->auditLogService->logExpenseRequestCreated(
                    $request->id,
                    $requester->id,
                    $amount,
                    $currency,
                    $description
                );

                Log::info("Заявка #{$request->id} успешно создана пользователем {$requester->id}");

                return $request->id;
            });

            // Send notification to director after successful transaction
            if ($requestId !== null) {
                $this->notifyDirector($bot, $requester, $requestId);
            }

            return $requestId;
        } catch (Throwable $e) {
            Log::error('Ошибка при создании заявки', [
                'requester_id' => $requester->id,
                'amount' => $amount,
                'currency' => $currency,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }

    /**
     * Get expense request by ID with related data.
     * Additional utility method for expense management.
     */
    public function getExpenseRequestById(int $requestId): ?ExpenseRequest
    {
        return ExpenseRequest::with(['requester', 'approvals'])->find($requestId);
    }

    /**
     * Get pending expense requests for a company.
     * Utility method for listing pending requests.
     */
    public function getPendingRequestsForCompany(int $companyId): Collection
    {
        return ExpenseRequest::with('requester')
            ->where('company_id', $companyId)
            ->where('status', ExpenseStatus::PENDING->value)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get approved expense requests for a company.
     * Utility method for accountants to see approved requests.
     */
    public function getApprovedRequestsForCompany(int $companyId): Collection
    {
        return ExpenseRequest::with('requester')
            ->where('company_id', $companyId)
            ->where('status', ExpenseStatus::APPROVED->value)
            ->orderBy('approved_at', 'desc')
            ->get();
    }

    /**
     * Delete an expense request.
     */
    public function deleteRequest(int $requestId, int $actorId, ?string $reason = null): void
    {
        DB::transaction(function () use ($requestId, $actorId, $reason) {
            $request = ExpenseRequest::where('id', $requestId)
                ->lockForUpdate()
                ->firstOrFail();

            $request->delete(); // ON DELETE CASCADE should remove related approvals

            // Log the deletion
            $this->auditLogService->logExpenseRequestDeleted(
                $requestId,
                $actorId,
                $reason
            );
        });
    }

    /**
     * Notify director about new expense request.
     */
    private function notifyDirector(Nutgram $bot, User $requester, int $requestId): void
    {
        try {
            $companyId = $requester->company_id;
            if ($companyId === null) {
                Log::warning('Requester has no company_id', [
                    'requester_id' => $requester->id,
                    'request_id' => $requestId
                ]);
                return;
            }

            $director = $this->userFinderService->findDirectorForCompany($companyId);
            if (!$director) {
                Log::info('No director to notify', [
                    'company_id' => $companyId,
                    'request_id' => $requestId
                ]);
                return;
            }

            $request = ExpenseRequest::with('requester')->findOrFail($requestId);

            $this->notificationService->notifyDirectorNewRequest($bot, $director, $request);

            Log::info('Director notified about new request', [
                'director_id' => $director->id,
                'request_id' => $requestId,
            ]);
        } catch (Throwable $e) {
            Log::error('Failed to notify director', [
                'request_id' => $requestId,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
