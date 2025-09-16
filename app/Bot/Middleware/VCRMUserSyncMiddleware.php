<?php

declare(strict_types=1);

namespace App\Bot\Middleware;

use SergiX44\Nutgram\Nutgram;
use App\Models\User;
use App\Services\VCRMUserSyncService;
use Illuminate\Support\Facades\Log;

class VCRMUserSyncMiddleware
{
    public function __construct(
        private readonly VCRMUserSyncService $syncService
    ) {}

    public function __invoke(Nutgram $bot, $next)
    {
        $tgId = $bot->userId() ?? $bot->user()?->id ?? $bot->from()?->id ?? null;

        if (!$tgId) {
            Log::warning('Cannot determine Telegram ID for VCRM sync');
            return $next($bot);
        }

        $user = User::where('telegram_id', $tgId)->first();

        if (!$user) {
            Log::info('User not found for VCRM sync', ['telegram_id' => $tgId]);
            return $next($bot);
        }

        try {
            $this->syncService->syncUser($user);

            // Refresh user model in case it was updated
            $user->refresh();

            // Update the user in auth context if needed
            if (auth()->check() && auth()->id() === $user->id) {
                auth()->setUser($user);
            }

            // Update the user in bot context
            app()->instance('telegram_user', $user);

        } catch (\Throwable $e) {
            Log::error('VCRM sync middleware failed', [
                'user_id' => $user->id,
                'telegram_id' => $tgId,
                'error' => $e->getMessage()
            ]);

            // Don't block the bot action if sync fails
        }

        return $next($bot);
    }
}