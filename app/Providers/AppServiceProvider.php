<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\Contracts\AuditLogServiceInterface;
use App\Services\Contracts\ExpenseServiceInterface;
use App\Services\Contracts\NotificationServiceInterface;
use App\Services\Contracts\UserFinderServiceInterface;
use App\Services\Contracts\ValidationServiceInterface;
use App\Services\Contracts\ExpenseApprovalServiceInterface;
use App\Services\AuditLogService;
use App\Services\ExpenseService;
use App\Services\TelegramNotificationService;
use App\Services\UserFinderService;
use App\Services\ValidationService;
use App\Services\ExpenseApprovalService;
use App\Services\VCRM\UserService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bind interfaces to concrete implementations
        $this->app->bind(
            ExpenseServiceInterface::class,
            ExpenseService::class
        );

        $this->app->bind(
            NotificationServiceInterface::class,
            TelegramNotificationService::class
        );

        $this->app->bind(
            ValidationServiceInterface::class,
            ValidationService::class
        );

        $this->app->bind(
            ExpenseApprovalServiceInterface::class,
            ExpenseApprovalService::class
        );

        $this->app->bind(
            AuditLogServiceInterface::class,
            AuditLogService::class
        );

        $this->app->bind(
            UserFinderServiceInterface::class,
            UserFinderService::class
        );

        // Bind VCRM service
        $this->app->singleton(UserService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
