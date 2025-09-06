<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Mockery;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Prevent actual logging during tests
        Log::spy();

        // Set up test-specific configurations
        $this->setupTestConfig();
    }

    /**
     * Clean up after tests.
     */
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Setup test-specific configurations.
     */
    protected function setupTestConfig(): void
    {
        // Use in-memory database for faster tests
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);

        // Disable broadcasting for tests
        config(['broadcasting.default' => 'null']);

        // Disable mail sending in tests
        config(['mail.default' => 'array']);

        // Set test-specific cache driver
        config(['cache.default' => 'array']);

        // Set test-specific session driver
        config(['session.driver' => 'array']);
    }

    /**
     * Create a user with specific attributes for testing.
     */
    protected function createTestUser(array $attributes = []): \App\Models\User
    {
        return \App\Models\User::factory()->create(array_merge([
            'company_id' => 1,
        ], $attributes));
    }

    /**
     * Create an expense request for testing.
     */
    protected function createTestExpenseRequest(\App\Models\User $requester, array $attributes = []): \App\Models\ExpenseRequest
    {
        return \App\Models\ExpenseRequest::factory()->create(array_merge([
            'requester_id' => $requester->id,
            'company_id' => $requester->company_id,
        ], $attributes));
    }

    /**
     * Assert that an audit log exists for a specific action.
     */
    protected function assertAuditLogExists(string $tableName, int $recordId, string $action, int $actorId): void
    {
        $this->assertDatabaseHas('audit_logs', [
            'table_name' => $tableName,
            'record_id' => $recordId,
            'action' => $action,
            'actor_id' => $actorId,
        ]);
    }

    /**
     * Assert that a database record exists with specific attributes.
     */
    protected function assertRecordExists(string $table, array $attributes): void
    {
        $this->assertDatabaseHas($table, $attributes);
    }

    /**
     * Assert that a database record does not exist with specific attributes.
     */
    protected function assertRecordNotExists(string $table, array $attributes): void
    {
        $this->assertDatabaseMissing($table, $attributes);
    }

    /**
     * Mock a service and bind it to the container.
     */
    protected function mockService(string $abstract, ?string $concrete = null): Mockery\MockInterface
    {
        $mock = Mockery::mock($concrete ?? $abstract);
        $this->app->instance($abstract, $mock);
        return $mock;
    }

    /**
     * Create a mock Telegram bot.
     */
    protected function createMockBot(): Mockery\MockInterface
    {
        return Mockery::mock(\SergiX44\Nutgram\Nutgram::class);
    }

    /**
     * Authenticate a user for the test.
     */
    protected function actingAsUser(\App\Models\User $user): static
    {
        \Illuminate\Support\Facades\Auth::shouldReceive('user')->andReturn($user);
        return $this;
    }
}
