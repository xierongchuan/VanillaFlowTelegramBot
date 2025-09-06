<?php

declare(strict_types=1);

use App\Services\ExpenseRequestService;
use App\Services\Contracts\NotificationServiceInterface;
use App\Services\Contracts\AuditLogServiceInterface;
use App\Services\Contracts\UserFinderServiceInterface;
use App\Models\User;
use App\Models\ExpenseRequest;
use App\Models\AuditLog;
use App\Enums\Role;
use App\Enums\ExpenseStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;
use Mockery\MockInterface;

beforeEach(function () {
    Log::spy();
    $this->mockBot = Mockery::mock(Nutgram::class);
    $this->mockNotificationService = Mockery::mock(NotificationServiceInterface::class);
    $this->mockAuditLogService = Mockery::mock(AuditLogServiceInterface::class);
    $this->mockUserFinderService = Mockery::mock(UserFinderServiceInterface::class);

    $this->expenseRequestService = new ExpenseRequestService(
        $this->mockNotificationService,
        $this->mockAuditLogService,
        $this->mockUserFinderService
    );
});

afterEach(function () {
    Mockery::close();
});

describe('ExpenseRequestService::createRequest', function () {
    it('creates expense request successfully with audit log', function () {
        // Arrange
        $requester = User::factory()->create([
            'role' => Role::USER->value,
            'company_id' => 1,
            'telegram_id' => 123456789,
        ]);

        $director = User::factory()->create([
            'role' => Role::DIRECTOR->value,
            'company_id' => 1,
            'telegram_id' => 987654321,
        ]);

        // Mock the dependencies
        $this->mockUserFinderService->shouldReceive('findDirectorForCompany')
            ->once()
            ->with(1)
            ->andReturn($director);

        $this->mockAuditLogService->shouldReceive('logExpenseRequestCreated')
            ->once()
            ->withArgs(function ($requestId, $requesterId, $amount, $currency, $description) use ($requester) {
                return is_int($requestId) &&
                       $requesterId === $requester->id &&
                       $amount === 1500.50 &&
                       $currency === 'UZS' &&
                       $description === 'Test expense description';
            });

        $this->mockNotificationService->shouldReceive('notifyDirectorNewRequest')
            ->once()
            ->with($this->mockBot, $director, Mockery::type(ExpenseRequest::class))
            ->andReturn(true);

        // Act
        $requestId = $this->expenseRequestService->createRequest(
            $this->mockBot,
            $requester,
            'Test expense description',
            1500.50,
            'UZS'
        );

        // Assert
        expect($requestId)->toBeInt()->toBeGreaterThan(0);

        $expenseRequest = ExpenseRequest::find($requestId);
        expect($expenseRequest)
            ->not->toBeNull()
            ->and($expenseRequest->requester_id)->toBe($requester->id)
            ->and($expenseRequest->description)->toBe('Test expense description')
            ->and($expenseRequest->amount)->toBe(1500.50)
            ->and($expenseRequest->currency)->toBe('UZS')
            ->and($expenseRequest->status)->toBe(ExpenseStatus::PENDING->value)
            ->and($expenseRequest->company_id)->toBe($requester->company_id);
    });

    it('returns null when database transaction fails', function () {
        // Arrange
        $requester = User::factory()->create([
            'role' => Role::USER->value,
            'company_id' => 1,
        ]);

        // Force a database error by using invalid data
        DB::shouldReceive('transaction')
            ->once()
            ->andThrow(new Exception('Database error'));

        // Act
        $requestId = $this->expenseRequestService->createRequest(
            $this->mockBot,
            $requester,
            'Test description',
            1000.00
        );

        // Assert
        expect($requestId)->toBeNull();
        Log::shouldHaveReceived('error')
            ->once()
            ->with('Ошибка при создании заявки', Mockery::type('array'));
    });

    it('handles missing director gracefully', function () {
        // Arrange
        $requester = User::factory()->create([
            'role' => Role::USER->value,
            'company_id' => 1,
        ]);

        // Mock finding no director
        $this->mockUserFinderService->shouldReceive('findDirectorForCompany')
            ->once()
            ->with(1)
            ->andReturn(null);

        $this->mockAuditLogService->shouldReceive('logExpenseRequestCreated')
            ->once();

        // No notification should be sent if no director found
        $this->mockNotificationService->shouldNotReceive('notifyDirectorNewRequest');

        // Act
        $requestId = $this->expenseRequestService->createRequest(
            $this->mockBot,
            $requester,
            'Test description',
            1000.00
        );

        // Assert
        expect($requestId)->toBeInt()->toBeGreaterThan(0);
    });

    it('handles notification failure gracefully', function () {
        // Arrange
        $requester = User::factory()->create([
            'role' => Role::USER->value,
            'company_id' => 1,
        ]);

        $director = User::factory()->create([
            'role' => Role::DIRECTOR->value,
            'company_id' => 1,
            'telegram_id' => 987654321,
        ]);

        $this->mockUserFinderService->shouldReceive('findDirectorForCompany')
            ->once()
            ->with(1)
            ->andReturn($director);

        $this->mockAuditLogService->shouldReceive('logExpenseRequestCreated')
            ->once();

        $this->mockNotificationService->shouldReceive('notifyDirectorNewRequest')
            ->once()
            ->andThrow(new Exception('Telegram API error'));

        // Act
        $requestId = $this->expenseRequestService->createRequest(
            $this->mockBot,
            $requester,
            'Test description',
            1000.00
        );

        // Assert
        expect($requestId)->toBeInt()->toBeGreaterThan(0);
    });

    it('validates input parameters correctly', function () {
        // Arrange
        $requester = User::factory()->create([
            'role' => Role::USER->value,
            'company_id' => 1,
        ]);

        $this->mockUserFinderService->shouldReceive('findDirectorForCompany')
            ->andReturn(null);
        $this->mockAuditLogService->shouldReceive('logExpenseRequestCreated')
            ->times(3);

        // Act & Assert - Test different parameter combinations
        $requestId1 = $this->expenseRequestService->createRequest(
            $this->mockBot,
            $requester,
            '',
            0.01,
            'USD'
        );
        expect($requestId1)->toBeInt();

        $requestId2 = $this->expenseRequestService->createRequest(
            $this->mockBot,
            $requester,
            'Very long description that might exceed certain limits but should still be processed correctly',
            999999.99
        );
        expect($requestId2)->toBeInt();

        $requestId3 = $this->expenseRequestService->createRequest(
            $this->mockBot,
            $requester,
            'Default currency test',
            100.00
        );
        expect($requestId3)->toBeInt();
    });

    it('handles requester without company_id gracefully', function () {
        // Arrange
        $requester = User::factory()->create([
            'role' => Role::USER->value,
            'company_id' => null,
        ]);

        // Should not be called because transaction will fail
        $this->mockAuditLogService->shouldNotReceive('logExpenseRequestCreated');
        $this->mockUserFinderService->shouldNotReceive('findDirectorForCompany');
        $this->mockNotificationService->shouldNotReceive('notifyDirectorNewRequest');

        // Act
        $requestId = $this->expenseRequestService->createRequest(
            $this->mockBot,
            $requester,
            'Test description',
            1000.00
        );

        // Assert - should return null because company_id is required in database
        expect($requestId)->toBeNull();
    });
});

