/**
 * Write-offs & Cancellations Store
 */

import { defineStore } from 'pinia';
import { ref, shallowRef, computed } from 'vue';
import api from '../api/index.js';
import type { WriteOff } from '@/shared/types';

interface WriteOffEntry extends WriteOff {
    created_at: string;
}

export const useWriteOffsStore = defineStore('pos-writeoffs', () => {
    const writeOffs = shallowRef<WriteOffEntry[]>([]);
    const pendingCancellations = ref<any[]>([]);

    const pendingCancellationsCount = computed(() => pendingCancellations.value.length);

    const loadWriteOffs = async (dateFrom: string | null = null, dateTo: string | null = null): Promise<void> => {
        const params: Record<string, string> = {};
        if (dateFrom) params.date_from = dateFrom;
        if (dateTo) params.date_to = dateTo;

        const [newWriteOffs, cancelledOrders] = await Promise.all([
            api.writeOffs.getAll(params).catch(() => []),
            api.writeOffs.getCancelledOrders(params).catch(() => [])
        ]);

        const combined = [...(newWriteOffs || []), ...(cancelledOrders || [])] as WriteOffEntry[];
        combined.sort((a: any, b: any) => new Date(b.created_at).getTime() - new Date(a.created_at).getTime());
        writeOffs.value = combined;
    };

    const loadPendingCancellations = async (): Promise<void> => {
        pendingCancellations.value = await api.cancellations.getPending();
    };

    return {
        writeOffs,
        pendingCancellations,
        pendingCancellationsCount,
        loadWriteOffs,
        loadPendingCancellations,
    };
});
