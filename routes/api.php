<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ExpenseRequestController;
use App\Http\Controllers\Api\V1\SessionController;
use App\Http\Controllers\Api\V1\UserApiController;
use App\Http\Controllers\FrontController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


// Webhook для Telegram
Route::post('/webhook', [FrontController::class, 'webhook']);

Route::prefix('v1')->group(function () {
    // Открытие сессии (логин)
    Route::post(
        '/session',
        [SessionController::class, 'store']
    )->middleware('throttle:100,1440');

    // Регистрация пользовтеля (регистрация)
    Route::post(
        '/register',
        [AuthController::class, 'register']
    )->middleware('throttle:50,1440');

    // Закрытие сессии (логаут)
    Route::delete(
        '/session',
        [SessionController::class, 'destroy']
    )->middleware(['auth:sanctum', 'throttle:50,1440']);

    // Проверка работоспособности API
    Route::get('/up', function () {
        return response()->json(['success' => true], 200);
    })->middleware('throttle:100,1');

    Route::middleware([
            'auth:sanctum',
            'throttle:150,1'
        ])
        ->group(function () {
            Route::get('/users', [UserApiController::class, 'index']);
            Route::get('/users/{id}', [UserApiController::class, 'show']);
            Route::get('/users/{id}/status', [UserApiController::class, 'status']);

            // Expense Request endpoints
            Route::get(
                '/companies/{companyId}/expenses/approved',
                [ExpenseRequestController::class, 'getApprovedRequests']
            );
            Route::get(
                '/companies/{companyId}/expenses/declined',
                [ExpenseRequestController::class, 'getDeclinedRequests']
            );
            Route::get(
                '/companies/{companyId}/expenses/issued',
                [ExpenseRequestController::class, 'getIssuedRequests']
            );
            Route::get(
                '/companies/{companyId}/expenses/pending',
                [ExpenseRequestController::class, 'getPendingRequests']
            );
        });
});
