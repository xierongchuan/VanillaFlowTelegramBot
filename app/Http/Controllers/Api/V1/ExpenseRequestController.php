<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\ExpenseStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\ApprovedExpenseResource;
use App\Http\Resources\DeclinedExpenseResource;
use App\Http\Resources\IssuedExpenseResource;
use App\Http\Resources\ExpenseRequestResource;
use App\Models\ExpenseRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Exception;

class ExpenseRequestController extends Controller
{
    /**
     * Get list of approved expense requests for a specific company
     */
    public function getApprovedRequests(Request $request, int $companyId): JsonResponse
    {
        try {
            // Validate pagination parameters
            $request->validate([
                'per_page' => 'integer|min:1|max:100',
                'page' => 'integer|min:1',
            ]);

            $perPage = (int) $request->query('per_page', '15');

            // Validate company ID
            if ($companyId <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid company ID provided',
                ], 400);
            }

            $expenses = ExpenseRequest::where('company_id', $companyId)
                ->whereIn('status', [ExpenseStatus::APPROVED->value, ExpenseStatus::ISSUED->value])
                ->with(['requester:id,full_name', 'cashier:id,full_name'])
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            $data = ApprovedExpenseResource::collection($expenses->items());

            return response()->json([
                'success' => true,
                'data' => $data,
                'pagination' => [
                    'current_page' => $expenses->currentPage(),
                    'last_page' => $expenses->lastPage(),
                    'per_page' => $expenses->perPage(),
                    'total' => $expenses->total(),
                    'from' => $expenses->firstItem(),
                    'to' => $expenses->lastItem(),
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
                'message' => 'An error occurred while fetching approved expenses',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get list of declined expense requests for a specific company
     */
    public function getDeclinedRequests(Request $request, int $companyId): JsonResponse
    {
        try {
            // Validate pagination parameters
            $request->validate([
                'per_page' => 'integer|min:1|max:100',
                'page' => 'integer|min:1',
            ]);

            $perPage = (int) $request->query('per_page', '15');

            // Validate company ID
            if ($companyId <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid company ID provided',
                ], 400);
            }

            $expenses = ExpenseRequest::where('company_id', $companyId)
                ->where('status', ExpenseStatus::DECLINED->value)
                ->with(['requester:id,full_name'])
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            $data = DeclinedExpenseResource::collection($expenses->items());

            return response()->json([
                'success' => true,
                'data' => $data,
                'pagination' => [
                    'current_page' => $expenses->currentPage(),
                    'last_page' => $expenses->lastPage(),
                    'per_page' => $expenses->perPage(),
                    'total' => $expenses->total(),
                    'from' => $expenses->firstItem(),
                    'to' => $expenses->lastItem(),
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
                'message' => 'An error occurred while fetching declined expenses',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get list of issued expense requests for a specific company
     */
    public function getIssuedRequests(Request $request, int $companyId): JsonResponse
    {
        try {
            // Validate pagination parameters
            $request->validate([
                'per_page' => 'integer|min:1|max:100',
                'page' => 'integer|min:1',
            ]);

            $perPage = (int) $request->query('per_page', '15');

            // Validate company ID
            if ($companyId <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid company ID provided',
                ], 400);
            }

            $expenses = ExpenseRequest::where('company_id', $companyId)
                ->where('status', ExpenseStatus::ISSUED->value)
                ->with(['requester:id,full_name', 'cashier:id,full_name'])
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            $data = IssuedExpenseResource::collection($expenses->items());

            return response()->json([
                'success' => true,
                'data' => $data,
                'pagination' => [
                    'current_page' => $expenses->currentPage(),
                    'last_page' => $expenses->lastPage(),
                    'per_page' => $expenses->perPage(),
                    'total' => $expenses->total(),
                    'from' => $expenses->firstItem(),
                    'to' => $expenses->lastItem(),
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
                'message' => 'An error occurred while fetching issued expenses',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get list of pending expense requests for a specific company
     */
    public function getPendingRequests(Request $request, int $companyId): JsonResponse
    {
        try {
            // Validate pagination parameters
            $request->validate([
                'per_page' => 'integer|min:1|max:100',
                'page' => 'integer|min:1',
            ]);

            $perPage = (int) $request->query('per_page', '15');

            // Validate company ID
            if ($companyId <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid company ID provided',
                ], 400);
            }

            $expenses = ExpenseRequest::where('company_id', $companyId)
                ->where('status', ExpenseStatus::PENDING->value)
                ->with(['requester:id,full_name'])
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            $data = ExpenseRequestResource::collection($expenses->items());

            return response()->json([
                'success' => true,
                'data' => $data,
                'pagination' => [
                    'current_page' => $expenses->currentPage(),
                    'last_page' => $expenses->lastPage(),
                    'per_page' => $expenses->perPage(),
                    'total' => $expenses->total(),
                    'from' => $expenses->firstItem(),
                    'to' => $expenses->lastItem(),
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
                'message' => 'An error occurred while fetching pending expenses',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }
}
