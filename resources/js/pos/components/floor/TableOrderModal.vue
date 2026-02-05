<template>
    <Teleport to="body">
        <Transition name="modal">
            <div v-if="show" class="fixed inset-0 bg-black/90 flex items-center justify-center z-50" data-testid="table-order-modal">
                <div class="bg-dark-900 w-full h-full flex flex-col overflow-hidden">
                    <!-- Loading state -->
                    <div v-if="loading" class="flex-1 flex items-center justify-center">
                        <div class="text-center">
                            <div class="w-12 h-12 border-4 border-accent border-t-transparent rounded-full animate-spin mx-auto mb-4"></div>
                            <p class="text-gray-400">Загрузка заказа...</p>
                        </div>
                    </div>

                    <!-- Error state -->
                    <div v-else-if="error" class="flex-1 flex items-center justify-center">
                        <div class="text-center">
                            <div class="text-red-400 text-6xl mb-4">!</div>
                            <p class="text-white mb-2">Ошибка загрузки</p>
                            <p class="text-gray-400 mb-4">{{ error }}</p>
                            <button @click="close" class="px-6 py-2 bg-dark-700 hover:bg-dark-600 rounded-lg text-white">
                                Закрыть
                            </button>
                        </div>
                    </div>

                    <!-- Table Order App (when data loaded) -->
                    <TableOrderAppWrapper
                        v-else-if="orderData"
                        :initialData="orderData"
                        @close="close"
                        @orderUpdated="handleOrderUpdated"
                    />
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup>
import { ref, watch, onMounted, onUnmounted, computed } from 'vue';
import api from '../../api';
import authService from '../../../shared/services/auth';
import TableOrderAppWrapper from './TableOrderAppWrapper.vue';
import { useRealtimeEvents } from '../../../shared/composables/useRealtimeEvents.js';
import { usePosStore } from '../../stores/pos';

const posStore = usePosStore();

// Enterprise Real-time (uses centralized store, auto-cleanup on unmount)
const { on: subscribeEvent, connected: realtimeConnected } = useRealtimeEvents();

const props = defineProps({
    show: { type: Boolean, default: false },
    tableId: { type: [Number, String], required: true },
    guests: { type: Number, default: null },
    linkedTables: { type: String, default: null },
    reservationId: { type: [Number, String], default: null },
});

const emit = defineEmits(['close', 'orderUpdated']);

const loading = ref(false);
const error = ref(null);
const orderData = ref(null);

const loadOrderData = async () => {
    loading.value = true;
    error.value = null;

    try {
        const params = {};
        if (props.guests) params.guests = props.guests;
        if (props.linkedTables) params.linked_tables = props.linkedTables;
        if (props.reservationId) params.reservation = props.reservationId;

        const isBar = props.tableId === 'bar';

        let data;
        if (isBar) {
            // Bar использует web route - нужен fetch с auth header
            const authHeader = authService.getAuthHeader();
            const queryString = new URLSearchParams(params).toString();
            const response = await fetch(`/pos/bar/data?${queryString}`, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...(authHeader ? { 'Authorization': authHeader } : {})
                }
            });
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            data = await response.json();
            if (!data?.success) {
                throw new Error(data?.message || 'Failed to load order data');
            }
        } else {
            // Используем централизованный API
            // Interceptor бросит исключение при success: false
            data = await api.tables.getOrderData(props.tableId, params);
        }

        orderData.value = data;
    } catch (e) {
        console.error('Failed to load order data:', e);
        error.value = e.message;
    } finally {
        loading.value = false;
    }
};

// Load data when modal opens
watch(() => props.show, async (newVal) => {
    if (newVal) {
        await loadOrderData();
        document.body.style.overflow = 'hidden';
    } else {
        document.body.style.overflow = '';
        orderData.value = null;
    }
}, { immediate: true });

const close = () => {
    emit('close');
};

const handleOrderUpdated = () => {
    emit('orderUpdated');
};

// Get current order IDs for filtering events
const currentOrderIds = computed(() => {
    if (!orderData.value?.orders) return [];
    return orderData.value.orders.map(o => o.id);
});

