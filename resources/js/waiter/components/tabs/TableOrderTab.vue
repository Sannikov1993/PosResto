<template>
    <div class="h-full flex flex-col">
        <!-- Header -->
        <div class="flex-shrink-0 px-4 py-3 bg-dark-800 flex items-center gap-3 border-b border-gray-800">
            <button @click="$emit('back')" class="text-2xl">←</button>
            <div class="flex-1">
                <h2 class="font-bold">Стол {{ table?.number }}</h2>
                <p class="text-xs text-gray-500">{{ table?.seats }} мест</p>
            </div>
            <span v-if="order" :class="['px-3 py-1 rounded-full text-xs font-medium', statusClass]">
                {{ statusLabel }}
            </span>
        </div>

        <!-- Order Items -->
        <div class="flex-1 overflow-y-auto">
            <div v-if="order?.items?.length" class="p-4 space-y-2">
                <div v-for="item in order.items" :key="item.id"
                     class="bg-dark-800 rounded-xl p-3 flex items-center gap-3 relative">
                    <!-- Status bar -->
                    <div :class="['absolute left-0 top-0 bottom-0 w-1 rounded-l-xl', itemStatusClass(item)]"></div>

                    <div class="w-8 h-8 rounded-lg bg-orange-500/20 text-orange-400 flex items-center justify-center font-bold">
                        {{ item.quantity }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="font-medium truncate">{{ item.name }}</p>
                        <p class="text-sm text-gray-500">{{ formatMoney(item.price) }} ₽</p>
                    </div>
                    <button v-if="item.status === 'pending'"
                            @click="$emit('removeItem', item)"
                            class="text-red-400 p-2">
                        ✕
                    </button>
                </div>
            </div>

            <!-- Empty state -->
            <div v-else class="flex flex-col items-center justify-center h-full text-gray-500 p-4">
                <p class="text-4xl mb-4">📝</p>
                <p>Заказ пуст</p>
                <p class="text-sm mt-2">Добавьте блюда из меню</p>
            </div>
        </div>

        <!-- Menu Categories -->
        <div class="flex-shrink-0 border-t border-gray-800">
            <div class="px-4 py-2 flex gap-2 overflow-x-auto bg-dark-900">
                <button v-for="cat in categories" :key="cat.id"
                        @click="selectedCategory = cat.id"
                        :class="['px-3 py-2 rounded-xl text-sm whitespace-nowrap transition',
                                 selectedCategory === cat.id ? 'bg-orange-500 text-white' : 'bg-dark-800 text-gray-400']">
                    {{ cat.icon }} {{ cat.name }}
                </button>
            </div>

            <!-- Dishes -->
            <div class="h-32 overflow-y-auto p-2 bg-dark-800">
                <div class="grid grid-cols-2 gap-2">
                    <button v-for="dish in filteredDishes" :key="dish.id"
                            @click="$emit('addItem', dish)"
                            class="p-2 bg-dark-700 rounded-xl text-left active:bg-dark-600">
                        <p class="text-sm font-medium truncate">{{ dish.name }}</p>
                        <p class="text-xs text-orange-400">{{ formatMoney(dish.price) }} ₽</p>
                    </button>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="flex-shrink-0 p-4 bg-dark-800 border-t border-gray-800 safe-bottom">
            <div class="flex justify-between items-center mb-3">
                <span class="text-gray-400">Итого:</span>
                <span class="text-2xl font-bold text-orange-400">{{ formatMoney(order?.total || 0) }} ₽</span>
            </div>
            <div class="flex gap-2">
                <button @click="$emit('sendToKitchen')"
                        :disabled="!hasPendingItems"
                        :class="['flex-1 py-3 rounded-xl font-medium transition',
                                 hasPendingItems ? 'bg-blue-500 text-white' : 'bg-gray-700 text-gray-500']">
                    🍳 На кухню
                </button>
                <button @click="$emit('requestBill')"
                        :disabled="!order?.total"
                        :class="['flex-1 py-3 rounded-xl font-medium transition',
                                 order?.total ? 'bg-green-500 text-white' : 'bg-gray-700 text-gray-500']">
                    💳 Счёт
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
    table: { type: Object, default: null },
    order: { type: Object, default: null },
    categories: { type: Array, default: () => [] }
});

defineEmits(['back', 'addItem', 'removeItem', 'sendToKitchen', 'requestBill']);

const selectedCategory = ref(null);

const statusClass = computed(() => {
    const classes = {
        new: 'bg-blue-500/20 text-blue-400',
        confirmed: 'bg-blue-500/20 text-blue-400',
        cooking: 'bg-orange-500/20 text-orange-400',
        ready: 'bg-green-500/20 text-green-400'
    };
    return classes[props.order?.status] || 'bg-gray-500/20 text-gray-400';
});

const statusLabel = computed(() => {
    const labels = { new: 'Новый', confirmed: 'Подтверждён', cooking: 'Готовится', ready: 'Готов' };
    return labels[props.order?.status] || props.order?.status;
});

const filteredDishes = computed(() => {
    if (!selectedCategory.value) {
        // Show first category's dishes by default
        const firstCat = props.categories[0];
        if (firstCat) {
            selectedCategory.value = firstCat.id;
            return firstCat.dishes || [];
        }
        return [];
    }
    const cat = props.categories.find(c => c.id === selectedCategory.value);
    return cat?.dishes || [];
});

const hasPendingItems = computed(() => {
    return props.order?.items?.some(i => i.status === 'pending');
});

const itemStatusClass = (item) => {
    const classes = {
        pending: 'bg-purple-500',
        cooking: 'bg-orange-500',
        ready: 'bg-green-500',
        served: 'bg-gray-500'
    };
    return classes[item.status] || 'bg-gray-500';
};

const formatMoney = (n) => Math.floor(n || 0).toLocaleString('ru-RU');
</script>