describe('ExpenseRequestService::deleteRequest', function () {
    it('deletes request with audit log', function () {
        // Arrange
        $user = User::factory()->create();
        $actor = User::factory()->create();
        $expenseRequest = ExpenseRequest::factory()->create([
            'requester_id' => $user->id,
        ]);

        $this->mockAuditLogService->shouldReceive('logExpenseRequestDeleted')
            ->once()
            ->with($expenseRequest->id, $actor->id, 'Test deletion');

        $reason = 'Test deletion';

        // Act
        $this->expenseRequestService->deleteRequest($expenseRequest->id, $actor->id, $reason);

        // Assert
        expect(ExpenseRequest::find($expenseRequest->id))->toBeNull();
    });

    it('handles non-existent request', function () {
        // Arrange
        $nonExistentId = 999999;

        // Act & Assert
        expect(fn () => $this->expenseRequestService->deleteRequest($nonExistentId, 1, 'Test'))
            ->toThrow(Illuminate\Database\Eloquent\ModelNotFoundException::class);
    });

    it('deletes request without reason', function () {
        // Arrange
        $user = User::factory()->create();
        $actor = User::factory()->create();
        $expenseRequest = ExpenseRequest::factory()->create([
            'requester_id' => $user->id,
        ]);

        $this->mockAuditLogService->shouldReceive('logExpenseRequestDeleted')
            ->once()
            ->with($expenseRequest->id, $actor->id, null);

        // Act
        $this->expenseRequestService->deleteRequest($expenseRequest->id, $actor->id);

        // Assert
        expect(ExpenseRequest::find($expenseRequest->id))->toBeNull();
    });
});

