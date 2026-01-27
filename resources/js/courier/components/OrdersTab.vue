<template>
    <div>
        <!-- Statistics -->
        <div class="grid grid-cols-2 gap-3 mb-4">
            <div class="bg-white rounded-xl p-4 shadow-sm">
                <p class="text-gray-500 text-sm">Сегодня</p>
                <p class="text-2xl font-bold text-gray-800">{{ store.stats.todayOrders }}</p>
                <p class="text-xs text-gray-400">заказов</p>
            </div>
            <div class="bg-white rounded-xl p-4 shadow-sm">
                <p class="text-gray-500 text-sm">Заработок</p>
                <p class="text-2xl font-bold text-green-600">{{ store.formatMoney(store.stats.todayEarnings) }}</p>
                <p class="text-xs text-gray-400">за сегодня</p>
            </div>
        </div>

        <!-- Active Orders -->
        <div class="mb-4">
            <h2 class="text-lg font-semibold text-gray-800 mb-3">Активные заказы</h2>

            <!-- Loading skeleton -->
            <div v-if="store.isLoading && !store.myOrders.length" class="space-y-3">
                <div v-for="i in 2" :key="i" class="bg-white rounded-xl p-4 shadow-sm">
                    <div class="skeleton h-5 w-24 rounded mb-2"></div>
                    <div class="skeleton h-4 w-full rounded mb-1"></div>
                    <div class="skeleton h-4 w-2/3 rounded"></div>
                </div>
            </div>

            <!-- Orders list -->
            <div v-else-if="store.activeOrders.length" class="space-y-3">
                <div v-for="order in store.activeOrders" :key="order.id"
                     @click="store.selectedOrder = order"
                     class="bg-white rounded-xl shadow-sm overflow-hidden cursor-pointer active:bg-gray-50 transition-colors">
                    <div class="p-4">
                        <div class="flex items-start justify-between mb-2">
                            <div>
                                <span class="font-semibold text-gray-800">{{ order.order_number }}</span>
                                <span :class="['ml-2 px-2 py-0.5 text-xs rounded-full text-white', store.getStatusClass(order.delivery_status)]">
                                    {{ store.getStatusLabel(order.delivery_status) }}
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
                            <span>{{ order.customer_phone }}</span>
                            <span>{{ store.formatPaymentMethod(order.payment_method) }}</span>
                        </div>
                    </div>
                    <!-- Quick action buttons -->
                    <div class="flex border-t border-gray-100">
                        <button @click.stop="callCustomer(order)"
                                class="flex-1 py-3 text-sm text-purple-600 hover:bg-purple-50 transition-colors flex items-center justify-center gap-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                            </svg>
                            Позвонить
                        </button>
                        <button @click.stop="navigateTo(order)"
                                class="flex-1 py-3 text-sm text-purple-600 hover:bg-purple-50 transition-colors border-l border-gray-100 flex items-center justify-center gap-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
                            </svg>
                            Маршрут
                        </button>
                    </div>
                </div>
            </div>

            <!-- Empty state -->
            <div v-else class="bg-white rounded-xl p-8 text-center shadow-sm">
                <svg class="w-16 h-16 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                </svg>
                <p class="text-gray-500">Нет активных заказов</p>
                <p class="text-gray-400 text-sm mt-1">Возьмите заказ из вкладки "Доступные"</p>
            </div>
        </div>
    </div>
</template>

<script setup>
import { useCourierStore } from '../stores/courier';

const store = useCourierStore();

function callCustomer(order) {
    window.location.href = `tel:${order.customer_phone}`;
}

function navigateTo(order) {
    const address = store.formatFullAddress(order);
    const lat = order.delivery_lat;
    const lng = order.delivery_lng;

    let url;
    if (lat && lng) {
        url = `https://yandex.ru/maps/?pt=${lng},${lat}&z=17&l=map`;
    } else {
        url = `https://yandex.ru/maps/?text=${encodeURIComponent(address)}`;
    }

    window.open(url, '_blank');
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
