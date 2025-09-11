<?php

declare(strict_types=1);

use App\Enums\ExpenseStatus;
use App\Enums\Role;
use App\Models\ExpenseRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Expense Request API', function () {
    it('can get approved expenses for a company', function () {
        // Create test users
        $requester = User::factory()->role(Role::USER)->company(1)->create();
        $director = User::factory()->role(Role::DIRECTOR)->company(1)->create();
        $cashier = User::factory()->role(Role::CASHIER)->company(1)->create();
        $authUser = User::factory()->role(Role::DIRECTOR)->company(1)->create();

        // Create approved expense (earlier)
        $expense = ExpenseRequest::factory()
            ->company(1)
            ->approved()
            ->amount(100.50)
            ->create([
                'requester_id' => $requester->id,
                'director_id' => $director->id,
                'description' => 'Test expense',
                'created_at' => now()->subHour(),
            ]);

        // Create issued expense (later - should be first in results)
        $issuedExpense = ExpenseRequest::factory()
            ->company(1)
            ->issued(180.00)
            ->amount(200.00)
            ->create([
                'requester_id' => $requester->id,
                'director_id' => $director->id,
                'cashier_id' => $cashier->id,
                'description' => 'Test issued expense',
                'created_at' => now(),
            ]);

        $response = $this->actingAs($authUser, 'sanctum')
            ->getJson('/api/v1/companies/1/expenses/approved');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'date',
                        'requester_name',
                        'description',
                        'amount',
                        'status'
                    ]
                ],
                'pagination' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                    'from',
                    'to'
                ]
            ]);

        // Get the response data to verify order and values
        $responseData = $response->json('data');

        // The issued expense should be first (most recent)
        expect($responseData[0]['amount'])->toBe(200);
        expect($responseData[0]['status'])->toBe('issued');
        expect($responseData[1]['amount'])->toBe(100.5);
        expect($responseData[1]['status'])->toBe('approved');
    });

    it('can get declined expenses for a company', function () {
        $requester = User::factory()->role(Role::USER)->company(1)->create();
        $director = User::factory()->role(Role::DIRECTOR)->company(1)->create();
        $authUser = User::factory()->role(Role::DIRECTOR)->company(1)->create();

        $expense = ExpenseRequest::factory()
            ->company(1)
            ->declined()
            ->amount(50.25)
            ->create([
                'requester_id' => $requester->id,
                'director_id' => $director->id,
                'description' => 'Declined expense',
            ]);

        $response = $this->actingAs($authUser, 'sanctum')
            ->getJson('/api/v1/companies/1/expenses/declined');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'date',
                        'requester_name',
                        'description',
                        'amount'
                    ]
                ],
                'pagination' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                    'from',
                    'to'
                ]
            ])
            ->assertJsonPath('data.0.amount', 50.25);
    });

    it('can get issued expenses for a company', function () {
        $requester = User::factory()->role(Role::USER)->company(1)->create();
        $director = User::factory()->role(Role::DIRECTOR)->company(1)->create();
        $cashier = User::factory()->role(Role::CASHIER)->company(1)->create();
        $authUser = User::factory()->role(Role::CASHIER)->company(1)->create();

        $expense = ExpenseRequest::factory()
            ->company(1)
            ->issued(250.00)
            ->amount(300.00)
            ->create([
                'requester_id' => $requester->id,
                'director_id' => $director->id,
                'cashier_id' => $cashier->id,
                'description' => 'Issued expense',
            ]);

        $response = $this->actingAs($authUser, 'sanctum')
            ->getJson('/api/v1/companies/1/expenses/issued');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'date',
                        'requester_name',
                        'description',
                        'amount',
                        'issuer_name',
                        'issued_amount'
                    ]
                ],
                'pagination' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                    'from',
                    'to'
                ]
            ]);

        // Check the actual values
        $responseData = $response->json('data');
        expect($responseData[0]['amount'])->toBe(300);
        expect($responseData[0]['issued_amount'])->toBe(250);
    });

    it('can get pending expenses for a company', function () {
        $requester = User::factory()->role(Role::USER)->company(1)->create();
        $authUser = User::factory()->role(Role::DIRECTOR)->company(1)->create();

        $expense = ExpenseRequest::factory()
            ->company(1)
            ->pending()
            ->amount(75.50)
            ->create([
                'requester_id' => $requester->id,
                'description' => 'Pending expense',
            ]);

        $response = $this->actingAs($authUser, 'sanctum')
            ->getJson('/api/v1/companies/1/expenses/pending');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'date',
                        'requester_name',
                        'description',
                        'amount',
                        'status'
                    ]
                ],
                'pagination' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                    'from',
                    'to'
                ]
            ])
            ->assertJsonPath('data.0.amount', 75.50)
            ->assertJsonPath('data.0.status', 'pending');
    });

    it('can handle pagination parameters', function () {
        $authUser = User::factory()->role(Role::DIRECTOR)->company(1)->create();

        // Create multiple expense requests
        ExpenseRequest::factory(25)
            ->company(1)
            ->approved()
            ->create();

        // Test with custom pagination
        $response = $this->actingAs($authUser, 'sanctum')
            ->getJson('/api/v1/companies/1/expenses/approved?per_page=10&page=2');

        $response->assertStatus(200)
            ->assertJsonPath('pagination.per_page', 10)
            ->assertJsonPath('pagination.current_page', 2)
            ->assertJsonPath('pagination.total', 25);
    });

    it('validates pagination parameters', function () {
        $authUser = User::factory()->role(Role::DIRECTOR)->company(1)->create();

        // Test invalid per_page
        $response = $this->actingAs($authUser, 'sanctum')
            ->getJson('/api/v1/companies/1/expenses/approved?per_page=0');

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure([
                'success',
                'message',
                'errors'
            ]);
    });

    it('handles invalid company ID', function () {
        $authUser = User::factory()->role(Role::DIRECTOR)->company(1)->create();

        $response = $this->actingAs($authUser, 'sanctum')
            ->getJson('/api/v1/companies/-1/expenses/approved');

        $response->assertStatus(400)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Invalid company ID provided');
    });

    it('returns empty result for company with no expenses', function () {
        $authUser = User::factory()->role(Role::DIRECTOR)->company(999)->create();

        $response = $this->actingAs($authUser, 'sanctum')
            ->getJson('/api/v1/companies/999/expenses/approved');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('pagination.total', 0)
            ->assertJsonCount(0, 'data');
    });
});
