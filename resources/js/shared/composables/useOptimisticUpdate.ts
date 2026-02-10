/**
 * useOptimisticUpdate - Composable for optimistic updates with automatic rollback
 *
 * @module shared/composables/useOptimisticUpdate
 */

import { ref, type Ref } from 'vue';
import { createLogger } from '../services/logger.js';
import { useRealtimeStore } from '../stores/realtime.js';

const log = createLogger('OptimisticUpdate');

function generateUUID(): string {
    if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
        return crypto.randomUUID();
    }
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
        const r = Math.random() * 16 | 0;
        const v = c === 'x' ? r : (r & 0x3 | 0x8);
        return v.toString(16);
    });
}

export interface OptimisticUpdateOptions<T = unknown, R = unknown> {
    snapshot: T;
    optimisticUpdate?: () => void;
    serverAction: () => Promise<R>;
    rollback?: (snapshot: T) => void;
    onSuccess?: (result: R) => void;
    onError?: (error: Error) => void;
}

export function useOptimisticUpdate() {
    const store = useRealtimeStore();
    const pending: Ref<boolean> = ref(false);
    const error: Ref<Error | null> = ref<any>(null);

    async function execute<T = unknown, R = unknown>(options: OptimisticUpdateOptions<T, R>): Promise<R> {
        const { snapshot, optimisticUpdate, serverAction, rollback, onSuccess, onError } = options;
        const id = generateUUID();
        pending.value = true;
        error.value = null;

        store.startOptimistic(id, snapshot);

        try {
            optimisticUpdate?.();
        } catch (err: any) {
            log.error('Error in optimistic update:', err);
        }

        try {
            const result = await serverAction();
            store.commitOptimistic(id);
            pending.value = false;
            onSuccess?.(result);
            return result;
        } catch (err: any) {
            log.warn('Rolling back due to error:', err);
            error.value = err as Error;

            const savedSnapshot = store.rollbackOptimistic(id) as T;

            try {
                rollback?.(savedSnapshot);
            } catch (rollbackErr: any) {
                log.error('Error in rollback:', rollbackErr);
            }

            pending.value = false;
            onError?.(err as Error);

            throw err;
        }
    }

    async function executeAll<T = unknown, R = unknown>(
        updates: OptimisticUpdateOptions<T, R>[]
    ): Promise<Array<{ success: boolean; result?: R; error?: unknown }>> {
        const results: Array<{ success: boolean; result?: R; error?: unknown }> = [];

        for (const update of updates) {
            try {
                const result = await execute(update);
                results.push({ success: true, result });
            } catch (err: any) {
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

export function createOptimisticAction<T extends Record<string, any>>(
    target: T,
    key: keyof T,
    serverAction: (newValue: T[keyof T]) => Promise<unknown>
): (newValue: T[keyof T]) => Promise<unknown> {
    const { execute } = useOptimisticUpdate();

    return async (newValue: T[keyof T]) => {
        const oldValue = target[key];

        return execute({
            snapshot: oldValue,
            optimisticUpdate: () => {
                target[key] = newValue;
            },
            serverAction: () => serverAction(newValue),
            rollback: (snapshot: any) => {
                target[key] = snapshot;
            },
        });
    };
}
