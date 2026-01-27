<template>
    <div class="h-full flex flex-col overflow-hidden">
        <!-- Status Filter Bar -->
        <div class="px-4 py-3 bg-dark-800/50 border-b border-gray-700 flex items-center gap-2 flex-shrink-0">
            <span class="text-xs text-gray-500 uppercase mr-2">Статус:</span>

            <!-- All Button -->
            <button
                @click="selectedStatus = null"
                :class="[
                    'px-3 py-1.5 rounded-lg text-sm font-medium transition-all',
                    selectedStatus === null
                        ? 'bg-accent text-white'
                        : 'bg-dark-700 text-gray-400 hover:text-white hover:bg-dark-600'
                ]"
            >
                Все
                <span class="ml-1.5 px-1.5 py-0.5 bg-white/20 rounded text-xs">{{ orders.length }}</span>
            </button>

            <!-- Status Buttons -->
            <button
                v-for="status in statuses"
                :key="status.value"
                @click="selectedStatus = status.value"
                :class="[
                    'px-3 py-1.5 rounded-lg text-sm font-medium transition-all flex items-center gap-2',
                    selectedStatus === status.value
                        ? status.activeClass
                        : 'bg-dark-700 text-gray-400 hover:text-white hover:bg-dark-600'
                ]"
            >
                <span :class="['w-2 h-2 rounded-full', status.dotClass]"></span>
                {{ status.label }}
                <span
                    v-if="getStatusCount(status.value) > 0"
                    :class="[
                        'px-1.5 py-0.5 rounded text-xs',
                        selectedStatus === status.value ? 'bg-white/20' : 'bg-dark-600'
                    ]"
                >
                    {{ getStatusCount(status.value) }}
                </span>
            </button>
        </div>

        <!-- Empty State -->
        <div
            v-if="filteredOrders.length === 0"
            class="flex-1 flex flex-col items-center justify-center text-gray-500"
        >
            <svg class="w-16 h-16 mb-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0" />
            </svg>
            <p class="text-lg font-medium mb-1">Нет заказов</p>
            <p class="text-sm">
                {{ selectedStatus ? 'Нет заказов с выбранным статусом' : 'Создайте новый заказ или измените фильтры' }}
            </p>
            <button
                v-if="selectedStatus"
                @click="selectedStatus = null"
                class="mt-4 px-4 py-2 bg-dark-700 hover:bg-dark-600 rounded-lg text-sm text-gray-300 transition-colors"
            >
                Показать все заказы
            </button>
        </div>

        <!-- Table -->
        <div v-else class="flex-1 overflow-auto">
            <table class="w-full">
                <thead class="bg-dark-800 sticky top-0 z-10">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">
                            Заказ
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">
                            Клиент
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">
                            Адрес
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">
                            Статус
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">
                            Сумма
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">
                            Курьер
                        </th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-400 uppercase tracking-wider w-24">
                            Действия
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-dark-700">
                    <tr
                        v-for="order in filteredOrders"
                        :key="order.id"
                        @click="$emit('select-order', order)"
                        class="hover:bg-dark-800/50 cursor-pointer transition-colors"
                        :class="{ 'bg-dark-800': selectedOrderId === order.id }"
                    >
                        <!-- Order Number -->
                        <td class="px-4 py-4">
                            <div class="flex flex-col">
                                <span class="font-mono text-white">#{{ order.order_number || order.id }}</span>
                                <span class="text-xs text-gray-500">{{ formatTime(order.created_at) }}</span>
                            </div>
                        </td>

                        <!-- Customer -->
                        <td class="px-4 py-4">
                            <div class="flex flex-col">
                                <span class="font-medium text-white">{{ order.customer?.name || order.customer_name || 'Клиент' }}</span>
                                <span class="text-sm text-gray-400">{{ order.phone }}</span>
                            </div>
                        </td>

                        <!-- Address -->
                        <td class="px-4 py-4 max-w-xs">
                            <span v-if="order.type === 'delivery'" class="text-sm text-gray-300 line-clamp-2">
                                {{ order.delivery_address }}
                            </span>
                            <span v-else class="text-sm text-green-400">Самовывоз</span>
                        </td>

                        <!-- Status -->
                        <td class="px-4 py-4">
                            <span :class="['px-3 py-1 rounded-full text-xs font-medium', getStatusClass(order)]">
                                {{ getStatusLabel(order) }}
                            </span>
                        </td>

                        <!-- Price -->
                        <td class="px-4 py-4">
                            <div class="flex flex-col">
                                <span class="font-bold text-white">{{ formatPrice(order.total) }} ₽</span>
                                <span
                                    :class="[
                                        'text-xs',
                                        order.payment_status === 'paid' ? 'text-green-400' : 'text-red-400'
                                    ]"
                                >
                                    {{ order.payment_status === 'paid' ? 'Оплачен' : 'Не оплачен' }}
                                </span>
                            </div>
                        </td>

                        <!-- Courier -->
                        <td class="px-4 py-4">
                            <div v-if="order.courier" class="flex items-center gap-2">
                                <div class="w-8 h-8 rounded-full bg-accent flex items-center justify-center text-xs font-medium text-white">
                                    {{ order.courier.name?.charAt(0) || 'К' }}
                                </div>
                                <span class="text-sm text-gray-300">{{ order.courier.name?.split(' ')[0] }}</span>
                            </div>
                            <button
                                v-else-if="order.delivery_status === 'ready' && order.type === 'delivery'"
                                @click.stop="$emit('assign-courier', order)"
                                class="text-sm text-accent hover:text-blue-400 transition-colors"
                            >
                                + Назначить
                            </button>
                            <span v-else class="text-gray-600">—</span>
                        </td>

                        <!-- Actions -->
                        <td class="px-4 py-4">
                            <div class="flex items-center justify-center gap-1">
                                <!-- Quick status change buttons -->
                                <button
                                    v-if="order.delivery_status === 'pending'"
                                    @click.stop="$emit('status-change', { order, status: 'preparing' })"
                                    class="p-1.5 bg-orange-600/20 hover:bg-orange-600/40 rounded text-orange-400 transition-colors"
                                    title="На кухню"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" />
                                    </svg>
                                </button>
                                <button
                                    v-if="order.delivery_status === 'preparing'"
                                    @click.stop="$emit('status-change', { order, status: 'ready' })"
                                    class="p-1.5 bg-cyan-600/20 hover:bg-cyan-600/40 rounded text-cyan-400 transition-colors"
                                    title="Готов"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                </button>
                                <button
                                    v-if="order.delivery_status === 'in_transit' || order.delivery_status === 'picked_up'"
                                    @click.stop="$emit('status-change', { order, status: 'delivered' })"
                                    class="p-1.5 bg-green-600/20 hover:bg-green-600/40 rounded text-green-400 transition-colors"
                                    title="Доставлен"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                </button>
                                <button
                                    v-if="order.delivery_status === 'ready' && order.type === 'pickup'"
                                    @click.stop="$emit('status-change', { order, status: 'delivered' })"
                                    class="p-1.5 bg-green-600/20 hover:bg-green-600/40 rounded text-green-400 transition-colors"
                                    title="Выдан"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                </button>
                                <!-- Details button -->
                                <button class="p-1.5 text-gray-500 hover:text-white hover:bg-dark-600 rounded transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                    </svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Summary Bar -->
        <div v-if="filteredOrders.length > 0" class="px-4 py-2 bg-dark-800 border-t border-gray-700 flex items-center justify-between text-sm flex-shrink-0">
            <span class="text-gray-400">
                Показано: <span class="text-white font-medium">{{ filteredOrders.length }}</span>
                <span v-if="selectedStatus"> из {{ orders.length }}</span>
            </span>
            <span class="text-gray-400">
                Сумма: <span class="text-green-400 font-medium">{{ formatPrice(totalSum) }} ₽</span>
            </span>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { formatAmount } from '@/utils/formatAmount.js';

