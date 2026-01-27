<template>
    <Teleport to="body">
        <Transition name="modal">
            <div v-if="show" class="fixed inset-0 bg-black/90 flex items-center justify-center z-50">
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
import { ref, watch, onMounted, onUnmounted } from 'vue';
import TableOrderAppWrapper from './TableOrderAppWrapper.vue';

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
        const params = new URLSearchParams();
        if (props.guests) params.append('guests', props.guests);
        if (props.linkedTables) params.append('linked_tables', props.linkedTables);
        if (props.reservationId) params.append('reservation', props.reservationId);

        // Определяем URL в зависимости от того, это бар или обычный стол
        const isBar = props.tableId === 'bar';
        const url = isBar
            ? `/pos/bar/data?${params.toString()}`
            : `/pos/table/${props.tableId}/data?${params.toString()}`;

        const response = await fetch(url, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const data = await response.json();

        if (!data.success) {
            throw new Error(data.message || 'Failed to load order data');
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
