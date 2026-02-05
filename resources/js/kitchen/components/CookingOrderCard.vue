<template>
    <div :class="[
        'rounded-lg overflow-hidden shadow-lg transition-all duration-300',
        'border-l-4 border-l-orange-500',
        cookingUrgencyClass
    ]">
        <!-- ═══════════════════════════════════════════════════════════
             HEADER - Номер заказа, тип, таймер
             ═══════════════════════════════════════════════════════════ -->
        <div class="bg-orange-500/10 px-3 py-2 sm:px-4 sm:py-3 flex items-center justify-between">
            <!-- Left: Type badge + table/details -->
            <div class="flex items-center gap-2 sm:gap-3">
                <!-- Order number - compact -->
                <span class="text-sm sm:text-base font-bold text-orange-400/70">
                    #{{ order.order_number }}
                </span>
                <!-- Order type badge - prominent -->
                <span class="px-2 sm:px-3 py-1 sm:py-1.5 rounded-lg text-sm sm:text-base font-bold bg-orange-500/20 text-orange-300">
                    {{ getTypeIcon(order.type) }} {{ getTypeLabel(order.type) }}
                </span>
                <!-- Table number - very prominent if exists -->
                <span v-if="order.table" class="text-xl sm:text-2xl font-black text-orange-400">
                    {{ order.table.number }}
                </span>
            </div>

            <!-- Right: Cooking Timer -->
            <div class="text-right">
                <div :class="['text-xl sm:text-2xl font-bold tabular-nums font-mono', cookingTimeColor]">
                    {{ cookingTime }}
                </div>
                <div class="text-xs text-gray-500 uppercase tracking-wide">готовится</div>
            </div>
        </div>

        <!-- ═══════════════════════════════════════════════════════════
             PROGRESS BAR
             ═══════════════════════════════════════════════════════════ -->
        <div class="bg-gray-800 px-3 py-2 sm:px-4 sm:py-2 border-b border-gray-700">
            <div class="flex items-center justify-between mb-1.5">
                <span class="text-sm text-gray-400">Прогресс</span>
                <span class="text-sm font-bold">
                    <span class="text-green-400">{{ doneCount }}</span>
                    <span class="text-gray-500"> / </span>
                    <span class="text-white">{{ order.items.length }}</span>
                </span>
            </div>
            <div class="h-2 bg-gray-700 rounded-full overflow-hidden">
                <div class="h-full bg-gradient-to-r from-green-500 to-green-400 transition-all duration-500"
                     :style="{ width: progress + '%' }">
                </div>
            </div>
        </div>

        <!-- ═══════════════════════════════════════════════════════════
             ITEMS TABLE - С интерактивными статусами
             ═══════════════════════════════════════════════════════════ -->
        <div class="bg-gray-800">
            <!-- Table Header -->
            <div class="grid grid-cols-[auto_auto_1fr_auto_auto] gap-2 px-3 py-1.5 sm:px-4 sm:py-2 bg-gray-750 border-b border-gray-700 text-xs sm:text-sm text-gray-500 uppercase tracking-wide">
                <div class="w-10 text-center">Статус</div>
                <div class="w-8 sm:w-10 text-center">Кол</div>
                <div>Блюдо</div>
                <div class="w-10 text-center">Инфо</div>
                <div class="w-12 text-center">Готово</div>
            </div>

            <!-- Items List -->
            <div class="divide-y divide-gray-700/50">
                <div v-for="(item, index) in order.items" :key="item.id"
                     @click="$emit('toggleItem', order, item)"
                     :class="[
                         'grid grid-cols-[auto_auto_1fr_auto_auto] gap-2 px-3 py-2 sm:px-4 sm:py-3 items-center cursor-pointer transition-colors',
                         item.done ? 'bg-green-500/10' : (index % 2 === 0 ? 'bg-gray-800' : 'bg-gray-800/50'),
                         'hover:bg-gray-700/50'
                     ]">
                    <!-- Status Indicator -->
                    <div class="w-10 flex justify-center">
                        <div :class="[
                            'w-3 h-3 rounded-full transition-all',
                            item.done ? 'bg-green-500 shadow-lg shadow-green-500/50' : 'bg-orange-500 animate-pulse'
                        ]"></div>
                    </div>

                    <!-- Quantity Badge -->
                    <div class="w-8 sm:w-10 flex justify-center">
                        <span :class="[
                            'inline-flex items-center justify-center w-7 h-7 sm:w-8 sm:h-8 rounded-lg text-sm sm:text-base font-bold transition-all',
                            item.done ? 'bg-green-500/20 text-green-400' : 'bg-orange-500/20 text-orange-400'
                        ]">
                            {{ item.quantity }}
                        </span>
                    </div>

                    <!-- Dish Info -->
                    <div class="min-w-0">
                        <div class="flex items-center gap-1.5 flex-wrap">
                            <span class="text-base sm:text-lg">{{ getCategoryIcon(item.dish?.category?.name) }}</span>
                            <span :class="[
                                'font-semibold text-sm sm:text-base transition-all',
                                item.done ? 'text-gray-500 line-through' : 'text-white'
                            ]">{{ item.name }}</span>
                            <span v-if="item.guest_number"
                                  class="text-xs bg-purple-500/30 text-purple-300 px-1.5 py-0.5 rounded font-medium">
                                Г{{ item.guest_number }}
                            </span>
                        </div>
                        <!-- Modifiers -->
                        <div v-if="item.modifiers?.length" class="mt-1 space-y-0.5">
                            <p v-for="mod in item.modifiers" :key="mod.option_id || mod.id"
                               :class="['text-xs sm:text-sm pl-5', item.done ? 'text-gray-600' : 'text-blue-400']">
                                + {{ mod.option_name || mod.name }}
                            </p>
                        </div>
                        <!-- Comment -->
                        <p v-if="item.comment"
                           :class="['text-xs sm:text-sm mt-1 pl-5', item.done ? 'text-gray-600' : 'text-yellow-400']">
                            💬 {{ item.comment }}
                        </p>
                    </div>

                    <!-- Info Button -->
                    <div class="w-10 flex justify-center">
                        <button @click.stop="$emit('showDishInfo', item)"
                                class="w-8 h-8 rounded-lg bg-gray-700 hover:bg-gray-600 active:bg-gray-500 text-gray-400 hover:text-white flex items-center justify-center transition"
                                title="Рецепт">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </button>
                    </div>

                    <!-- Ready Button -->
                    <div class="w-12 flex justify-center">
                        <button @click.stop="$emit('markItemReady', order, item)"
                                :class="[
                                    'w-10 h-10 rounded-lg flex items-center justify-center transition-all font-bold',
                                    item.done
                                        ? 'bg-green-500 text-white'
                                        : 'bg-gray-700 hover:bg-green-500 text-gray-400 hover:text-white'
                                ]"
                                :title="item.done ? 'Готово' : 'Отметить готовым'">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ═══════════════════════════════════════════════════════════
             FOOTER - Действия
             ═══════════════════════════════════════════════════════════ -->
        <div class="bg-gray-850 px-3 py-2 sm:px-4 sm:py-3 border-t border-gray-700">
            <div class="flex gap-2">
                <!-- Return Button -->
                <button @click="$emit('returnToNew', order)"
                        class="px-4 py-3 rounded-lg bg-gray-700 hover:bg-gray-600 active:bg-gray-500 text-gray-400 hover:text-white transition flex items-center justify-center gap-2 font-medium">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 17l-5-5m0 0l5-5m-5 5h12" />
                    </svg>
                    <span class="hidden sm:inline">Вернуть</span>
                </button>

                <!-- Mark All Ready Button -->
                <button @click="$emit('markReady', order)"
                        class="flex-1 py-3 sm:py-4 rounded-lg bg-green-500 hover:bg-green-400 active:bg-green-600 text-white font-bold text-base sm:text-lg transition-all flex items-center justify-center gap-2 active:scale-[0.98]">
                    <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    ВСЁ ГОТОВО
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { getOrderTypeIcon, getOrderTypeLabel, getCategoryIcon } from '../utils/format.js';

