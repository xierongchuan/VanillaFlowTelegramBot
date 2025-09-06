<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature', 'Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

expect()->extend('toBeValidAmount', function () {
    return $this->toBeFloat()->toBeGreaterThan(0);
});

expect()->extend('toBeValidCurrency', function () {
    return $this->toBeString()->toMatch('/^[A-Z]{3}$/');
});

expect()->extend('toBeValidExpenseStatus', function () {
    $validStatuses = ['pending', 'approved', 'declined', 'issued'];
    return $this->toBeIn($validStatuses);
});

expect()->extend('toBeValidRole', function () {
    $validRoles = ['user', 'director', 'accountant'];
    return $this->toBeIn($validRoles);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Create a user with a specific role for testing
 */
function createUserWithRole(string $role, array $attributes = []): \App\Models\User
{
    return \App\Models\User::factory()->create(array_merge([
        'role' => $role,
        'company_id' => 1,
    ], $attributes));
}

/**
 * Create an expense request for testing
 */
function createExpenseRequest(\App\Models\User $requester, array $attributes = []): \App\Models\ExpenseRequest
{
    return \App\Models\ExpenseRequest::factory()->create(array_merge([
        'requester_id' => $requester->id,
        'company_id' => $requester->company_id,
    ], $attributes));
}

/**
 * Create a mock Telegram bot for testing
 */
function mockTelegramBot(): \Mockery\MockInterface
{
    return \Mockery::mock(\SergiX44\Nutgram\Nutgram::class);
}

/**
 * Set up authentication for a user in tests
 */
function authenticateUser(\App\Models\User $user): void
{
    \Illuminate\Support\Facades\Auth::shouldReceive('user')->andReturn($user);
}

/**
 * Create multiple expense requests with different statuses
 */
function createExpenseRequestsWithStatuses(\App\Models\User $requester, array $statuses): \Illuminate\Support\Collection
{
    return collect($statuses)->map(function ($status) use ($requester) {
        return createExpenseRequest($requester, ['status' => $status]);
    });
}
