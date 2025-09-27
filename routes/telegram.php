<?php

declare(strict_types=1);

/** @var SergiX44\Nutgram\Nutgram $bot */

use App\Bot\Callbacks\ExpenseConfirmCallback;
use App\Bot\Callbacks\ExpenseDeclineCallback;
use App\Bot\Commands\Director\PendingExpensesCommand;
use App\Enums\Role;
use App\Bot\Middleware\AuthUser;
use App\Bot\Middleware\RoleMiddleware;
use App\Bot\Middleware\VCRMUserSyncMiddleware;
use App\Bot\Dispatchers\StartConversationDispatcher;
use App\Bot\Conversations\User\RequestExpenseConversation;
use App\Bot\Conversations\Director\ConfirmWithCommentConversation;

/*
| Nutgram Handlers
*/

// Handle the '/start' command by dispatching to the appropriate conversation based on user role
$bot->onCommand(
    'start',
    StartConversationDispatcher::class
)
->middleware(VCRMUserSyncMiddleware::class);

// Users & Cashiers Middleware

// Handle '📝 Создать заявку' text command to start the expense request conversation for users and cashiers
$bot->onText(
    '📝 Создать заявку',
    RequestExpenseConversation::class
)
->middleware(new RoleMiddleware([Role::USER->value, Role::CASHIER->value]))
->middleware(AuthUser::class)
->middleware(VCRMUserSyncMiddleware::class);

// Handle '📄 Мои заявки' text command to show expense history for users and cashiers
$bot->onText(
    '📄 Мои заявки',
    \App\Bot\Commands\User\HistoryCommand::class
)
->middleware(new RoleMiddleware([Role::USER->value, Role::CASHIER->value]))
->middleware(AuthUser::class)
->middleware(VCRMUserSyncMiddleware::class);

// Director Commands

// Handle '🔃 Ожидающие заявки' text command to show pending expenses for directors
$bot->onText(
    '🔃 Ожидающие заявки',
    PendingExpensesCommand::class
)
->middleware(new RoleMiddleware([Role::DIRECTOR->value]))
->middleware(AuthUser::class)
->middleware(VCRMUserSyncMiddleware::class);

// Handle '📋 История заявок' text command to show expense history for directors
$bot->onText(
    '📋 История заявок',
    \App\Bot\Commands\Director\HistoryCommand::class
)
->middleware(new RoleMiddleware([Role::DIRECTOR->value]))
->middleware(AuthUser::class)
->middleware(VCRMUserSyncMiddleware::class);

// Cashier Commands

// Handle '💰 Ожидающие выдачи' text command to show pending expenses for cashiers
$bot->onText(
    '💰 Ожидающие выдачи',
    \App\Bot\Commands\Cashier\PendingExpensesCommand::class
)
->middleware(new RoleMiddleware([Role::CASHIER->value]))
->middleware(AuthUser::class)
->middleware(VCRMUserSyncMiddleware::class);

// Handle '💼 История операций' text command to show transaction history for cashiers
$bot->onText(
    '💼 История операций',
    \App\Bot\Commands\Cashier\HistoryCommand::class
)
->middleware(new RoleMiddleware([Role::CASHIER->value]))
->middleware(AuthUser::class)
->middleware(VCRMUserSyncMiddleware::class);

// Handle '⚡ Прямая выдача' text command to start direct expense issuing process for cashiers
$bot->onText(
    '⚡ Прямая выдача',
    \App\Bot\Commands\Cashier\DirectIssueCommand::class
)
->middleware(new RoleMiddleware([Role::CASHIER->value]))
->middleware(AuthUser::class)
->middleware(VCRMUserSyncMiddleware::class);

// Director Callbacks

// Handle callback query for confirming an expense request by director
$bot->onCallbackQueryData(
    'expense:confirm:{id}',
    ExpenseConfirmCallback::class
)
->middleware(new RoleMiddleware([Role::DIRECTOR->value]))
->middleware(AuthUser::class)
->middleware(VCRMUserSyncMiddleware::class);

// Handle callback query for declining an expense request by director
$bot->onCallbackQueryData(
    'expense:decline:{id}',
    ExpenseDeclineCallback::class
)
->middleware(new RoleMiddleware([Role::DIRECTOR->value]))
->middleware(AuthUser::class)
->middleware(VCRMUserSyncMiddleware::class);

// Handle callback query for confirming an expense request with a comment by director
$bot->onCallbackQueryData(
    'expense:confirm_with_comment:{id}',
    ConfirmWithCommentConversation::class
)
->middleware(new RoleMiddleware([Role::DIRECTOR->value]))
->middleware(AuthUser::class)
->middleware(VCRMUserSyncMiddleware::class);

// Cashier Callbacks

// Handle callback query for marking an expense as issued by cashier
$bot->onCallbackQueryData(
    'expense:issued:{id}',
    \App\Bot\Callbacks\ExpenseIssuedCallback::class
)
->middleware(new RoleMiddleware([Role::CASHIER->value]))
->middleware(AuthUser::class)
->middleware(VCRMUserSyncMiddleware::class);

// Handle callback query for marking an expense as fully issued by cashier
$bot->onCallbackQueryData(
    'expense:issued_full:{id}',
    \App\Bot\Callbacks\ExpenseIssuedFullCallback::class
)
->middleware(new RoleMiddleware([Role::CASHIER->value]))
->middleware(AuthUser::class)
->middleware(VCRMUserSyncMiddleware::class);

// Handle callback query for issuing a different amount for an expense by cashier
$bot->onCallbackQueryData(
    'expense:issued_different:{id}',
    \App\Bot\Conversations\Cashier\IssueDifferentAmountConversation::class
)
->middleware(new RoleMiddleware([Role::CASHIER->value]))
->middleware(AuthUser::class)
->middleware(VCRMUserSyncMiddleware::class);

// Direct Issue Callbacks

// Handle callback query for confirming a direct expense issue by cashier
$bot->onCallbackQueryData(
    'direct_issue:confirm:{id}',
    \App\Bot\Callbacks\DirectIssueConfirmCallback::class
)
->middleware(new RoleMiddleware([Role::CASHIER->value]))
->middleware(AuthUser::class)
->middleware(VCRMUserSyncMiddleware::class);

// Handle callback query for canceling a direct expense issue by cashier
$bot->onCallbackQueryData(
    'direct_issue:cancel:{id}',
    \App\Bot\Callbacks\DirectIssueCancelCallback::class
)
->middleware(new RoleMiddleware([Role::CASHIER->value]))
->middleware(AuthUser::class)
->middleware(VCRMUserSyncMiddleware::class);
