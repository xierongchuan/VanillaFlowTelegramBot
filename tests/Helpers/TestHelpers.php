<?php

/**
 * Test Configuration and Helper Functions
 *
 * This file contains configuration and helper functions specifically for testing
 * the VanillaFlowTelegramBot application.
 */

declare(strict_types=1);

use App\Models\User;
use App\Models\ExpenseRequest;
use App\Enums\Role;
use App\Enums\ExpenseStatus;

/**
 * Create test data scenarios for expense workflow testing
 */
function createExpenseWorkflowScenario(): array
{
    // Create users for different roles
    $requester = User::factory()->create([
        'role' => Role::USER->value,
        'company_id' => 1,
        'full_name' => 'John Requester',
        'telegram_id' => 123456789,
    ]);

    $director = User::factory()->create([
        'role' => Role::DIRECTOR->value,
        'company_id' => 1,
        'full_name' => 'Jane Director',
        'telegram_id' => 987654321,
    ]);

    $accountant = User::factory()->create([
        'role' => Role::ACCOUNTANT->value,
        'company_id' => 1,
        'full_name' => 'Bob Accountant',
        'telegram_id' => 555666777,
    ]);

    return compact('requester', 'director', 'accountant');
}

/**
 * Create multi-company test scenario
 */
function createMultiCompanyScenario(): array
{
    $company1Users = [
        'requester' => User::factory()->create([
            'role' => Role::USER->value,
            'company_id' => 1,
            'full_name' => 'User Company 1',
        ]),
        'director' => User::factory()->create([
            'role' => Role::DIRECTOR->value,
            'company_id' => 1,
            'full_name' => 'Director Company 1',
        ]),
        'accountant' => User::factory()->create([
            'role' => Role::ACCOUNTANT->value,
            'company_id' => 1,
            'full_name' => 'Accountant Company 1',
        ]),
    ];

    $company2Users = [
        'requester' => User::factory()->create([
            'role' => Role::USER->value,
            'company_id' => 2,
            'full_name' => 'User Company 2',
        ]),
        'director' => User::factory()->create([
            'role' => Role::DIRECTOR->value,
            'company_id' => 2,
            'full_name' => 'Director Company 2',
        ]),
        'accountant' => User::factory()->create([
            'role' => Role::ACCOUNTANT->value,
            'company_id' => 2,
            'full_name' => 'Accountant Company 2',
        ]),
    ];

    return compact('company1Users', 'company2Users');
}

/**
 * Create expense requests with various states and amounts
 */
function createVariousExpenseRequests(User $requester): array
{
    $requests = [];

    // Small amount request
    $requests['small'] = ExpenseRequest::factory()->create([
        'requester_id' => $requester->id,
        'company_id' => $requester->company_id,
        'description' => 'Small office supplies',
        'amount' => 25.50,
        'currency' => 'UZS',
        'status' => ExpenseStatus::PENDING->value,
    ]);

    // Medium amount request
    $requests['medium'] = ExpenseRequest::factory()->create([
        'requester_id' => $requester->id,
        'company_id' => $requester->company_id,
        'description' => 'Team lunch expense',
        'amount' => 150.00,
        'currency' => 'UZS',
        'status' => ExpenseStatus::APPROVED->value,
        'approved_at' => now()->subHours(2),
    ]);

    // Large amount request
    $requests['large'] = ExpenseRequest::factory()->create([
        'requester_id' => $requester->id,
        'company_id' => $requester->company_id,
        'description' => 'Business travel expenses',
        'amount' => 2500.00,
        'currency' => 'USD',
        'status' => ExpenseStatus::ISSUED->value,
        'approved_at' => now()->subDays(1),
        'issued_at' => now()->subHours(1),
    ]);

    // Declined request
    $requests['declined'] = ExpenseRequest::factory()->create([
        'requester_id' => $requester->id,
        'company_id' => $requester->company_id,
        'description' => 'Unnecessary expense',
        'amount' => 75.00,
        'currency' => 'UZS',
        'status' => ExpenseStatus::DECLINED->value,
    ]);

    return $requests;
}

