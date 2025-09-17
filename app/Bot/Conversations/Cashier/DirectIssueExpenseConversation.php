<?php

declare(strict_types=1);

namespace App\Bot\Conversations\Cashier;

use App\Bot\Abstracts\BaseConversationHandler;
use App\Models\User;
use App\Services\Contracts\ExpenseServiceInterface;
use App\Services\Contracts\ValidationServiceInterface;
use SergiX44\Nutgram\Nutgram;

/**
 * Conversation for cashiers to directly issue expenses without director approval.
 * Allows cashiers to issue funds and notify director with a comment.
 */
class DirectIssueExpenseConversation extends BaseConversationHandler
{
    public string $recipientName;
    public string $description;
    public float $amount;
    public string $comment;

    /**
     * Start the conversation by asking for the recipient name.
     */
    public function start(Nutgram $bot): void
    {
        try {
            $bot->sendMessage("Введите имя получателя (или /cancel для отмены):");
            $this->next('handleRecipient');
        } catch (\Throwable $e) {
            $this->handleError($bot, $e, 'start');
        }
    }

    /**
     * Handle recipient name input.
     */
    public function handleRecipient(Nutgram $bot): void
    {
        try {
            $text = trim($bot->message()?->text ?? '');

            // Check for cancel command
            if (strtolower($text) === '/cancel') {
                $bot->sendMessage('Операция отменена.');
                $this->end();
                return;
            }

            // Manual validation for recipient name
            if ($text === '') {
                $bot->sendMessage('Имя получателя не может быть пустым.');
                $this->next('handleRecipient');
                return;
            }

            $this->recipientName = $text;

            $bot->sendMessage(sprintf(
                "Получатель: %s\nВведите назначение платежа (или /cancel для отмены):",
                $this->recipientName
            ));
            $this->next('handleDescription');
        } catch (\Throwable $e) {
            $this->handleError($bot, $e, 'handleRecipient');
        }
    }

    /**
     * Handle description input.
     */
    public function handleDescription(Nutgram $bot): void
    {
        try {
            $text = trim($bot->message()?->text ?? '');

            // Check for cancel command
            if (strtolower($text) === '/cancel') {
                $bot->sendMessage('Операция отменена.');
                $this->end();
                return;
            }

            // Manual validation for description
            if ($text === '') {
                $bot->sendMessage('Назначение платежа не может быть пустым.');
                $this->next('handleDescription');
                return;
            }

            // Check minimum length
            if (strlen($text) < 3) {
                $bot->sendMessage('Назначение платежа должно содержать минимум 3 символа.');
                $this->next('handleDescription');
                return;
            }

            // Check maximum length
            if (strlen($text) > 1000) {
                $bot->sendMessage('Назначение платежа не может превышать 1000 символов.');
                $this->next('handleDescription');
                return;
            }

            $this->description = $text;
            $bot->sendMessage("Введите сумму для выдачи (или /cancel для отмены):");
            $this->next('handleAmount');
        } catch (\Throwable $e) {
            $this->handleError($bot, $e, 'handleDescription');
        }
    }

    /**
     * Handle amount input.
     */
    public function handleAmount(Nutgram $bot): void
    {
        try {
            $text = trim($bot->message()?->text ?? '');

            // Check for cancel command
            if (strtolower($text) === '/cancel') {
                $bot->sendMessage('Операция отменена.');
                $this->end();
                return;
            }

            $validation = $this->getValidationService()->validateAmount($text);

            if (!$validation['valid']) {
                $bot->sendMessage($validation['message']);
                $this->next('handleAmount');
                return;
            }

            $this->amount = $this->amount = $validation['value'];

            $bot->sendMessage("Введите комментарий (или /skip для пропуска, /cancel для отмены):");
            $this->next('handleComment');
        } catch (\Throwable $e) {
            $this->handleError($bot, $e, 'handleAmount');
        }
    }

