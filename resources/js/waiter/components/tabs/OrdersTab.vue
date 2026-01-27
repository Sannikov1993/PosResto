<template>
    <div class="h-full flex flex-col">
        <!-- Filters -->
        <div class="flex-shrink-0 px-4 py-2 flex gap-2 overflow-x-auto bg-dark-900">
            <button v-for="f in filters" :key="f.value"
                    @click="$emit('changeFilter', f.value)"
                    :class="['px-4 py-2 rounded-xl text-sm font-medium whitespace-nowrap transition',
                             filter === f.value ? 'bg-orange-500 text-white' : 'bg-dark-800 text-gray-400']">
                {{ f.label }}
                <span v-if="getCount(f.value)" class="ml-1 opacity-75">({{ getCount(f.value) }})</span>
            </button>
        </div>

        <!-- Orders List -->
        <div class="flex-1 p-4 overflow-y-auto space-y-3">
            <div v-for="order in orders" :key="order.id"
                 @click="$emit('selectOrder', order)"
                 class="bg-dark-800 rounded-2xl p-4 active:bg-dark-700 transition">
                <div class="flex justify-between items-start mb-2">
                    <div>
                        <p class="font-bold text-lg">#{{ order.order_number }}</p>
                        <p class="text-sm text-gray-400">
                            {{ order.table ? 'Стол ' + order.table.number : getTypeLabel(order.type) }}
                        </p>
                    </div>
                    <span :class="['px-3 py-1 rounded-full text-xs font-medium', statusClass(order.status)]">
                        {{ statusLabel(order.status) }}
                    </span>
                </div>

                <div class="text-sm text-gray-500 mb-2">
                    {{ order.items?.length || 0 }} позиций
                </div>

                <div class="flex justify-between items-center">
                    <span class="text-gray-400 text-sm">{{ formatTime(order.created_at) }}</span>
                    <span class="font-bold text-orange-400">{{ formatMoney(order.total) }} ₽</span>
                </div>
            </div>

            <div v-if="!orders.length" class="flex flex-col items-center justify-center h-full text-gray-500">
                <p class="text-4xl mb-4">📋</p>
                <p>Нет заказов</p>
            </div>
        </div>
    </div>
</template>

<script setup>
const props = defineProps({
    orders: { type: Array, default: () => [] },
    filter: { type: String, default: 'active' }
});

defineEmits(['changeFilter', 'selectOrder']);

const filters = [
    { value: 'active', label: 'Активные' },
    { value: 'new', label: 'Новые' },
    { value: 'cooking', label: 'Готовятся' },
    { value: 'ready', label: 'Готовы' }
];

const getCount = (status) => {
    if (status === 'active') {
        return props.orders.filter(o => ['confirmed', 'cooking', 'ready'].includes(o.status)).length;
    }
    return props.orders.filter(o => o.status === status).length;
};

const getTypeLabel = (type) => {
    const labels = { dine_in: 'В зале', delivery: 'Доставка', pickup: 'Самовывоз' };
    return labels[type] || type;
};

const statusClass = (status) => {
    const classes = {
        new: 'bg-blue-500/20 text-blue-400',
        confirmed: 'bg-blue-500/20 text-blue-400',
        cooking: 'bg-orange-500/20 text-orange-400',
        ready: 'bg-green-500/20 text-green-400',
        served: 'bg-purple-500/20 text-purple-400'
    };
    return classes[status] || 'bg-gray-500/20 text-gray-400';
};

const statusLabel = (status) => {
    const labels = {
        new: 'Новый',
        confirmed: 'Подтверждён',
        cooking: 'Готовится',
        ready: 'Готов',
        served: 'Выдан'
    };
    return labels[status] || status;
};

const formatTime = (dateStr) => {
    if (!dateStr) return '';
    return new Date(dateStr).toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' });
};

const formatMoney = (n) => Math.floor(n || 0).toLocaleString('ru-RU');
</script>
