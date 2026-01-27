<template>
    <div :class="[
        'border-2 rounded-2xl slide-in',
        compact ? 'p-3' : 'p-4',
        urgencyClass,
        order.isNew ? 'shake' : ''
    ]">
        <!-- Urgency indicator bar -->
        <div v-if="waitMinutes >= 5" :class="['h-1.5 -mx-3 -mt-3 mb-3 rounded-t-xl', compact ? '' : '-mx-4 -mt-4', urgencyBarClass]"></div>

        <!-- COMPACT MODE -->
        <template v-if="compact">
            <div class="flex items-center justify-between gap-3">
                <!-- Order number & type -->
                <div class="flex items-center gap-3">
                    <p :class="['text-4xl font-black', urgencyTextClass]">#{{ order.order_number }}</p>
                    <span class="text-2xl">{{ getTypeIcon(order.type) }}</span>
                    <span v-if="order.table" class="text-lg text-gray-400">
                        Стол {{ order.table.number }}
                    </span>
                </div>
                <!-- Items count & wait time -->
                <div class="flex items-center gap-4">
                    <div class="bg-gray-700 px-3 py-1 rounded-lg">
                        <span class="text-2xl font-bold text-white">{{ order.items.length }}</span>
                        <span class="text-gray-400 ml-1">поз.</span>
                    </div>
                    <p :class="['text-xl font-bold', waitTimeClass]">{{ getWaitTime(order.created_at) }}</p>
                </div>
            </div>
            <!-- Compact items preview -->
            <div class="mt-2 flex flex-wrap gap-2">
                <span v-for="item in order.items.slice(0, 4)" :key="item.id"
                      class="bg-gray-700 px-3 py-1 rounded-lg text-lg flex items-center gap-2">
                    <span class="text-xl">{{ getCategoryIcon(item.dish?.category?.name) }}</span>
                    <span :class="['font-bold', quantityBadgeClass.replace('bg-', 'text-')]">{{ item.quantity }}×</span>
                    <span class="text-gray-200 truncate max-w-32">{{ item.name }}</span>
                </span>
                <span v-if="order.items.length > 4" class="bg-gray-600 px-3 py-1 rounded-lg text-lg text-gray-300">
                    +{{ order.items.length - 4 }}
                </span>
            </div>
            <!-- Compact action button -->
            <button @click="$emit('startCooking', order)"
                    :class="['w-full py-3 mt-3 rounded-xl text-xl font-bold transition flex items-center justify-center gap-2 cursor-pointer', buttonClass]">
                👨‍🍳 ВЗЯТЬ
            </button>
        </template>

        <!-- FULL MODE -->
        <template v-else>
            <!-- Order Header -->
            <div class="flex justify-between items-start mb-4">
                <div>
                    <p :class="['text-5xl font-black', urgencyTextClass]">#{{ order.order_number }}</p>
                    <p class="text-xl text-gray-400 mt-1">
                        {{ getTypeIcon(order.type) }}
                        <span v-if="order.type === 'preorder' && order.table" class="text-purple-400">
                            Бронь · {{ order.table.name || order.table.number }}
                        </span>
                        <span v-else-if="order.table">Стол {{ order.table.number }}</span>
                        <span v-else>{{ getTypeLabel(order.type) }}</span>
                    </p>
                </div>
                <div class="text-right">
                    <p class="text-sm text-gray-500">Поступил</p>
                    <p class="text-2xl font-bold">{{ formatTime(order.created_at) }}</p>
                    <p :class="['text-lg font-bold mt-1', waitTimeClass]">{{ getWaitTime(order.created_at) }}</p>
                </div>
            </div>

            <!-- Items count indicator -->
            <div class="flex items-center gap-2 mb-3 bg-gray-700/50 rounded-lg px-3 py-2">
                <span class="text-gray-400">Позиций:</span>
                <span class="text-2xl font-bold text-white">{{ order.items.length }}</span>
            </div>

            <!-- Items -->
            <div class="space-y-2 mb-4">
                <div v-for="item in order.items" :key="item.id"
                     class="bg-gray-800 rounded-xl p-4 flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <!-- Category icon + quantity -->
                        <div :class="['w-14 h-14 rounded-xl flex flex-col items-center justify-center text-white', quantityBadgeClass]">
                            <span class="text-xl">{{ getCategoryIcon(item.dish?.category?.name) }}</span>
                            <span class="text-lg font-black">×{{ item.quantity }}</span>
                        </div>
                        <div class="flex-1">
                            <div class="flex items-center gap-2">
                                <p class="font-bold text-xl text-white">{{ item.name }}</p>
                                <!-- Info button for recipe/photo -->
                                <button @click.stop="$emit('showDishInfo', item)"
                                        class="w-7 h-7 rounded-full bg-blue-500/30 hover:bg-blue-500/50 text-blue-300 flex items-center justify-center text-base transition flex-shrink-0"
                                        title="Показать рецепт">
                                    ℹ️
                                </button>
                                <span v-if="item.guest_number" class="text-sm bg-purple-500/30 text-purple-300 px-2 py-1 rounded font-medium">
                                    Гость {{ item.guest_number }}
                                </span>
                            </div>
                            <!-- Модификаторы -->
                            <div v-if="item.modifiers?.length" class="mt-1 space-y-0.5">
                                <p v-for="mod in item.modifiers" :key="mod.option_id || mod.id"
                                   class="text-base text-blue-300 font-medium">
                                    + {{ mod.option_name || mod.name }}
                                </p>
                            </div>
                            <p v-if="item.comment" class="text-base text-yellow-400 italic mt-1">💬 {{ item.comment }}</p>
                            <p v-if="item.notes" class="text-base text-yellow-400 mt-1">📝 {{ item.notes }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Notes -->
            <div v-if="order.notes" class="bg-yellow-500/20 rounded-xl p-4 mb-4">
                <p class="text-yellow-400 font-bold text-lg">📝 {{ order.notes }}</p>
            </div>

            <!-- Action Button -->
            <button @click="$emit('startCooking', order)"
                    :class="['w-full py-5 rounded-xl text-2xl font-black transition flex items-center justify-center gap-2 cursor-pointer', buttonClass]">
                👨‍🍳 ВЗЯТЬ В РАБОТУ
            </button>
        </template>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    order: { type: Object, required: true },
    compact: { type: Boolean, default: false }
});

