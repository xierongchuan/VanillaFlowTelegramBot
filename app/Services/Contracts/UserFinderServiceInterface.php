<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Interface for user finder operations.
 * Follows Single Responsibility Principle.
 */
interface UserFinderServiceInterface
{
    /**
     * Find director for a company.
     *
     * @param int $companyId Company ID
     * @return User|null Director user or null if not found
     */
    public function findDirectorForCompany(int $companyId): ?User;

    /**
     * Find accountant for a company.
     *
     * @param int $companyId Company ID
     * @return User|null Accountant user or null if not found
     */
    public function findAccountantForCompany(int $companyId): ?User;

    /**
     * Find users by role for a company.
     *
     * @param int $companyId Company ID
     * @param Role $role User role
     * @param bool $requireTelegramId Whether to require telegram_id
     * @return Collection Collection of users
     */
    public function findUsersByRoleForCompany(
        int $companyId,
        Role $role,
        bool $requireTelegramId = true
    ): Collection;

    /**
     * Find all directors for a company.
     *
     * @param int $companyId Company ID
     * @return Collection Collection of director users
     */
    public function findDirectorsForCompany(int $companyId): Collection;

    /**
     * Find all accountants for a company.
     *
     * @param int $companyId Company ID
     * @return Collection Collection of accountant users
     */
    public function findAccountantsForCompany(int $companyId): Collection;
}
