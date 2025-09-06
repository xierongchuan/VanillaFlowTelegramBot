<?php

declare(strict_types=1);

use App\Bot\Commands\User\StartCommand;
use App\Bot\Commands\User\HistoryCommand;
use App\Models\User;
use App\Models\ExpenseRequest;
use App\Enums\Role;
use App\Enums\ExpenseStatus;
use SergiX44\Nutgram\Nutgram;
use Illuminate\Support\Facades\Auth;
use Mockery;

beforeEach(function () {
    $this->mockBot = Mockery::mock(Nutgram::class);
    $this->startCommand = new StartCommand();
    $this->historyCommand = new HistoryCommand();
});

afterEach(function () {
    Mockery::close();
});

describe('User Commands', function () {
    describe('StartCommand', function () {
        it('handles start command for authenticated user with full name', function () {
            // Arrange
            $user = User::factory()->create([
                'role' => Role::USER->value,
                'company_id' => 1,
                'telegram_id' => 123456789,
                'full_name' => 'John Doe',
            ]);

            Auth::shouldReceive('user')->andReturn($user);

            $this->mockBot->shouldReceive('sendMessage')
                ->once()
                ->withArgs(function ($message, $options = null) use ($user) {
                    return str_contains($message, 'Добро пожаловать') &&
                           str_contains($message, $user->full_name);
                });

            // Act
            $this->startCommand->handle($this->mockBot);

            // Assert - No exceptions thrown means success
            expect(true)->toBeTrue();
        });

        it('handles start command for director', function () {
            // Arrange
            $user = User::factory()->create([
                'role' => Role::DIRECTOR->value,
                'company_id' => 1,
                'telegram_id' => 123456789,
                'full_name' => 'Jane Director',
            ]);

            Auth::shouldReceive('user')->andReturn($user);

            $this->mockBot->shouldReceive('sendMessage')
                ->once()
                ->withArgs(function ($message, $options = null) use ($user) {
                    return str_contains($message, 'Добро пожаловать') &&
                           str_contains($message, 'Директор') &&
                           str_contains($message, $user->full_name);
                });

            // Act
            $this->startCommand->handle($this->mockBot);

            // Assert
            expect(true)->toBeTrue();
        });

        it('handles authentication error gracefully', function () {
            // Arrange
            Auth::shouldReceive('user')->andReturn(null);

            $this->mockBot->shouldReceive('sendMessage')
                ->once()
                ->withArgs(function ($message) {
                    return str_contains($message, 'Произошла ошибка');
                });

            // Act
            $this->startCommand->handle($this->mockBot);

            // Assert
            expect(true)->toBeTrue();
        });
    });

    describe('HistoryCommand', function () {
        it('displays expense history for user with requests', function () {
            // Arrange
            $user = User::factory()->create([
                'role' => Role::USER->value,
                'company_id' => 1,
                'telegram_id' => 123456789,
            ]);

            $director = User::factory()->create([
                'role' => Role::DIRECTOR->value,
                'full_name' => 'Jane Director',
            ]);

            $accountant = User::factory()->create([
                'role' => Role::ACCOUNTANT->value,
                'full_name' => 'Bob Accountant',
            ]);

            // Create expense requests with different statuses
            $request1 = ExpenseRequest::factory()->create([
                'requester_id' => $user->id,
                'description' => 'Office supplies',
                'amount' => 150.00,
                'currency' => 'UZS',
                'status' => ExpenseStatus::APPROVED->value,
                'director_id' => $director->id,
                'approved_at' => now()->subHours(2),
                'created_at' => now()->subDays(1),
            ]);

            $request2 = ExpenseRequest::factory()->create([
                'requester_id' => $user->id,
                'description' => 'Travel expenses',
                'amount' => 500.00,
                'currency' => 'USD',
                'status' => ExpenseStatus::ISSUED->value,
                'director_id' => $director->id,
                'accountant_id' => $accountant->id,
                'approved_at' => now()->subHours(4),
                'issued_at' => now()->subHours(1),
                'created_at' => now()->subDays(2),
            ]);

            Auth::shouldReceive('user')->andReturn($user);

            $this->mockBot->shouldReceive('sendMessage')
                ->once()
                ->withArgs(function ($message, $parseMode = null, $replyMarkup = null) {
                    return str_contains($message, 'История ваших заявок') &&
                           str_contains($message, 'Office supplies') &&
                           str_contains($message, 'Travel expenses') &&
                           str_contains($message, '150.00') &&
                           str_contains($message, '500.00') &&
                           str_contains($message, 'Jane Director') &&
                           str_contains($message, 'Bob Accountant');
                });

            // Act
            $this->historyCommand->handle($this->mockBot);

            // Assert
            expect(true)->toBeTrue();
        });

        it('displays empty message for user without requests', function () {
            // Arrange
            $user = User::factory()->create([
                'role' => Role::USER->value,
                'company_id' => 1,
                'telegram_id' => 123456789,
            ]);

            Auth::shouldReceive('user')->andReturn($user);

            $this->mockBot->shouldReceive('sendMessage')
                ->once()
                ->withArgs(function ($message, $parseMode = null, $replyMarkup = null) {
                    return str_contains($message, 'У вас пока нет заявок');
                });

            // Act
            $this->historyCommand->handle($this->mockBot);

            // Assert
            expect(true)->toBeTrue();
        });

        it('handles authentication error gracefully', function () {
            // Arrange
            Auth::shouldReceive('user')->andReturn(null);

            $this->mockBot->shouldReceive('sendMessage')
                ->once()
                ->withArgs(function ($message) {
                    return str_contains($message, 'Произошла ошибка');
                });

            // Act
            $this->historyCommand->handle($this->mockBot);

            // Assert
            expect(true)->toBeTrue();
        });
    });
});