// Silent refresh (without loading indicator)
const silentRefresh = async () => {
    console.log('[TableOrderModal] silentRefresh called', { show: props.show, loading: loading.value });
    if (!props.show || loading.value) return;

    try {
        const params = {};
        if (props.guests) params.guests = props.guests;
        if (props.linkedTables) params.linked_tables = props.linkedTables;
        if (props.reservationId) params.reservation = props.reservationId;

        const isBar = props.tableId === 'bar';

        let data;
        if (isBar) {
            const authHeader = authService.getAuthHeader();
            const queryString = new URLSearchParams(params).toString();
            const response = await fetch(`/pos/bar/data?${queryString}`, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...(authHeader ? { 'Authorization': authHeader } : {})
                }
            });
            if (!response.ok) return;
            data = await response.json();
            if (!data?.success) return;
        } else {
            data = await api.tables.getOrderData(props.tableId, params);
        }

        // Update order data without resetting everything
        if (data?.orders) {
            console.log('[TableOrderModal] silentRefresh updating orderData', {
                oldOrdersCount: orderData.value?.orders?.length,
                newOrdersCount: data.orders.length,
                firstOrderItems: data.orders[0]?.items?.map(i => ({ name: i.name, status: i.status })),
            });
            orderData.value = data;
        }
    } catch (e) {
        console.warn('[TableOrderModal] Silent refresh failed:', e);
    }
};

// Subscribe to real-time events for order updates (using centralized store)
// Note: The centralized RealtimeStore is already connected by POS App.vue
// We just subscribe to events we care about - auto-cleanup happens on unmount
let eventUnsubscribers = [];

const setupEventSubscriptions = () => {
    // Cleanup previous subscriptions
    eventUnsubscribers.forEach(unsub => unsub?.());
    eventUnsubscribers = [];

    console.log('[TableOrderModal] Setting up event subscriptions', {
        currentOrderIds: currentOrderIds.value,
    });

    // Handle order status changes
    eventUnsubscribers.push(subscribeEvent('order_status', (data) => {
        console.log('[TableOrderModal] Received order_status event', data, 'currentOrderIds:', currentOrderIds.value);
        if (currentOrderIds.value.includes(data.order_id)) {
            console.log('[TableOrderModal] Order status changed, refreshing...');
            silentRefresh();
        }
    }));

    // Handle order updates (item status changes, etc)
    eventUnsubscribers.push(subscribeEvent('order_updated', (data) => {
        console.log('[TableOrderModal] Received order_updated event', data, 'currentOrderIds:', currentOrderIds.value);
        if (currentOrderIds.value.includes(data.order_id)) {
            console.log('[TableOrderModal] Order updated, refreshing...');
            silentRefresh();
        }
    }));

    // Handle kitchen ready events
    eventUnsubscribers.push(subscribeEvent('kitchen_ready', (data) => {
        console.log('[TableOrderModal] Received kitchen_ready event', data, 'currentOrderIds:', currentOrderIds.value);
        if (currentOrderIds.value.includes(data.order_id)) {
            console.log('[TableOrderModal] Kitchen ready, refreshing...');
            silentRefresh();
        }
    }));

    console.log('[TableOrderModal] Event subscriptions set up');
};

const cleanupEventSubscriptions = () => {
    eventUnsubscribers.forEach(unsub => unsub?.());
    eventUnsubscribers = [];
};

// Setup event subscriptions when modal opens
watch(() => props.show, (newVal) => {
    if (newVal) {
        setupEventSubscriptions();
    } else {
        cleanupEventSubscriptions();
    }
}, { immediate: true });

// Handle Escape key
const handleKeydown = (e) => {
    if (e.key === 'Escape' && props.show) {
        close();
    }
};

onMounted(() => {
    document.addEventListener('keydown', handleKeydown);
});

onUnmounted(() => {
    document.removeEventListener('keydown', handleKeydown);
    cleanupEventSubscriptions();
    document.body.style.overflow = '';
});
</script>

<style scoped>
.modal-enter-active,
.modal-leave-active {
    transition: opacity 0.2s ease;
}

.modal-enter-from,
.modal-leave-to {
    opacity: 0;
}
</style>
