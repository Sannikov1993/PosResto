<template>
    <div :class="['bg-green-500/10 border-2 border-green-500 rounded-2xl pulse', compact ? 'p-3' : 'p-4']">
        <!-- Preorder Badge -->
        <div v-if="isPreorder" :class="['flex items-center gap-2 px-3 py-2 rounded-lg mb-3 text-base font-medium', preorderBadgeClass]">
            <span>⏰</span>
            <span>Предзаказ на {{ formattedScheduledTime }}</span>
            <span :class="['ml-auto', preorderTimeClass]">{{ preorderTimeLeft }}</span>
        </div>

        <!-- COMPACT MODE -->
        <template v-if="compact">
            <div class="flex items-center justify-between gap-3">
                <!-- Order number & type -->
                <div class="flex items-center gap-3">
                    <p class="text-4xl font-black text-green-400">#{{ order.order_number }}</p>
                    <span class="text-2xl">{{ getTypeIcon(order.type) }}</span>
                    <span v-if="order.table" class="text-lg text-gray-400">
                        Стол {{ order.table.number }}
                    </span>
                </div>
                <!-- Items count & wait time -->
                <div class="flex items-center gap-4">
                    <div class="bg-green-500/30 px-3 py-1 rounded-lg">
                        <span class="text-2xl font-bold text-green-400">{{ order.items?.length || 0 }}</span>
                        <span class="text-green-300/70 ml-1">поз.</span>
                    </div>
                    <p class="text-xl font-bold text-green-400">{{ getWaitTime(order.ready_at) }}</p>
                </div>
            </div>
            <!-- Status badge -->
            <div class="bg-green-500 text-white rounded-lg py-2 text-center mt-2">
                <p class="text-xl font-bold">🔔 ОЖИДАЕТ ВЫДАЧИ</p>
            </div>
            <!-- Compact buttons -->
            <div class="flex gap-2 mt-3">
                <button @click="$emit('returnToCooking', order)"
                        class="px-4 py-3 rounded-xl text-lg font-bold bg-gray-700 hover:bg-gray-600 text-gray-300">
                    ↩️
                </button>
                <button v-if="order.waiter"
                        @click="$emit('callWaiter', order)"
                        :disabled="waiterCalled"
                        :class="['flex-1 py-3 rounded-xl text-xl font-bold transition',
                                 waiterCalled ? 'bg-yellow-500/20 text-yellow-400' : 'bg-yellow-500 hover:bg-yellow-600 text-white']">
                    {{ waiterCalled ? '✅ ВЫЗВАН' : '📣 ВЫЗВАТЬ' }}
                </button>
            </div>
        </template>

        <!-- FULL MODE -->
        <template v-else>
            <!-- Order Header -->
            <div class="flex justify-between items-start mb-4">
                <div>
                    <p class="text-5xl font-black text-green-400">#{{ order.order_number }}</p>
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
                    <p class="text-sm text-gray-500">Готов</p>
                    <p class="text-3xl font-bold text-green-400">{{ getWaitTime(order.ready_at) }}</p>
                </div>
            </div>

            <!-- Items Summary -->
            <div class="bg-gray-800 rounded-xl p-4 mb-4">
                <div v-for="item in order.items" :key="item.id" class="py-2">
                    <div class="flex items-center gap-3">
                        <span class="text-2xl">{{ getCategoryIcon(item.dish?.category?.name) }}</span>
                        <span class="text-green-400 font-black text-xl">{{ item.quantity }}×</span>
                        <span class="text-xl text-white">{{ item.name }}</span>
                        <span v-if="item.guest_number" class="text-sm bg-purple-500/30 text-purple-300 px-2 py-1 rounded font-medium">
                            Г{{ item.guest_number }}
                        </span>
                    </div>
                    <!-- Модификаторы -->
                    <div v-if="item.modifiers?.length" class="ml-12 mt-1">
                        <p v-for="mod in item.modifiers" :key="mod.option_id || mod.id"
                           class="text-base text-blue-300 font-medium">
                            + {{ mod.option_name || mod.name }}
                        </p>
                    </div>
                    <p v-if="item.comment" class="text-base text-yellow-400 italic ml-12 mt-1">💬 {{ item.comment }}</p>
                </div>
            </div>

            <!-- Status & Actions -->
            <div class="bg-green-500 text-white rounded-xl py-5 text-center mb-4">
                <p class="text-3xl font-black">🔔 ОЖИДАЕТ ВЫДАЧИ</p>
            </div>

            <!-- Waiter info -->
            <div v-if="order.waiter" class="bg-gray-800 rounded-xl p-4 mb-4 flex items-center gap-4">
                <span class="text-3xl">👤</span>
                <div>
                    <p class="text-sm text-gray-500">Официант</p>
                    <p class="font-bold text-xl text-white">{{ order.waiter.name }}</p>
                </div>
            </div>

            <!-- Call Waiter Button -->
            <button v-if="order.waiter"
                    @click="$emit('callWaiter', order)"
                    :disabled="waiterCalled"
                    :class="[
                        'w-full py-5 rounded-xl text-2xl font-black transition flex items-center justify-center gap-2 mb-4',
                        waiterCalled
                            ? 'bg-yellow-500/20 text-yellow-400 cursor-not-allowed'
                            : 'bg-yellow-500 hover:bg-yellow-600 text-white cursor-pointer'
                    ]">
                <span>{{ waiterCalled ? '✅' : '📣' }}</span>
                {{ waiterCalled ? 'ОФИЦИАНТ ВЫЗВАН' : 'ВЫЗВАТЬ ОФИЦИАНТА' }}
            </button>

            <!-- Return Button -->
            <button @click="$emit('returnToCooking', order)"
                    class="w-full py-4 bg-gray-700 hover:bg-gray-600 rounded-xl text-xl font-bold transition flex items-center justify-center gap-2 text-gray-300 hover:text-white">
                ↩️ Вернуть в готовку
            </button>
        </template>
    </div>