const props = defineProps({
    orders: {
        type: Array,
        default: () => []
    },
    selectedOrderId: {
        type: [Number, String],
        default: null
    }
});

defineEmits(['select-order', 'assign-courier', 'status-change']);

// Selected status filter
const selectedStatus = ref(null);

// Status configurations (всегда показываем все, включая "Завершён")
const statuses = [
    { value: 'pending', label: 'Новый', dotClass: 'bg-blue-400', activeClass: 'bg-blue-600 text-white' },
    { value: 'preparing', label: 'Готовится', dotClass: 'bg-orange-400', activeClass: 'bg-orange-600 text-white' },
    { value: 'ready', label: 'Готов', dotClass: 'bg-cyan-400', activeClass: 'bg-cyan-600 text-white' },
    { value: 'in_transit', label: 'В пути', dotClass: 'bg-purple-400', activeClass: 'bg-purple-600 text-white' },
    { value: 'delivered', label: 'Доставлен', dotClass: 'bg-green-400', activeClass: 'bg-green-600 text-white' },
    { value: 'completed', label: 'Завершён', dotClass: 'bg-gray-400', activeClass: 'bg-gray-600 text-white' }
];

// Filtered orders
const filteredOrders = computed(() => {
    if (!selectedStatus.value) return props.orders;

    if (selectedStatus.value === 'in_transit') {
        return props.orders.filter(o =>
            o.delivery_status === 'in_transit' || o.delivery_status === 'picked_up'
        );
    }

    // Доставлен - только НЕоплаченные
    if (selectedStatus.value === 'delivered') {
        return props.orders.filter(o =>
            o.delivery_status === 'delivered' && o.payment_status !== 'paid'
        );
    }

    // Завершён - доставленные И оплаченные
    if (selectedStatus.value === 'completed') {
        return props.orders.filter(o =>
            o.delivery_status === 'delivered' && o.payment_status === 'paid'
        );
    }

    return props.orders.filter(o => o.delivery_status === selectedStatus.value);
});

