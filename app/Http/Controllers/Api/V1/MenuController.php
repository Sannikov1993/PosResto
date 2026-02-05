<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\V1\CategoryResource;
use App\Http\Resources\V1\DishResource;
use App\Http\Resources\V1\ModifierResource;
use App\Models\Category;
use App\Models\Dish;
use App\Models\Modifier;
use App\Models\StopList;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Menu API Controller
 *
 * Provides read-only access to menu data: categories, dishes, modifiers.
 */
class MenuController extends BaseApiController
{
    /**
     * Get all categories
     *
     * GET /api/v1/menu/categories
     *
     * Query params:
     * - include_inactive: bool (default: false)
     * - include_children: bool (default: true)
     * - include_dishes_count: bool (default: false)
     */
    public function categories(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        if (!$restaurantId) {
            return $this->error('INVALID_REQUEST', 'Restaurant ID required', 400);
        }

        $query = Category::where('restaurant_id', $restaurantId)
            ->whereNull('parent_id') // Top-level categories only
            ->orderBy('sort_order');

        // Include inactive
        if (!$request->boolean('include_inactive', false)) {
            $query->where('is_active', true);
        }

        // Include children
        if ($request->boolean('include_children', true)) {
            $query->with(['children' => function ($q) use ($request) {
                $q->orderBy('sort_order');
                if (!$request->boolean('include_inactive', false)) {
                    $q->where('is_active', true);
                }
            }]);
        }

        // Include dishes count
        if ($request->boolean('include_dishes_count', false)) {
            $query->withCount(['dishes' => function ($q) {
                $q->where('is_available', true)
                    ->whereIn('product_type', ['simple', 'parent']);
            }]);
        }

        $categories = $query->get();

        return $this->collection($categories, CategoryResource::class);
    }

    /**
     * Get single category
     *
     * GET /api/v1/menu/categories/{id}
     */
    public function category(Request $request, int $id): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        $category = Category::where('restaurant_id', $restaurantId)
            ->with(['children', 'dishes' => function ($q) {
                $q->where('is_available', true)
                    ->whereIn('product_type', ['simple', 'parent'])
                    ->orderBy('sort_order');
            }])
            ->find($id);

        if (!$category) {
            return $this->notFound('Category not found');
        }

