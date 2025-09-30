<?php

declare(strict_types=1);

use App\Models\User;
use App\Enums\Role;
use App\Bot\Middleware\ConversationAuthMiddleware;
use SergiX44\Nutgram\Nutgram;

beforeEach(function () {
    $this->mockBot = Mockery::mock(Nutgram::class);
});

afterEach(function () {
    Mockery::close();
    auth()->logout();
});

describe('Conversation Auth Middleware', function () {
    it('authenticates user based on Telegram ID', function () {
        // Arrange
        $user = User::factory()->create([
            'telegram_id' => 123456789,
            'role' => Role::USER->value,
        ]);

        $this->mockBot->shouldReceive('userId')->andReturn(123456789);
        $this->mockBot->shouldReceive('user')->andReturn(null);
        $this->mockBot->shouldReceive('from')->andReturn(null);

        // Act
        $middleware = new ConversationAuthMiddleware();
        $called = false;
        $next = function () use (&$called) {
            $called = true;
            return true;
        };

        $result = $middleware($this->mockBot, $next);

        // Assert
        expect($called)->toBeTrue()
            ->and(auth()->check())->toBeTrue()
            ->and(auth()->user()->id)->toBe($user->id)
            ->and(auth()->user()->telegram_id)->toBe(123456789);
    });

    it('does not authenticate when Telegram ID not found', function () {
        // Arrange
        $this->mockBot->shouldReceive('userId')->andReturn(null);
        $this->mockBot->shouldReceive('user')->andReturn(null);
        $this->mockBot->shouldReceive('from')->andReturn(null);

        // Act
        $middleware = new ConversationAuthMiddleware();
        $called = false;
        $next = function () use (&$called) {
            $called = true;
            return true;
        };

        $result = $middleware($this->mockBot, $next);

        // Assert
        expect($called)->toBeTrue()
            ->and(auth()->check())->toBeFalse();
    });

    it('does not re-authenticate when user already authenticated', function () {
        // Arrange
        $existingUser = User::factory()->create([
            'telegram_id' => 111111111,
            'role' => Role::USER->value,
        ]);

        auth()->setUser($existingUser);

        $otherUser = User::factory()->create([
            'telegram_id' => 222222222,
            'role' => Role::USER->value,
        ]);

        $this->mockBot->shouldReceive('userId')->andReturn(222222222);
        $this->mockBot->shouldReceive('user')->andReturn(null);
        $this->mockBot->shouldReceive('from')->andReturn(null);

        // Act
        $middleware = new ConversationAuthMiddleware();
        $called = false;
        $next = function () use (&$called) {
            $called = true;
            return true;
        };

        $result = $middleware($this->mockBot, $next);

        // Assert
        expect($called)->toBeTrue()
            ->and(auth()->check())->toBeTrue()
            ->and(auth()->user()->id)->toBe($existingUser->id) // Should still be the existing user
            ->and(auth()->user()->telegram_id)->toBe(111111111);
    });
});
