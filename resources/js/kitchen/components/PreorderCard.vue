<template>
    <div :class="['rounded-2xl p-4 slide-in border-2', cardClasses]">
        <!-- Order Header -->
        <div class="flex justify-between items-start mb-4">
            <div>
                <p :class="['text-4xl font-extrabold', textColorClass]">#{{ order.order_number }}</p>
                <p class="text-lg text-gray-400 mt-1">
                    {{ getTypeIcon(order.type) }}
                    <span v-if="order.type === 'preorder' && order.table" class="text-purple-400">
                        Бронь · {{ order.table.name || order.table.number }}
                    </span>
                    <span v-else-if="order.table">Стол {{ order.table.number }}</span>
                    <span v-else>{{ getTypeLabel(order.type) }}</span>
                </p>
            </div>
            <div class="text-right">
                <p class="text-sm text-gray-500">Доставить к</p>
                <p :class="['text-2xl font-bold', urgencyColorClass]">{{ scheduledTimeDisplay }}</p>
                <p :class="['font-bold mt-1', urgencyColorClass]">{{ timeUntilDisplay }}</p>
            </div>
        </div>

        <!-- Items -->
        <div class="space-y-2 mb-4">
            <div v-for="item in order.items" :key="item.id"
                 :class="['rounded-xl p-3 flex items-center justify-between', itemBgClass]">
                <div class="flex items-center gap-3">
                    <span :class="['text-white w-10 h-10 rounded-lg flex items-center justify-center text-xl font-bold', badgeColorClass]">
                        {{ item.quantity }}
                    </span>
                    <div class="flex-1">
                        <div class="flex items-center gap-2">
                            <p class="font-bold text-lg">{{ item.name }}</p>
                            <!-- Info button for recipe/photo -->
                            <button @click.stop="$emit('showDishInfo', item)"
                                    class="w-6 h-6 rounded-full bg-blue-500/30 hover:bg-blue-500/50 text-blue-300 flex items-center justify-center text-sm transition flex-shrink-0"
                                    title="Показать рецепт">
                                ℹ️
                            </button>
                            <span v-if="item.guest_number" class="text-xs bg-purple-500/30 text-purple-300 px-2 py-0.5 rounded">
                                Гость {{ item.guest_number }}
                            </span>
                        </div>
                        <p v-if="item.comment" class="text-sm text-yellow-400 italic">{{ item.comment }}</p>
                        <p v-if="item.notes" class="text-sm text-yellow-400">{{ item.notes }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Notes -->
        <div v-if="order.notes" class="bg-yellow-500/20 rounded-xl p-3 mb-4">
            <p class="text-yellow-400 font-medium">{{ order.notes }}</p>
        </div>

        <!-- Action Button -->
        <button @click="$emit('startCooking', order)"
                :class="['w-full py-4 rounded-xl text-xl font-bold transition flex items-center justify-center gap-2 cursor-pointer', buttonClasses]">
            {{ urgencyLevel === 'overdue' ? '⚠️ СРОЧНО!' : '⏰' }} ВЗЯТЬ В РАБОТУ
        </button>
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
    order: { type: Object, required: true }
});

defineEmits(['startCooking', 'showDishInfo']);

const getTypeIcon = (type) => ({ dine_in: '🍽️', delivery: '🛵', pickup: '🏃', preorder: '📅' }[type] || '📋');
const getTypeLabel = (type) => ({ dine_in: 'В зале', delivery: 'Доставка', pickup: 'Самовывоз', preorder: 'Бронь' }[type] || type);

// Parse scheduled_at without timezone conversion
const parseScheduledTime = (scheduledAt) => {
    if (!scheduledAt) return null;
    const match = scheduledAt.match(/(\d{4}-\d{2}-\d{2})[T ](\d{2}):(\d{2})/);
    if (!match) return null;
    return {
        date: match[1],
        hours: parseInt(match[2]),
        minutes: parseInt(match[3]),
        timeStr: `${match[2]}:${match[3]}`
    };
};

// Display scheduled time
const scheduledTimeDisplay = computed(() => {
    const parsed = parseScheduledTime(props.order.scheduled_at);
    return parsed ? parsed.timeStr : '--:--';
});

// Minutes until delivery
const minutesUntilDelivery = computed(() => {
    const parsed = parseScheduledTime(props.order.scheduled_at);
    if (!parsed) return null;

    const now = new Date();
    const todayStr = getLocalDateString(now);

    // If order is for a different date
    if (parsed.date !== todayStr) {
        // Future date - show large positive value
        if (parsed.date > todayStr) {
            return 9999;
        }
        // Past date - show as overdue
        return -9999;
    }

    const currentMinutes = now.getHours() * 60 + now.getMinutes();
    const targetMinutes = parsed.hours * 60 + parsed.minutes;
    return targetMinutes - currentMinutes;
});

// Display time until delivery
const timeUntilDisplay = computed(() => {
    const mins = minutesUntilDelivery.value;
    if (mins === null) return '';
    if (mins === 9999) return 'Завтра';
    if (mins <= -9999) return 'Просрочен';
    if (mins < 0) return `просрочен на ${Math.abs(mins)} мин`;
    if (mins === 0) return 'сейчас';
    if (mins < 60) return `через ${mins} мин`;
    const h = Math.floor(mins / 60);
    const m = mins % 60;
    return m > 0 ? `через ${h} ч ${m} мин` : `через ${h} ч`;
});

// Urgency level based on time
const urgencyLevel = computed(() => {
    const mins = minutesUntilDelivery.value;
    if (mins === null) return 'normal';
    if (mins < 0) return 'overdue';
    if (mins <= 30) return 'urgent';
    if (mins <= 60) return 'warning';
    return 'normal';
});

// Card styling based on urgency
const cardClasses = computed(() => {
    switch (urgencyLevel.value) {
        case 'overdue':
            return 'bg-red-500/20 border-red-500 animate-pulse';
        case 'urgent':
            return 'bg-red-500/10 border-red-500';
        case 'warning':
            return 'bg-yellow-500/10 border-yellow-500';
        default:
            return 'bg-amber-500/10 border-amber-500';
    }
});

const textColorClass = computed(() => {
    switch (urgencyLevel.value) {
        case 'overdue':
        case 'urgent':
            return 'text-red-400';
        case 'warning':
            return 'text-yellow-400';
        default:
            return 'text-amber-400';
    }
});

const urgencyColorClass = computed(() => {
    switch (urgencyLevel.value) {
        case 'overdue':
        case 'urgent':
            return 'text-red-400';
        case 'warning':
            return 'text-yellow-400';
        default:
            return 'text-green-400';
    }
});

const itemBgClass = computed(() => 'bg-gray-800');

const badgeColorClass = computed(() => {
    switch (urgencyLevel.value) {
        case 'overdue':
        case 'urgent':
            return 'bg-red-500';
        case 'warning':
            return 'bg-yellow-500';
        default:
            return 'bg-amber-500';
    }
});

const buttonClasses = computed(() => {
    switch (urgencyLevel.value) {
        case 'overdue':
        case 'urgent':
            return 'bg-red-500 hover:bg-red-600';
        case 'warning':
            return 'bg-yellow-500 hover:bg-yellow-600 text-gray-900';
        default:
            return 'bg-amber-500 hover:bg-amber-600';
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
</style>
