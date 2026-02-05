<template>
    <div
        @click="$emit('click', order)"
        :draggable="draggable"
        @dragstart="onDragStart"
        @dragend="onDragEnd"
        :data-testid="`delivery-order-${order.id}`"
        class="rounded-xl cursor-pointer transition-all border-l-4 overflow-hidden"
        :class="[
            statusBorderColor,
            { 'ring-2 ring-accent': selected },
            { 'bg-dark-800 hover:bg-dark-750': !isUrgent },
            { 'bg-red-900/30 hover:bg-red-900/40 animate-pulse-subtle': isUrgent },
            { 'opacity-50': isDragging },
            compact ? 'p-2' : ''
        ]"
    >
        <!-- Compact Mode -->
        <template v-if="compact">
            <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-2 min-w-0">
                    <span class="text-xs font-mono text-gray-400">#{{ order.order_number || order.id }}</span>
                    <span class="font-medium text-white text-sm truncate">{{ order.customer?.name || order.customer_name || 'Клиент' }}</span>
                </div>
                <div class="flex items-center gap-2 flex-shrink-0">
                    <span class="text-sm font-bold text-white">{{ formatPrice(order.total) }} ₽</span>
                    <span :class="['text-xs', timeAgoClass]">{{ timeAgo }}</span>
                </div>
            </div>
        </template>

        <!-- Full Mode -->
        <template v-else>
            <!-- Header -->
            <div class="px-4 pt-3 pb-2 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="text-sm font-mono text-gray-400">#{{ order.order_number || order.id }}</span>
                    <span :class="['px-2 py-0.5 rounded text-xs font-medium', statusClass]">
                        {{ statusLabel }}
                    </span>
                    <!-- Scheduled time badge for preorders -->
                    <span v-if="isScheduledOrder" :class="['px-2 py-0.5 rounded text-xs font-bold flex items-center gap-1', timeAgoClass]">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        {{ scheduledTimeDisplay }}
                    </span>
                    <!-- Urgency indicator -->
                    <span v-if="isUrgent" class="flex items-center gap-1 text-xs text-red-400">
                        <svg class="w-3.5 h-3.5 animate-pulse" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                        </svg>
                        Срочно!
                    </span>
                </div>
                <div class="flex items-center gap-2">
                    <!-- Time ago/until badge -->
                    <span :class="['px-2 py-0.5 rounded text-xs font-medium', timeAgoClass]">
                        {{ timeAgo }}
                    </span>
                </div>
            </div>

            <!-- Customer -->
            <div class="px-4 py-2">
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                    <span class="font-medium text-white">{{ order.customer?.name || order.customer_name || 'Клиент' }}</span>
                </div>
                <div class="flex items-center gap-2 mt-1">
                    <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                    </svg>
                    <span class="text-sm text-gray-400">{{ order.phone }}</span>
                    <button
                        @click.stop="copyPhone"
                        class="p-1 hover:bg-dark-700 rounded text-gray-500 hover:text-white transition-colors"
                        title="Копировать"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Address -->
            <div v-if="order.type === 'delivery'" class="px-4 py-2">
                <div class="flex items-start gap-2">
                    <svg class="w-4 h-4 text-gray-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    <span class="text-sm text-gray-300 line-clamp-2">{{ order.delivery_address }}</span>
                </div>
            </div>
            <div v-else class="px-4 py-2">
                <span class="text-sm text-green-400 flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                    </svg>
                    Самовывоз
                </span>
            </div>

            <!-- Items Preview -->
            <div v-if="order.items?.length" class="px-4 py-2 bg-dark-900/50">
                <p class="text-sm text-gray-400 line-clamp-1">
                    {{ itemsPreview }}
                </p>
            </div>

            <!-- Footer -->
            <div class="px-4 py-3 flex items-center justify-between border-t border-dark-700">
                <div class="flex items-center gap-3">
                    <span class="text-lg font-bold text-white">{{ formatPrice(order.total) }} ₽</span>
                    <span
                        :class="[
                            'px-2 py-0.5 rounded text-xs font-medium',
                            order.payment_status === 'paid'
                                ? 'bg-green-500/20 text-green-400'
                                : 'bg-red-500/20 text-red-400'
                        ]"
                    >
                        {{ order.payment_status === 'paid' ? 'Оплачен' : 'Не оплачен' }}
                    </span>
                </div>

                <!-- Courier -->
                <div v-if="order.courier" class="flex items-center gap-2">
                    <div class="w-7 h-7 rounded-full bg-accent flex items-center justify-center text-xs font-medium text-white">
                        {{ order.courier.name?.charAt(0) || 'К' }}
                    </div>
                    <span class="text-sm text-gray-400">{{ order.courier.name?.split(' ')[0] }}</span>
                </div>
                <button
                    v-else-if="order.delivery_status === 'ready' && order.type === 'delivery'"
                    @click.stop="$emit('assign-courier', order)"
                    class="text-sm text-accent hover:text-blue-400 transition-colors"
                >
                    + Назначить
                </button>
            </div>

            <!-- Quick Action -->
            <div v-if="showQuickAction" class="px-4 pb-3">
                <button
                    @click.stop="handleQuickAction"
                    :class="['w-full py-2 rounded-lg text-sm font-medium transition-colors', quickActionClass]"
                >
                    {{ quickActionLabel }}
                </button>
            </div>
        </template>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { formatAmount } from '@/utils/formatAmount.js';

