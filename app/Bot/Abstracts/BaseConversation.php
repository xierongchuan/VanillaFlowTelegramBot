<?php

declare(strict_types=1);

namespace App\Bot\Abstracts;

use App\Bot\Contracts\ConversationInterface;
use App\Models\User;
use App\Traits\KeyboardTrait;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Conversations\Conversation;
use SergiX44\Nutgram\Nutgram;
use Throwable;

/**
 * Abstract base class for all conversations.
 * Provides common validation and error handling functionality.
 */
abstract class BaseConversation extends Conversation implements ConversationInterface
{
    use KeyboardTrait;

    /**
     * Validate user input.
     * Default implementation always returns true.
     * Override in concrete classes for specific validation.
     */
    public function validateInput(mixed $input): bool
    {
        return true;
    }

    /**
     * Default closing handler.
     */
    public function closing(Nutgram $bot): void
    {
        // Default: do nothing
    }

    /**
     * Get authenticated user with validation.
     */
    protected function getAuthenticatedUser(): User
    {
        $user = auth()->user();

        if (!$user) {
            throw new \RuntimeException('User not authenticated');
        }

        return $user;
    }

    /**
     * Handle errors in conversation steps.
     */
    protected function handleError(Nutgram $bot, Throwable $e, string $step): void
    {
        Log::error('Conversation error', [
            'conversation' => static::class,
            'step' => $step,
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        // Try to get keyboard, but use removeKeyboard if user not authenticated
        $keyboard = $this->getDefaultKeyboard();

        $bot->sendMessage(
            'Произошла ошибка. Попробуйте начать заново.',
            reply_markup: $keyboard
        );

        $this->end();
    }

    /**
     * Get default keyboard for the conversation.
     * Override in concrete classes if needed.
     */
    protected function getDefaultKeyboard()
    {
        // Check if user is authenticated before accessing role
        $user = auth()->user();
        if (!$user) {
            return static::removeKeyboard();
        }

        // Import Role enum if it's used in concrete implementations
        if (class_exists('\App\Enums\Role')) {
            $role = \App\Enums\Role::tryFromString($user->role);
            if ($role) {
                return match ($role) {
                    \App\Enums\Role::USER => static::userMenu(),
                    \App\Enums\Role::CASHIER => static::cashierMenu(),
                    \App\Enums\Role::DIRECTOR => static::directorMenu(),
                    default => static::removeKeyboard()
                };
            }
        }

        return static::removeKeyboard();
    }

    /**
     * Validate text input is not empty.
     */
    protected function validateNotEmpty(string $input): bool
    {
        return trim($input) !== '';
    }

    /**
     * Validate numeric input.
     */
    protected function validateNumeric(string $input): bool
    {
        $normalized = str_replace([',', ' '], ['.', ''], $input);
        return is_numeric($normalized) && (float)$normalized > 0;
    }
}
