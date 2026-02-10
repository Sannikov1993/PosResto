<template>
    <div class="h-full overflow-y-auto p-4">
        <!-- Empty State -->
        <div
            v-if="orders.length === 0"
            class="flex flex-col items-center justify-center h-full text-gray-500"
        >
            <svg class="w-16 h-16 mb-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0" />
            </svg>
            <p class="text-lg font-medium mb-1">Нет заказов</p>
            <p class="text-sm">Создайте новый заказ или измените фильтры</p>
        </div>

        <!-- Orders List -->
        <div v-else class="space-y-3 max-w-4xl mx-auto">
            <DeliveryOrderCard
                v-for="order in orders"
                :key="order.id"
                :order="order"
                :selected="selectedOrderId === order.id"
                @click="$emit('select-order', order)"
                @assign-courier="$emit('assign-courier', order)"
                @status-change="$emit('status-change', $event)"
            />
        </div>
    </div>
</template>

<script setup lang="ts">
import type { PropType } from 'vue';
import DeliveryOrderCard from './DeliveryOrderCard.vue';

defineProps({
    orders: {
        type: Array as PropType<any[]>,
        default: () => []
    },
    selectedOrderId: {
        type: [Number, String],
        default: null as any
    }
});

defineEmits(['select-order', 'assign-courier', 'status-change']);
</script>