const props = defineProps({
    order: {
        type: Object,
        required: true
    },
    selected: {
        type: Boolean,
        default: false
    },
    compact: {
        type: Boolean,
        default: false
    },
    draggable: {
        type: Boolean,
        default: false
    }
});

const emit = defineEmits(['click', 'assign-courier', 'status-change', 'drag-start', 'drag-end']);

// Drag state
const isDragging = ref(false);

// Time ago update
const now = ref(Date.now());
let timeInterval = null;

onMounted(() => {
    // Обновляем "время назад" каждую минуту
    timeInterval = setInterval(() => {
        now.value = Date.now();
    }, 60000);
});

onUnmounted(() => {
    if (timeInterval) clearInterval(timeInterval);
});

// Конфигурация статусов
const statusConfig = {
    pending: {
        label: 'Новый',
        class: 'bg-blue-500/20 text-blue-400',
        borderColor: 'border-l-blue-500',
        quickAction: { label: 'На кухню', class: 'bg-orange-600 hover:bg-orange-700 text-white', nextStatus: 'preparing' },
        urgentMinutes: 10 // Срочный после 10 минут
    },
    preparing: {
        label: 'Готовится',
        class: 'bg-orange-500/20 text-orange-400',
        borderColor: 'border-l-orange-500',
        quickAction: { label: 'Готов', class: 'bg-cyan-600 hover:bg-cyan-700 text-white', nextStatus: 'ready' },
        urgentMinutes: 30 // Срочный после 30 минут
    },
    ready: {
        label: 'Готов',
        class: 'bg-cyan-500/20 text-cyan-400',
        borderColor: 'border-l-cyan-500',
        quickAction: null,
        urgentMinutes: 15 // Срочный после 15 минут (еда остывает!)
    },
    picked_up: {
        label: 'Забран',
        class: 'bg-purple-500/20 text-purple-400',
        borderColor: 'border-l-purple-500',
        quickAction: { label: 'Доставлен', class: 'bg-green-600 hover:bg-green-700 text-white', nextStatus: 'delivered' },
        urgentMinutes: 45
    },
    in_transit: {
        label: 'В пути',
        class: 'bg-purple-500/20 text-purple-400',
        borderColor: 'border-l-purple-500',
        quickAction: { label: 'Доставлен', class: 'bg-green-600 hover:bg-green-700 text-white', nextStatus: 'delivered' },
        urgentMinutes: 45
    },
    delivered: {
        label: 'Доставлен',
        class: 'bg-green-500/20 text-green-400',
        borderColor: 'border-l-green-500',
        quickAction: null,
        urgentMinutes: null
    },
    completed: {
        label: 'Завершён',
        class: 'bg-gray-500/20 text-gray-400',
        borderColor: 'border-l-gray-500',
        quickAction: null,
        urgentMinutes: null
    },
    cancelled: {
        label: 'Отменён',
        class: 'bg-red-500/20 text-red-400',
        borderColor: 'border-l-red-500',
        quickAction: null,
        urgentMinutes: null
    }
};

