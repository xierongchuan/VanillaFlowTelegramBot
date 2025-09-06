<?php

declare(strict_types=1);

use App\Models\ExpenseRequest;
use App\Models\User;
use App\Enums\ExpenseStatus;
use App\Enums\Role;

describe('ExpenseRequest Model', function () {
    it('can create an expense request with all fields', function () {
        // Arrange
        $user = User::factory()->create();

        // Act
        $expenseRequest = ExpenseRequest::create([
            'requester_id' => $user->id,
            'title' => 'Office Supplies',
            'description' => 'Purchase of office supplies for the team',
            'amount' => 1500.75,
            'currency' => 'UZS',
            'status' => ExpenseStatus::PENDING->value,
            'company_id' => 1,
        ]);

        // Assert
        expect($expenseRequest)
            ->toBeInstanceOf(ExpenseRequest::class)
            ->and($expenseRequest->requester_id)->toBe($user->id)
            ->and($expenseRequest->title)->toBe('Office Supplies')
            ->and($expenseRequest->description)->toBe('Purchase of office supplies for the team')
            ->and($expenseRequest->amount)->toBe(1500.75)
            ->and($expenseRequest->currency)->toBe('UZS')
            ->and($expenseRequest->status)->toBe(ExpenseStatus::PENDING->value)
            ->and($expenseRequest->company_id)->toBe(1)
            ->and($expenseRequest->exists)->toBeTrue();
    });

    it('can create expense request with minimum required fields', function () {
        // Arrange
        $user = User::factory()->create();

        // Act
        $expenseRequest = ExpenseRequest::create([
            'requester_id' => $user->id,
            'description' => 'Basic expense',
            'amount' => 100.00,
            'currency' => 'UZS',
            'status' => ExpenseStatus::PENDING->value,
            'company_id' => 1,
        ]);

        // Assert
        expect($expenseRequest)
            ->toBeInstanceOf(ExpenseRequest::class)
            ->and($expenseRequest->requester_id)->toBe($user->id)
            ->and($expenseRequest->description)->toBe('Basic expense')
            ->and($expenseRequest->amount)->toBe(100.00)
            ->and($expenseRequest->exists)->toBeTrue();
    });

    it('belongs to a user (requester relationship)', function () {
        // Arrange
        $user = User::factory()->create(['full_name' => 'John Doe']);
        $expenseRequest = ExpenseRequest::factory()->create([
            'requester_id' => $user->id,
        ]);

        // Act
        $requester = $expenseRequest->requester;

        // Assert
        expect($requester)
            ->toBeInstanceOf(User::class)
            ->and($requester->id)->toBe($user->id)
            ->and($requester->full_name)->toBe('John Doe');
    });

    it('can handle different expense amounts', function () {
        // Arrange
        $user = User::factory()->create();
        $amounts = [0.01, 99.99, 1000.00, 999999.99];

        // Act & Assert
        foreach ($amounts as $amount) {
            $expenseRequest = ExpenseRequest::create([
                'requester_id' => $user->id,
                'description' => "Expense for {$amount}",
                'amount' => $amount,
                'currency' => 'UZS',
                'status' => ExpenseStatus::PENDING->value,
                'company_id' => 1,
            ]);

            expect($expenseRequest->amount)->toBe($amount);
        }
    });

    it('can handle different currencies', function () {
        // Arrange
        $user = User::factory()->create();
        $currencies = ['UZS', 'USD', 'EUR', 'RUB'];

        // Act & Assert
        foreach ($currencies as $currency) {
            $expenseRequest = ExpenseRequest::create([
                'requester_id' => $user->id,
                'description' => "Expense in {$currency}",
                'amount' => 100.00,
                'currency' => $currency,
                'status' => ExpenseStatus::PENDING->value,
                'company_id' => 1,
            ]);

            expect($expenseRequest->currency)->toBe($currency);
        }
    });

    it('can handle different expense statuses', function () {
        // Arrange
        $user = User::factory()->create();
        $statuses = [
            ExpenseStatus::PENDING->value,
            ExpenseStatus::APPROVED->value,
            ExpenseStatus::DECLINED->value,
            ExpenseStatus::ISSUED->value,
        ];

        // Act & Assert
        foreach ($statuses as $status) {
            $expenseRequest = ExpenseRequest::create([
                'requester_id' => $user->id,
                'description' => "Expense with status {$status}",
                'amount' => 100.00,
                'currency' => 'UZS',
                'status' => $status,
                'company_id' => 1,
            ]);

            expect($expenseRequest->status)->toBe($status);
        }
    });

    it('can query expense requests by status', function () {
        // Arrange
        $user = User::factory()->create();

        ExpenseRequest::factory()->create([
            'requester_id' => $user->id,
            'status' => ExpenseStatus::PENDING->value,
        ]);

        ExpenseRequest::factory()->create([
            'requester_id' => $user->id,
            'status' => ExpenseStatus::APPROVED->value,
        ]);

        ExpenseRequest::factory()->create([
            'requester_id' => $user->id,
            'status' => ExpenseStatus::PENDING->value,
        ]);

        // Act
        $pendingRequests = ExpenseRequest::where('status', ExpenseStatus::PENDING->value)->get();
        $approvedRequests = ExpenseRequest::where('status', ExpenseStatus::APPROVED->value)->get();

        // Assert
        expect($pendingRequests)->toHaveCount(2)
            ->and($approvedRequests)->toHaveCount(1);
    });

    it('can query expense requests by company', function () {
        // Arrange
        $user1 = User::factory()->create(['company_id' => 1]);
        $user2 = User::factory()->create(['company_id' => 2]);

        ExpenseRequest::factory()->create([
            'requester_id' => $user1->id,
            'company_id' => 1,
        ]);

        ExpenseRequest::factory()->create([
            'requester_id' => $user2->id,
            'company_id' => 2,
        ]);

        ExpenseRequest::factory()->create([
            'requester_id' => $user1->id,
            'company_id' => 1,
        ]);

        // Act
        $company1Requests = ExpenseRequest::where('company_id', 1)->get();
        $company2Requests = ExpenseRequest::where('company_id', 2)->get();

        // Assert
        expect($company1Requests)->toHaveCount(2)
            ->and($company2Requests)->toHaveCount(1);
    });

    it('can update expense request status', function () {
        // Arrange
        $user = User::factory()->create();
        $expenseRequest = ExpenseRequest::factory()->create([
            'requester_id' => $user->id,
            'status' => ExpenseStatus::PENDING->value,
        ]);

        // Act
        $expenseRequest->update(['status' => ExpenseStatus::APPROVED->value]);

        // Assert
        expect($expenseRequest->fresh()->status)->toBe(ExpenseStatus::APPROVED->value);
    });

    it('handles long descriptions correctly', function () {
        // Arrange
        $user = User::factory()->create();
        $longDescription = str_repeat('This is a very long description. ', 100);

        // Act
        $expenseRequest = ExpenseRequest::create([
            'requester_id' => $user->id,
            'description' => $longDescription,
            'amount' => 100.00,
            'currency' => 'UZS',
            'status' => ExpenseStatus::PENDING->value,
            'company_id' => 1,
        ]);

        // Assert
        expect($expenseRequest->description)->toBe($longDescription)
            ->and(strlen($expenseRequest->description))->toBeGreaterThan(1000);
    });

    it('has director relationship', function () {
        // Arrange
        $director = User::factory()->create(['role' => Role::DIRECTOR->value]);
        $user = User::factory()->create();
        $expenseRequest = ExpenseRequest::factory()->create([
            'requester_id' => $user->id,
            'director_id' => $director->id,
        ]);

        // Act
        $directorFromRequest = $expenseRequest->director;

        // Assert
        expect($directorFromRequest)
            ->toBeInstanceOf(User::class)
            ->and($directorFromRequest->id)->toBe($director->id)
            ->and($directorFromRequest->role)->toBe(Role::DIRECTOR->value);
    });

    it('has accountant relationship', function () {
        // Arrange
        $accountant = User::factory()->create(['role' => Role::ACCOUNTANT->value]);
        $user = User::factory()->create();
        $expenseRequest = ExpenseRequest::factory()->create([
            'requester_id' => $user->id,
            'accountant_id' => $accountant->id,
        ]);

        // Act
        $accountantFromRequest = $expenseRequest->accountant;

        // Assert
        expect($accountantFromRequest)
            ->toBeInstanceOf(User::class)
            ->and($accountantFromRequest->id)->toBe($accountant->id)
            ->and($accountantFromRequest->role)->toBe(Role::ACCOUNTANT->value);
    });

    it('has approvals relationship', function () {
        // Arrange
        $user = User::factory()->create();
        $director = User::factory()->create(['role' => Role::DIRECTOR->value]);
        $expenseRequest = ExpenseRequest::factory()->create([
            'requester_id' => $user->id,
        ]);

        // Create some expense approvals
        \App\Models\ExpenseApproval::create([
            'expense_request_id' => $expenseRequest->id,
            'actor_id' => $director->id,
            'actor_role' => Role::DIRECTOR->value,
            'action' => 'approved',
            'comment' => 'Approved by director',
            'created_at' => now(),
        ]);

        // Act
        $approvals = $expenseRequest->approvals;

        // Assert
        expect($approvals)
            ->toHaveCount(1)
            ->and($approvals->first()->actor_id)->toBe($director->id)
            ->and($approvals->first()->action)->toBe('approved');
    });

    it('handles nullable director and accountant relationships', function () {
        // Arrange
        $user = User::factory()->create();
        $expenseRequest = ExpenseRequest::factory()->create([
            'requester_id' => $user->id,
            'director_id' => null,
            'accountant_id' => null,
        ]);

        // Act & Assert
        expect($expenseRequest->director)->toBeNull()
            ->and($expenseRequest->accountant)->toBeNull();
    });

    it('validates amount constraints', function () {
        // Arrange
        $user = User::factory()->create();

        // Test very small amounts
        $smallExpense = ExpenseRequest::create([
            'requester_id' => $user->id,
            'description' => 'Small expense',
            'amount' => 0.01,
            'currency' => 'UZS',
            'status' => ExpenseStatus::PENDING->value,
            'company_id' => 1,
        ]);

        expect($smallExpense->amount)->toBe(0.01);

        // Test large amounts
        $largeExpense = ExpenseRequest::create([
            'requester_id' => $user->id,
            'description' => 'Large expense',
            'amount' => 999999.99,
            'currency' => 'UZS',
            'status' => ExpenseStatus::PENDING->value,
            'company_id' => 1,
        ]);

        expect($largeExpense->amount)->toBe(999999.99);
    });

    it('handles special currency codes', function () {
        // Arrange
        $user = User::factory()->create();
        $specialCurrencies = ['EUR', 'GBP', 'JPY', 'CNY', 'RUB'];

        // Act & Assert
        foreach ($specialCurrencies as $currency) {
            $expenseRequest = ExpenseRequest::create([
                'requester_id' => $user->id,
                'description' => "Expense in {$currency}",
                'amount' => 100.00,
                'currency' => $currency,
                'status' => ExpenseStatus::PENDING->value,
                'company_id' => 1,
            ]);

            expect($expenseRequest->currency)->toBe($currency);
        }
    });

    it('can query by multiple statuses', function () {
        // Arrange
        $user = User::factory()->create();

        // Create requests with different statuses
        ExpenseRequest::factory()->create([
            'requester_id' => $user->id,
            'status' => ExpenseStatus::PENDING->value,
        ]);

        ExpenseRequest::factory()->create([
            'requester_id' => $user->id,
            'status' => ExpenseStatus::APPROVED->value,
        ]);

        ExpenseRequest::factory()->create([
            'requester_id' => $user->id,
            'status' => ExpenseStatus::DECLINED->value,
        ]);

        ExpenseRequest::factory()->create([
            'requester_id' => $user->id,
            'status' => ExpenseStatus::ISSUED->value,
        ]);

        // Act
        $activeRequests = ExpenseRequest::whereIn('status', [
            ExpenseStatus::PENDING->value,
            ExpenseStatus::APPROVED->value
        ])->get();

        $completedRequests = ExpenseRequest::whereIn('status', [
            ExpenseStatus::DECLINED->value,
            ExpenseStatus::ISSUED->value
        ])->get();

        // Assert
        expect($activeRequests)->toHaveCount(2)
            ->and($completedRequests)->toHaveCount(2);
    });

    it('updates timestamps correctly', function () {
        // Arrange
        $user = User::factory()->create();
        $expenseRequest = ExpenseRequest::factory()->create([
            'requester_id' => $user->id,
            'status' => ExpenseStatus::PENDING->value,
        ]);

        $originalUpdatedAt = $expenseRequest->updated_at;

        // Act - wait a moment and update
        sleep(1);
        $expenseRequest->update(['status' => ExpenseStatus::APPROVED->value]);

        // Assert
        expect($expenseRequest->fresh()->updated_at)
            ->toBeGreaterThan($originalUpdatedAt);
    });

    it('handles director comment field', function () {
        // Arrange
        $user = User::factory()->create();
        $longComment = str_repeat('This is a detailed comment. ', 50);

        // Act
        $expenseRequest = ExpenseRequest::create([
            'requester_id' => $user->id,
            'description' => 'Test expense',
            'amount' => 100.00,
            'currency' => 'UZS',
            'status' => ExpenseStatus::PENDING->value,
            'company_id' => 1,
            'director_comment' => $longComment,
        ]);

        // Assert
        expect($expenseRequest->director_comment)
            ->toBe($longComment)
            ->and(strlen($expenseRequest->director_comment))->toBeGreaterThan(500);
    });

    it('handles approved_at and issued_at timestamps', function () {
        // Arrange
        $user = User::factory()->create();
        $approvedAt = now()->subHour();
        $issuedAt = now();

        // Act
        $expenseRequest = ExpenseRequest::create([
            'requester_id' => $user->id,
            'description' => 'Test expense',
            'amount' => 100.00,
            'currency' => 'UZS',
            'status' => ExpenseStatus::ISSUED->value,
            'company_id' => 1,
            'approved_at' => $approvedAt,
            'issued_at' => $issuedAt,
        ]);

        // Assert
        expect($expenseRequest->approved_at)
            ->toBeInstanceOf(\Carbon\Carbon::class)
            ->and($expenseRequest->issued_at)
            ->toBeInstanceOf(\Carbon\Carbon::class)
            ->and($expenseRequest->approved_at->format('Y-m-d H:i'))
            ->toBe($approvedAt->format('Y-m-d H:i'))
            ->and($expenseRequest->issued_at->format('Y-m-d H:i'))
            ->toBe($issuedAt->format('Y-m-d H:i'));
    });
});
