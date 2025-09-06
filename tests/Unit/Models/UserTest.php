<?php

declare(strict_types=1);

use App\Models\User;
use App\Enums\Role;
use Illuminate\Support\Facades\Hash;

describe('User Model', function () {
    it('can create a user with all fields', function () {
        // Arrange & Act
        $user = User::create([
            'login' => 'testuser123',
            'full_name' => 'Test User Full Name',
            'telegram_id' => 123456789,
            'phone' => '+998901234567',
            'role' => Role::USER->value,
            'company_id' => 1,
            'password' => Hash::make('password123'),
        ]);

        // Assert
        expect($user)
            ->toBeInstanceOf(User::class)
            ->and($user->login)->toBe('testuser123')
            ->and($user->full_name)->toBe('Test User Full Name')
            ->and($user->telegram_id)->toBe(123456789)
            ->and($user->phone)->toBe('+998901234567')
            ->and($user->role)->toBe(Role::USER->value)
            ->and($user->company_id)->toBe(1)
            ->and($user->exists)->toBeTrue();
    });

    it('can create user with minimum required fields', function () {
        // Arrange & Act
        $user = User::create([
            'login' => 'minuser',
            'role' => Role::USER->value,
        ]);

        // Assert
        expect($user)
            ->toBeInstanceOf(User::class)
            ->and($user->login)->toBe('minuser')
            ->and($user->role)->toBe(Role::USER->value)
            ->and($user->exists)->toBeTrue();
    });

    it('hides password in array representation', function () {
        // Arrange
        $user = User::factory()->create([
            'password' => Hash::make('secret123'),
        ]);

        // Act
        $userArray = $user->toArray();

        // Assert
        expect($userArray)->not->toHaveKey('password');
    });

    it('can create users with different roles', function () {
        // Arrange & Act
        $director = User::create([
            'login' => 'director1',
            'role' => Role::DIRECTOR->value,
            'company_id' => 1,
        ]);

        $accountant = User::create([
            'login' => 'accountant1',
            'role' => Role::ACCOUNTANT->value,
            'company_id' => 1,
        ]);

        $user = User::create([
            'login' => 'user1',
            'role' => Role::USER->value,
            'company_id' => 1,
        ]);

        // Assert
        expect($director->role)->toBe(Role::DIRECTOR->value)
            ->and($accountant->role)->toBe(Role::ACCOUNTANT->value)
            ->and($user->role)->toBe(Role::USER->value);
    });

    it('can query users by role and company', function () {
        // Arrange
        User::factory()->create([
            'role' => Role::DIRECTOR->value,
            'company_id' => 1,
        ]);

        User::factory()->create([
            'role' => Role::DIRECTOR->value,
            'company_id' => 2,
        ]);

        User::factory()->create([
            'role' => Role::USER->value,
            'company_id' => 1,
        ]);

        // Act
        $directorsCompany1 = User::where('role', Role::DIRECTOR->value)
            ->where('company_id', 1)
            ->get();

        // Assert
        expect($directorsCompany1)->toHaveCount(1)
            ->and($directorsCompany1->first()->role)->toBe(Role::DIRECTOR->value)
            ->and($directorsCompany1->first()->company_id)->toBe(1);
    });

    it('can find user by telegram_id', function () {
        // Arrange
        $telegramId = 987654321;
        $user = User::factory()->create([
            'telegram_id' => $telegramId,
        ]);

        // Act
        $foundUser = User::where('telegram_id', $telegramId)->first();

        // Assert
        expect($foundUser)
            ->not->toBeNull()
            ->and($foundUser->id)->toBe($user->id)
            ->and($foundUser->telegram_id)->toBe($telegramId);
    });

    it('stores and retrieves phone numbers correctly', function () {
        // Arrange
        $phoneNumbers = [
            '+998901234567',
            '+1234567890',
            '+7123456789',
        ];

        // Act & Assert
        foreach ($phoneNumbers as $phone) {
            $user = User::factory()->create(['phone' => $phone]);
            expect($user->phone)->toBe($phone);
        }
    });

    it('can update user fields', function () {
        // Arrange
        $user = User::factory()->create([
            'full_name' => 'Original Name',
            'phone' => '+998901111111',
        ]);

        // Act
        $user->update([
            'full_name' => 'Updated Name',
            'phone' => '+998902222222',
        ]);

        // Assert
        expect($user->fresh())
            ->full_name->toBe('Updated Name')
            ->phone->toBe('+998902222222');
    });

    it('handles special characters in full name', function () {
        // Arrange & Act
        $specialNames = [
            'José María García-López',
            'Алексей Иванов',
            'محمد علي',
            'O\'Connor Smith',
            'Jean-Pierre Müller',
        ];

        foreach ($specialNames as $name) {
            $user = User::create([
                'login' => 'user_' . md5($name),
                'full_name' => $name,
                'role' => Role::USER->value,
                'company_id' => 1,
            ]);

            // Assert
            expect($user->full_name)->toBe($name);
        }
    });

    it('can create users with all role types', function () {
        // Arrange & Act
        $roles = [
            Role::USER,
            Role::DIRECTOR,
            Role::ACCOUNTANT,
        ];

        foreach ($roles as $role) {
            $user = User::create([
                'login' => 'user_' . $role->value,
                'role' => $role->value,
                'company_id' => 1,
            ]);

            // Assert
            expect($user->role)->toBe($role->value);
        }
    });

    it('handles telegram_id uniqueness across companies', function () {
        // Arrange
        $telegramId = 123456789;

        $user1 = User::factory()->create([
            'telegram_id' => $telegramId,
            'company_id' => 1,
        ]);

        // Act & Assert - Creating another user with same telegram_id should work
        // as there's no unique constraint on telegram_id
        $user2 = User::factory()->create([
            'telegram_id' => $telegramId,
            'company_id' => 2,
        ]);

        expect($user1->telegram_id)->toBe($telegramId)
            ->and($user2->telegram_id)->toBe($telegramId)
            ->and($user1->company_id)->not->toBe($user2->company_id);
    });

    it('handles various phone number formats', function () {
        // Arrange
        $phoneFormats = [
            '+998901234567',    // Uzbekistan
            '+1-555-123-4567',  // US with dashes
            '+44 20 7123 4567', // UK with spaces
            '+7 (495) 123-45-67', // Russia with parentheses
            '+86 138 0013 8000', // China
        ];

        // Act & Assert
        foreach ($phoneFormats as $phone) {
            $user = User::factory()->create(['phone' => $phone]);
            expect($user->phone)->toBe($phone);
        }
    });

    it('validates login uniqueness', function () {
        // Arrange
        $login = 'unique_user';
        User::factory()->create(['login' => $login]);

        // Act & Assert - This should throw an exception due to unique constraint
        expect(function () use ($login) {
            User::create([
                'login' => $login,
                'role' => Role::USER->value,
                'company_id' => 1,
            ]);
        })->toThrow(\Illuminate\Database\QueryException::class);
    });

    it('handles nullable fields correctly', function () {
        // Arrange & Act
        $user = User::create([
            'login' => 'minimal_user',
            'role' => Role::USER->value,
            // All other fields are nullable
        ]);

        // Assert
        expect($user->full_name)->toBeNull()
            ->and($user->telegram_id)->toBeNull()
            ->and($user->phone)->toBeNull()
            ->and($user->company_id)->toBeNull()
            ->and($user->password)->toBeNull();
    });

    it('can query users by multiple criteria', function () {
        // Arrange
        $company1Id = 1;
        $company2Id = 2;

        User::factory()->create([
            'role' => Role::DIRECTOR->value,
            'company_id' => $company1Id,
            'telegram_id' => 111111,
        ]);

        User::factory()->create([
            'role' => Role::USER->value,
            'company_id' => $company1Id,
            'telegram_id' => 222222,
        ]);

        User::factory()->create([
            'role' => Role::DIRECTOR->value,
            'company_id' => $company2Id,
            'telegram_id' => null, // No telegram_id
        ]);

        // Act
        $directorsWithTelegram = User::where('role', Role::DIRECTOR->value)
            ->whereNotNull('telegram_id')
            ->get();

        $company1Users = User::where('company_id', $company1Id)->get();

        // Assert
        expect($directorsWithTelegram)->toHaveCount(1)
            ->and($company1Users)->toHaveCount(2);
    });

    it('handles password hashing and verification', function () {
        // Arrange
        $password = 'secret123';
        $user = User::factory()->create([
            'password' => Hash::make($password)
        ]);

        // Act & Assert
        expect(Hash::check($password, $user->password))->toBeTrue()
            ->and(Hash::check('wrong_password', $user->password))->toBeFalse();
    });

    it('handles very long login names', function () {
        // Arrange
        $longLogin = str_repeat('a', 190); // Close to typical VARCHAR limit

        // Act
        $user = User::create([
            'login' => $longLogin,
            'role' => Role::USER->value,
            'company_id' => 1,
        ]);

        // Assert
        expect($user->login)->toBe($longLogin)
            ->and(strlen($user->login))->toBe(190);
    });

    it('maintains data integrity with timestamps', function () {
        // Arrange
        $user = User::factory()->create();
        $originalUpdatedAt = $user->updated_at;

        // Act
        sleep(1);
        $user->update(['full_name' => 'Updated Name']);

        // Assert
        expect($user->fresh()->updated_at)
            ->toBeGreaterThan($originalUpdatedAt)
            ->and($user->created_at->lessThanOrEqualTo($user->updated_at))
            ->toBeTrue();
    });
});