        return $this->success(new CategoryResource($category));
    }

    /**
     * Get all dishes
     *
     * GET /api/v1/menu/dishes
     *
     * Query params:
     * - category_id: int (filter by category)
     * - search: string (search by name, description)
     * - is_available: bool (default: true)
     * - is_popular: bool
     * - is_new: bool
     * - is_vegetarian: bool
     * - include_variants: bool (default: true)
     * - include_modifiers: bool (default: false)
     * - page, per_page: pagination
     */
    public function dishes(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        if (!$restaurantId) {
            return $this->error('INVALID_REQUEST', 'Restaurant ID required', 400);
        }

        $query = Dish::where('restaurant_id', $restaurantId)
            ->whereIn('product_type', ['simple', 'parent']) // Top-level only
            ->orderBy('sort_order');

        // Availability filter (default: only available)
        if ($request->boolean('is_available', true)) {
            $query->where('is_available', true);
        }

        // Category filter
        if ($request->has('category_id')) {
            $query->where('category_id', $request->input('category_id'));
        }

        // Boolean filters
        if ($request->has('is_popular')) {
            $query->where('is_popular', $request->boolean('is_popular'));
        }
        if ($request->has('is_new')) {
            $query->where('is_new', $request->boolean('is_new'));
        }
        if ($request->has('is_vegetarian')) {
            $query->where('is_vegetarian', $request->boolean('is_vegetarian'));
        }
        if ($request->has('is_vegan')) {
            $query->where('is_vegan', $request->boolean('is_vegan'));
        }

        // Search
        $this->applySearchFilter($query, $request, ['name', 'description', 'sku']);

        // Include variants
        if ($request->boolean('include_variants', true)) {
            $query->with(['variants' => function ($q) {
                $q->where('is_available', true)->orderBy('variant_sort');
            }]);
        }

        // Include modifiers
        if ($request->boolean('include_modifiers', false)) {
            $query->with(['modifiers.options']);
        }

        // Include category
        $query->with('category');

        // Paginate
        $pagination = $this->getPaginationParams($request);
        $dishes = $query->paginate($pagination['per_page'], ['*'], 'page', $pagination['page']);

        return $this->paginated($dishes, DishResource::class);
    }

    /**
     * Get single dish
     *
     * GET /api/v1/menu/dishes/{id}
     */
    public function dish(Request $request, int $id): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        $dish = Dish::where('restaurant_id', $restaurantId)
            ->with([
                'category',
                'variants' => function ($q) {
                    $q->where('is_available', true)->orderBy('variant_sort');
                },
                'modifiers.options',
            ])
            ->find($id);

        if (!$dish) {
            return $this->notFound('Dish not found');
        }

        return $this->success(new DishResource($dish));
    }

    /**
     * Get all modifiers
     *
     * GET /api/v1/menu/modifiers
     */
    public function modifiers(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        if (!$restaurantId) {
            return $this->error('INVALID_REQUEST', 'Restaurant ID required', 400);
        }

        $query = Modifier::where('restaurant_id', $restaurantId)
            ->with('options')
            ->orderBy('sort_order');

        if (!$request->boolean('include_inactive', false)) {
            $query->where('is_active', true);
        }

        $modifiers = $query->get();

        return $this->collection($modifiers, ModifierResource::class);
    }

    /**
     * Get single modifier
     *
     * GET /api/v1/menu/modifiers/{id}
     */
    public function modifier(Request $request, int $id): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        $modifier = Modifier::where('restaurant_id', $restaurantId)
            ->with('options')
            ->find($id);

        if (!$modifier) {
            return $this->notFound('Modifier not found');
        }

        return $this->success(new ModifierResource($modifier));
    }

    /**
     * Get current stop list
     *
     * GET /api/v1/menu/stop-list
     */
    public function stopList(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        if (!$restaurantId) {
            return $this->error('INVALID_REQUEST', 'Restaurant ID required', 400);
        }

        $stopList = StopList::where('restaurant_id', $restaurantId)
            ->with('dish:id,name,api_external_id,sku')
            ->get();

        $items = $stopList->map(function ($item) {
            return [
                'dish_id' => $item->dish_id,
                'dish_external_id' => $item->dish?->api_external_id,
                'dish_name' => $item->dish?->name,
                'reason' => $item->reason,
                'stopped_at' => $this->formatDateTime($item->stopped_at),
                'resume_at' => $this->formatDateTime($item->resume_at),
            ];
        });

        return $this->success($items);
    }

    /**
     * Get full menu (categories with dishes)
     *
     * GET /api/v1/menu/full
     */
    public function fullMenu(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        if (!$restaurantId) {
            return $this->error('INVALID_REQUEST', 'Restaurant ID required', 400);
        }

        // Get categories with dishes
        $categories = Category::where('restaurant_id', $restaurantId)
            ->where('is_active', true)
            ->whereNull('parent_id')
            ->with([
                'children' => function ($q) {
                    $q->where('is_active', true)->orderBy('sort_order');
                },
                'dishes' => function ($q) {
                    $q->where('is_available', true)
                        ->whereIn('product_type', ['simple', 'parent'])
                        ->with([
                            'variants' => function ($vq) {
                                $vq->where('is_available', true)->orderBy('variant_sort');
                            },
                            'modifiers.options',
                        ])
                        ->orderBy('sort_order');
                },
            ])
            ->orderBy('sort_order')
            ->get();

        // Get stop list
        $stopList = StopList::where('restaurant_id', $restaurantId)
            ->pluck('dish_id')
            ->toArray();

        // Transform
        $menu = $categories->map(function ($category) use ($stopList) {
            return [
                'category' => new CategoryResource($category),
                'dishes' => DishResource::collection($category->dishes)->map(function ($dish) use ($stopList) {
                    $data = $dish->resolve();
                    $data['flags']['is_in_stop_list'] = in_array($dish->id, $stopList);
                    return $data;
                }),
                'children' => $category->children->map(function ($child) use ($stopList) {
                    return [
                        'category' => new CategoryResource($child),
                        'dishes' => DishResource::collection($child->dishes ?? collect())->map(function ($dish) use ($stopList) {
                            $data = $dish->resolve();
                            $data['flags']['is_in_stop_list'] = in_array($dish->id, $stopList);
                            return $data;
                        }),
                    ];
                }),
            ];
        });

        return $this->success($menu, null, 200, [
            'stop_list_count' => count($stopList),
            'categories_count' => $categories->count(),
            'generated_at' => now()->toIso8601String(),
        ]);
    }
}
