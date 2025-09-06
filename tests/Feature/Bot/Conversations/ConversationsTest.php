<?php

declare(strict_types=1);

use App\Bot\Conversations\User\RequestExpenseConversation;
use App\Bot\Conversations\Director\ConfirmWithCommentConversation;
use App\Models\User;
use App\Models\ExpenseRequest;
use App\Enums\Role;
use App\Enums\ExpenseStatus;
use App\Services\Contracts\ExpenseServiceInterface;
use App\Services\Contracts\ValidationServiceInterface;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;
use SergiX44\Nutgram\Telegram\Types\Message\Message;
use Illuminate\Support\Facades\Auth;
use Mockery;

beforeEach(function () {
    $this->mockBot = Mockery::mock(Nutgram::class);
    $this->mockExpenseService = Mockery::mock(ExpenseServiceInterface::class);
    $this->mockValidationService = Mockery::mock(ValidationServiceInterface::class);

    app()->instance(ExpenseServiceInterface::class, $this->mockExpenseService);
    app()->instance(ValidationServiceInterface::class, $this->mockValidationService);

    $this->requestExpenseConversation = new RequestExpenseConversation();
    $this->confirmWithCommentConversation = new ConfirmWithCommentConversation();
});

afterEach(function () {
    Mockery::close();
});

