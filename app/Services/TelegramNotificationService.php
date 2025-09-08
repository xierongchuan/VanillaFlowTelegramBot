<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ExpenseStatus;
use App\Models\ExpenseRequest;
use App\Models\User;
use App\Services\Contracts\NotificationServiceInterface;
use App\Traits\KeyboardTrait;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;
use Throwable;

/**
 * Centralized Telegram notification service.
 * Follows Single Responsibility Principle and eliminates code duplication.
 */
class TelegramNotificationService implements NotificationServiceInterface
{
    use KeyboardTrait;

    /**
     * Notify user about expense request status change.
     */
    public function notifyExpenseStatus(
        Nutgram $bot,
        User $user,
        ExpenseRequest $request,
        string $status,
        ?string $comment = null
    ): bool {
        if (!$user->telegram_id) {
            Log::warning('Cannot notify user without telegram_id', [
                'user_id' => $user->id,
                'request_id' => $request->id
            ]);
            return false;
        }

        $message = $this->buildStatusMessage($request, $status, $comment);

        return $this->sendMessage($bot, $user->telegram_id, $message);
    }

    /**
     * Send notification to director about new expense request.
     */
    public function notifyDirectorNewRequest(
        Nutgram $bot,
        User $director,
        ExpenseRequest $request
    ): bool {
        if (!$director->telegram_id) {
            Log::warning('Cannot notify director without telegram_id', [
                'director_id' => $director->id,
                'request_id' => $request->id
            ]);
            return false;
        }

        $requester = $request->requester;
        $message = $this->buildNewRequestMessage($request, $requester);
        $keyboard = $this->buildApprovalKeyboard($request->id);

        return $this->sendMessage($bot, $director->telegram_id, $message, $keyboard);
    }

    /**
     * Notify cashier about approved expense.
     */
    public function notifyCashierApproved(
        Nutgram $bot,
        User $cashier,
        ExpenseRequest $request,
        ?string $directorComment = null
    ): bool {
        if (!$cashier->telegram_id) {
            Log::warning('Cannot notify cashier without telegram_id', [
                'cashier_id' => $cashier->id,
                'request_id' => $request->id
            ]);
            return false;
        }

        $requester = $request->requester;
        $message = $this->buildCashierMessage($request, $directorComment);

        $keyboard = static::inlineConfirmIssuedWithAmount(
            "expense:issued_full:{$request->id}",
            "expense:issued_different:{$request->id}"
        );

        return $this->sendMessage($bot, $cashier->telegram_id, $message, $keyboard);
    }

    /**
     * Update existing message (for callbacks).
     */
    public function updateMessage(
        Nutgram $bot,
        string $text,
        ?int $messageId = null
    ): bool {
        try {
            // If no messageId provided, try to get it from callback query
            if ($messageId === null && $bot->callbackQuery()) {
                $messageId = $bot->callbackQuery()->message?->message_id;
            }

            $bot->editMessageText(
                text: $text,
                reply_markup: null,
                message_id: $messageId
            );
            return true;
        } catch (Throwable $e) {
            Log::error('Failed to update message', [
                'message' => $e->getMessage(),
                'message_id' => $messageId
            ]);
            return false;
        }
    }

    /**
     * Build cashier notification message.
     * Consolidates cashier message building logic.
     */
    private function buildCashierMessage(
        ExpenseRequest $request,
        ?string $directorComment = null
    ): string {
        $requester = $request->requester;
        $message = sprintf(
            "Заявка #%d подтверждена директором.\nСумма: %s %s\nОжидает выдачи указанной суммы %s (ID: %d)",
            $request->id,
            number_format((float) $request->amount, 2, '.', ' '),
            $request->currency,
            $requester->full_name ?? $requester->login ?? 'Unknown',
            $requester->id
        );

        // Add director's comment for cashier if provided
        if ($directorComment && $directorComment !== '-') {
            $message .= "\nКомментарий директора: {$directorComment}";
        }

        return $message;
    }

    /**
     * Build status notification message.
     */
    private function buildStatusMessage(
        ExpenseRequest $request,
        string $status,
        ?string $comment = null
    ): string {
        $statusText = match ($status) {
            ExpenseStatus::APPROVED->value => '✅ подтверждена',
            ExpenseStatus::DECLINED->value => '🚫 отклонена',
            ExpenseStatus::ISSUED->value => '💰 выдана',
            default => 'обновлена'
        };

        $message = "Ваша заявка #{$request->id} {$statusText}";

        if ($status === ExpenseStatus::APPROVED->value) {
            $message .= " директором.\nОжидайте выдачи от кассира.";
        } elseif ($status === ExpenseStatus::DECLINED->value) {
            $message .= sprintf(
                " директором.\nСумма: %s %s\nОписание: %s",
                number_format((float) $request->amount, 2, '.', ' '),
                $request->currency,
                $request->description ?: '-'
            );
        } elseif ($status === ExpenseStatus::ISSUED->value) {
            $message .= " кассиром.\nВы можете получить средства у кассира.";
        }

        if ($comment && $comment !== '-') {
            $message .= "\nКомментарий: {$comment}";
        }

        return $message;
    }

    /**
     * Build new request notification message.
     */
    private function buildNewRequestMessage(ExpenseRequest $request, User $requester): string
    {
        return sprintf(
            "Новая заявка #%d\nПользователь: %s (ID: %d)\nСумма: %s %s\nКомментарий: %s",
            $request->id,
            $requester->full_name ?? ($requester->login ?? 'Unknown'),
            $requester->id,
            number_format((float) $request->amount, 2, '.', ' '),
            $request->currency,
            $request->description ?: '-'
        );
    }

    /**
     * Build approval keyboard for director.
     */
    private function buildApprovalKeyboard(int $requestId)
    {
        $confirmData = "expense:confirm:{$requestId}";
        $confirmWithCommentData = "expense:confirm_with_comment:{$requestId}";
        $declineData = "expense:decline:{$requestId}";

        return static::inlineConfirmCommentDecline(
            $confirmData,
            $confirmWithCommentData,
            $declineData
        );
    }

    /**
     * Send message with error handling.
     */
    private function sendMessage(
        Nutgram $bot,
        string|int $chatId,
        string $text,
        $keyboard = null
    ): bool {
        try {
            $params = [
                'chat_id' => (string) $chatId,
                'text' => $text,
            ];

            if ($keyboard) {
                $params['reply_markup'] = $keyboard;
            }

            $bot->sendMessage(...$params);
            return true;
        } catch (Throwable $e) {
            Log::error('Failed to send message', [
                'chat_id' => $chatId,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }
}
