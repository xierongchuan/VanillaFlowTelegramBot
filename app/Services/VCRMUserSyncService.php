<?php

declare(strict_types=1);

namespace App\Services;

use App\DTO\VCRM\User as VCRMUser;
use App\Enums\Role;
use App\Models\User;
use App\Services\VCRM\UserService as VCRMUserService;
use Illuminate\Support\Facades\Log;

class VCRMUserSyncService
{
    public function __construct(
        private readonly VCRMUserService $vcrmUserService
    ) {
        //
    }

    /**
     * Sync user data from VCRM to local database.
     *
     * @param User $user
     * @return bool
     */
    public function syncUser(User $user): bool
    {
        try {
            if (empty($user->phone)) {
                Log::warning('Cannot sync user without phone number', ['user_id' => $user->id]);
                return false;
            }

            $vcrmUser = $this->vcrmUserService->fetchByPhone($user->phone);

            if (!$vcrmUser || empty($vcrmUser->id)) {
                Log::warning('User not found in VCRM', ['phone' => $user->phone]);
                return false;
            }

            $changes = [];

            // Sync full name
            if ($vcrmUser->fullName !== $user->full_name) {
                $changes['full_name'] = $vcrmUser->fullName;
                $user->full_name = $vcrmUser->fullName;
            }

            // Sync role
            $vcrmRole = $this->mapVCRMRole($vcrmUser->role);
            if ($vcrmRole && $vcrmRole->value !== $user->role) {
                $changes['role'] = $vcrmRole->value;
                $user->role = $vcrmRole->value;
            }

            // Sync company ID
            if ($vcrmUser->company && $vcrmUser->company->id !== $user->company_id) {
                $changes['company_id'] = $vcrmUser->company->id;
                $user->company_id = $vcrmUser->company->id;
            }

            if (!empty($changes)) {
                $user->save();
                Log::info('User data synced from VCRM', [
                    'user_id' => $user->id,
                    'changes' => $changes
                ]);
                return true;
            }

            Log::debug('No changes needed for user sync', ['user_id' => $user->id]);
            return false;
        } catch (\Throwable $e) {
            Log::error('Failed to sync user from VCRM', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Map VCRM role to local role.
     *
     * @param string $vcrmRole
     * @return Role|null
     */
    private function mapVCRMRole(string $vcrmRole): ?Role
    {
        return match (strtolower($vcrmRole)) {
            'director', 'admin' => Role::DIRECTOR,
            'cashier', 'accountant' => Role::CASHIER,
            'user', 'employee' => Role::USER,
            default => null,
        };
    }
}