/**
 * Assert expense request state matches expected values
 */
function assertExpenseRequestState(ExpenseRequest $request, array $expected): void
{
    foreach ($expected as $field => $value) {
        expect($request->{$field})->toBe($value, "Field {$field} does not match expected value");
    }
}

/**
 * Create mock expectations for Telegram bot
 */
function expectTelegramBotMessage(Mockery\MockInterface $bot, string $expectedText, int $times = 1): void
{
    $bot->shouldReceive('sendMessage')
        ->times($times)
        ->withArgs(function ($message) use ($expectedText) {
            return str_contains($message, $expectedText);
        });
}

/**
 * Create mock expectations for Telegram bot edit message
 */
function expectTelegramBotEditMessage(Mockery\MockInterface $bot, string $expectedText, int $times = 1): void
{
    $bot->shouldReceive('editMessageText')
        ->times($times)
        ->withArgs(function ($text) use ($expectedText) {
            return str_contains($text, $expectedText);
        });
}

/**
 * Test data sets for boundary testing
 */
function getAmountBoundaryTestCases(): array
{
    return [
        'minimum_amount' => ['amount' => 0.01, 'description' => 'Minimum possible amount'],
        'small_amount' => ['amount' => 1.00, 'description' => 'Small expense'],
        'medium_amount' => ['amount' => 500.00, 'description' => 'Medium expense'],
        'large_amount' => ['amount' => 9999.99, 'description' => 'Large expense'],
        'maximum_amount' => ['amount' => 999999.99, 'description' => 'Maximum possible amount'],
    ];
}

/**
 * Test data sets for currency testing
 */
function getCurrencyTestCases(): array
{
    return [
        'uzs' => ['currency' => 'UZS', 'description' => 'Uzbek Som'],
        'usd' => ['currency' => 'USD', 'description' => 'US Dollar'],
        'eur' => ['currency' => 'EUR', 'description' => 'Euro'],
        'rub' => ['currency' => 'RUB', 'description' => 'Russian Ruble'],
        'gbp' => ['currency' => 'GBP', 'description' => 'British Pound'],
    ];
}

/**
 * Test data sets for status transition testing
 */
function getStatusTransitionTestCases(): array
{
    return [
        'pending_to_approved' => [
            'from' => ExpenseStatus::PENDING->value,
            'to' => ExpenseStatus::APPROVED->value,
            'actor_role' => Role::DIRECTOR->value,
        ],
        'pending_to_declined' => [
            'from' => ExpenseStatus::PENDING->value,
            'to' => ExpenseStatus::DECLINED->value,
            'actor_role' => Role::DIRECTOR->value,
        ],
        'approved_to_issued' => [
            'from' => ExpenseStatus::APPROVED->value,
            'to' => ExpenseStatus::ISSUED->value,
            'actor_role' => Role::ACCOUNTANT->value,
        ],
    ];
}

/**
 * Create test messages with various lengths and content
 */
function getMessageTestCases(): array
{
    return [
        'short' => 'Short message',
        'medium' => str_repeat('Medium length message. ', 10),
        'long' => str_repeat('This is a very long message that tests the system boundaries. ', 50),
        'special_chars' => 'Message with special chars: !@#$%^&*()_+-=[]{}|;:",.<>?',
        'unicode' => 'Сообщение с юникодом и эмодзи 🚀💰📝',
        'empty' => '',
        'whitespace_only' => '   \t\n   ',
    ];
}

/**
 * Mock service method with specific return value
 */
function mockServiceMethod(string $serviceClass, string $method, $returnValue): Mockery\MockInterface
{
    $mock = Mockery::mock($serviceClass);
    $mock->shouldReceive($method)->andReturn($returnValue);
    app()->instance($serviceClass, $mock);
    return $mock;
}
