<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Authentication API', function () {
    it('returns 401 JSON response for unauthenticated API requests', function () {
        // Make a request to a protected API endpoint without authentication
        $response = $this->getJson('/api/v1/users');

        // Assert that we get a 401 status code
        $response->assertStatus(401)
                 ->assertJson(['message' => 'Unauthenticated.']);
    });

    it('returns 401 JSON response for invalid token', function () {
        // Make a request with an invalid token
        $response = $this->withHeader('Authorization', 'Bearer invalid-token')
                         ->getJson('/api/v1/users');

        // Assert that we get a 401 status code
        $response->assertStatus(401)
                 ->assertJson(['message' => 'Unauthenticated.']);
    });
});