const props = defineProps({
    order: {
        type: Object,
        required: true,
        validator: (o) => o && typeof o.id !== 'undefined' && typeof o.order_number !== 'undefined' && Array.isArray(o.items),
    },
    itemDoneState: {
        type: Object,
        default: () => ({}),
    },
    compact: {
        type: Boolean,
        default: false,
    },
});

defineEmits(['toggleItem', 'markReady', 'returnToNew', 'markItemReady', 'showDishInfo']);

const getTypeIcon = getOrderTypeIcon;
const getTypeLabel = getOrderTypeLabel;

// Progress calculation
const doneCount = computed(() => {
    return props.order.items?.filter(i => i.done).length || 0;
});

const progress = computed(() => {
    if (!props.order.items || props.order.items.length === 0) return 0;
    return Math.round((doneCount.value / props.order.items.length) * 100);
});

// Cooking time
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

const cookingUrgencyClass = computed(() => {
    const startTime = props.order.cooking_started_at || props.order.updated_at;
    if (!startTime) return '';
    const diff = Math.floor((new Date() - new Date(startTime)) / 60000);
    if (diff >= 20) return 'ring-2 ring-red-500/50';
    return '';
});
</script>

<style scoped>
.bg-gray-750 {
    background-color: rgb(38, 42, 51);
}
.bg-gray-850 {
    background-color: rgb(24, 27, 33);
}
</style>
