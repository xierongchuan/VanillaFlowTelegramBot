<?php

declare(strict_types=1);

use App\Services\ExpenseService;
use App\Services\TelegramNotificationService;
use App\Services\AuditLogService;
use App\Services\UserFinderService;
use App\Services\ValidationService;
use App\Services\ExpenseApprovalService;
use App\Models\User;
use App\Models\ExpenseRequest;
use App\Models\AuditLog;
use App\Enums\Role;
use App\Enums\ExpenseStatus;
use SergiX44\Nutgram\Nutgram;

describe('Services Integration', function () {
    it('integrates all services for complete expense workflow', function () {
        // Arrange
        $scenario = createExpenseWorkflowScenario();
        extract($scenario); // $requester, $director, $accountant

        $mockBot = mockTelegramBot();

        // Set up real service instances
        $notificationService = app(TelegramNotificationService::class);
        $auditLogService = app(AuditLogService::class);
        $userFinderService = app(UserFinderService::class);
        $validationService = app(ValidationService::class);
        $approvalService = app(ExpenseApprovalService::class);

        $expenseService = new ExpenseService(
            $notificationService,
            $auditLogService,
            $userFinderService
        );

        // Mock telegram notifications
        $mockBot->shouldReceive('sendMessage')->andReturn(null);

        // Act 1: Create expense request
        $requestId = $expenseService->createRequest(
            $mockBot,
            $requester,
            'Integration test expense',
            1000.00,
            'UZS'
        );

        // Assert 1: Request created successfully
        expect($requestId)->toBeInt()->toBeGreaterThan(0);

        $request = ExpenseRequest::find($requestId);
        expect($request)
            ->not->toBeNull()
            ->and($request->status)->toBe(ExpenseStatus::PENDING->value)
            ->and($request->amount)->toBe(1000.00)
            ->and($request->requester_id)->toBe($requester->id);

        // Verify audit log created
        $creationAudit = AuditLog::where('record_id', $requestId)
            ->where('action', 'insert')
            ->first();
        expect($creationAudit)->not->toBeNull();

        // Act 2: Director approves request
        authenticateUser($director);

        $approvalResult = $approvalService->approveRequest(
            $mockBot,
            $requestId,
            $director,
            'Approved for business needs'
        );

        // Assert 2: Request approved successfully
        expect($approvalResult['success'])->toBeTrue();

        $request = $request->fresh();
        expect($request->status)->toBe(ExpenseStatus::APPROVED->value)
            ->and($request->director_id)->toBe($director->id)
            ->and($request->approved_at)->not->toBeNull();

        // Act 3: Accountant issues payment
        authenticateUser($accountant);

        $issuanceResult = $approvalService->issueRequest(
            $mockBot,
            $requestId,
            $accountant,
            1000.00 // Full amount
        );

        // Assert 3: Request issued successfully
        expect($issuanceResult['success'])->toBeTrue();

        $request = $request->fresh();
        expect($request->status)->toBe(ExpenseStatus::ISSUED->value)
            ->and($request->accountant_id)->toBe($accountant->id)
            ->and($request->issued_at)->not->toBeNull();

        // Assert 4: Complete audit trail exists
        $auditLogs = AuditLog::where('record_id', $requestId)->get();
        expect($auditLogs)->toHaveCount(3); // insert, approved, issued
    });

    it('validates amount and comment correctly', function () {
        // Arrange
        $validationService = app(ValidationService::class);

        // Test valid amounts
        $validAmounts = ['100', '100.50', '0.01', '999999.99'];
        foreach ($validAmounts as $amount) {
            $result = $validationService->validateAmount($amount);
            expect($result['valid'])->toBeTrue();
        }

        // Test invalid amounts
        $invalidAmounts = ['', 'abc', '-100', '0', '1000000'];
        foreach ($invalidAmounts as $amount) {
            $result = $validationService->validateAmount($amount);
            expect($result['valid'])->toBeFalse();
        }

        // Test valid comments
        $validComments = [
            'Office supplies',
            'Business travel to conference',
            str_repeat('a', 500), // Long but valid
        ];
        foreach ($validComments as $comment) {
            $result = $validationService->validateComment($comment);
            expect($result['valid'])->toBeTrue();
        }

        // Test invalid comments
        $invalidComments = [
            '',
            '   ',
            str_repeat('a', 1001), // Too long
        ];
        foreach ($invalidComments as $comment) {
            $result = $validationService->validateComment($comment);
            expect($result['valid'])->toBeFalse();
        }
    });

    it('finds users by role and company correctly', function () {
        // Arrange
        $multiCompany = createMultiCompanyScenario();
        $userFinderService = app(UserFinderService::class);

        // Act & Assert: Find directors
        $director1 = $userFinderService->findDirectorForCompany(1);
        $director2 = $userFinderService->findDirectorForCompany(2);

        expect($director1)
            ->not->toBeNull()
            ->and($director1->role)->toBe(Role::DIRECTOR->value)
            ->and($director1->company_id)->toBe(1);

        expect($director2)
            ->not->toBeNull()
            ->and($director2->role)->toBe(Role::DIRECTOR->value)
            ->and($director2->company_id)->toBe(2);

        // Act & Assert: Find accountants
        $accountant1 = $userFinderService->findAccountantForCompany(1);
        $accountant2 = $userFinderService->findAccountantForCompany(2);

        expect($accountant1)
            ->not->toBeNull()
            ->and($accountant1->role)->toBe(Role::ACCOUNTANT->value)
            ->and($accountant1->company_id)->toBe(1);

        expect($accountant2)
            ->not->toBeNull()
            ->and($accountant2->role)->toBe(Role::ACCOUNTANT->value)
            ->and($accountant2->company_id)->toBe(2);

        // Test no director found for non-existent company
        $noDirector = $userFinderService->findDirectorForCompany(999);
        expect($noDirector)->toBeNull();
    });

    it('handles company isolation correctly', function () {
        // Arrange
        $multiCompany = createMultiCompanyScenario();
        $mockBot = mockTelegramBot();

        $notificationService = app(TelegramNotificationService::class);
        $auditLogService = app(AuditLogService::class);
        $userFinderService = app(UserFinderService::class);

        $expenseService = new ExpenseService(
            $notificationService,
            $auditLogService,
            $userFinderService
        );

        $mockBot->shouldReceive('sendMessage')->andReturn(null);

        // Act: Create requests for both companies
        $request1Id = $expenseService->createRequest(
            $mockBot,
            $multiCompany['company1Users']['requester'],
            'Company 1 expense',
            500.00
        );

        $request2Id = $expenseService->createRequest(
            $mockBot,
            $multiCompany['company2Users']['requester'],
            'Company 2 expense',
            750.00
        );

        // Assert: Requests are isolated by company
        $company1Requests = $expenseService->getPendingRequestsForCompany(1);
        $company2Requests = $expenseService->getPendingRequestsForCompany(2);

        expect($company1Requests)->toHaveCount(1)
            ->and($company1Requests->first()->id)->toBe($request1Id)
            ->and($company1Requests->first()->amount)->toBe(500.00);

        expect($company2Requests)->toHaveCount(1)
            ->and($company2Requests->first()->id)->toBe($request2Id)
            ->and($company2Requests->first()->amount)->toBe(750.00);

        // Cross-company requests should not appear
        expect($company1Requests->contains('id', $request2Id))->toBeFalse();
        expect($company2Requests->contains('id', $request1Id))->toBeFalse();
    });

    it('handles edge cases and error scenarios', function () {
        // Arrange
        $user = createUserWithRole(Role::USER->value);
        $mockBot = mockTelegramBot();

        $notificationService = app(TelegramNotificationService::class);
        $auditLogService = app(AuditLogService::class);
        $userFinderService = app(UserFinderService::class);

        $expenseService = new ExpenseService(
            $notificationService,
            $auditLogService,
            $userFinderService
        );

        // Test 1: Very small amount
        $smallRequestId = $expenseService->createRequest(
            $mockBot,
            $user,
            'Very small expense',
            0.01
        );
        expect($smallRequestId)->toBeInt();

        // Test 2: Very large amount
        $largeRequestId = $expenseService->createRequest(
            $mockBot,
            $user,
            'Very large expense',
            999999.99
        );
        expect($largeRequestId)->toBeInt();

        // Test 3: Special characters in description
        $specialRequestId = $expenseService->createRequest(
            $mockBot,
            $user,
            'Special chars: !@#$%^&*()_+ юникод 🚀',
            100.00
        );
        expect($specialRequestId)->toBeInt();

        // Test 4: Long description
        $longDescription = str_repeat('Very long description. ', 100);
        $longRequestId = $expenseService->createRequest(
            $mockBot,
            $user,
            $longDescription,
            100.00
        );
        expect($longRequestId)->toBeInt();

        // Verify all requests were created successfully
        $allRequests = ExpenseRequest::whereIn('id', [
            $smallRequestId, $largeRequestId, $specialRequestId, $longRequestId
        ])->get();
        expect($allRequests)->toHaveCount(4);
    });
});
