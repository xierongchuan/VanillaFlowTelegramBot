<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Exception;

class UserApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            // Validate pagination parameters
            $request->validate([
                'per_page' => 'integer|min:1|max:100',
                'page' => 'integer|min:1',
                'phone' => 'string|max:50',
            ]);

            $perPage = (int) $request->query('per_page', '15');
            $phone = (string) $request->query('phone', '');

            $query = User::query();

            // Если передан phone -> делаем поиск по нормализованному номеру (только цифры)
            if ($phone !== '') {
                $normalized = $this->normalizePhone($phone);

                // Если после нормализации пусто — возвращаем пустую страницу
                if ($normalized === '') {
                    return response()->json([
                        'success' => true,
                        'data' => [],
                        'pagination' => [
                            'current_page' => 1,
                            'last_page' => 1,
                            'per_page' => $perPage,
                            'total' => 0,
                            'from' => null,
                            'to' => null,
                        ],
                    ]);
                }

                // Определяем драйвер БД
                $driver = config('database.default');

                if ($driver === 'pgsql') {
                    $query->whereRaw("regexp_replace(phone, '\\\\D', '', 'g') LIKE ?", ["%{$normalized}%"]);
                } elseif ($driver === 'mysql') {
                    $query->whereRaw("REGEXP_REPLACE(phone, '[^0-9]', '') LIKE ?", ["%{$normalized}%"]);
                } else {
                    $query->whereRaw(
                        "REPLACE(REPLACE(REPLACE(phone, ' ', ''), '+', ''), '-', '') LIKE ?",
                        ["%{$normalized}%"]
                    );
                }
            }

            $users = $query->orderByDesc('created_at')->paginate($perPage);
            $data = UserResource::collection($users->items());

            return response()->json([
                'success' => true,
                'data' => $data,
                'pagination' => [
                    'current_page' => $users->currentPage(),
                    'last_page' => $users->lastPage(),
                    'per_page' => $users->perPage(),
                    'total' => $users->total(),
                    'from' => $users->firstItem(),
                    'to' => $users->lastItem(),
                ],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching users',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    public function show($id): JsonResponse
    {
        try {
            $user = User::find($id);

            if (! $user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Пользователь не найден'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => new UserResource($user),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching user',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    public function status($id): JsonResponse
    {
        try {
            $user = User::find($id);

            // If user exists, they are considered active
            $isActive = (bool) $user;

            return response()->json([
                'success' => true,
                'data' => [
                    'is_active' => $isActive,
                ],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while checking user status',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Нормализует телефон: убирает все не-цифры.
     * Возвращает строку из цифр или пустую строку.
     */
    private function normalizePhone(string $phone): string
    {
        return preg_replace('/\D+/', '', $phone) ?? '';
    }
}
