<?php

declare(strict_types=1);

namespace App\Bot\Callbacks;

use App\Bot\Abstracts\BaseCallbackHandler;
use SergiX44\Nutgram\Nutgram;

/**
 * Handle direct issue cancellation callback.
 */
class DirectIssueCancelCallback extends BaseCallbackHandler
{
    /**
     * Execute the direct issue cancellation logic.
     */
    protected function execute(Nutgram $bot, string $id): void
    {
        $this->validateUser($bot);

        $callbackData = $bot->getGlobalData("direct_issue:cancel:{$id}", null);

        // Update the message to show cancellation
        $bot->editMessageText(
            text: "❌ Операция отменена.",
            reply_markup: null
        );
    }

    /**
     * Get specific error message for this callback.
     */
    protected function getErrorMessage(\Throwable $e): string
    {
        return 'Ошибка при отмене операции.';
    }
}
