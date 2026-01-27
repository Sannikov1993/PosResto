<template>
    <div>
        <h2 class="text-lg font-semibold text-gray-800 mb-3">
            Доступные заказы
            <span v-if="store.availableOrders.length" class="text-purple-600">({{ store.availableOrders.length }})</span>
        </h2>

        <!-- Loading -->
        <div v-if="store.isLoading && !store.availableOrders.length" class="space-y-3">
            <div v-for="i in 3" :key="i" class="bg-white rounded-xl p-4 shadow-sm">
                <div class="skeleton h-5 w-24 rounded mb-2"></div>
                <div class="skeleton h-4 w-full rounded mb-1"></div>
                <div class="skeleton h-4 w-2/3 rounded"></div>
            </div>
        </div>

        <!-- Available orders list -->
        <div v-else-if="store.availableOrders.length" class="space-y-3">
            <div v-for="order in store.availableOrders" :key="order.id"
                 class="bg-white rounded-xl shadow-sm overflow-hidden">
                <div class="p-4" @click="store.selectedOrder = order">
                    <div class="flex items-start justify-between mb-2">
                        <div>
                            <span class="font-semibold text-gray-800">{{ order.order_number }}</span>
                            <span class="ml-2 px-2 py-0.5 text-xs rounded-full text-white bg-green-500">
                                Готов
                            </span>
                        </div>
                        <span class="font-bold text-gray-800">{{ store.formatMoney(order.total) }}</span>
                    </div>
                    <p class="text-gray-600 text-sm mb-1">
                        <svg class="w-4 h-4 inline mr-1 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        {{ store.formatAddress(order) }}
                    </p>
                    <div class="flex items-center justify-between text-xs text-gray-400">
                        <span>{{ store.formatPaymentMethod(order.payment_method) }}</span>
                        <span v-if="order.deliver_at">К {{ store.formatTime(order.deliver_at) }}</span>
                    </div>
                </div>
                <div class="border-t border-gray-100">
                    <button @click="acceptOrder(order)" :disabled="store.isLoading"
                            class="w-full py-3 text-sm font-semibold text-white bg-purple-600 hover:bg-purple-700 disabled:opacity-50 transition-colors flex items-center justify-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                        </svg>
                        Взять заказ
                    </button>
                </div>
            </div>
        </div>

        <!-- Empty state -->
        <div v-else class="bg-white rounded-xl p-8 text-center shadow-sm">
            <svg class="w-16 h-16 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
            </svg>
            <p class="text-gray-500">Нет доступных заказов</p>
            <p class="text-gray-400 text-sm mt-1">Потяните вниз для обновления</p>
        </div>
    </div>
</template>

<script setup>
import { useCourierStore } from '../stores/courier';

const store = useCourierStore();

async function acceptOrder(order) {
    await store.acceptOrder(order);
}
</script>

<style scoped>
.skeleton {
    background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
    background-size: 200% 100%;
    animation: skeleton-loading 1.5s infinite;
}
@keyframes skeleton-loading {
    0% { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}
</style>
