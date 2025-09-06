<?php

declare(strict_types=1);

use App\Bot\Commands\Director\StartCommand;
use App\Bot\Commands\Director\PendingExpensesCommand;
use App\Bot\Commands\Director\HistoryCommand;
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
    $this->pendingExpensesCommand = new PendingExpensesCommand();
    $this->historyCommand = new HistoryCommand();
});

afterEach(function () {
    Mockery::close();
});

describe('Director Commands', function () {
    describe('StartCommand', function () {
        it('handles start command for director', function () {
            // Arrange
            $director = User::factory()->create([
                'role' => Role::DIRECTOR->value,
                'company_id' => 1,
                'full_name' => 'Jane Director',
            ]);

            Auth::shouldReceive('user')->andReturn($director);

            $this->mockBot->shouldReceive('sendMessage')
                ->once()
                ->withArgs(function ($message, $options = null) use ($director) {
                    return str_contains($message, 'Добро пожаловать') &&
                           str_contains($message, 'Директор') &&
                           str_contains($message, $director->full_name);
                });

            // Act
            $this->startCommand->handle($this->mockBot);

            // Assert
            expect(true)->toBeTrue();
        });
    });

    describe('PendingExpensesCommand', function () {
        it('displays pending expenses for director company', function () {
            // Arrange
            $director = User::factory()->create([
                'role' => Role::DIRECTOR->value,
                'company_id' => 1,
                'full_name' => 'Jane Director',
            ]);

            $user1 = User::factory()->create([
                'role' => Role::USER->value,
                'company_id' => 1,
                'full_name' => 'John User',
            ]);

            $user2 = User::factory()->create([
                'role' => Role::USER->value,
                'company_id' => 2, // Different company
                'full_name' => 'Bob User',
            ]);

            // Create pending requests
            $request1 = ExpenseRequest::factory()->create([
                'requester_id' => $user1->id,
                'company_id' => 1,
                'description' => 'Office supplies',
                'amount' => 150.00,
                'currency' => 'UZS',
                'status' => ExpenseStatus::PENDING->value,
                'created_at' => now()->subDays(1),
            ]);

            $request2 = ExpenseRequest::factory()->create([
                'requester_id' => $user2->id,
                'company_id' => 2,
                'description' => 'Different company request',
                'amount' => 200.00,
                'currency' => 'UZS',
                'status' => ExpenseStatus::PENDING->value,
                'created_at' => now()->subDays(1),
            ]);

            // Create already processed request for same company
            $request3 = ExpenseRequest::factory()->create([
                'requester_id' => $user1->id,
                'company_id' => 1,
                'description' => 'Already approved',
                'amount' => 300.00,
                'currency' => 'UZS',
                'status' => ExpenseStatus::APPROVED->value,
                'created_at' => now()->subDays(2),
            ]);

            Auth::shouldReceive('user')->andReturn($director);

            $this->mockBot->shouldReceive('sendMessage')
                ->once()
                ->withArgs(function ($message, $parseMode = null, $replyMarkup = null) {
                    return str_contains($message, 'Ожидающие заявки') &&
                           str_contains($message, 'Office supplies') &&
                           str_contains($message, '150.00') &&
                           str_contains($message, 'John User') &&
                           !str_contains($message, 'Different company request') &&
                           !str_contains($message, 'Already approved');
                });

            // Act
            $this->pendingExpensesCommand->handle($this->mockBot);

            // Assert
            expect(true)->toBeTrue();
        });

        it('displays empty message when no pending requests', function () {
            // Arrange
            $director = User::factory()->create([
                'role' => Role::DIRECTOR->value,
                'company_id' => 1,
                'full_name' => 'Jane Director',
            ]);

            Auth::shouldReceive('user')->andReturn($director);

            $this->mockBot->shouldReceive('sendMessage')
                ->once()
                ->withArgs(function ($message, $parseMode = null, $replyMarkup = null) {
                    return str_contains($message, 'Нет ожидающих заявок');
                });

            // Act
            $this->pendingExpensesCommand->handle($this->mockBot);

            // Assert
            expect(true)->toBeTrue();
        });
    });

    describe('HistoryCommand', function () {
        it('displays history of processed requests for director company', function () {
            // Arrange
            $director = User::factory()->create([
                'role' => Role::DIRECTOR->value,
                'company_id' => 1,
                'full_name' => 'Jane Director',
            ]);

            $user = User::factory()->create([
                'role' => Role::USER->value,
                'company_id' => 1,
                'full_name' => 'John User',
            ]);

            // Create processed requests
            $request1 = ExpenseRequest::factory()->create([
                'requester_id' => $user->id,
                'company_id' => 1,
                'description' => 'Approved request',
                'amount' => 150.00,
                'currency' => 'UZS',
                'status' => ExpenseStatus::APPROVED->value,
                'director_id' => $director->id,
                'approved_at' => now()->subHours(2),
                'created_at' => now()->subDays(1),
            ]);

            $request2 = ExpenseRequest::factory()->create([
                'requester_id' => $user->id,
                'company_id' => 1,
                'description' => 'Declined request',
                'amount' => 500.00,
                'currency' => 'UZS',
                'status' => ExpenseStatus::DECLINED->value,
                'director_id' => $director->id,
                'created_at' => now()->subDays(2),
            ]);

            // Pending request should not appear in history
            $request3 = ExpenseRequest::factory()->create([
                'requester_id' => $user->id,
                'company_id' => 1,
                'description' => 'Still pending',
                'amount' => 300.00,
                'currency' => 'UZS',
                'status' => ExpenseStatus::PENDING->value,
                'created_at' => now()->subDays(1),
            ]);

            Auth::shouldReceive('user')->andReturn($director);

            $this->mockBot->shouldReceive('sendMessage')
                ->once()
                ->withArgs(function ($message, $parseMode = null, $replyMarkup = null) {
                    return str_contains($message, 'История решений') &&
                           str_contains($message, 'Approved request') &&
                           str_contains($message, 'Declined request') &&
                           str_contains($message, '150.00') &&
                           str_contains($message, '500.00') &&
                           !str_contains($message, 'Still pending');
                });

            // Act
            $this->historyCommand->handle($this->mockBot);

            // Assert
            expect(true)->toBeTrue();
        });

        it('displays empty message when no processed requests', function () {
            // Arrange
            $director = User::factory()->create([
                'role' => Role::DIRECTOR->value,
                'company_id' => 1,
                'full_name' => 'Jane Director',
            ]);

            Auth::shouldReceive('user')->andReturn($director);

            $this->mockBot->shouldReceive('sendMessage')
                ->once()
                ->withArgs(function ($message, $parseMode = null, $replyMarkup = null) {
                    return str_contains($message, 'Пока нет обработанных заявок');
                });

            // Act
            $this->historyCommand->handle($this->mockBot);

            // Assert
            expect(true)->toBeTrue();
        });
    });
});
