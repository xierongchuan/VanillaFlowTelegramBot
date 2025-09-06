<?php

declare(strict_types=1);

use App\Bot\Callbacks\ExpenseConfirmCallback;
use App\Bot\Callbacks\ExpenseDeclineCallback;
use App\Bot\Callbacks\ExpenseIssuedCallback;
use App\Bot\Callbacks\ExpenseIssuedFullCallback;
use App\Models\User;
use App\Models\ExpenseRequest;
use App\Enums\Role;
use App\Enums\ExpenseStatus;
use App\Services\Contracts\ExpenseApprovalServiceInterface;
use App\Services\Contracts\NotificationServiceInterface;
use SergiX44\Nutgram\Nutgram;
use Illuminate\Support\Facades\Auth;
use Mockery;

beforeEach(function () {
    $this->mockBot = Mockery::mock(Nutgram::class);
    $this->mockApprovalService = Mockery::mock(ExpenseApprovalServiceInterface::class);
    $this->mockNotificationService = Mockery::mock(NotificationServiceInterface::class);

    app()->instance(ExpenseApprovalServiceInterface::class, $this->mockApprovalService);
    app()->instance(NotificationServiceInterface::class, $this->mockNotificationService);

    $this->confirmCallback = new ExpenseConfirmCallback();
    $this->declineCallback = new ExpenseDeclineCallback();
    $this->issuedCallback = new ExpenseIssuedCallback();
    $this->issuedFullCallback = new ExpenseIssuedFullCallback();
});

afterEach(function () {
    Mockery::close();
});

describe('Bot Callbacks', function () {
    describe('ExpenseConfirmCallback', function () {
        it('handles expense approval successfully', function () {
            // Arrange
            $director = User::factory()->create([
                'role' => Role::DIRECTOR->value,
                'company_id' => 1,
                'full_name' => 'Jane Director',
            ]);

            $requester = User::factory()->create([
                'role' => Role::USER->value,
                'company_id' => 1,
                'full_name' => 'John User',
            ]);

            $request = ExpenseRequest::factory()->create([
                'requester_id' => $requester->id,
                'company_id' => 1,
                'description' => 'Office supplies',
                'amount' => 150.00,
                'currency' => 'UZS',
                'status' => ExpenseStatus::PENDING->value,
            ]);

            Auth::shouldReceive('user')->andReturn($director);

            $this->mockApprovalService->shouldReceive('approveRequest')
                ->once()
                ->with($this->mockBot, $request->id, $director)
                ->andReturn([
                    'success' => true,
                    'request' => $request->fresh(),
                ]);

            $this->mockBot->shouldReceive('editMessageText')
                ->once()
                ->withArgs(function ($text, $replyMarkup = null) use ($request) {
                    return str_contains($text, 'подтверждена директором') &&
                           str_contains($text, "#{$request->id}") &&
                           str_contains($text, '150.00') &&
                           str_contains($text, 'Office supplies');
                });

            // Act
            $this->confirmCallback->execute($this->mockBot, (string) $request->id);

            // Assert - No exceptions thrown means success
            expect(true)->toBeTrue();
        });

        it('handles approval service error', function () {
            // Arrange
            $director = User::factory()->create([
                'role' => Role::DIRECTOR->value,
                'company_id' => 1,
            ]);

            Auth::shouldReceive('user')->andReturn($director);

            $this->mockApprovalService->shouldReceive('approveRequest')
                ->once()
                ->andReturn([
                    'success' => false,
                    'message' => 'Request not found',
                ]);

            // Act & Assert
            expect(fn () => $this->confirmCallback->execute($this->mockBot, '999'))
                ->toThrow(RuntimeException::class, 'Request not found');
        });
    });

    describe('ExpenseDeclineCallback', function () {
        it('handles expense decline successfully', function () {
            // Arrange
            $director = User::factory()->create([
                'role' => Role::DIRECTOR->value,
                'company_id' => 1,
                'full_name' => 'Jane Director',
            ]);

            $requester = User::factory()->create([
                'role' => Role::USER->value,
                'company_id' => 1,
                'full_name' => 'John User',
            ]);

            $request = ExpenseRequest::factory()->create([
                'requester_id' => $requester->id,
                'company_id' => 1,
                'description' => 'Office supplies',
                'amount' => 150.00,
                'currency' => 'UZS',
                'status' => ExpenseStatus::PENDING->value,
            ]);

            Auth::shouldReceive('user')->andReturn($director);

            $this->mockApprovalService->shouldReceive('declineRequest')
                ->once()
                ->with($this->mockBot, $request->id, $director, null)
                ->andReturn([
                    'success' => true,
                    'request' => $request->fresh(),
                ]);

            $this->mockBot->shouldReceive('editMessageText')
                ->once()
                ->withArgs(function ($text, $replyMarkup = null) use ($request) {
                    return str_contains($text, 'отклонена директором') &&
                           str_contains($text, "#{$request->id}");
                });

            // Act
            $this->declineCallback->execute($this->mockBot, (string) $request->id);

            // Assert
            expect(true)->toBeTrue();
        });
    });

    describe('ExpenseIssuedFullCallback', function () {
        it('handles full amount issuance successfully', function () {
            // Arrange
            $accountant = User::factory()->create([
                'role' => Role::ACCOUNTANT->value,
                'company_id' => 1,
                'full_name' => 'Bob Accountant',
            ]);

            $requester = User::factory()->create([
                'role' => Role::USER->value,
                'company_id' => 1,
                'full_name' => 'John User',
            ]);

            $request = ExpenseRequest::factory()->create([
                'requester_id' => $requester->id,
                'company_id' => 1,
                'description' => 'Office supplies',
                'amount' => 150.00,
                'currency' => 'UZS',
                'status' => ExpenseStatus::APPROVED->value,
            ]);

            Auth::shouldReceive('user')->andReturn($accountant);

            $this->mockApprovalService->shouldReceive('issueRequest')
                ->once()
                ->with($this->mockBot, $request->id, $accountant, $request->amount)
                ->andReturn([
                    'success' => true,
                    'request' => $request->fresh(),
                ]);

            $this->mockBot->shouldReceive('editMessageText')
                ->once()
                ->withArgs(function ($text, $replyMarkup = null) use ($request) {
                    return str_contains($text, 'выдана бухгалтером') &&
                           str_contains($text, "#{$request->id}") &&
                           str_contains($text, '150.00');
                });

            // Act
            $this->issuedFullCallback->execute($this->mockBot, (string) $request->id);

            // Assert
            expect(true)->toBeTrue();
        });
    });

    describe('Error Handling', function () {
        it('handles authentication errors gracefully', function () {
            // Arrange
            Auth::shouldReceive('user')->andReturn(null);

            // Act & Assert
            expect(fn () => $this->confirmCallback->execute($this->mockBot, '1'))
                ->toThrow(RuntimeException::class);
        });

        it('handles invalid request ID gracefully', function () {
            // Arrange
            $director = User::factory()->create([
                'role' => Role::DIRECTOR->value,
                'company_id' => 1,
            ]);

            Auth::shouldReceive('user')->andReturn($director);

            $this->mockApprovalService->shouldReceive('approveRequest')
                ->once()
                ->andReturn([
                    'success' => false,
                    'message' => 'Request not found',
                ]);

            // Act & Assert
            expect(fn () => $this->confirmCallback->execute($this->mockBot, 'invalid'))
                ->toThrow(RuntimeException::class);
        });
    });
});
