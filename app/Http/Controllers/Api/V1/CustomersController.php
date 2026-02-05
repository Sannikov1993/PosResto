<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\V1\CustomerResource;
use App\Http\Resources\V1\CustomerAddressResource;
use App\Http\Resources\V1\OrderResource;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Customers API Controller
 *
 * CRUD operations for customers via public API.
 */
class CustomersController extends BaseApiController
{
    /**
     * List customers
     *
     * GET /api/v1/customers
     *
     * Query params:
     * - search: string (search by name, phone, email)
     * - loyalty_level_id: int
     * - has_orders: bool
     * - page, per_page: pagination
     */
    public function index(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        if (!$restaurantId) {
            return $this->error('INVALID_REQUEST', 'Restaurant ID required', 400);
        }

        $query = Customer::where('restaurant_id', $restaurantId)
            ->with('loyaltyLevel');

        // Search
        $this->applySearchFilter($query, $request, ['name', 'phone', 'email']);

        // Loyalty level filter
        if ($request->has('loyalty_level_id')) {
            $query->where('loyalty_level_id', $request->input('loyalty_level_id'));
        }

        // Has orders filter
        if ($request->has('has_orders')) {
            if ($request->boolean('has_orders')) {
                $query->where('total_orders', '>', 0);
            } else {
                $query->where('total_orders', 0);
            }
        }

        // Sort
        $sort = $this->getSortParams(
            $request,
            ['created_at', 'name', 'total_spent', 'total_orders', 'last_order_at'],
            'created_at',
            'desc'
        );
        $query->orderBy($sort['field'], $sort['direction']);

        // Paginate
        $pagination = $this->getPaginationParams($request);
        $customers = $query->paginate($pagination['per_page'], ['*'], 'page', $pagination['page']);

        return $this->paginated($customers, CustomerResource::class);
    }

    /**
     * Get single customer
     *
     * GET /api/v1/customers/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        $customer = Customer::where('restaurant_id', $restaurantId)
            ->with(['loyaltyLevel', 'addresses'])
            ->find($id);

        if (!$customer) {
            return $this->notFound('Customer not found');
        }

        return $this->success(new CustomerResource($customer));
    }

    /**
     * Find customer by phone
     *
     * GET /api/v1/customers/phone/{phone}
     */
    public function findByPhone(Request $request, string $phone): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        // Normalize phone
        $phone = preg_replace('/[^0-9+]/', '', $phone);

        $customer = Customer::where('restaurant_id', $restaurantId)
            ->where('phone', $phone)
            ->with(['loyaltyLevel', 'addresses'])
            ->first();

        if (!$customer) {
            return $this->notFound('Customer not found');
        }