defineEmits(['startCooking', 'showDishInfo']);

const getTypeIcon = (type) => ({ dine_in: '🍽️', delivery: '🛵', pickup: '🏃', preorder: '📅' }[type] || '📋');
const getTypeLabel = (type) => ({ dine_in: 'В зале', delivery: 'Доставка', pickup: 'Самовывоз', preorder: 'Бронь' }[type] || type);

// Category icons mapping
const getCategoryIcon = (categoryName) => {
    if (!categoryName) return '🍽️';
    const name = categoryName.toLowerCase();
    if (name.includes('пицц')) return '🍕';
    if (name.includes('салат')) return '🥗';
    if (name.includes('суп')) return '🍲';
    if (name.includes('мяс') || name.includes('стейк') || name.includes('гриль')) return '🥩';
    if (name.includes('рыб') || name.includes('море')) return '🐟';
    if (name.includes('паст') || name.includes('макарон')) return '🍝';
    if (name.includes('бургер')) return '🍔';
    if (name.includes('десерт') || name.includes('торт') || name.includes('пирог')) return '🍰';
    if (name.includes('напит') || name.includes('кофе') || name.includes('чай')) return '☕';
    if (name.includes('завтрак')) return '🍳';
    if (name.includes('суши') || name.includes('ролл')) return '🍣';
    if (name.includes('закуск')) return '🥟';
    if (name.includes('гарнир')) return '🍚';
    if (name.includes('соус')) return '🫙';
    return '🍽️';
};