</template>

<script setup>
import { computed } from 'vue';

// Helper для локальной даты (не UTC!)
const getLocalDateString = (date = new Date()) => {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
};

const props = defineProps({
    order: { type: Object, required: true },
    waiterCalled: { type: Boolean, default: false },
    compact: { type: Boolean, default: false }
});

defineEmits(['returnToCooking', 'callWaiter']);

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

const getWaitTime = (dateStr) => {
    if (!dateStr) return '';
    const diff = Math.floor((new Date() - new Date(dateStr)) / 60000);
    if (diff < 1) return 'только что';
    if (diff < 60) return `${diff} мин`;
    return `${Math.floor(diff / 60)} ч ${diff % 60} мин`;
};

// Preorder helpers
const parseScheduledTime = (scheduledAt) => {
    if (!scheduledAt) return null;
    const match = scheduledAt.match(/(\d{4}-\d{2}-\d{2})[T ](\d{2}):(\d{2})/);
    if (!match) return null;
    return { date: match[1], hours: parseInt(match[2]), minutes: parseInt(match[3]) };
};

const isPreorder = computed(() => props.order.scheduled_at && !props.order.is_asap);

const formattedScheduledTime = computed(() => {
    const parsed = parseScheduledTime(props.order.scheduled_at);
    if (!parsed) return '';
    return `${parsed.hours}:${parsed.minutes.toString().padStart(2, '0')}`;
});

const getMinutesUntil = () => {
    const parsed = parseScheduledTime(props.order.scheduled_at);
    if (!parsed) return null;
    const now = new Date();
    const todayStr = getLocalDateString(now);
    if (parsed.date !== todayStr) return parsed.date > todayStr ? 9999 : -9999;
    const currentMins = now.getHours() * 60 + now.getMinutes();
    const targetMins = parsed.hours * 60 + parsed.minutes;
    return targetMins - currentMins;
};

const preorderTimeLeft = computed(() => {
    const mins = getMinutesUntil();
    if (mins === null) return '';
    if (mins >= 9999) return 'завтра';
    if (mins <= -9999) return 'просрочен';
    if (mins < 0) return `просрочен ${Math.abs(mins)}м`;
    if (mins === 0) return 'сейчас';
    if (mins < 60) return `через ${mins}м`;
    const h = Math.floor(mins / 60);
    const m = mins % 60;
    return m > 0 ? `через ${h}ч ${m}м` : `через ${h}ч`;
});

const preorderBadgeClass = computed(() => {
    const mins = getMinutesUntil();
    if (mins === null) return 'bg-gray-700 text-gray-300';
    if (mins < 0) return 'bg-red-500/30 text-red-300';
    if (mins <= 30) return 'bg-red-500/20 text-red-400';
    if (mins <= 60) return 'bg-yellow-500/20 text-yellow-400';
    return 'bg-green-500/20 text-green-400';
});

const preorderTimeClass = computed(() => {
    const mins = getMinutesUntil();
    if (mins === null) return 'text-gray-400';
    if (mins < 0) return 'text-red-400 font-bold';
    if (mins <= 30) return 'text-red-400';
    if (mins <= 60) return 'text-yellow-400';
    return 'text-green-400';
});
</script>

<style scoped>
.pulse {
    animation: pulse 2s infinite;
}
@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.7; }
}
</style>
