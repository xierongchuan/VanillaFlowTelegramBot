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

$bot->onCommand(
    'start',
    StartConversationDispatcher::class
)
->middleware(VCRMUserSyncMiddleware::class);

// Users & Cashiers Middleware
$bot->onText(
    '📝 Создать заявку',
    RequestExpenseConversation::class
)
->middleware(new RoleMiddleware([Role::USER->value, Role::CASHIER->value]))
->middleware(AuthUser::class)
->middleware(VCRMUserSyncMiddleware::class);

$bot->onText(
    '📄 Мои заявки',
    \App\Bot\Commands\User\HistoryCommand::class
)
->middleware(new RoleMiddleware([Role::USER->value, Role::CASHIER->value]))
->middleware(AuthUser::class)
->middleware(VCRMUserSyncMiddleware::class);

// Director Commands
$bot->onText(
    '🔃 Ожидающие заявки',
    PendingExpensesCommand::class
)
->middleware(new RoleMiddleware([Role::DIRECTOR->value]))
->middleware(AuthUser::class)
->middleware(VCRMUserSyncMiddleware::class);

$bot->onText(
    '📋 История заявок',
    \App\Bot\Commands\Director\HistoryCommand::class
)
->middleware(new RoleMiddleware([Role::DIRECTOR->value]))
->middleware(AuthUser::class)
->middleware(VCRMUserSyncMiddleware::class);

// Cashier Commands
$bot->onText(
    '💰 Ожидающие выдачи',
    \App\Bot\Commands\Cashier\PendingExpensesCommand::class
)
->middleware(new RoleMiddleware([Role::CASHIER->value]))
->middleware(AuthUser::class)
->middleware(VCRMUserSyncMiddleware::class);

$bot->onText(
    '💼 История операций',
    \App\Bot\Commands\Cashier\HistoryCommand::class
)
->middleware(new RoleMiddleware([Role::CASHIER->value]))
->middleware(AuthUser::class)
->middleware(VCRMUserSyncMiddleware::class);

$bot->onText(
    '⚡ Прямая выдача',
    \App\Bot\Commands\Cashier\DirectIssueCommand::class
)
->middleware(new RoleMiddleware([Role::CASHIER->value]))
->middleware(AuthUser::class)
->middleware(VCRMUserSyncMiddleware::class);

// Director Callbacks
$bot->onCallbackQueryData(
    'expense:confirm:{id}',
    ExpenseConfirmCallback::class
)
->middleware(new RoleMiddleware([Role::DIRECTOR->value]))
->middleware(AuthUser::class)
->middleware(VCRMUserSyncMiddleware::class);

$bot->onCallbackQueryData(
    'expense:decline:{id}',
    ExpenseDeclineCallback::class
)
->middleware(new RoleMiddleware([Role::DIRECTOR->value]))
->middleware(AuthUser::class)
->middleware(VCRMUserSyncMiddleware::class);

$bot->onCallbackQueryData(
    'expense:confirm_with_comment:{id}',
    ConfirmWithCommentConversation::class
)
->middleware(new RoleMiddleware([Role::DIRECTOR->value]))
->middleware(AuthUser::class)
->middleware(VCRMUserSyncMiddleware::class);

// Cashier Callbacks
$bot->onCallbackQueryData(
    'expense:issued:{id}',
    \App\Bot\Callbacks\ExpenseIssuedCallback::class
)
->middleware(new RoleMiddleware([Role::CASHIER->value]))
->middleware(AuthUser::class)
->middleware(VCRMUserSyncMiddleware::class);

$bot->onCallbackQueryData(
    'expense:issued_full:{id}',
    \App\Bot\Callbacks\ExpenseIssuedFullCallback::class
)
->middleware(new RoleMiddleware([Role::CASHIER->value]))
->middleware(AuthUser::class)
->middleware(VCRMUserSyncMiddleware::class);

$bot->onCallbackQueryData(
    'expense:issued_different:{id}',
    \App\Bot\Conversations\Cashier\IssueDifferentAmountConversation::class
)
->middleware(new RoleMiddleware([Role::CASHIER->value]))
->middleware(AuthUser::class)
->middleware(VCRMUserSyncMiddleware::class);

// Direct Issue Callbacks
$bot->onCallbackQueryData(
    'direct_issue:confirm:{id}',
    \App\Bot\Callbacks\DirectIssueConfirmCallback::class
)
->middleware(new RoleMiddleware([Role::CASHIER->value]))
->middleware(AuthUser::class)
->middleware(VCRMUserSyncMiddleware::class);

$bot->onCallbackQueryData(
    'direct_issue:cancel:{id}',
    \App\Bot\Callbacks\DirectIssueCancelCallback::class
)
->middleware(new RoleMiddleware([Role::CASHIER->value]))
->middleware(AuthUser::class)
->middleware(VCRMUserSyncMiddleware::class);
