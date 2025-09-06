<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Role;
use App\Models\User;
use App\Services\Contracts\UserFinderServiceInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * Service for finding users by roles and companies.
 * Follows Single Responsibility Principle - only handles user finding operations.
 * Eliminates code duplication across services.
 */
class UserFinderService implements UserFinderServiceInterface
{
    /**
     * Find director for a company.
     */
    public function findDirectorForCompany(int $companyId): ?User
    {
        return $this->findUsersByRoleForCompany($companyId, Role::DIRECTOR)->first();
    }

    /**
     * Find accountant for a company.
     */
    public function findAccountantForCompany(int $companyId): ?User
    {
        return $this->findUsersByRoleForCompany($companyId, Role::ACCOUNTANT)->first();
    }

    /**
     * Find users by role for a company.
     */
    public function findUsersByRoleForCompany(
        int $companyId,
        Role $role,
        bool $requireTelegramId = true
    ): Collection {
        $query = User::where('company_id', $companyId)
            ->where('role', $role->value);

        if ($requireTelegramId) {
            $query->whereNotNull('telegram_id');
        }

        return $query->get();
    }

    /**
     * Find all directors for a company.
     */
    public function findDirectorsForCompany(int $companyId): Collection
    {
        return $this->findUsersByRoleForCompany($companyId, Role::DIRECTOR);
    }

    /**
     * Find all accountants for a company.
     */
    public function findAccountantsForCompany(int $companyId): Collection
    {
        return $this->findUsersByRoleForCompany($companyId, Role::ACCOUNTANT);
    }
}
