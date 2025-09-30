<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ExpenseStatus;
use App\Enums\Role;
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
 * Primary service for expense request lifecycle management.
 * Handles creation, deletion, and basic operations for expense requests.
 * Follows Single Responsibility Principle - manages complete expense request lifecycle.
 * Follows Dependency Inversion Principle - depends on abstractions.
 * Consolidated from ExpenseService to eliminate duplication.
 */
class ExpenseRequestService implements ExpenseServiceInterface
{
    public function __construct(
        private NotificationServiceInterface $notificationService,
        private AuditLogServiceInterface $auditLogService,
        private UserFinderServiceInterface $userFinderService
    ) {
    }

    /**
     * Create a new expense request.
     */
    public function createRequest(
        Nutgram $bot,
        User $requester,
        string $description,
        float $amount,
        string $currency = 'UZS'
    ): ?int {
        // Check if requester is cashier - if so, deny access
        // if ($requester->role === Role::CASHIER->value) {
        //     Log::warning('Cashier attempted to create expense request through createRequest method', [
        //         'cashier_id' => $requester->id,
        //         'amount' => $amount,
        //         'currency' => $currency,
        //     ]);
        //     return null;
        // }

        try {
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

                // Create audit log
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
     * Create a new expense request and directly issue it (cashier functionality).
     * This allows cashiers to directly issue funds without director approval.
     */
    public function createAndIssueRequest(
        Nutgram $bot,
        User $cashier,
        User $recipient,
        string $description,
        float $amount,
        string $currency = 'UZS',
        ?string $comment = null
    ): ?int {
        try {
            $requestId = DB::transaction(function () use ($cashier, $recipient, $description, $amount, $currency, $comment) {
                // Create expense request with ISSUED status directly
                $request = ExpenseRequest::create([
                    'requester_id' => $recipient->id,
                    'description' => $description,
                    'amount' => $amount,
                    'currency' => $currency,
                    'status' => ExpenseStatus::ISSUED->value,
                    'company_id' => $cashier->company_id,
                    'cashier_id' => $cashier->id, // Using cashier_id to store cashier who issued
                    'director_comment' => $comment, // Using director_comment to store issuance comment
                    'approved_at' => now(), // Set approved time for consistency
                    'issued_at' => now(), // Set issued time
                ]);

                // Create audit log for creation
                $this->auditLogService->logExpenseRequestCreated(
                    $request->id,
                    $recipient->id,
                    $amount,
                    $currency,
                    $description
                );

                // Create audit log for issuance
                $this->auditLogService->logExpenseIssued(
                    $request->id,
                    $cashier->id,
                    null, // issued amount (null means same as requested)
                    $comment
                );

                Log::info("Заявка #{$request->id} успешно создана и выдана кассиром {$cashier->id} для пользователя {$recipient->id}");

                return $request->id;
            });

            // Send notification to director after successful transaction
            if ($requestId !== null) {
                $this->notifyDirectorOfDirectIssuance($bot, $cashier, $recipient, $requestId, $comment);

                if ($cashier->id != $recipient->id) {
                    // Notify recipient about the issuance
                    $this->notifyRecipientOfIssuance($bot, $recipient, $requestId);
                }
            }

            return $requestId;
        } catch (Throwable $e) {
            Log::error('Ошибка при создании и выдаче заявки', [
                'cashier_id' => $cashier->id,
                'recipient_id' => $recipient->id,
                'amount' => $amount,
                'currency' => $currency,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }

    /**
     * Delete expense request.
     */
    public function deleteRequest(int $requestId, int $actorId, ?string $reason = null): void
    {
        DB::transaction(function () use ($requestId, $actorId, $reason) {
            $request = ExpenseRequest::where('id', $requestId)
                ->lockForUpdate()
                ->firstOrFail();

            $request->delete(); // ON DELETE CASCADE should remove related approvals

            $this->auditLogService->logExpenseRequestDeleted(
                $requestId,
                $actorId,
                $reason
            );
        });
    }

    /**
     * Get expense request by ID with related data.
     * Additional utility method for expense management.
     */
    public function getExpenseRequestById(int $requestId): ?ExpenseRequest
    {
        return ExpenseRequest::with(['requester', 'approvals', 'director', 'cashier'])->find($requestId);
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
     * Utility method for cashiers to see approved requests.
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

    /**
     * Notify director about direct expense issuance by cashier.
     */
    private function notifyDirectorOfDirectIssuance(
        Nutgram $bot,
        User $cashier,
        User $recipient,
        int $requestId,
        ?string $comment = null
    ): void {
        try {
            $companyId = $cashier->company_id;
            if ($companyId === null) {
                Log::warning('Cashier has no company_id', [
                    'cashier_id' => $cashier->id,
                    'request_id' => $requestId
                ]);
                return;
            }

            $director = $this->userFinderService->findDirectorForCompany($companyId);

            if (!$director) {
                Log::info('No director to notify about direct issuance', [
                    'company_id' => $companyId,
                    'request_id' => $requestId
                ]);
                return;
            }

            $request = ExpenseRequest::with('requester')->findOrFail($requestId);

            // Build custom message for direct issuance
            $message = sprintf(
                "💰 Кассир %s (ID: %d) выдал средства без подтверждения директора.\n" .
                "Заявка #%d\nПолучатель: %s (ID: %d)\nСумма: %s %s\nНазначение: %s",
                $cashier->full_name ?? ($cashier->login ?? 'Unknown'),
                $cashier->id,
                $request->id,
                $recipient->full_name ?? ($recipient->login ?? 'Unknown'),
                $recipient->id,
                number_format((float) $request->amount, 2, '.', ' '),
                $request->currency,
                $request->description ?: '-'
            );

            if ($comment) {
                $message .= "\nКомментарий кассира: {$comment}";
            }

            // Send notification to director
            $this->notificationService->sendMessage($bot, $director->telegram_id, $message);

            Log::info('Director notified about direct expense issuance', [
                'director_id' => $director->id,
                'cashier_id' => $cashier->id,
                'request_id' => $requestId,
            ]);
        } catch (Throwable $e) {
            Log::error('Failed to notify director of direct issuance', [
                'request_id' => $requestId,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Notify recipient about direct expense issuance.
     */
    private function notifyRecipientOfIssuance(Nutgram $bot, User $recipient, int $requestId): void
    {
        try {
            if (!$recipient->telegram_id) {
                Log::warning('Recipient has no telegram_id', [
                    'recipient_id' => $recipient->id,
                    'request_id' => $requestId
                ]);
                return;
            }

            $request = ExpenseRequest::findOrFail($requestId);

            // Build message for recipient
            $message = sprintf(
                "💰 Ваши средства выданы кассиром.\n" .
                "Заявка #%d\nСумма: %s %s\nНазначение: %s\n" .
                "Вы можете получить средства у кассира.",
                $request->id,
                number_format((float) $request->amount, 2, '.', ' '),
                $request->currency,
                $request->description ?: '-'
            );

            // Send notification to recipient
            $this->notificationService->sendMessage($bot, $recipient->telegram_id, $message);

            Log::info('Recipient notified about expense issuance', [
                'recipient_id' => $recipient->id,
                'request_id' => $requestId,
            ]);
        } catch (Throwable $e) {
            Log::error('Failed to notify recipient of expense issuance', [
                'request_id' => $requestId,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