describe('ExpenseRequestService::getExpenseRequestById', function () {
    it('returns expense request with relations', function () {
        // Arrange
        $requester = User::factory()->create();
        $expenseRequest = ExpenseRequest::factory()->create([
            'requester_id' => $requester->id,
        ]);

        // Act
        $result = $this->expenseRequestService->getExpenseRequestById($expenseRequest->id);

        // Assert
        expect($result)
            ->not->toBeNull()
            ->and($result->id)->toBe($expenseRequest->id)
            ->and($result->requester)->not->toBeNull()
            ->and($result->requester->id)->toBe($requester->id);
    });

    it('returns null for non-existent request', function () {
        // Act
        $result = $this->expenseRequestService->getExpenseRequestById(999999);

        // Assert
        expect($result)->toBeNull();
    });
});

describe('ExpenseRequestService::getPendingRequestsForCompany', function () {
    it('returns only pending requests for specified company', function () {
        // Arrange
        $company1Id = 1;
        $company2Id = 2;

        $user1 = User::factory()->create(['company_id' => $company1Id]);
        $user2 = User::factory()->create(['company_id' => $company2Id]);

        // Create requests
        ExpenseRequest::factory()->create([
            'requester_id' => $user1->id,
            'company_id' => $company1Id,
            'status' => ExpenseStatus::PENDING->value,
        ]);

        ExpenseRequest::factory()->create([
            'requester_id' => $user1->id,
            'company_id' => $company1Id,
            'status' => ExpenseStatus::APPROVED->value, // Different status
        ]);

        ExpenseRequest::factory()->create([
            'requester_id' => $user2->id,
            'company_id' => $company2Id,
            'status' => ExpenseStatus::PENDING->value, // Different company
        ]);

        // Act
        $result = $this->expenseRequestService->getPendingRequestsForCompany($company1Id);

        // Assert
        expect($result)
            ->toHaveCount(1)
            ->and($result->first()->status)->toBe(ExpenseStatus::PENDING->value)
            ->and($result->first()->company_id)->toBe($company1Id);
    });
});

describe('ExpenseRequestService::getApprovedRequestsForCompany', function () {
    it('returns only approved requests for specified company', function () {
        // Arrange
        $company1Id = 1;
        $company2Id = 2;

        $user1 = User::factory()->create(['company_id' => $company1Id]);
        $user2 = User::factory()->create(['company_id' => $company2Id]);

        // Create requests
        ExpenseRequest::factory()->create([
            'requester_id' => $user1->id,
            'company_id' => $company1Id,
            'status' => ExpenseStatus::APPROVED->value,
        ]);

        ExpenseRequest::factory()->create([
            'requester_id' => $user1->id,
            'company_id' => $company1Id,
            'status' => ExpenseStatus::PENDING->value, // Different status
        ]);

        ExpenseRequest::factory()->create([
            'requester_id' => $user2->id,
            'company_id' => $company2Id,
            'status' => ExpenseStatus::APPROVED->value, // Different company
        ]);

        // Act
        $result = $this->expenseRequestService->getApprovedRequestsForCompany($company1Id);

        // Assert
        expect($result)
            ->toHaveCount(1)
            ->and($result->first()->status)->toBe(ExpenseStatus::APPROVED->value)
            ->and($result->first()->company_id)->toBe($company1Id);
    });
});
