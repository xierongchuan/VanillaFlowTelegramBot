<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('User API with Custom Pagination', function () {
    it('can get paginated users with custom format', function () {
        // Create test users
        $users = User::factory(5)->create(['company_id' => 1]);
        $authUser = User::factory()->role(Role::DIRECTOR)->create();

        $response = $this->actingAs($authUser, 'sanctum')
            ->getJson('/api/v1/users');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'login',
                        'full_name',
                        'role',
                        'telegram_id',
                        'phone_number',
                        'company_id',
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
            ->assertJsonPath('success', true);
    });

    it('can handle pagination parameters', function () {
        User::factory(25)->create();
        $authUser = User::factory()->role(Role::DIRECTOR)->create();

        $response = $this->actingAs($authUser, 'sanctum')
            ->getJson('/api/v1/users?per_page=10&page=2');

        $response->assertStatus(200)
            ->assertJsonPath('pagination.per_page', 10)
            ->assertJsonPath('pagination.current_page', 2);
    });

    it('can search users by phone number', function () {
        User::factory()->create(['phone' => '+1234567890']);
        User::factory()->create(['phone' => '+9876543210']);
        $authUser = User::factory()->role(Role::DIRECTOR)->create();

        $response = $this->actingAs($authUser, 'sanctum')
            ->getJson('/api/v1/users?phone=1234');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    });

    it('validates pagination parameters', function () {
        $authUser = User::factory()->role(Role::DIRECTOR)->create();

        $response = $this->actingAs($authUser, 'sanctum')
            ->getJson('/api/v1/users?per_page=0');

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure([
                'success',
                'message',
                'errors'
            ]);
    });

    it('can get single user with custom format', function () {
        $user = User::factory()->create();
        $authUser = User::factory()->role(Role::DIRECTOR)->create();

        $response = $this->actingAs($authUser, 'sanctum')
            ->getJson("/api/v1/users/{$user->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'login',
                    'full_name',
                    'role',
                    'telegram_id',
                    'phone_number',
                    'company_id',
                ]
            ])
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $user->id);
    });

    it('handles user not found', function () {
        $authUser = User::factory()->role(Role::DIRECTOR)->create();

        $response = $this->actingAs($authUser, 'sanctum')
            ->getJson('/api/v1/users/999999');

        $response->assertStatus(404)
            ->assertJsonPath('success', false);
    });

    it('can get user status with custom format', function () {
        $user = User::factory()->create();
        $authUser = User::factory()->role(Role::DIRECTOR)->create();

        $response = $this->actingAs($authUser, 'sanctum')
            ->getJson("/api/v1/users/{$user->id}/status");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'is_active'
                ]
            ])
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.is_active', true);
    });
});