const formatTime = (dateStr) => {
    if (!dateStr) return '';
    return new Date(dateStr).toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' });
};

const getWaitTime = (dateStr) => {
    if (!dateStr) return '';
    const diff = Math.floor((new Date() - new Date(dateStr)) / 60000);
    if (diff < 1) return 'только что';
    if (diff < 60) return `${diff} мин`;
    return `${Math.floor(diff / 60)} ч ${diff % 60} мин`;
};

// Время ожидания в минутах
const waitMinutes = computed(() => {
    if (!props.order.created_at) return 0;
    return Math.floor((new Date() - new Date(props.order.created_at)) / 60000);
});

// Уровень срочности: normal (0-5мин), warning (5-10мин), urgent (10-15мин), critical (15+мин)
const urgencyLevel = computed(() => {
    const mins = waitMinutes.value;
    if (mins < 5) return 'normal';
    if (mins < 10) return 'warning';
    if (mins < 15) return 'urgent';
    return 'critical';
});

// Класс рамки и фона карточки
const urgencyClass = computed(() => {
    switch (urgencyLevel.value) {
        case 'warning': return 'bg-yellow-500/10 border-yellow-500';
        case 'urgent': return 'bg-orange-500/10 border-orange-500';
        case 'critical': return 'bg-red-500/10 border-red-500 pulse';
        default: return 'bg-blue-500/10 border-blue-500';
    }
});

// Класс верхней полоски индикатора
const urgencyBarClass = computed(() => {
    switch (urgencyLevel.value) {
        case 'warning': return 'bg-yellow-500';
        case 'urgent': return 'bg-orange-500';
        case 'critical': return 'bg-red-500';
        default: return 'bg-blue-500';
    }
});

// Класс текста номера заказа
const urgencyTextClass = computed(() => {
    switch (urgencyLevel.value) {
        case 'warning': return 'text-yellow-400';
        case 'urgent': return 'text-orange-400';
        case 'critical': return 'text-red-400';
        default: return 'text-blue-400';
    }
});

// Класс текста времени ожидания
const waitTimeClass = computed(() => {
    switch (urgencyLevel.value) {
        case 'warning': return 'text-yellow-400';
        case 'urgent': return 'text-orange-400';
        case 'critical': return 'text-red-400 animate-pulse';
        default: return 'text-green-400';
    }
});

// Класс бейджа количества
const quantityBadgeClass = computed(() => {
    switch (urgencyLevel.value) {
        case 'warning': return 'bg-yellow-500';
        case 'urgent': return 'bg-orange-500';
        case 'critical': return 'bg-red-500';
        default: return 'bg-blue-500';
    }
});

// Класс кнопки
const buttonClass = computed(() => {
    switch (urgencyLevel.value) {
        case 'warning': return 'bg-yellow-500 hover:bg-yellow-600 text-gray-900';
        case 'urgent': return 'bg-orange-500 hover:bg-orange-600 text-white';
        case 'critical': return 'bg-red-500 hover:bg-red-600 text-white';
        default: return 'bg-blue-500 hover:bg-blue-600 text-white';
    }
});
</script>

<style scoped>
.slide-in {
    animation: slideIn 0.3s ease-out;
}
@keyframes slideIn {
    from { opacity: 0; transform: translateY(-20px); }
    to { opacity: 1; transform: translateY(0); }
}
.shake {
    animation: shake 0.5s ease-in-out;
}
@keyframes shake {
    0%, 100% { transform: translateX(0); }
    25% { transform: translateX(-5px); }
    75% { transform: translateX(5px); }
}
.pulse {
    animation: pulse 1.5s infinite;
}
@keyframes pulse {
    0%, 100% { opacity: 1; box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.4); }
    50% { opacity: 0.85; box-shadow: 0 0 0 10px rgba(239, 68, 68, 0); }
}
</style>
