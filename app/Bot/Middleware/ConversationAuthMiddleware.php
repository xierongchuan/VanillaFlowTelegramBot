<?php

declare(strict_types=1);

namespace App\Bot\Middleware;

use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Conversations\Conversation;
use App\Models\User;

/**
 * Middleware to ensure user authentication is maintained across conversation steps.
 * This middleware should be registered globally to run on every bot request.
 */
class ConversationAuthMiddleware
{
    public function __invoke(Nutgram $bot, $next)
    {
        // If user is not authenticated, try to authenticate them
        if (!auth()->check()) {
            $tgId = $bot->userId() ?? $bot->user()?->id ?? $bot->from()?->id ?? null;

            if ($tgId) {
                $user = User::where('telegram_id', $tgId)->first();

                if ($user) {
                    app()->instance('telegram_user', $user);
                    auth()->setUser($user);
                }
            }
        }

        return $next($bot);
    }
}
