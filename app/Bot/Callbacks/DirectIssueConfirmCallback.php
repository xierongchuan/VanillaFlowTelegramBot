<?php

declare(strict_types=1);

namespace App\Bot\Callbacks;

use App\Bot\Abstracts\BaseCallbackHandler;
use App\Services\Contracts\ExpenseServiceInterface;
use SergiX44\Nutgram\Nutgram;

/**
 * Handle direct issue confirmation callback.
 */
class DirectIssueConfirmCallback extends BaseCallbackHandler
{
    /**
     * Get expense service from container.
     */
    private function getExpenseService(): ExpenseServiceInterface
    {
        return app(ExpenseServiceInterface::class);
    }

    /**
     * Execute the direct issue confirmation logic.
     */
    protected function execute(Nutgram $bot, string $id): void
    {
        $cashier = $this->validateUser($bot);

        // Get stored conversation data
        $callbackData = $bot->getGlobalData("direct_issue:confirm:{$id}", null);

        if (!$callbackData || !isset($callbackData['data'])) {
            throw new \RuntimeException('Данные для подтверждения не найдены');
        }

        $data = $callbackData['data'];

        // Use cashier as requester to avoid foreign key issues
        // Store real recipient information in the comment
        $commentWithRecipient = sprintf(
            "[Для: %s] %s",
            $data['recipientName'],
            $data['comment']
        );

        $requestId = $this->getExpenseService()->createAndIssueRequest(
            $bot,
            $cashier, // Use cashier as requester
            $cashier, // Use cashier as recipient
            $data['description'],
            (float) $data['amount'],
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
                $data['recipientName'],
                number_format((float) $data['amount'], 2, '.', ' '),
                $data['description']
            );

            if ($data['comment']) {
                $message .= "\nКомментарий: {$data['comment']}";
            }

            $bot->editMessageText(
                text: $message,
                reply_markup: null
            );
        } else {
            $bot->editMessageText(
                text: '❌ Ошибка при выдаче средств. Попробуйте позже.',
                reply_markup: null
            );
        }
    }

    /**
     * Get specific error message for this callback.
     */
    protected function getErrorMessage(\Throwable $e): string
    {
        return 'Ошибка при подтверждении выдачи.';
    }
}
