/**
 * useOptimisticUpdate - Composable for optimistic updates with automatic rollback
 *
 * Provides a pattern for optimistically updating the UI before server confirmation,
 * with automatic rollback on failure.
 *
 * @module shared/composables/useOptimisticUpdate
 *
 * @example
 * const { execute } = useOptimisticUpdate();
 *
 * await execute({
 *     snapshot: { status: order.status },
 *     optimisticUpdate: () => {
 *         // Immediately update UI
 *         order.status = 'ready';
 *     },
 *     serverAction: async () => {
 *         // Send to server
 *         return await api.updateOrderStatus(orderId, 'ready');
 *     },
 *     rollback: (snapshot) => {
 *         // Rollback on error
 *         order.status = snapshot.status;
 *     },
 * });
 */

import { ref } from 'vue';
import { useRealtimeStore } from '../stores/realtime.js';

/**
 * Generate a UUID with fallback for browsers without crypto.randomUUID
 * @returns {string} UUID string
 */
function generateUUID() {
    if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
        return crypto.randomUUID();
    }
    // Fallback for older browsers
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
        const r = Math.random() * 16 | 0;
        const v = c === 'x' ? r : (r & 0x3 | 0x8);
        return v.toString(16);
    });
}

/**
 * @typedef {Object} OptimisticUpdateOptions
 * @property {*} snapshot - State snapshot for rollback
 * @property {Function} optimisticUpdate - Function to apply optimistic update
 * @property {Function} serverAction - Async function to execute server action
 * @property {Function} rollback - Function to rollback on failure
 * @property {Function} onSuccess - Optional callback on success
 * @property {Function} onError - Optional callback on error
 */

/**
 * Composable for executing optimistic updates
 */
export function useOptimisticUpdate() {
    const store = useRealtimeStore();
    const pending = ref(false);
    const error = ref(null);

    /**
     * Execute an optimistic update
     * @param {OptimisticUpdateOptions} options
     * @returns {Promise<*>} Result from server action
     */
    async function execute({
        snapshot,
        optimisticUpdate,
        serverAction,
        rollback,
        onSuccess,
        onError,
    }) {
        const id = generateUUID();
        pending.value = true;
        error.value = null;

        // 1. Save snapshot for potential rollback
        store.startOptimistic(id, snapshot);

        // 2. Apply optimistic update immediately
        try {
            optimisticUpdate?.();
        } catch (err) {
            console.error('[OptimisticUpdate] Error in optimistic update:', err);
        }

        // 3. Execute server action
        try {
            const result = await serverAction();

            // 4. Success - commit the change
            store.commitOptimistic(id);
            pending.value = false;
            onSuccess?.(result);

            return result;

        } catch (err) {
            // 5. Error - rollback
            console.warn('[OptimisticUpdate] Rolling back due to error:', err);
            error.value = err;

            const savedSnapshot = store.rollbackOptimistic(id);

            try {
                rollback?.(savedSnapshot);
            } catch (rollbackErr) {
                console.error('[OptimisticUpdate] Error in rollback:', rollbackErr);
            }

            pending.value = false;
            onError?.(err);

            throw err;
        }
    }

    /**
     * Execute multiple optimistic updates in sequence
     * @param {OptimisticUpdateOptions[]} updates - Array of update configs
     * @returns {Promise<Array>} Results from all server actions
     */
    async function executeAll(updates) {
        const results = [];

        for (const update of updates) {
            try {
                const result = await execute(update);
                results.push({ success: true, result });
            } catch (err) {
                results.push({ success: false, error: err });
            }
        }

        return results;
    }

    return {
        execute,
        executeAll,
        pending,
        error,
    };
}

/**
 * Create a simple optimistic update wrapper for common cases
 *
 * @example
 * const updateStatus = createOptimisticAction(
 *     order,
 *     'status',
 *     (newStatus) => api.updateOrderStatus(order.id, newStatus)
 * );
 *
 * await updateStatus('ready');
 *
 * @param {Object} target - Reactive object to update
 * @param {string} key - Property key to update
 * @param {Function} serverAction - Server action (receives new value)
 * @returns {Function} Update function
 */
export function createOptimisticAction(target, key, serverAction) {
    const { execute } = useOptimisticUpdate();

    return async (newValue) => {
        const oldValue = target[key];

        return execute({
            snapshot: oldValue,
            optimisticUpdate: () => {
                target[key] = newValue;
            },
            serverAction: () => serverAction(newValue),
            rollback: (snapshot) => {
                target[key] = snapshot;
            },
        });
    };
}