// Определяем эффективный статус (учитываем завершённые заказы)
const effectiveStatus = computed(() => {
    if (props.order.delivery_status === 'delivered' && props.order.payment_status === 'paid') {
        return 'completed';
    }
    return props.order.delivery_status;
});

const currentStatus = computed(() => statusConfig[effectiveStatus.value] || statusConfig.pending);
const statusLabel = computed(() => {
    // Для самовывоза показываем "Выдан" вместо "Доставлен"
    if (effectiveStatus.value === 'delivered' && props.order.type === 'pickup') {
        return 'Выдан';
    }
    return currentStatus.value.label;
});
const statusClass = computed(() => currentStatus.value.class);
const statusBorderColor = computed(() => currentStatus.value.borderColor);

// Parse scheduled_at without timezone conversion (extract time directly from string)
const parseScheduledTime = (scheduledAt) => {
    if (!scheduledAt) return null;
    // Handle formats: "2026-01-17T20:00:00.000000Z" or "2026-01-17 20:00:00"
    const match = scheduledAt.match(/(\d{4}-\d{2}-\d{2})[T ](\d{2}):(\d{2})/);
    if (!match) return null;
    return {
        date: match[1],
        hours: parseInt(match[2]),
        minutes: parseInt(match[3]),
        timeStr: `${match[2]}:${match[3]}`
    };
};

// Check if order has scheduled time (preorder)
const isScheduledOrder = computed(() => {
    return props.order.scheduled_at && !props.order.is_asap;
});

// Time until scheduled delivery (for preorders) - using parseScheduledTime to avoid timezone issues
const minutesUntilDelivery = computed(() => {
    if (!isScheduledOrder.value) return null;
    const parsed = parseScheduledTime(props.order.scheduled_at);
    if (!parsed) return null;
    const scheduled = new Date(parsed.date + 'T' + parsed.timeStr + ':00');
    return Math.floor((scheduled.getTime() - now.value) / 60000);
});

// Scheduled time display (without timezone conversion)
const scheduledTimeDisplay = computed(() => {
    const parsed = parseScheduledTime(props.order.scheduled_at);
    return parsed ? parsed.timeStr : '';
});

// Time ago calculation (for regular orders)
const minutesAgo = computed(() => {
    if (!props.order.created_at) return 0;
    const created = new Date(props.order.created_at).getTime();
    return Math.floor((now.value - created) / 60000);
});

const timeAgo = computed(() => {
    // For scheduled orders, show time until delivery
    if (isScheduledOrder.value) {
        const mins = minutesUntilDelivery.value;
        if (mins < 0) return 'Просрочено';
        if (mins < 60) return `через ${mins} мин`;
        const hours = Math.floor(mins / 60);
        const remainMins = mins % 60;
        if (hours < 24) {
            return remainMins > 0 ? `через ${hours}ч ${remainMins}м` : `через ${hours}ч`;
        }
        return `через ${Math.floor(hours / 24)}д`;
    }

    // Regular orders - show time since creation
    const mins = minutesAgo.value;
    if (mins < 1) return 'Только что';
    if (mins < 60) return `${mins} мин`;
    const hours = Math.floor(mins / 60);
    if (hours < 24) return `${hours} ч`;
    return `${Math.floor(hours / 24)} д`;
});

