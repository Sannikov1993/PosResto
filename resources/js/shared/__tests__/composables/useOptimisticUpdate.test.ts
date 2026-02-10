/**
 * useOptimisticUpdate Composable Unit Tests
 */

import { describe, it, expect, beforeEach, vi } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';

// Mock logger
vi.mock('@/shared/services/logger.js', () => ({
    createLogger: () => ({
        debug: vi.fn(),
        warn: vi.fn(),
        error: vi.fn(),
        info: vi.fn(),
    }),
}));

// Mock WebSocketManager (required by realtime store)
vi.mock('@/shared/services/WebSocketManager.js', () => ({
    WebSocketManager: vi.fn().mockImplementation(() => ({
        connect: vi.fn(),
        disconnect: vi.fn(),
        send: vi.fn(),
        reconnect: vi.fn(),
    })),
}));

import { useOptimisticUpdate, createOptimisticAction } from '@/shared/composables/useOptimisticUpdate.js';

describe('useOptimisticUpdate', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        vi.clearAllMocks();
    });

    describe('return values', () => {
        it('should return execute, executeAll, pending, and error', () => {
            const { execute, executeAll, pending, error } = useOptimisticUpdate();

            expect(typeof execute).toBe('function');
            expect(typeof executeAll).toBe('function');
            expect(pending.value).toBe(false);
            expect(error.value).toBeNull();
        });
    });

    describe('execute', () => {
        it('should call optimisticUpdate before server action', async () => {
            const { execute } = useOptimisticUpdate();
            const callOrder: string[] = [];

            await execute({
                snapshot: 'old',
                optimisticUpdate: () => { callOrder.push('optimistic'); },
                serverAction: async () => { callOrder.push('server'); return 'result'; },
            });

            expect(callOrder).toEqual(['optimistic', 'server']);
        });

        it('should return the server action result on success', async () => {
            const { execute } = useOptimisticUpdate();

            const result = await execute({
                snapshot: 'old',
                serverAction: async () => 'server-response',
            });

            expect(result).toBe('server-response');
        });

        it('should set pending to true during execution and false after', async () => {
            const { execute, pending } = useOptimisticUpdate();
            let pendingDuringAction = false;

            await execute({
                snapshot: 'old',
                serverAction: async () => {
                    pendingDuringAction = pending.value;
                    return 'done';
                },
            });

            expect(pendingDuringAction).toBe(true);
            expect(pending.value).toBe(false);
        });

        it('should call onSuccess callback on success', async () => {
            const { execute } = useOptimisticUpdate();
            const onSuccess = vi.fn();

            await execute({
                snapshot: 'old',
                serverAction: async () => 'result',
                onSuccess,
            });

            expect(onSuccess).toHaveBeenCalledWith('result');
        });

        it('should call rollback and onError on server action failure', async () => {
            const { execute, error } = useOptimisticUpdate();
            const rollback = vi.fn();
            const onError = vi.fn();
            const serverError = new Error('server failed');

            await expect(
                execute({
                    snapshot: { data: 'original' },
                    serverAction: async () => { throw serverError; },
                    rollback,
                    onError,
                })
            ).rejects.toThrow('server failed');

            expect(rollback).toHaveBeenCalled();
            expect(onError).toHaveBeenCalledWith(serverError);
            expect(error.value).toBe(serverError);
        });

        it('should set pending to false after failure', async () => {
            const { execute, pending } = useOptimisticUpdate();

            try {
                await execute({
                    snapshot: 'old',
                    serverAction: async () => { throw new Error('fail'); },
                });
            } catch {
                // expected
            }

            expect(pending.value).toBe(false);
        });

        it('should reset error on new execute call', async () => {
            const { execute, error } = useOptimisticUpdate();

            try {
                await execute({
                    snapshot: 'old',
                    serverAction: async () => { throw new Error('first error'); },
                });
            } catch {
                // expected
            }

            expect(error.value).not.toBeNull();

            await execute({
                snapshot: 'old',
                serverAction: async () => 'success',
            });

            expect(error.value).toBeNull();
        });

        it('should still succeed even if optimisticUpdate throws', async () => {
            const { execute } = useOptimisticUpdate();

            const result = await execute({
                snapshot: 'old',
                optimisticUpdate: () => { throw new Error('optimistic error'); },
                serverAction: async () => 'server-result',
            });

            expect(result).toBe('server-result');
        });

        it('should handle rollback error gracefully without re-throwing', async () => {
            const { execute } = useOptimisticUpdate();

            await expect(
                execute({
                    snapshot: 'old',
                    serverAction: async () => { throw new Error('server fail'); },
                    rollback: () => { throw new Error('rollback fail'); },
                })
            ).rejects.toThrow('server fail');
            // The rollback error is caught and logged, not thrown
        });
    });

    describe('executeAll', () => {
        it('should execute multiple updates in order', async () => {
            const { executeAll } = useOptimisticUpdate();
            const order: number[] = [];

            const results = await executeAll([
                {
                    snapshot: 'a',
                    serverAction: async () => { order.push(1); return 'r1'; },
                },
                {
                    snapshot: 'b',
                    serverAction: async () => { order.push(2); return 'r2'; },
                },
            ]);

            expect(order).toEqual([1, 2]);
            expect(results).toHaveLength(2);
            expect(results[0]).toEqual({ success: true, result: 'r1' });
            expect(results[1]).toEqual({ success: true, result: 'r2' });
        });

        it('should continue executing even if one update fails', async () => {
            const { executeAll } = useOptimisticUpdate();

            const results = await executeAll([
                {
                    snapshot: 'a',
                    serverAction: async () => { throw new Error('fail'); },
                },
                {
                    snapshot: 'b',
                    serverAction: async () => 'success',
                },
            ]);

            expect(results).toHaveLength(2);
            expect(results[0].success).toBe(false);
            expect(results[0].error).toBeInstanceOf(Error);
            expect(results[1].success).toBe(true);
            expect(results[1].result).toBe('success');
        });

        it('should return empty array for empty input', async () => {
            const { executeAll } = useOptimisticUpdate();

            const results = await executeAll([]);

            expect(results).toEqual([]);
        });
    });
});

describe('createOptimisticAction', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        vi.clearAllMocks();
    });

    it('should return a function', () => {
        const target = { name: 'original' };
        const action = createOptimisticAction(target, 'name', async () => {});

        expect(typeof action).toBe('function');
    });

    it('should optimistically update the target property', async () => {
        const target = { count: 0 };
        const action = createOptimisticAction(
            target,
            'count',
            async (_newValue: number) => 'ok'
        );

        await action(42);

        expect(target.count).toBe(42);
    });

    it('should rollback on server error', async () => {
        const target = { count: 10 };
        const action = createOptimisticAction(
            target,
            'count',
            async () => { throw new Error('server fail'); }
        );

        try {
            await action(99);
        } catch {
            // expected
        }

        // Rollback sets target[key] to snapshot, which was the value
        // passed to rollback from the realtime store
        // The snapshot stored in the realtime store is the oldValue (10)
        expect(target.count).toBe(10);
    });
});
