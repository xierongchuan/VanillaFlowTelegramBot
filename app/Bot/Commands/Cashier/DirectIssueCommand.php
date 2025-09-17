<?php

declare(strict_types=1);

namespace App\Bot\Commands\Cashier;

use App\Bot\Abstracts\BaseCommandHandler;
use App\Bot\Conversations\Cashier\DirectIssueExpenseConversation;
use App\Models\User;
use SergiX44\Nutgram\Nutgram;

/**
 * Command to directly issue expenses without director approval.
 * Starts the DirectIssueExpenseConversation for cashiers.
 */
class DirectIssueCommand extends BaseCommandHandler
{
    protected string $command = 'direct_issue';
    protected ?string $description = 'Directly issue expense without director approval';

    /**
     * Execute the direct issue command logic for cashiers.
     */
    protected function execute(Nutgram $bot, User $user): void
    {
        // Start the direct issue conversation
        DirectIssueExpenseConversation::begin($bot);
    }
}