const timeAgoClass = computed(() => {
    // For scheduled orders - color based on time until delivery
    if (isScheduledOrder.value) {
        const mins = minutesUntilDelivery.value;
        if (mins < 0) return 'bg-red-500/30 text-red-400 animate-pulse';
        if (mins < 30) return 'bg-red-500/20 text-red-400';
        if (mins < 60) return 'bg-yellow-500/20 text-yellow-400';
        return 'bg-green-500/20 text-green-400';
    }

    // Regular orders - color based on time since creation
    const mins = minutesAgo.value;
    const urgentMins = currentStatus.value.urgentMinutes;

    if (!urgentMins || ['delivered', 'completed', 'cancelled'].includes(effectiveStatus.value)) {
        return 'bg-dark-700 text-gray-400';
    }

    if (mins >= urgentMins) {
        return 'bg-red-500/20 text-red-400';
    } else if (mins >= urgentMins * 0.7) {
        return 'bg-yellow-500/20 text-yellow-400';
    }
    return 'bg-dark-700 text-gray-400';
});

// Urgency check
const isUrgent = computed(() => {
    if (['delivered', 'completed', 'cancelled'].includes(effectiveStatus.value)) return false;

    // For scheduled orders - urgent if less than 30 mins until delivery or overdue
    if (isScheduledOrder.value) {
        const mins = minutesUntilDelivery.value;
        return mins !== null && mins < 30;
    }

    // Regular orders - urgent if exceeds status threshold
    const urgentMins = currentStatus.value.urgentMinutes;
    if (!urgentMins) return false;
    return minutesAgo.value >= urgentMins;
});

// Quick Action логика
const showQuickAction = computed(() => {
    if (props.compact) return false;
    const status = effectiveStatus.value;
    if (['delivered', 'completed', 'cancelled'].includes(status)) return false;
    if (status === 'ready') {
        return props.order.type === 'pickup' || props.order.courier;
    }
    return !!currentStatus.value.quickAction;
});

const quickActionLabel = computed(() => {
    const status = props.order.delivery_status;
    if (status === 'ready') {
        if (props.order.type === 'pickup') return 'Выдан';
        if (props.order.courier) return 'В пути';
    }
    return currentStatus.value.quickAction?.label || '';
});

const quickActionClass = computed(() => {
    const status = props.order.delivery_status;
    if (status === 'ready') {
        return 'bg-green-600 hover:bg-green-700 text-white';
    }
    return currentStatus.value.quickAction?.class || '';
});

const handleQuickAction = () => {
    const status = props.order.delivery_status;
    let nextStatus;

    if (status === 'ready') {
        nextStatus = props.order.type === 'pickup' ? 'delivered' : 'in_transit';
    } else {
        nextStatus = currentStatus.value.quickAction?.nextStatus;
    }

    if (nextStatus) {
        emit('status-change', { order: props.order, status: nextStatus });
    }
};

// Drag handlers
const onDragStart = (e) => {
    if (!props.draggable) return;
    isDragging.value = true;
    e.dataTransfer.setData('application/json', JSON.stringify({
        orderId: props.order.id,
        fromStatus: props.order.delivery_status
    }));
    e.dataTransfer.effectAllowed = 'move';
    emit('drag-start', props.order);
};

const onDragEnd = () => {
    isDragging.value = false;
    emit('drag-end', props.order);
};

// Хелперы
const itemsPreview = computed(() => {
    if (!props.order.items?.length) return '';
    return props.order.items
        .map(item => `${item.dish?.name || item.name}${item.quantity > 1 ? ' x' + item.quantity : ''}`)
        .join(', ');
});

const formatPrice = (price) => {
    return formatAmount(price).toLocaleString('ru-RU');
};

const copyPhone = async () => {
    try {
        await navigator.clipboard.writeText(props.order.phone);
        window.$toast?.('Телефон скопирован', 'success');
    } catch (e) {
        console.error('Failed to copy:', e);
    }
};
</script>

<style scoped>
.line-clamp-1 {
    display: -webkit-box;
    -webkit-line-clamp: 1;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.bg-dark-750 {
    background-color: rgba(30, 35, 45, 1);
}

@keyframes pulse-subtle {
    0%, 100% {
        opacity: 1;
    }
    50% {
        opacity: 0.85;
    }
}

.animate-pulse-subtle {
    animation: pulse-subtle 2s ease-in-out infinite;
}
</style>