    /**
    * Get validation service from container.
    */
    private function getValidationService(): ValidationServiceInterface
    {
        return app(ValidationServiceInterface::class);
    }

    /**
     * Handle comment input.
     */
    public function handleComment(Nutgram $bot): void
    {
        try {
            $text = trim($bot->message()?->text ?? '');

            // Check for cancel command
            if (strtolower($text) === '/cancel') {
                $bot->sendMessage('Операция отменена.');
                $this->end();
                return;
            }

            // Check for skip command
            if (strtolower($text) === '/skip') {
                $this->comment = '';
            } else {
                // Manual validation for comment
                if ($text === '') {
                    $bot->sendMessage('Комментарий не может быть пустым.');
                    $this->next('handleComment');
                    return;
                }

                // Check minimum length
                if (strlen($text) < 3) {
                    $bot->sendMessage('Комментарий должен содержать минимум 3 символа.');
                    $this->next('handleComment');
                    return;
                }

                // Check maximum length
                if (strlen($text) > 1000) {
                    $bot->sendMessage('Комментарий не может превышать 1000 символов.');
                    $this->next('handleComment');
                    return;
                }

                $this->comment = $text;
            }

            // Confirm the operation
            $message = sprintf(
                "Подтвердите операцию:\n" .
                "Получатель: %s\n" .
                "Назначение: %s\n" .
                "Сумма: %s UZS\n" .
                "Комментарий: %s\n\n" .
                "Введите /confirm для подтверждения или /cancel для отмены:",
                $this->recipientName,
                $this->description,
                number_format($this->amount, 2, '.', ' '),
                $this->comment ?: '-'
            );

            $bot->sendMessage($message);
            $this->next('handleConfirmation');
        } catch (\Throwable $e) {
            $this->handleError($bot, $e, 'handleComment');
        }
    }

    /**
     * Handle confirmation.
     */
    public function handleConfirmation(Nutgram $bot): void
    {
        try {
            $text = trim($bot->message()?->text ?? '');

            // Check for cancel command
            if (strtolower($text) === '/cancel') {
                $bot->sendMessage('Операция отменена.');
                $this->end();
                return;
            }

            // Check for confirm command
            if (strtolower($text) !== '/confirm') {
                $bot->sendMessage('Введите /confirm для подтверждения или /cancel для отмены.');
                $this->next('handleConfirmation');
                return;
            }

            // Execute the direct issuance
            $cashier = $this->getAuthenticatedUser();

            // Use cashier as requester to avoid foreign key issues
            // Store real recipient information in the comment
            $commentWithRecipient = sprintf(
                "[Для: %s] %s",
                $this->recipientName,
                $this->comment
            );

            $expenseService = $this->getExpenseService();
            $requestId = $expenseService->createAndIssueRequest(
                $bot,
                $cashier, // Use cashier as requester
                $cashier, // Use cashier as recipient
                $this->description,
                $this->amount,
                'UZS',
                $commentWithRecipient // Include real recipient in comment
            );

            if ($requestId !== null) {
                $message = sprintf(
                    "✅ Средства выданы без подтверждения директора!\n" .
                    "Заявка #%d создана и выдана.\n" .
                    "Получатель: %s\n" .
                    "Сумма: %s UZS\n" .
                    "Назначение: %s",
                    $requestId,
                    $this->recipientName,
                    number_format($this->amount, 2, '.', ' '),
                    $this->description
                );

                if ($this->comment) {
                    $message .= "\nКомментарий: {$this->comment}";
                }

                $bot->sendMessage($message);
            } else {
                $bot->sendMessage('❌ Ошибка при выдаче средств. Попробуйте позже.');
            }

            $this->end();
        } catch (\Throwable $e) {
            $this->handleError($bot, $e, 'handleConfirmation');
        }
    }

    /**
     * Get expense service instance.
     */
    private function getExpenseService(): ExpenseServiceInterface
    {
        return app(ExpenseServiceInterface::class);
    }
}
