<template>
    <div :class="['border-2 rounded-2xl', compact ? 'p-3' : 'p-4', urgencyClass]">
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
                    <p class="text-4xl font-black text-orange-400">#{{ order.order_number }}</p>
                    <span class="text-2xl">{{ getTypeIcon(order.type) }}</span>
                    <span v-if="order.table" class="text-lg text-gray-400">
                        Стол {{ order.table.number }}
                    </span>
                </div>
                <!-- Progress & time -->
                <div class="flex items-center gap-4">
                    <!-- Progress indicator -->
                    <div class="flex items-center gap-2 bg-gray-700 px-3 py-1 rounded-lg">
                        <span class="text-green-400 text-xl font-bold">{{ doneCount }}</span>
                        <span class="text-gray-500">/</span>
                        <span class="text-white text-xl font-bold">{{ order.items.length }}</span>
                    </div>
                    <p :class="['text-xl font-bold', cookingTimeColor]">{{ cookingTime }}</p>
                </div>
            </div>
            <!-- Progress bar -->
            <div class="mt-2 h-2 bg-gray-700 rounded-full overflow-hidden">
                <div class="h-full bg-green-500 transition-all duration-300" :style="{ width: progress + '%' }"></div>
            </div>
            <!-- Compact items -->
            <div class="mt-2 flex flex-wrap gap-2">
                <span v-for="item in order.items" :key="item.id"
                      :class="['px-3 py-1 rounded-lg text-lg flex items-center gap-2 transition',
                               item.done ? 'bg-green-500/30 line-through opacity-60' : 'bg-gray-700']">
                    <span class="text-xl">{{ getCategoryIcon(item.dish?.category?.name) }}</span>
                    <span class="font-bold text-orange-400">{{ item.quantity }}×</span>
                    <span class="text-gray-200 truncate max-w-28">{{ item.name }}</span>
                </span>
            </div>
            <!-- Compact buttons -->
            <div class="flex gap-2 mt-3">
                <button @click="$emit('returnToNew', order)"
                        class="px-4 py-3 rounded-xl text-lg font-bold bg-gray-700 hover:bg-gray-600 text-gray-300">
                    ↩️
                </button>
                <button @click="$emit('markReady', order)"
                        class="flex-1 py-3 rounded-xl text-xl font-bold bg-green-500 hover:bg-green-600 text-white">
                    ✅ ГОТОВО
                </button>
            </div>
        </template>

        <!-- FULL MODE -->
        <template v-else>
            <!-- Order Header -->
            <div class="flex justify-between items-start mb-4">
                <div>
                    <p class="text-5xl font-black text-orange-400">#{{ order.order_number }}</p>
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
                    <p class="text-sm text-gray-500">В работе</p>
                    <p :class="['text-3xl font-bold', cookingTimeColor]">{{ cookingTime }}</p>
                </div>
            </div>

            <!-- Progress indicator -->
            <div class="flex items-center gap-3 mb-4 bg-gray-700/50 rounded-xl p-3">
                <div class="flex-1">
                    <div class="flex justify-between items-center mb-1">
                        <span class="text-gray-400 text-lg">Прогресс</span>
                        <span class="text-xl font-bold">
                            <span class="text-green-400">{{ doneCount }}</span>
                            <span class="text-gray-500"> / </span>
                            <span class="text-white">{{ order.items.length }}</span>
                        </span>
                    </div>
                    <div class="h-3 bg-gray-700 rounded-full overflow-hidden">
                        <div class="h-full bg-green-500 transition-all duration-300" :style="{ width: progress + '%' }"></div>
                    </div>
                </div>
            </div>

            <!-- Items: click to toggle visual check, button to mark ready -->
            <div class="space-y-2 mb-4">
                <div v-for="item in order.items" :key="item.id"
                     :class="['rounded-xl p-4 transition',
                              item.done ? 'bg-green-500/20' : 'bg-gray-700']">
                    <div class="flex items-center gap-4">
                        <!-- Clickable area for visual toggle -->
                        <div @click="$emit('toggleItem', order, item)"
                             class="flex items-center gap-4 flex-1 min-w-0 cursor-pointer">
                            <!-- Category icon + quantity -->
                            <div :class="['w-14 h-14 rounded-xl flex flex-col items-center justify-center border-2 flex-shrink-0 transition',
                                          item.done ? 'bg-green-500 border-green-500 text-white' : 'border-orange-500 text-orange-500']">
                                <span class="text-xl">{{ item.done ? '✓' : getCategoryIcon(item.dish?.category?.name) }}</span>
                                <span v-if="!item.done" class="text-lg font-black">×{{ item.quantity }}</span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <p :class="['font-bold text-xl', item.done ? 'line-through opacity-50' : 'text-white']">
                                        {{ item.name }}
                                    </p>
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
                        <!-- Button to actually mark item ready (sends to API) -->
                        <button @click.stop="$emit('markItemReady', order, item)"
                                class="px-4 py-3 bg-green-600 hover:bg-green-500 rounded-xl text-lg font-bold transition flex-shrink-0"
                                title="Отправить в Готово">
                            ✅
                        </button>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex gap-2">
                <!-- Return Button -->
                <button @click="$emit('returnToNew', order)"
                        class="px-5 py-5 rounded-xl text-xl font-bold transition bg-gray-700 hover:bg-gray-600 text-gray-300 hover:text-white flex items-center justify-center gap-2">
                    ↩️ Вернуть
                </button>
                <!-- Mark All Ready Button -->
                <button @click="$emit('markReady', order)"
                        class="flex-1 py-5 rounded-xl text-2xl font-black transition flex items-center justify-center gap-2 bg-green-500 hover:bg-green-600 text-white cursor-pointer">
                    ✅ ВСЁ ГОТОВО
                </button>
            </div>
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
    itemDoneState: { type: Object, default: () => ({}) },
    compact: { type: Boolean, default: false }
});

defineEmits(['toggleItem', 'markReady', 'returnToNew', 'markItemReady', 'showDishInfo']);

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

// Progress calculation
const doneCount = computed(() => {
    return props.order.items?.filter(i => i.done).length || 0;
});

const progress = computed(() => {
    if (!props.order.items || props.order.items.length === 0) return 0;
    return Math.round((doneCount.value / props.order.items.length) * 100);
});

const cookingTime = computed(() => {
    const startTime = props.order.cooking_started_at || props.order.updated_at;
    if (!startTime) return '0:00';
    const diff = Math.floor((new Date() - new Date(startTime)) / 1000);
    const mins = Math.floor(diff / 60);
    const secs = diff % 60;
    return `${mins}:${secs.toString().padStart(2, '0')}`;
});

const cookingTimeColor = computed(() => {
    const startTime = props.order.cooking_started_at || props.order.updated_at;
    if (!startTime) return 'text-white';
    const diff = Math.floor((new Date() - new Date(startTime)) / 60000);
    if (diff < 10) return 'text-green-400';
    if (diff < 20) return 'text-yellow-400';
    return 'text-red-400';
});

const urgencyClass = computed(() => {
    const startTime = props.order.cooking_started_at || props.order.updated_at;
    if (!startTime) return 'bg-orange-500/10 border-orange-500';
    const diff = Math.floor((new Date() - new Date(startTime)) / 60000);
    if (diff < 10) return 'bg-orange-500/10 border-orange-500';
    if (diff < 20) return 'bg-yellow-500/10 border-yellow-500';
    return 'bg-red-500/10 border-red-500 pulse';
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
