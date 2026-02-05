/**
 * Kitchen Module Type Definitions
 *
 * JSDoc type definitions for the kitchen display system.
 * These provide IDE autocomplete and documentation without requiring TypeScript.
 *
 * @module kitchen/types
 */

/**
 * @typedef {Object} KitchenStation
 * @property {number} id - Station unique identifier
 * @property {string} name - Station display name (e.g., "Горячий цех")
 * @property {string} slug - Station URL-safe identifier
 * @property {string} [icon] - Emoji icon for the station
 * @property {string} [notification_sound] - Sound type for notifications
 * @property {boolean} is_active - Whether station is active
 * @property {number} sort_order - Display order
 */

/**
 * @typedef {Object} KitchenDevice
 * @property {string} id - Device unique identifier (UUID)
 * @property {string} [name] - Device display name
 * @property {string} status - Device status
 * @property {KitchenStation} [kitchen_station] - Assigned station
 * @property {string} [last_seen_at] - Last activity timestamp
 * @property {Object} [settings] - Device-specific settings
 */

/**
 * @typedef {Object} OrderModifier
 * @property {number} id - Modifier unique identifier
 * @property {string} name - Modifier name
 * @property {string} [option_name] - Selected option name
 * @property {number} price - Modifier price
 * @property {number} quantity - Quantity
 */

/**
 * @typedef {Object} OrderItem
 * @property {number} id - Item unique identifier
 * @property {number} order_id - Parent order ID
 * @property {string} name - Dish name
 * @property {number} quantity - Quantity ordered
 * @property {string} status - Item status (pending, cooking, ready, served, cancelled)
 * @property {string} [cooking_started_at] - When cooking started (ISO timestamp)
 * @property {string} [ready_at] - When item became ready (ISO timestamp)
 * @property {string} [comment] - Special instructions
 * @property {OrderModifier[]} [modifiers] - Applied modifiers
 * @property {Object} [dish] - Full dish object with details
 * @property {string} [dish.image] - Dish image URL
 * @property {string} [dish.description] - Dish description/recipe
 * @property {number} [dish.cooking_time] - Expected cooking time in minutes
 * @property {number} [dish.weight] - Weight in grams
 * @property {number} [dish.calories] - Calories
 * @property {number} [dish.proteins] - Proteins per 100g
 * @property {number} [dish.fats] - Fats per 100g
 * @property {number} [dish.carbs] - Carbs per 100g
 * @property {boolean} [dish.is_spicy] - Is spicy flag
 * @property {boolean} [dish.is_vegetarian] - Is vegetarian flag
 * @property {boolean} [dish.is_vegan] - Is vegan flag
 */

/**
 * @typedef {Object} OrderTable
 * @property {number} id - Table unique identifier
 * @property {string} [name] - Table name
 * @property {string|number} [number] - Table number
 */

/**
 * @typedef {Object} Order
 * @property {number} id - Order unique identifier
 * @property {string} order_number - Display order number (e.g., "A-001")
 * @property {string} type - Order type (dine_in, pickup, delivery, preorder)
 * @property {string} status - Order status
 * @property {string} created_at - Creation timestamp (ISO)
 * @property {string} updated_at - Last update timestamp (ISO)
 * @property {string} [scheduled_at] - Scheduled time for preorders (ISO)
 * @property {boolean} [is_asap] - Whether order should be prepared ASAP
 * @property {string} [cooking_started_at] - When cooking started (ISO)
 * @property {OrderItem[]} items - Order items
 * @property {OrderTable} [table] - Associated table
 * @property {string} [comment] - Order-level comment
 * @property {Object} [customer] - Customer information
 */

/**
 * @typedef {Object} ProcessedOrder
 * @extends Order
 * @property {OrderItem[]} items - Filtered items for current view
 * @property {number} [cookingMinutes] - Minutes since cooking started
 * @property {boolean} [isWarning] - Is in warning state
 * @property {boolean} [isCritical] - Is in critical state
 * @property {boolean} [isAlert] - Should show alert
 */

/**
 * @typedef {Object} TimeSlot
 * @property {string} key - Unique slot key (YYYY-MM-DD-HH:mm)
 * @property {string} label - Display label (e.g., "14:00 - 14:30")
 * @property {Order[]} orders - Orders in this slot
 * @property {string} urgency - Urgency level (normal, warning, urgent, overdue)
 */

/**
 * @typedef {Object} StopListItem
 * @property {number} id - Stop list entry ID
 * @property {Object} dish - Dish that's stopped
 * @property {string} dish.name - Dish name
 * @property {string} [dish.image] - Dish image
 * @property {string} reason - Reason for stopping
 * @property {string} [resume_at] - When dish will be available again
 */

/**
 * @typedef {Object} CancellationData
 * @property {string} item_name - Cancelled item name
 * @property {number} quantity - Cancelled quantity
 * @property {string} order_number - Order number
 * @property {string} [table_number] - Table number/name
 * @property {string} reason_label - Cancellation reason label
 * @property {string} [reason_comment] - Additional comment
 */

/**
 * @typedef {Object} WaiterCallData
 * @property {string} waiterName - Assigned waiter name
 * @property {string} orderNumber - Order number
 * @property {string} [tableName] - Table name/number
 */

/**
 * @typedef {Object} OrderCountsByDate
 * @property {Object.<string, number>} counts - Map of date strings to order counts
 */

/**
 * @typedef {Object} ApiResponse
 * @template T
 * @property {boolean} success - Whether request succeeded
 * @property {T} [data] - Response data
 * @property {string} [message] - Response message
 * @property {string} [error_code] - Error code if failed
 * @property {string} [status] - Additional status info
 */

/**
 * @typedef {Object} DeviceStatusResponse
 * @property {boolean} success
 * @property {KitchenDevice} [data]
 * @property {string} status - Device status string
 */

/**
 * @typedef {Object} OrdersResponse
 * @property {boolean} success
 * @property {Order[]} data
 */

// Export empty object to make this a module
export default {};
