/**
 * Write-offs & Cancellations Store
 */

import { defineStore } from 'pinia';
import { ref, shallowRef, computed } from 'vue';
import api from '../api';

export const useWriteOffsStore = defineStore('pos-writeoffs', () => {
    const writeOffs = shallowRef([]);
    const pendingCancellations = ref([]);

    const pendingCancellationsCount = computed(() => pendingCancellations.value.length);

    const loadWriteOffs = async (dateFrom = null, dateTo = null) => {
        const params = {};
        if (dateFrom) params.date_from = dateFrom;
        if (dateTo) params.date_to = dateTo;

        const [newWriteOffs, cancelledOrders] = await Promise.all([
            api.writeOffs.getAll(params).catch(() => []),
            api.writeOffs.getCancelledOrders(params).catch(() => [])
        ]);

        const combined = [...(newWriteOffs || []), ...(cancelledOrders || [])];
        combined.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
        writeOffs.value = combined;
    };

    const loadPendingCancellations = async () => {
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