// Get count for each status
const getStatusCount = (status) => {
    if (status === 'in_transit') {
        return props.orders.filter(o =>
            o.delivery_status === 'in_transit' || o.delivery_status === 'picked_up'
        ).length;
    }

    if (status === 'delivered') {
        return props.orders.filter(o =>
            o.delivery_status === 'delivered' && o.payment_status !== 'paid'
        ).length;
    }

    if (status === 'completed') {
        return props.orders.filter(o =>
            o.delivery_status === 'delivered' && o.payment_status === 'paid'
        ).length;
    }

    return props.orders.filter(o => o.delivery_status === status).length;
};

// Total sum of filtered orders
const totalSum = computed(() => {
    return filteredOrders.value.reduce((sum, o) => sum + (o.total || 0), 0);
});

// Status config for table display
const statusConfig = {
    pending: { label: 'Новый', class: 'bg-blue-500/20 text-blue-400 border border-blue-500/30' },
    preparing: { label: 'Готовится', class: 'bg-orange-500/20 text-orange-400 border border-orange-500/30' },
    ready: { label: 'Готов', class: 'bg-cyan-500/20 text-cyan-400 border border-cyan-500/30' },
    picked_up: { label: 'Забран', class: 'bg-purple-500/20 text-purple-400 border border-purple-500/30' },
    in_transit: { label: 'В пути', class: 'bg-purple-500/20 text-purple-400 border border-purple-500/30' },
    delivered: { label: 'Доставлен', class: 'bg-green-500/20 text-green-400 border border-green-500/30' },
    completed: { label: 'Завершён', class: 'bg-gray-500/20 text-gray-400 border border-gray-500/30' },
    cancelled: { label: 'Отменён', class: 'bg-red-500/20 text-red-400 border border-red-500/30' }
};

// Get display status (considering completed = delivered + paid)
const getStatusClass = (order) => {
    if (order.delivery_status === 'delivered' && order.payment_status === 'paid') {
        return statusConfig.completed.class;
    }
    return statusConfig[order.delivery_status]?.class || 'bg-gray-500/20 text-gray-400';
};

const getStatusLabel = (order) => {
    if (order.delivery_status === 'delivered' && order.payment_status === 'paid') {
        return statusConfig.completed.label;
    }
    return statusConfig[order.delivery_status]?.label || order.delivery_status;
};

const formatPrice = (price) => formatAmount(price).toLocaleString('ru-RU');

const formatTime = (dt) => {
    if (!dt) return '';
    return new Date(dt).toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' });
};
</script>

<style scoped>
.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>