        return $this->success(new CustomerResource($customer));
    }

    /**
     * Create customer
     *
     * POST /api/v1/customers
     */
    public function store(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        if (!$restaurantId) {
            return $this->error('INVALID_REQUEST', 'Restaurant ID required', 400);
        }

        $data = $this->validateRequest($request, [
            'phone' => 'required|string|max:20',
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'birth_date' => 'nullable|date',
            'gender' => 'nullable|in:male,female,other',
            'notes' => 'nullable|string|max:1000',
            'marketing_consent' => 'nullable|boolean',
            'external_id' => 'nullable|string|max:100',
        ]);

        // Check for duplicate phone
        $existing = Customer::where('restaurant_id', $restaurantId)
            ->where('phone', $data['phone'])
            ->first();

        if ($existing) {
            return $this->conflict('Customer with this phone already exists');
        }

        $customer = Customer::create([
            'tenant_id' => $this->getTenantId($request),
            'restaurant_id' => $restaurantId,
            'phone' => $data['phone'],
            'name' => $data['name'] ?? 'Гость',
            'email' => $data['email'] ?? null,
            'birth_date' => $data['birth_date'] ?? null,
            'gender' => $data['gender'] ?? null,
            'notes' => $data['notes'] ?? null,
            'marketing_consent' => $data['marketing_consent'] ?? false,
            'external_id' => $data['external_id'] ?? null,
        ]);

        $customer->load('loyaltyLevel');

        return $this->created(new CustomerResource($customer), 'Customer created successfully');
    }

    /**
     * Update customer
     *
     * PATCH /api/v1/customers/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        $customer = Customer::where('restaurant_id', $restaurantId)->find($id);

        if (!$customer) {
            return $this->notFound('Customer not found');
        }

        $data = $this->validateRequest($request, [
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'birth_date' => 'nullable|date',
            'gender' => 'nullable|in:male,female,other',
            'notes' => 'nullable|string|max:1000',
            'marketing_consent' => 'nullable|boolean',
            'external_id' => 'nullable|string|max:100',
        ]);

        $customer->update(array_filter($data, fn($v) => $v !== null));
        $customer->refresh();
        $customer->load('loyaltyLevel');

        return $this->success(new CustomerResource($customer), 'Customer updated successfully');
    }

    /**
     * Get customer bonus balance
     *
     * GET /api/v1/customers/{id}/bonus
     */
    public function bonusBalance(Request $request, int $id): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        $customer = Customer::where('restaurant_id', $restaurantId)
            ->with('loyaltyLevel')
            ->find($id);

        if (!$customer) {
            return $this->notFound('Customer not found');
        }

        return $this->success([
            'customer_id' => $customer->id,
            'balance' => number_format($customer->bonus_balance ?? 0, 2, '.', ''),
            'balance_cents' => (int) (($customer->bonus_balance ?? 0) * 100),
            'total_earned' => number_format($customer->total_bonus_earned ?? 0, 2, '.', ''),
            'total_spent' => number_format($customer->total_bonus_spent ?? 0, 2, '.', ''),
            'deposit_balance' => number_format($customer->deposit_balance ?? 0, 2, '.', ''),
            'loyalty_level' => $customer->loyaltyLevel ? [
                'id' => $customer->loyaltyLevel->id,
                'name' => $customer->loyaltyLevel->name,
                'discount_percent' => $customer->loyaltyLevel->discount_percent,
                'bonus_percent' => $customer->loyaltyLevel->bonus_percent,
            ] : null,
        ]);
    }

    /**
     * Get customer orders
     *
     * GET /api/v1/customers/{id}/orders
     */
    public function orders(Request $request, int $id): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        $customer = Customer::where('restaurant_id', $restaurantId)->find($id);

        if (!$customer) {
            return $this->notFound('Customer not found');
        }

        $query = Order::where('restaurant_id', $restaurantId)
            ->where('customer_id', $id)
            ->with(['items.dish', 'table']);

        // Status filter
        if ($request->has('status')) {
            $query->whereIn('status', explode(',', $request->input('status')));
        }

        // Date filter
        $this->applyDateFilter($query, $request);

        // Sort
        $query->orderBy('created_at', 'desc');

        // Paginate
        $pagination = $this->getPaginationParams($request);
        $orders = $query->paginate($pagination['per_page'], ['*'], 'page', $pagination['page']);

        return $this->paginated($orders, OrderResource::class);
    }

    /**
     * Get customer addresses
     *
     * GET /api/v1/customers/{id}/addresses
     */
    public function addresses(Request $request, int $id): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        $customer = Customer::where('restaurant_id', $restaurantId)->find($id);

        if (!$customer) {
            return $this->notFound('Customer not found');
        }

        $addresses = CustomerAddress::where('customer_id', $id)->get();

        return $this->collection($addresses, CustomerAddressResource::class);
    }

    /**
     * Add customer address
     *
     * POST /api/v1/customers/{id}/addresses
     */
    public function addAddress(Request $request, int $id): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        $customer = Customer::where('restaurant_id', $restaurantId)->find($id);

        if (!$customer) {
            return $this->notFound('Customer not found');
        }

        $data = $this->validateRequest($request, [
            'label' => 'nullable|string|max:50',
            'street' => 'required|string|max:255',
            'building' => 'nullable|string|max:50',
            'apartment' => 'nullable|string|max:50',
            'entrance' => 'nullable|string|max:50',
            'floor' => 'nullable|string|max:50',
            'intercom' => 'nullable|string|max:50',
            'city' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'delivery_notes' => 'nullable|string|max:500',
            'is_default' => 'nullable|boolean',
        ]);

        // If is_default, unset other defaults
        if ($data['is_default'] ?? false) {
            CustomerAddress::where('customer_id', $id)->update(['is_default' => false]);
        }

        $address = CustomerAddress::create([
            'customer_id' => $id,
            'label' => $data['label'] ?? null,
            'street' => $data['street'],
            'building' => $data['building'] ?? null,
            'apartment' => $data['apartment'] ?? null,
            'entrance' => $data['entrance'] ?? null,
            'floor' => $data['floor'] ?? null,
            'intercom' => $data['intercom'] ?? null,
            'city' => $data['city'] ?? null,
            'postal_code' => $data['postal_code'] ?? null,
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'delivery_notes' => $data['delivery_notes'] ?? null,
            'is_default' => $data['is_default'] ?? false,
        ]);

        return $this->created(new CustomerAddressResource($address), 'Address added successfully');
    }
}
