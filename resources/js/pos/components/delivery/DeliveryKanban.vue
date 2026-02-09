<template>
    <div class="h-full flex gap-3 p-4">
        <!-- Columns -->
        <div
            v-for="column in columns"
            :key="column.status"
            :class="[
                'flex flex-col transition-all duration-300',
                column.collapsible && collapsedColumns[column.status] ? 'w-12 flex-none' : 'flex-1 min-w-0'
            ]"
            @dragover.prevent="onDragOver($event, column.status)"
            @dragleave="onDragLeave($event, column.status)"
            @drop="onDrop($event, column.status)"
        >
            <!-- Collapsed state -->
            <div
                v-if="column.collapsible && collapsedColumns[column.status]"
                :class="[column.headerClass, 'rounded-xl flex-1 flex flex-col items-center py-4 cursor-pointer hover:opacity-90 transition']"
                @click="toggleColumn(column.status)"
            >
                <span :class="['w-2 h-2 rounded-full mb-3', column.dotClass]"></span>
                <div class="flex-1 flex items-center justify-center">
                    <span class="text-white font-medium text-sm whitespace-nowrap vertical-text">{{ column.label }}</span>
                </div>
                <span class="w-7 h-7 bg-white/20 rounded-full text-sm font-medium flex items-center justify-center mt-3">
                    {{ getColumnOrders(column.status).length }}
                </span>
            </div>

            <!-- Expanded state -->
            <template v-else>
                <!-- Column Header -->
                <div
                    :class="[
                        'flex items-center justify-between px-4 py-3 rounded-t-xl transition-colors',
                        column.headerClass,
                        dragOverColumn === column.status ? 'ring-2 ring-white/50' : '',
                        column.collapsible ? 'cursor-pointer hover:opacity-90' : ''
                    ]"
                    @click="column.collapsible && toggleColumn(column.status)"
                >
                    <div class="flex items-center gap-2">
                        <span :class="['w-2 h-2 rounded-full', column.dotClass]"></span>
                        <span class="font-medium text-white">{{ column.label }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="px-2 py-0.5 bg-white/20 rounded text-sm font-medium">
                            {{ getColumnOrders(column.status).length }}
                        </span>
                        <span v-if="column.collapsible" class="text-white/70 text-sm">▶</span>
                    </div>
                </div>

            <!-- Column Content -->
            <div
                :class="[
                    'flex-1 bg-dark-900/50 rounded-b-xl p-3 pb-6 space-y-3 overflow-y-auto transition-colors',
                    dragOverColumn === column.status ? 'bg-dark-800/70' : ''
                ]"
            >
                <!-- Empty State -->
                <div
                    v-if="getColumnOrders(column.status).length === 0"
                    class="flex flex-col items-center justify-center h-32 text-gray-600"
                >
                    <svg class="w-8 h-8 mb-2 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                    </svg>
                    <span class="text-sm">Нет заказов</span>
                </div>

                <!-- Pending column with Time Slots + ASAP -->
                <template v-else-if="column.status === 'pending'">
                    <!-- Time Slots for Preorders -->
                    <div v-for="slot in preorderTimeSlots" :key="slot.key" class="mb-3">
                        <!-- Slot Header -->
                        <div
                            :class="[
                                'flex items-center gap-2 px-2 py-1.5 rounded-lg mb-2 text-xs font-medium',
                                slot.urgency === 'overdue' ? 'bg-red-500/30 text-red-300' :
                                slot.urgency === 'urgent' ? 'bg-red-500/20 text-red-400' :
                                slot.urgency === 'warning' ? 'bg-yellow-500/20 text-yellow-400' :
                                'bg-dark-700 text-gray-300'
                            ]"
                        >
                            <span>⏰</span>
                            <span>{{ slot.label }}</span>
                            <span class="ml-auto opacity-70">({{ slot.orders.length }})</span>
                        </div>

                        <!-- Slot Orders -->
                        <div class="space-y-2 pl-2 border-l-2"
                             :class="[
                                 slot.urgency === 'overdue' ? 'border-red-500' :
                                 slot.urgency === 'urgent' ? 'border-red-400' :
                                 slot.urgency === 'warning' ? 'border-yellow-400' :
                                 'border-gray-600'
                             ]"
                        >
                            <div
                                v-for="order in slot.orders"
                                :key="order.id"
                                :class="[
                                    'bg-dark-800 rounded-lg p-2 cursor-pointer hover:bg-dark-700 transition text-sm',
                                    selectedOrderId === order.id ? 'ring-2 ring-accent' : '',
                                    slot.urgency === 'overdue' || slot.urgency === 'urgent' ? 'ring-1 ring-red-500/50' : ''
                                ]"
                                draggable="true"
                                @dragstart="$event.dataTransfer.setData('application/json', JSON.stringify({ orderId: order.id, fromStatus: order.delivery_status }))"
                                @click="$emit('select-order', order)"
                            >
                                <div class="flex items-center justify-between mb-1">
                                    <div class="flex items-center gap-1.5">
                                        <span>{{ getOrderUrgencyDot(order.scheduled_at) }}</span>
                                        <span class="font-bold text-white">#{{ order.order_number }}</span>
                                        <span class="text-xs opacity-60">
                                            {{ order.type === 'delivery' ? '🛵' : '🏃' }}
                                        </span>
                                    </div>
                                    <span :class="['text-xs font-medium', getOrderUrgencyClass(order.scheduled_at)]">
                                        {{ formatTimeUntil(getMinutesUntil(order.scheduled_at)) }}
                                    </span>
                                </div>
                                <p class="text-xs text-gray-400 truncate">{{ order.delivery_address || 'Самовывоз' }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Divider -->
                    <div v-if="preorderTimeSlots.length > 0 && getPendingAsap.length > 0" class="border-t border-gray-700 my-2"></div>

                    <!-- ASAP Orders Section -->
                    <div v-if="getPendingAsap.length > 0">
                        <div class="flex items-center gap-2 px-2 py-1.5 rounded-lg mb-2 text-xs font-medium bg-blue-500/20 text-blue-400">
                            <span>⚡</span>
                            <span>Ближайшие</span>
                            <span class="ml-auto opacity-70">({{ getPendingAsap.length }})</span>
                        </div>
                        <div class="space-y-2">
                            <DeliveryOrderCard
                                v-for="order in getPendingAsap"
                                :key="order.id"
                                :order="order"
                                :selected="selectedOrderId === order.id"
                                :compact="compactMode"
                                :draggable="true"
                                @click="$emit('select-order', order)"
                                @assign-courier="$emit('assign-courier', order)"
                                @status-change="handleStatusChange"
                            />
                        </div>
                    </div>
                </template>

                <!-- Grouped by courier (for in_transit) -->
                <template v-else-if="column.status === 'in_transit' && groupByCourier">
                    <div v-for="group in getCourierGroups()" :key="group.courier?.id || 'unassigned'" class="space-y-2">
                        <!-- Courier Header -->
                        <div v-if="group.courier" class="flex items-center gap-2 px-2 py-1.5 bg-dark-800 rounded-lg">
                            <div class="w-6 h-6 rounded-full bg-accent flex items-center justify-center text-xs font-medium text-white">
                                {{ group.courier.name?.charAt(0) || 'К' }}
                            </div>
                            <span class="text-sm text-gray-300 truncate">{{ group.courier.name }}</span>
                            <span class="text-xs text-gray-500 ml-auto flex-shrink-0">{{ group.orders.length }}</span>
                        </div>

                        <!-- Orders -->
                        <DeliveryOrderCard
                            v-for="order in group.orders"
                            :key="order.id"
                            :order="order"
                            :selected="selectedOrderId === order.id"
                            :compact="compactMode"
                            :draggable="true"
                            @click="$emit('select-order', order)"
                            @assign-courier="$emit('assign-courier', order)"
                            @status-change="handleStatusChange"
                        />
                    </div>
                </template>

                <!-- Regular list (for other columns) -->
                <template v-else>
                    <DeliveryOrderCard
                        v-for="order in getColumnOrders(column.status)"
                        :key="order.id"
                        :order="order"
                        :selected="selectedOrderId === order.id"
                        :compact="compactMode"
                        :draggable="true"
                        @click="$emit('select-order', order)"
                        @assign-courier="$emit('assign-courier', order)"
                        @status-change="handleStatusChange"
                    />
                </template>
            </div>
            </template>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import DeliveryOrderCard from './DeliveryOrderCard.vue';
import { createLogger } from '../../../shared/services/logger.js';

const log = createLogger('POS:DeliveryKanban');

const props = defineProps({
    orders: {
        type: Array,
        default: () => []
    },
    selectedOrderId: {
        type: [Number, String],
        default: null
    },
    compactMode: {
        type: Boolean,
        default: false
    },
    groupByCourier: {
        type: Boolean,
        default: true
    }
});

const emit = defineEmits(['select-order', 'assign-courier', 'status-change']);

// Helper для локальной даты (не UTC!)
const getLocalDateString = (date = new Date()) => {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
};

// Drag state
const dragOverColumn = ref(null);

// Collapsed columns state
const collapsedColumns = ref({});

const toggleColumn = (status) => {
    collapsedColumns.value[status] = !collapsedColumns.value[status];
};

// Parse scheduled_at to extract time without timezone conversion
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

// Get time slot key for grouping (30-minute slots)
const getTimeSlotKey = (scheduledAt) => {
    const parsed = parseScheduledTime(scheduledAt);
    if (!parsed) return null;
    const slotMinutes = parsed.minutes < 30 ? '00' : '30';
    return `${parsed.date}-${parsed.hours.toString().padStart(2, '0')}:${slotMinutes}`;
};

// Get time slot label
const getTimeSlotLabel = (slotKey) => {
    if (!slotKey) return '';
    const parts = slotKey.split('-');
    const timePart = parts[parts.length - 1];
    const [hours, mins] = timePart.split(':');
    const h = parseInt(hours);
    const m = parseInt(mins);
    const endM = m + 30;
    const endH = endM >= 60 ? h + 1 : h;
    const endMins = endM >= 60 ? '00' : '30';
    return `${hours}:${mins} - ${endH.toString().padStart(2, '0')}:${endMins}`;
};

// Get slot urgency based on time remaining
const getSlotUrgency = (slotKey) => {
    if (!slotKey) return 'normal';
    const parts = slotKey.split('-');
    const timePart = parts[parts.length - 1];
    const datePart = parts.slice(0, 3).join('-');
    const [hours, mins] = timePart.split(':').map(Number);

    const now = new Date();
    const todayStr = getLocalDateString();

    if (datePart !== todayStr) {
        return datePart > todayStr ? 'normal' : 'overdue';
    }

    const slotStart = hours * 60 + mins;
    const currentMins = now.getHours() * 60 + now.getMinutes();
    const diff = slotStart - currentMins;

    if (diff < 0) return 'overdue';
    if (diff <= 30) return 'urgent';
    if (diff <= 60) return 'warning';
    return 'normal';
};

// Get minutes until order time
const getMinutesUntil = (scheduledAt) => {
    const parsed = parseScheduledTime(scheduledAt);
    if (!parsed) return null;

    const now = new Date();
    const todayStr = getLocalDateString();

    if (parsed.date !== todayStr) {
        return parsed.date > todayStr ? 9999 : -9999;
    }

    const currentMins = now.getHours() * 60 + now.getMinutes();
    const targetMins = parsed.hours * 60 + parsed.minutes;
    return targetMins - currentMins;
};

// Format time until
const formatTimeUntil = (mins) => {
    if (mins === null) return '';
    if (mins >= 9999) return 'завтра';
    if (mins <= -9999) return 'просрочен';
    if (mins < 0) return `просрочен ${Math.abs(mins)}м`;
    if (mins === 0) return 'сейчас';
    if (mins < 60) return `через ${mins}м`;
    const h = Math.floor(mins / 60);
    const m = mins % 60;
    return m > 0 ? `через ${h}ч ${m}м` : `через ${h}ч`;
};

// Get order urgency dot
const getOrderUrgencyDot = (scheduledAt) => {
    const mins = getMinutesUntil(scheduledAt);
    if (mins === null) return '⚪';
    if (mins < 0) return '🔴';
    if (mins <= 30) return '🔴';
    if (mins <= 60) return '🟡';
    return '🟢';
};

// Get order urgency class
const getOrderUrgencyClass = (scheduledAt) => {
    const mins = getMinutesUntil(scheduledAt);
    if (mins === null) return 'text-gray-400';
    if (mins < 0) return 'text-red-400';
    if (mins <= 30) return 'text-red-400';
    if (mins <= 60) return 'text-yellow-400';
    return 'text-green-400';
};

// Check if order is a scheduled preorder (not ASAP)
const isPreorder = (order) => {
    return order.scheduled_at && !order.is_asap;
};

// Get preorders for pending column (sorted by scheduled time)
const getPendingPreorders = computed(() => {
    return props.orders
        .filter(o => o.delivery_status === 'pending' && isPreorder(o))
        .sort((a, b) => {
            const timeA = parseScheduledTime(a.scheduled_at);
            const timeB = parseScheduledTime(b.scheduled_at);
            if (!timeA || !timeB) return 0;
            return (timeA.date + timeA.timeStr).localeCompare(timeB.date + timeB.timeStr);
        });
});

// Get ASAP orders for pending column (sorted by creation time - oldest first)
const getPendingAsap = computed(() => {
    return props.orders
        .filter(o => o.delivery_status === 'pending' && !isPreorder(o))
        .sort((a, b) => new Date(a.created_at) - new Date(b.created_at));
});

// Group preorders by 30-minute time slots
const preorderTimeSlots = computed(() => {
    const slots = {};

    getPendingPreorders.value.forEach(order => {
        const slotKey = getTimeSlotKey(order.scheduled_at);
        if (!slotKey) return;

        if (!slots[slotKey]) {
            slots[slotKey] = {
                key: slotKey,
                label: getTimeSlotLabel(slotKey),
                orders: [],
                urgency: 'normal'
            };
        }
        slots[slotKey].orders.push(order);
    });

    return Object.values(slots)
        .map(slot => ({
            ...slot,
            urgency: getSlotUrgency(slot.key)
        }))
        .sort((a, b) => a.key.localeCompare(b.key));
});

// Колонки (фиксированные)
const columns = [
    {
        status: 'pending',
        label: 'Новый',
        headerClass: 'bg-blue-600',
        dotClass: 'bg-blue-300'
    },
    {
        status: 'preparing',
        label: 'Готовится',
        headerClass: 'bg-orange-600',
        dotClass: 'bg-orange-300'
    },
    {
        status: 'ready',
        label: 'Готов',
        headerClass: 'bg-cyan-600',
        dotClass: 'bg-cyan-300'
    },
    {
        status: 'in_transit',
        label: 'В пути',
        headerClass: 'bg-purple-600',
        dotClass: 'bg-purple-300'
    },
    {
        status: 'delivered',
        label: 'Доставлен / Выдан',
        headerClass: 'bg-green-600',
        dotClass: 'bg-green-300',
        collapsible: false
    }
];

// Общее количество новых заказов (предзаказы + ближайшие)
const totalPendingOrders = computed(() => {
    return getPendingPreorders.value.length + getPendingAsap.value.length;
});

// Получить заказы для колонки
const getColumnOrders = (status) => {
    // Колонка "Новый" - возвращаем общее количество для счётчика
    // (секции отображаются отдельно в шаблоне)
    if (status === 'pending') {
        return [...getPendingPreorders.value, ...getPendingAsap.value];
    }

    if (status === 'in_transit') {
        return props.orders.filter(o =>
            (o.delivery_status === 'in_transit' || o.delivery_status === 'picked_up')
        );
    }

    // Доставлен/Выдан - все завершённые заказы за сегодня
    if (status === 'delivered') {
        const today = getLocalDateString();
        return props.orders
            .filter(o => o.delivery_status === 'delivered')
            .filter(o => {
                // Показываем заказы, завершённые сегодня
                const orderDate = o.updated_at || o.created_at;
                return orderDate && orderDate.startsWith(today);
            })
            .sort((a, b) => new Date(b.updated_at || b.created_at) - new Date(a.updated_at || a.created_at));
    }

    return props.orders.filter(o => o.delivery_status === status);
};

// Группировка по курьерам для колонки "В пути"
const getCourierGroups = () => {
    const inTransitOrders = getColumnOrders('in_transit');
    const groups = {};

    inTransitOrders.forEach(order => {
        const courierId = order.courier?.id || 'unassigned';
        if (!groups[courierId]) {
            groups[courierId] = {
                courier: order.courier,
                orders: []
            };
        }
        groups[courierId].orders.push(order);
    });

    return Object.values(groups).sort((a, b) => {
        if (!a.courier) return 1;
        if (!b.courier) return -1;
        return a.courier.name.localeCompare(b.courier.name);
    });
};

// Drag & Drop handlers
const onDragOver = (e, status) => {
    e.preventDefault();
    dragOverColumn.value = status;
};

const onDragLeave = (e, status) => {
    const rect = e.currentTarget.getBoundingClientRect();
    if (
        e.clientX < rect.left ||
        e.clientX > rect.right ||
        e.clientY < rect.top ||
        e.clientY > rect.bottom
    ) {
        dragOverColumn.value = null;
    }
};

// Допустимый порядок статусов (только вперёд)
const statusOrder = ['pending', 'preparing', 'ready', 'picked_up', 'in_transit', 'delivered'];

const isForwardTransition = (from, to) => {
    const fromIdx = statusOrder.indexOf(from);
    const toIdx = statusOrder.indexOf(to);
    if (fromIdx === -1 || toIdx === -1) return false;
    return toIdx > fromIdx;
};

const onDrop = (e, targetStatus) => {
    e.preventDefault();
    dragOverColumn.value = null;

    try {
        const data = JSON.parse(e.dataTransfer.getData('application/json'));
        const { orderId, fromStatus } = data;

        if (fromStatus === targetStatus) return;

        // Разрешаем только переходы вперёд по цепочке статусов
        if (!isForwardTransition(fromStatus, targetStatus)) return;

        const order = props.orders.find(o => o.id === orderId);
        if (!order) return;

        if (targetStatus === 'in_transit' && !order.courier && order.type === 'delivery') {
            emit('assign-courier', order);
            return;
        }

        if (targetStatus === 'delivered' && fromStatus === 'ready' && order.type === 'pickup') {
            emit('status-change', { order, status: 'delivered' });
            return;
        }

        emit('status-change', { order, status: targetStatus });
    } catch (error) {
        log.error('Drop error:', error);
    }
};

const handleStatusChange = (payload) => {
    emit('status-change', payload);
};
</script>

<style scoped>
/* Vertical text for collapsed columns */
.vertical-text {
    writing-mode: vertical-rl;
    text-orientation: mixed;
    transform: rotate(180deg);
}

/* Custom scrollbar */
::-webkit-scrollbar {
    width: 6px;
}

::-webkit-scrollbar-track {
    background: transparent;
}

::-webkit-scrollbar-thumb {
    background-color: rgba(75, 85, 99, 0.5);
    border-radius: 3px;
}

::-webkit-scrollbar-thumb:hover {
    background-color: rgba(75, 85, 99, 0.7);
}
</style>