describe('Bot Conversations', function () {
    describe('RequestExpenseConversation', function () {
        it('starts conversation by asking for amount', function () {
            // Arrange
            $this->mockBot->shouldReceive('sendMessage')
                ->once()
                ->with('Введите сумму в UZS:');

            // Act
            $this->requestExpenseConversation->askAmount($this->mockBot);

            // Assert
            expect($this->requestExpenseConversation->step)->toBe('handleAmount');
        });

        it('handles valid amount input', function () {
            // Arrange
            $mockMessage = Mockery::mock(Message::class);
            $mockMessage->shouldReceive('getAttribute')->with('text')->andReturn('150.50');
            $mockMessage->text = '150.50';

            $this->mockBot->shouldReceive('message')->andReturn($mockMessage);

            $this->mockValidationService->shouldReceive('validateAmount')
                ->once()
                ->with('150.50')
                ->andReturn([
                    'valid' => true,
                    'value' => 150.50,
                ]);

            $this->mockBot->shouldReceive('sendMessage')
                ->once()
                ->withArgs(function ($message) {
                    return str_contains($message, 'Сумма принята: 150.50') &&
                           str_contains($message, 'введите комментарий');
                });

            // Act
            $this->requestExpenseConversation->handleAmount($this->mockBot);

            // Assert
            expect($this->requestExpenseConversation->amount)->toBe(150.50)
                ->and($this->requestExpenseConversation->step)->toBe('handleComment');
        });

        it('handles invalid amount input', function () {
            // Arrange
            $mockMessage = Mockery::mock(Message::class);
            $mockMessage->shouldReceive('getAttribute')->with('text')->andReturn('invalid');
            $mockMessage->text = 'invalid';

            $this->mockBot->shouldReceive('message')->andReturn($mockMessage);

            $this->mockValidationService->shouldReceive('validateAmount')
                ->once()
                ->with('invalid')
                ->andReturn([
                    'valid' => false,
                    'message' => 'Неверный формат суммы',
                ]);

            $this->mockBot->shouldReceive('sendMessage')
                ->once()
                ->with('Неверный формат суммы');

            // Act
            $this->requestExpenseConversation->handleAmount($this->mockBot);

            // Assert
            expect($this->requestExpenseConversation->step)->toBe('handleAmount');
        });

        it('handles valid comment and creates expense request', function () {
            // Arrange
            $user = User::factory()->create([
                'role' => Role::USER->value,
                'company_id' => 1,
            ]);

            $this->requestExpenseConversation->amount = 150.50;

            $mockMessage = Mockery::mock(Message::class);
            $mockMessage->shouldReceive('getAttribute')->with('text')->andReturn('Office supplies');
            $mockMessage->text = 'Office supplies';

            $this->mockBot->shouldReceive('message')->andReturn($mockMessage);

            $this->mockValidationService->shouldReceive('validateComment')
                ->once()
                ->with('Office supplies')
                ->andReturn([
                    'valid' => true,
                ]);

            Auth::shouldReceive('user')->andReturn($user);

            $this->mockExpenseService->shouldReceive('createRequest')
                ->once()
                ->with($this->mockBot, $user, 'Office supplies', 150.50, 'UZS')
                ->andReturn(123); // Request ID

            $this->mockBot->shouldReceive('sendMessage')
                ->once()
                ->withArgs(function ($message, $parseMode = null, $replyMarkup = null) {
                    return str_contains($message, 'Готово! — Создана заявка #123') &&
                           str_contains($message, '150.50');
                });

            // Act
            $this->requestExpenseConversation->handleComment($this->mockBot);

            // Assert
            expect($this->requestExpenseConversation->comment)->toBe('Office supplies');
        });

        it('handles comment validation error', function () {
            // Arrange
            $this->requestExpenseConversation->amount = 150.50;

            $mockMessage = Mockery::mock(Message::class);
            $mockMessage->shouldReceive('getAttribute')->with('text')->andReturn('');
            $mockMessage->text = '';

            $this->mockBot->shouldReceive('message')->andReturn($mockMessage);

            $this->mockValidationService->shouldReceive('validateComment')
                ->once()
                ->with('')
                ->andReturn([
                    'valid' => false,
                    'message' => 'Комментарий не может быть пустым',
                ]);

            $this->mockBot->shouldReceive('sendMessage')
                ->once()
                ->with('Комментарий не может быть пустым');

            // Act
            $this->requestExpenseConversation->handleComment($this->mockBot);

            // Assert
            expect($this->requestExpenseConversation->step)->toBe('handleComment');
        });

        it('handles expense service creation failure', function () {
            // Arrange
            $user = User::factory()->create([
                'role' => Role::USER->value,
                'company_id' => 1,
            ]);

            $this->requestExpenseConversation->amount = 150.50;

            $mockMessage = Mockery::mock(Message::class);
            $mockMessage->shouldReceive('getAttribute')->with('text')->andReturn('Office supplies');
            $mockMessage->text = 'Office supplies';

            $this->mockBot->shouldReceive('message')->andReturn($mockMessage);

            $this->mockValidationService->shouldReceive('validateComment')
                ->once()
                ->andReturn(['valid' => true]);

            Auth::shouldReceive('user')->andReturn($user);

            $this->mockExpenseService->shouldReceive('createRequest')
                ->once()
                ->andReturn(null); // Failure

            $this->mockBot->shouldReceive('sendMessage')
                ->once()
                ->withArgs(function ($message, $parseMode = null, $replyMarkup = null) {
                    return str_contains($message, 'произошла ошибка') &&
                           str_contains($message, 'администратору');
                });

            // Act
            $this->requestExpenseConversation->handleComment($this->mockBot);

            // Assert - The conversation should end after error
            expect(true)->toBeTrue();
        });
    });

    describe('ConfirmWithCommentConversation', function () {
        it('starts conversation by asking for comment', function () {
            // Arrange
            $this->mockBot->shouldReceive('sendMessage')
                ->once()
                ->withArgs(function ($message) {
                    return str_contains($message, 'Введите комментарий');
                });

            // Act
            $this->confirmWithCommentConversation->askComment($this->mockBot);

            // Assert
            expect($this->confirmWithCommentConversation->step)->toBe('handleComment');
        });

        it('handles comment input and approves with comment', function () {
            // Arrange
            $director = User::factory()->create([
                'role' => Role::DIRECTOR->value,
                'company_id' => 1,
            ]);

            $this->confirmWithCommentConversation->requestId = 123;

            $mockMessage = Mockery::mock(Message::class);
            $mockMessage->shouldReceive('getAttribute')->with('text')->andReturn('Approved with conditions');
            $mockMessage->text = 'Approved with conditions';

            $this->mockBot->shouldReceive('message')->andReturn($mockMessage);

            Auth::shouldReceive('user')->andReturn($director);

            // We'll need to mock the approval service
            $mockApprovalService = Mockery::mock(\App\Services\Contracts\ExpenseApprovalServiceInterface::class);
            app()->instance(\App\Services\Contracts\ExpenseApprovalServiceInterface::class, $mockApprovalService);

            $mockApprovalService->shouldReceive('approveRequest')
                ->once()
                ->with($this->mockBot, 123, $director, 'Approved with conditions')
                ->andReturn([
                    'success' => true,
                    'request' => new \stdClass(),
                ]);

            $this->mockBot->shouldReceive('editMessageText')
                ->once()
                ->withArgs(function ($text, $replyMarkup = null) {
                    return str_contains($text, 'подтверждена директором') &&
                           str_contains($text, 'Approved with conditions');
                });

            // Act
            $this->confirmWithCommentConversation->handleComment($this->mockBot);

            // Assert
            expect($this->confirmWithCommentConversation->comment)->toBe('Approved with conditions');
        });
    });
});
