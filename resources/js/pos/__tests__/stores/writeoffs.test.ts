/**
 * POS Write-offs Store Unit Tests
 */

import { describe, it, expect, beforeEach, vi } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';

// Mock POS API
const { mockApi } = vi.hoisted(() => ({
    mockApi: {
        writeOffs: {
            getAll: vi.fn(),
            getCancelledOrders: vi.fn(),
        },
        cancellations: {
            getPending: vi.fn(),
        },
    },
}));

vi.mock('@/pos/api/index.js', () => ({
    default: mockApi,
}));

import { useWriteOffsStore } from '@/pos/stores/writeoffs.js';

describe('POS Write-offs Store', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        vi.clearAllMocks();
    });

    describe('Initial State', () => {
        it('should have empty writeOffs', () => {
            const store = useWriteOffsStore();
            expect(store.writeOffs).toEqual([]);
        });

        it('should have empty pendingCancellations', () => {
            const store = useWriteOffsStore();
            expect(store.pendingCancellations).toEqual([]);
        });

        it('should have zero pendingCancellationsCount', () => {
            const store = useWriteOffsStore();
            expect(store.pendingCancellationsCount).toBe(0);
        });
    });

    describe('pendingCancellationsCount', () => {
        it('should reflect the length of pendingCancellations', async () => {
            const mockCancellations = [
                { id: 1, status: 'pending', reason: 'Spoiled' },
                { id: 2, status: 'pending', reason: 'Customer request' },
                { id: 3, status: 'pending', reason: 'Wrong order' },
            ];

            mockApi.cancellations.getPending.mockResolvedValue(mockCancellations);

            const store = useWriteOffsStore();
            await store.loadPendingCancellations();

            expect(store.pendingCancellationsCount).toBe(3);
        });

        it('should return zero when no pending cancellations', () => {
            const store = useWriteOffsStore();
            expect(store.pendingCancellationsCount).toBe(0);
        });
    });

    describe('loadWriteOffs', () => {
        it('should fetch and combine write-offs and cancelled orders', async () => {
            const mockWriteOffs = [
                { id: 1, type: 'writeoff', created_at: '2024-01-15T10:00:00Z' },
            ];
            const mockCancelledOrders = [
                { id: 2, type: 'cancellation', created_at: '2024-01-15T12:00:00Z' },
            ];

            mockApi.writeOffs.getAll.mockResolvedValue(mockWriteOffs);
            mockApi.writeOffs.getCancelledOrders.mockResolvedValue(mockCancelledOrders);

            const store = useWriteOffsStore();
            await store.loadWriteOffs();

            expect(store.writeOffs).toHaveLength(2);
        });

        it('should sort combined results by created_at descending', async () => {
            const mockWriteOffs = [
                { id: 1, type: 'writeoff', created_at: '2024-01-15T08:00:00Z' },
            ];
            const mockCancelledOrders = [
                { id: 2, type: 'cancellation', created_at: '2024-01-15T14:00:00Z' },
                { id: 3, type: 'cancellation', created_at: '2024-01-15T10:00:00Z' },
            ];

            mockApi.writeOffs.getAll.mockResolvedValue(mockWriteOffs);
            mockApi.writeOffs.getCancelledOrders.mockResolvedValue(mockCancelledOrders);

            const store = useWriteOffsStore();
            await store.loadWriteOffs();

            expect(store.writeOffs).toHaveLength(3);
            expect(store.writeOffs[0].id).toBe(2); // 14:00 - newest
            expect(store.writeOffs[1].id).toBe(3); // 10:00
            expect(store.writeOffs[2].id).toBe(1); // 08:00 - oldest
        });

        it('should pass date_from and date_to params to API', async () => {
            mockApi.writeOffs.getAll.mockResolvedValue([]);
            mockApi.writeOffs.getCancelledOrders.mockResolvedValue([]);

            const store = useWriteOffsStore();
            await store.loadWriteOffs('2024-01-01', '2024-01-31');

            expect(mockApi.writeOffs.getAll).toHaveBeenCalledWith({
                date_from: '2024-01-01',
                date_to: '2024-01-31',
            });
            expect(mockApi.writeOffs.getCancelledOrders).toHaveBeenCalledWith({
                date_from: '2024-01-01',
                date_to: '2024-01-31',
            });
        });

        it('should pass empty params when dates are null', async () => {
            mockApi.writeOffs.getAll.mockResolvedValue([]);
            mockApi.writeOffs.getCancelledOrders.mockResolvedValue([]);

            const store = useWriteOffsStore();
            await store.loadWriteOffs(null, null);

            expect(mockApi.writeOffs.getAll).toHaveBeenCalledWith({});
            expect(mockApi.writeOffs.getCancelledOrders).toHaveBeenCalledWith({});
        });

        it('should pass only date_from when date_to is null', async () => {
            mockApi.writeOffs.getAll.mockResolvedValue([]);
            mockApi.writeOffs.getCancelledOrders.mockResolvedValue([]);

            const store = useWriteOffsStore();
            await store.loadWriteOffs('2024-01-01', null);

            expect(mockApi.writeOffs.getAll).toHaveBeenCalledWith({
                date_from: '2024-01-01',
            });
        });

        it('should handle API failure for getAll gracefully', async () => {
            mockApi.writeOffs.getAll.mockRejectedValue(new Error('Network error'));
            mockApi.writeOffs.getCancelledOrders.mockResolvedValue([
                { id: 1, created_at: '2024-01-15T10:00:00Z' },
            ]);

            const store = useWriteOffsStore();
            await store.loadWriteOffs();

            expect(store.writeOffs).toHaveLength(1);
        });

        it('should handle API failure for getCancelledOrders gracefully', async () => {
            mockApi.writeOffs.getAll.mockResolvedValue([
                { id: 1, created_at: '2024-01-15T10:00:00Z' },
            ]);
            mockApi.writeOffs.getCancelledOrders.mockRejectedValue(new Error('Network error'));

            const store = useWriteOffsStore();
            await store.loadWriteOffs();

            expect(store.writeOffs).toHaveLength(1);
        });

        it('should handle both API calls failing gracefully', async () => {
            mockApi.writeOffs.getAll.mockRejectedValue(new Error('Network error'));
            mockApi.writeOffs.getCancelledOrders.mockRejectedValue(new Error('Network error'));

            const store = useWriteOffsStore();
            await store.loadWriteOffs();

            expect(store.writeOffs).toEqual([]);
        });
    });

    describe('loadPendingCancellations', () => {
        it('should fetch and set pending cancellations', async () => {
            const mockCancellations = [
                { id: 1, status: 'pending', reason: 'Spoiled' },
                { id: 2, status: 'pending', reason: 'Customer request' },
            ];

            mockApi.cancellations.getPending.mockResolvedValue(mockCancellations);

            const store = useWriteOffsStore();
            await store.loadPendingCancellations();

            expect(store.pendingCancellations).toEqual(mockCancellations);
            expect(mockApi.cancellations.getPending).toHaveBeenCalledOnce();
        });

        it('should replace previous cancellations on reload', async () => {
            mockApi.cancellations.getPending.mockResolvedValue([
                { id: 1, status: 'pending' },
            ]);

            const store = useWriteOffsStore();
            await store.loadPendingCancellations();
            expect(store.pendingCancellationsCount).toBe(1);

            mockApi.cancellations.getPending.mockResolvedValue([
                { id: 1, status: 'pending' },
                { id: 2, status: 'pending' },
            ]);

            await store.loadPendingCancellations();
            expect(store.pendingCancellationsCount).toBe(2);
        });
    });
});
