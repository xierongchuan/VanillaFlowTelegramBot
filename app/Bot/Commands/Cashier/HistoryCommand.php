<?php

declare(strict_types=1);

namespace App\Bot\Commands\Cashier;

use App\Bot\Abstracts\BaseCommandHandler;
use App\Models\User;
use App\Models\ExpenseRequest;
use App\Models\ExpenseApproval;
use App\Enums\ExpenseStatus;
use SergiX44\Nutgram\Nutgram;

/**
 * History command for cashiers.
 * Shows requests processed by the cashier and approved requests requiring issuance.
 */
class HistoryCommand extends BaseCommandHandler
{
    protected string $command = 'cashier_history';
    protected ?string $description = 'Show expense request history';

    /**
     * Execute the history command logic for cashiers.
     */
    protected function execute(Nutgram $bot, User $user): void
    {
        // Get requests from the same company that reached cashier stage
        $requests = ExpenseRequest::with(['requester', 'director', 'cashier'])
            ->where('company_id', $user->company_id)
            ->whereIn('status', ['approved', 'issued'])
            ->orderBy('created_at', 'asc')
            ->limit(20) // Limit to last 20 requests
            ->get();

        if ($requests->isEmpty()) {
            $bot->sendMessage(
                "📋 Пока нет заявок для обработки.",
                reply_markup: static::cashierMenu()
            );
            return;
        }

        $message = "📋 *История заявок (одобренные/выданные)*\n\n";

        foreach ($requests as $request) {
            $status = ExpenseStatus::tryFromString($request->status);
            $statusLabel = $status?->label() ?? $request->status;

            // Format created date
            $createdAt = $request->created_at->format('d.m.Y H:i');

            // Build request line
            $message .= "💼 *Заявка #{$request->id}*\n";
            // $message .= "📝 {$request->title}\n";
            $message .= "👤 Заявитель: {$request->requester->full_name}\n";
            $message .= "💰 " . number_format($request->amount, 2, '.', ' ') . " {$request->currency}\n";
            $message .= "📅 {$createdAt}\n";
            $message .= "📊 Статус: *{$statusLabel}*\n";

            // Add processor information
            // if ($request->director) {
            //     $message .= "👔 Директор: {$request->director->full_name}\n";
            // }

            if ($request->cashier) {
                $cashierName = $request->cashier->full_name;
                // Highlight if current user processed it
                if ($request->cashier_id === $user->id) {
                    $cashierName = "💼 *{$cashierName}* (Вы)";
                }
                $message .= "💼 Кассир: {$cashierName}\n";
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

            // Add director comment if available
            if (!empty($request->director_comment)) {
                $message .= "💬 Комментарий директора: {$request->director_comment}\n";
            }

            $message .= "\n";
        }

        $message .= "📌 *Показаны последние 20 заявок (одобренные/выданные)*";

        $bot->sendMessage(
            $message,
            parse_mode: 'Markdown',
            reply_markup: static::cashierMenu()
        );
    }
}
