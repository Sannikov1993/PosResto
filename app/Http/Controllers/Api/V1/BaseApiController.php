<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Traits\ApiResponses;
use Illuminate\Http\Request;

/**
 * Base controller for Public API v1
 *
 * Provides common functionality for all v1 API controllers.
 */
abstract class BaseApiController extends Controller
{
    use ApiResponses;

    /**
     * Get tenant ID from request
     */
    protected function getTenantId(Request $request): ?int
    {
        return $request->attributes->get('tenant_id');
    }

    /**
     * Get restaurant ID from request
     */
    protected function getRestaurantId(Request $request): ?int
    {
        return $request->attributes->get('restaurant_id');
    }

    /**
     * Get API client from request
     */
    protected function getApiClient(Request $request): ?\App\Models\ApiClient
    {
        return $request->attributes->get('api_client');
    }

    /**
     * Get API scopes from request
     */
    protected function getScopes(Request $request): array
    {
        return $request->attributes->get('api_scopes', []);
    }

    /**
     * Check if request has a specific scope
     */
    protected function hasScope(Request $request, string $scope): bool
    {
        $scopes = $this->getScopes($request);

        if (in_array('*', $scopes)) {
            return true;
        }

        if (in_array($scope, $scopes)) {
            return true;
        }

        // Check resource wildcard
        $parts = explode(':', $scope);
        if (count($parts) === 2) {
            return in_array("{$parts[0]}:*", $scopes);
        }

        return false;
    }

    /**
     * Build base query with tenant/restaurant scope
     */
    protected function scopedQuery(Request $request, string $modelClass)
    {
        $query = $modelClass::query();

        // Apply tenant scope if model has tenant_id
        if (method_exists($modelClass, 'scopeTenant') || in_array('tenant_id', (new $modelClass)->getFillable())) {
            $tenantId = $this->getTenantId($request);
            if ($tenantId) {
                $query->where('tenant_id', $tenantId);
            }
        }

        // Apply restaurant scope if model has restaurant_id
        $restaurantId = $this->getRestaurantId($request);
        if ($restaurantId && in_array('restaurant_id', (new $modelClass)->getFillable())) {
            $query->where('restaurant_id', $restaurantId);
        }

        return $query;
    }

    /**
     * Get pagination parameters from request
     */
    protected function getPaginationParams(Request $request): array
    {
        return [
            'page' => max(1, (int) $request->input('page', 1)),
            'per_page' => min(100, max(1, (int) $request->input('per_page', 20))),
        ];
    }

    /**
     * Get sort parameters from request
     */
    protected function getSortParams(Request $request, array $allowedFields, string $defaultField = 'created_at', string $defaultDirection = 'desc'): array
    {
        $field = $request->input('sort', $defaultField);
        $direction = strtolower($request->input('direction', $defaultDirection));

        // Validate field
        if (!in_array($field, $allowedFields)) {
            $field = $defaultField;
        }

        // Validate direction
        if (!in_array($direction, ['asc', 'desc'])) {
            $direction = $defaultDirection;
        }

        return [
            'field' => $field,
            'direction' => $direction,
        ];
    }

    /**
     * Apply date range filter to query
     */
    protected function applyDateFilter($query, Request $request, string $column = 'created_at'): void
    {
        if ($request->has('from')) {
            $query->where($column, '>=', $request->input('from'));
        }

        if ($request->has('to')) {
            $query->where($column, '<=', $request->input('to'));
        }

        if ($request->has('date')) {
            $query->whereDate($column, $request->input('date'));
        }
    }

    /**
     * Apply search filter to query
     */
    protected function applySearchFilter($query, Request $request, array $searchFields): void
    {
        $search = $request->input('search', $request->input('q'));

        if (!$search) {
            return;
        }

        $query->where(function ($q) use ($search, $searchFields) {
            foreach ($searchFields as $field) {
                $q->orWhere($field, 'like', "%{$search}%");
            }
        });
    }

    /**
     * Validate request data with custom messages
     */
    protected function validateRequest(Request $request, array $rules, array $messages = []): array
    {
        $defaultMessages = [
            'required' => ':attribute is required',
            'string' => ':attribute must be a string',
            'integer' => ':attribute must be an integer',
            'numeric' => ':attribute must be a number',
            'array' => ':attribute must be an array',
            'min' => ':attribute must be at least :min',
            'max' => ':attribute must not exceed :max',
            'exists' => ':attribute not found',
            'unique' => ':attribute already exists',
            'email' => ':attribute must be a valid email address',
            'date' => ':attribute must be a valid date',
            'in' => ':attribute must be one of: :values',
        ];

        return $request->validate($rules, array_merge($defaultMessages, $messages));
    }
}
