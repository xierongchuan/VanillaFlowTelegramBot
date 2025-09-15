<?php

declare(strict_types=1);

namespace App\Bot\Commands\User;

use App\Bot\Abstracts\BaseCommandHandler;
use App\Models\User;
use App\Models\ExpenseRequest;
use App\Enums\ExpenseStatus;
use App\Enums\Role;
use SergiX44\Nutgram\Nutgram;

/**
 * History command for users and cashiers.
 * Shows the user's expense request history in a concise format.
 */
class HistoryCommand extends BaseCommandHandler
{
    protected string $command = 'user_history';
    protected ?string $description = 'Show expense request history';

    /**
     * Get appropriate keyboard based on user role.
     */
    private function getRoleKeyboard(User $user)
    {
        $role = Role::tryFromString($user->role);
        return match ($role) {
            Role::USER => static::userMenu(),
            Role::CASHIER => static::cashierMenu(),
            default => static::userMenu()
        };
    }

    /**
     * Execute the history command logic for users and cashiers.
     */
    protected function execute(Nutgram $bot, User $user): void
    {
        $requests = ExpenseRequest::with(['director', 'cashier'])
            ->where('requester_id', $user->id)
            ->orderBy('created_at', 'asc')
            ->limit(20) // Limit to last 20 requests
            ->get();

        if ($requests->isEmpty()) {
            $bot->sendMessage(
                "📋 У вас пока нет заявок.\n\nСоздайте первую заявку с помощью кнопки '📝 Создать заявку'",
                reply_markup: $this->getRoleKeyboard($user)
            );
            return;
        }

        $message = "📋 *История ваших заявок*\n\n";

        foreach ($requests as $request) {
            $status = ExpenseStatus::tryFromString($request->status);
            $statusLabel = $status?->label() ?? $request->status;

            // Format created date
            $createdAt = $request->created_at->format('d.m.Y H:i');

            // Build request line
            $message .= "💼 *Заявка #{$request->id}*\n";
            // $message .= "📝 {$request->title}\n";
            $message .= "💰 " . number_format($request->amount, 2, '.', ' ') . " {$request->currency}\n";
            $message .= "📅 {$createdAt}\n";
            $message .= "📊 Статус: *{$statusLabel}*\n";

            // Add processor information if available
            if ($request->director) {
                $message .= "👔 Директор: {$request->director->full_name}\n";
            }

            if ($request->cashier) {
                $message .= "💼 Кассир: {$request->cashier->full_name}\n";
            }

            // Add timestamps for processed requests
            if ($request->approved_at) {
                $message .= "✅ Одобрено: " . $request->approved_at->format('d.m.Y H:i') . "\n";
            }

            if ($request->issued_at) {
                $message .= "💸 Выдано: " . $request->issued_at->format('d.m.Y H:i') . "\n";

                // Check if issued amount is different from original amount
                if ($request->issued_amount !== null && abs($request->amount - $request->issued_amount) > 0.01) {
                    $formattedIssuedAmount = number_format($request->issued_amount, 2, '.', ' ');
                    $message .= "⚠️ Выдана иная сумма: {$formattedIssuedAmount} {$request->currency}\n";
                }
            }

            $message .= "\n";
        }

        $message .= "📌 *Показаны последние 20 заявок*";

        $bot->sendMessage(
            $message,
            parse_mode: 'Markdown',
            reply_markup: $this->getRoleKeyboard($user)
        );
    }
}
