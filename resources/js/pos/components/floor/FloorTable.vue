<template>
    <div
        @click="$emit('click', table, $event)"
        @contextmenu.prevent="$emit('contextmenu', $event, table)"
        @mouseenter="$emit('mouseenter', table)"
        @mouseleave="$emit('mouseleave')"
        :class="tableClasses"
        :style="tableStyle"
        :data-testid="`table-${table.id}`">

        <!-- Тултип при наведении (скрыт в transfer mode) -->
        <div class="table-tooltip" :class="{ 'tooltip-bottom': isNearTop }" v-if="!isInLinkedGroup && !transferMode">
            <!-- Свободный стол -->
            <template v-if="tooltipStatus === 'free'">
                <div class="table-tooltip-header">
                    <span class="title">Стол {{ table.number }}</span>
                    <span class="badge free">Свободен</span>
                </div>
                <div class="table-tooltip-body">
                    <div class="table-tooltip-row">
                        <span class="label">Мест</span>
                        <span class="value">{{ table.seats }} чел.</span>
                    </div>
                </div>
            </template>

            <!-- Забронированный стол (для других дней) -->
            <template v-else-if="tooltipStatus === 'reserved'">
                <div class="table-tooltip-header">
                    <span class="title">Стол {{ table.number }}</span>
                    <span class="badge reserved">Бронь</span>
                </div>
                <div class="table-tooltip-body">
                    <div class="table-tooltip-row">
                        <span class="label">Время</span>
                        <span class="value">{{ table.next_reservation?.time_from?.substring(0,5) }} - {{ table.next_reservation?.time_to?.substring(0,5) }}</span>
                    </div>
                    <div class="table-tooltip-row">
                        <span class="label">Гостей</span>
                        <span class="value">{{ table.next_reservation?.guests_count || table.seats }} чел.</span>
                    </div>
                    <div class="table-tooltip-row" v-if="table.next_reservation?.guest_name">
                        <span class="label">Гость</span>
                        <span class="value">{{ table.next_reservation.guest_name }}</span>
                    </div>
                    <div class="table-tooltip-row" v-if="table.reservations_count > 1">
                        <span class="label">Всего броней</span>
                        <span class="value highlight">{{ table.reservations_count }}</span>
                    </div>
                </div>
            </template>

            <!-- Гости по брони (seated) -->
            <template v-else-if="tooltipStatus === 'seated'">
                <div class="table-tooltip-header">
                    <span class="title">Стол {{ table.number }}</span>
                    <span class="badge occupied">По брони</span>
                </div>
                <div class="table-tooltip-body">
                    <div class="table-tooltip-row">
                        <span class="label">Гостей</span>
                        <span class="value">{{ activeReservation?.guests_count || table.seats }} чел.</span>
                    </div>
                    <div class="table-tooltip-row">
                        <span class="label">Сумма заказа</span>
                        <span class="value highlight">{{ formatMoney(table.active_orders_total || 0) }}</span>
                    </div>
                    <div class="table-tooltip-row" v-if="activeReservation?.guest_name">
                        <span class="label">Гость</span>
                        <span class="value">{{ activeReservation.guest_name }}</span>
                    </div>
                    <div class="table-tooltip-row" v-if="activeReservation?.guest_phone">
                        <span class="label">Телефон</span>
                        <span class="value">{{ formatPhone(activeReservation.guest_phone) }}</span>
                    </div>
                    <div class="table-tooltip-row">
                        <span class="label">Бронь</span>
                        <span class="value">{{ activeReservation?.time_from?.substring(0,5) }} - {{ activeReservation?.time_to?.substring(0,5) }}</span>
                    </div>
                </div>
            </template>

            <!-- Занятый стол -->
            <template v-else>
                <div class="table-tooltip-header">
                    <span class="title">Стол {{ table.number }}</span>
                    <span class="badge occupied">Занят</span>
                </div>
                <div class="table-tooltip-body">
                    <div class="table-tooltip-row">
                        <span class="label">Гостей</span>
                        <span class="value">{{ table.active_order?.guests_count || table.seats }} чел.</span>
                    </div>
                    <div class="table-tooltip-row">
                        <span class="label">Сумма заказа</span>
                        <span class="value highlight">{{ formatMoney(table.active_orders_total || 0) }}</span>
                    </div>
                    <div class="table-tooltip-row" v-if="table.active_order?.guest_name">
                        <span class="label">Гость</span>
                        <span class="value">{{ table.active_order.guest_name }}</span>
                    </div>
                </div>
            </template>
        </div>

        <!-- Стулья вокруг стола (не для бара) -->
        <div v-if="!table.is_bar" v-for="(chair, cIdx) in chairPositions" :key="cIdx"
             class="chair"
             :class="chairClass"
             :style="chair.style">
        </div>

        <!-- Поверхность стола с текстурой -->
        <div class="wood-texture flex flex-col items-center justify-center w-full h-full"
             :class="surfaceClasses">
            <span class="text-white font-bold drop-shadow-lg relative z-10 text-base">{{ table.number }}</span>
            <!-- Сегодня: Занят - показываем сумму (НО не для столов в группе - там сумма на рамке) -->
            <span v-if="isFloorDateToday && table.active_orders_total > 0 && !isInLinkedGroup" class="text-white/80 text-xs font-medium">
                {{ formatMoney(table.active_orders_total) }}
            </span>
            <!-- Для столов в группе - показываем количество мест -->
            <span v-else-if="isInLinkedGroup" class="text-white/60 text-xs">{{ table.seats }}м</span>
            <!-- Остальные - количество мест -->
            <span v-else class="text-white/60 text-xs">{{ table.seats }}м</span>
        </div>

        <!-- Иконка счёта -->
        <div v-if="isFloorDateToday && table.status === 'bill'" class="bill-icon">💳</div>

        <!-- Бейдж срочности брони -->
        <div v-if="showUrgencyBadge && urgency === 'soon'" class="urgency-badge soon">
            ⏰ через {{ reservationMinutes }} мин
        </div>
        <div v-if="showUrgencyBadge && urgency === 'overdue'" class="urgency-badge overdue">
            ⚠️ опоздание {{ Math.abs(reservationMinutes) }} мин
        </div>

        <!-- Бейдж брони на сегодня -->
        <div v-if="showReservationBadge"
             ref="badgeRef"
             class="reservation-badge"
             :class="{ 'has-multiple': effectiveReservations.length > 1, 'expanded': reservationsExpanded }"
             @click="onBadgeClick($event)">

            <!-- Основная информация -->
            <span class="rb-time">{{ effectiveReservations[0]?.time_from?.substring(0,5) }}</span>
            <span class="rb-divider"></span>
            <span class="rb-guests">{{ effectiveReservations[0]?.guests_count || 2 }}</span>

            <!-- Индикатор дополнительных броней -->
            <span v-if="effectiveReservations.length > 1" class="rb-more">
                +{{ effectiveReservations.length - 1 }}
            </span>

            <!-- Выпадающий список всех броней (скрыт в transfer mode) -->
            <div v-if="reservationsExpanded && !transferMode" class="rb-dropdown" @click.stop>
                <div v-for="(res, idx) in effectiveReservations"
                     :key="res.id || idx"
                     class="rb-dropdown-item"
                     @click.stop="$emit('openReservation', res)">
                    <!-- Аватар с инициалами -->
                    <div class="rb-item-avatar" :style="{ background: getAvatarColor(res.guest_name) }">
                        {{ getInitials(res.guest_name) }}
                    </div>
                    <!-- Информация о брони -->
                    <div class="rb-item-content">
                        <div class="rb-item-top">
                            <span class="rb-item-name">{{ truncateName(res.guest_name) }}</span>
                            <span class="rb-item-guests">
                                <svg viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/>
                                </svg>
                                {{ res.guests_count || 2 }}
                            </span>
                        </div>
                        <div class="rb-item-bottom">
                            <span class="rb-item-time">{{ res.time_from?.substring(0,5) }}</span>
                            <span class="rb-item-phone" v-if="res.guest_phone">{{ formatPhone(res.guest_phone) }}</span>
                        </div>
                        <!-- Комментарий -->
                        <div v-if="res.notes" class="rb-item-notes">
                            <svg viewBox="0 0 24 24" fill="currentColor">
                                <path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H6l-2 2V4h16v12z"/>
                            </svg>
                            <span>{{ truncateNotes(res.notes) }}</span>
                        </div>
                    </div>
                    <!-- Кнопка звонка -->
                    <a v-if="res.guest_phone"
                       :href="'tel:' + res.guest_phone"
                       class="rb-item-call"
                       @click.stop>
                        <svg viewBox="0 0 24 24" fill="currentColor">
                            <path d="M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z"/>
                        </svg>
                    </a>
                    <!-- Стрелка вправо -->
                    <svg class="rb-item-arrow" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M8.59 16.59L13.17 12 8.59 7.41 10 6l6 6-6 6-1.41-1.41z"/>
                    </svg>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { formatAmount } from '@/utils/formatAmount.js';

const props = defineProps({
    table: { type: Object, required: true },
    scale: { type: Number, default: 1 },
    isFloorDateToday: { type: Boolean, default: true },
    isSelected: { type: Boolean, default: false },
    isMultiSelected: { type: Boolean, default: false },
    multiSelectMode: { type: Boolean, default: false },
    isInLinkedGroup: { type: Boolean, default: false },
    isInHoveredGroup: { type: Boolean, default: false },
    isInLinkedReservation: { type: Boolean, default: false },
    tableReservations: { type: Array, default: () => [] },
    transferMode: { type: Boolean, default: false },
    isTransferSource: { type: Boolean, default: false }
});

const emit = defineEmits(['click', 'contextmenu', 'mouseenter', 'mouseleave', 'openReservation']);

// State for expanded reservations list
const reservationsExpanded = ref(false);
const badgeRef = ref(null);

// Toggle reservations list
const toggleReservationsList = () => {
    reservationsExpanded.value = !reservationsExpanded.value;
};

// Close dropdown when clicking outside
const handleClickOutside = (event) => {
    if (reservationsExpanded.value && badgeRef.value && !badgeRef.value.contains(event.target)) {
        reservationsExpanded.value = false;
    }
};

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
});

// Handle badge click - в transfer mode пропускаем клик к родителю (столу)
const onBadgeClick = (event) => {
    if (props.transferMode) {
        // В режиме переноса НЕ перехватываем клик — пусть всплывёт к столу
        return;
    }
    event.stopPropagation();
    handleBadgeClick();
};

// Handle badge click - open reservation or toggle dropdown
const handleBadgeClick = () => {
    if (effectiveReservations.value.length === 1) {
        // Одна бронь - сразу открываем её
        emit('openReservation', effectiveReservations.value[0]);
    } else {
        // Несколько броней - показываем/скрываем список
        toggleReservationsList();
    }
};

// Close dropdown when clicking outside or when table changes
watch(() => props.table.id, () => {
    reservationsExpanded.value = false;
});

// Computed: effective reservations list (with fallback)
// Исключаем seated - они уже "на столе" и работа ведётся с заказом
// Исключаем объединённые брони - они отображаются в LinkedGroup
const effectiveReservations = computed(() => {
    const activeStatuses = ['pending', 'confirmed'];

    // If we have tableReservations from parent, filter them
    if (props.tableReservations && props.tableReservations.length > 0) {
        return props.tableReservations.filter(r => activeStatuses.includes(r.status));
    }
    // Fallback: create array from next_reservation if it's active AND not linked
    const nextRes = props.table.next_reservation;
    if (nextRes && activeStatuses.includes(nextRes.status)) {
        // Проверяем, что это не объединённая бронь
        const isLinked = Array.isArray(nextRes.linked_table_ids) && nextRes.linked_table_ids.length > 0;
        if (!isLinked) {
            return [nextRes];
        }
    }
    return [];
});

// Computed: has reservations for selected date (using effectiveReservations or tableReservations)
const hasReservationsForDate = computed(() => {
    // Используем effectiveReservations которые берутся из tableReservations (отфильтрованные по выбранной дате)
    return effectiveReservations.value.length > 0;
});

// Computed: table style
const tableStyle = computed(() => {
    const x = (props.table.position_x || 0) * props.scale;
    const y = (props.table.position_y || 0) * props.scale;
    const w = (props.table.width || 80) * props.scale;
    const h = (props.table.height || 80) * props.scale;
    const rotation = props.table.rotation || 0;

    return {
        left: x + 'px',
        top: y + 'px',
        width: w + 'px',
        height: h + 'px',
        transform: rotation ? `rotate(${rotation}deg)` : 'none'
    };
});

// Computed: table status class
const tableStatus = computed(() => {
    // Для других дней - не показываем текущий статус занятости
    if (!props.isFloorDateToday) {
        if (hasReservationsForDate.value) {
            return 'reserved';
        }
        return 'free';
    }

    // Сегодня - показываем актуальный статус
    if (props.isInLinkedGroup) return 'occupied';
    if (props.table.next_reservation && !props.table.active_orders_total && props.table.next_reservation.status !== 'seated') {
        return 'free';
    }
    return props.table.status || 'free';
});

// Computed: table classes
const tableClasses = computed(() => {
    const classes = [
        'floor-table absolute cursor-pointer',
        'table-' + (props.table.shape || 'square'),
        'status-' + tableStatus.value
    ];

    if (props.table.is_bar) classes.push('table-bar');
    if (props.isSelected) classes.push('selected');
    if (props.isMultiSelected) classes.push('multi-selected');
    if (props.multiSelectMode && !props.isMultiSelected) classes.push('multi-select-available');
    if (props.isInHoveredGroup) classes.push('linked-hover');
    if (props.table.next_reservation && urgency.value === 'soon') classes.push('reservation-soon');
    if (props.table.next_reservation && urgency.value === 'overdue') classes.push('reservation-overdue');

    // Режим переноса заказа
    if (props.transferMode) {
        if (props.isTransferSource) {
            classes.push('transfer-source');
        } else {
            classes.push('transfer-target');
        }
    }

    return classes;
});

// Computed: surface classes
const surfaceClasses = computed(() => {
    const classes = [];

    if (!props.isFloorDateToday) {
        // Для других дней - показываем только бронирования на выбранную дату
        // Используем hasReservationsForDate вместо table.next_reservation
        if (hasReservationsForDate.value) {
            classes.push('table-reserved');
        } else {
            classes.push('table-free');
        }
    } else if (props.isInLinkedGroup) {
        classes.push('table-occupied');
    } else if (props.table.active_orders_total > 0) {
        classes.push('table-occupied');
    } else if (props.table.next_reservation && props.table.next_reservation.status === 'seated') {
        classes.push('table-occupied');
    } else if (props.table.next_reservation) {
        classes.push('table-free');
    } else {
        classes.push('table-' + (props.table.status || 'free'));
    }

    if (props.table.shape === 'round' || props.table.shape === 'oval') {
        classes.push('rounded-full');
    } else {
        classes.push('rounded-xl');
    }

    return classes;
});

// Computed: chair class
const chairClass = computed(() => {
    const classes = [];

    if (!props.isFloorDateToday) {
        // Для других дней - показываем только бронирования на выбранную дату
        if (hasReservationsForDate.value) {
            classes.push('chair-reserved');
        } else {
            classes.push('chair-free');
        }
    } else if (props.isInLinkedGroup) {
        classes.push('chair-occupied');
    } else if (props.table.active_orders_total > 0) {
        classes.push('chair-occupied');
    } else if (props.table.next_reservation && props.table.next_reservation.status === 'seated') {
        classes.push('chair-occupied');
    } else if (props.table.next_reservation) {
        classes.push('chair-free');
    } else {
        classes.push('chair-' + (props.table.status || 'free'));
    }

    if (props.table.chair_style === 'soft') {
        classes.push('soft');
    }

    return classes;
});

// Computed: tooltip status
const tooltipStatus = computed(() => {
    // Если не сегодня - не показываем статус занятости (это данные сегодняшнего дня)
    if (!props.isFloorDateToday) {
        // Для других дней показываем только бронирования на выбранную дату
        if (hasReservationsForDate.value) {
            return 'reserved';
        }
        return 'free';
    }

    // Сегодня - показываем актуальный статус
    if (props.table.next_reservation && props.table.next_reservation.status === 'seated') {
        return 'seated';
    }
    if (props.table.status === 'occupied' || props.table.active_orders_total > 0) {
        return 'occupied';
    }
    return 'free';
});

// Computed: active reservation
const activeReservation = computed(() => {
    if (props.table.next_reservation?.status === 'seated') {
        return props.table.next_reservation;
    }
    return null;
});

// Computed: is near top (for tooltip position)
const isNearTop = computed(() => {
    return (props.table.position_y || 0) < 150;
});

// Computed: urgency
const urgency = computed(() => {
    const res = props.table.next_reservation;
    if (!res || !props.isFloorDateToday || props.table.active_orders_total > 0) return null;

    const now = new Date();
    const [hours, minutes] = (res.time_from || '00:00').split(':').map(Number);
    const resTime = new Date();
    resTime.setHours(hours, minutes, 0, 0);

    const diffMinutes = Math.round((resTime - now) / 60000);

    if (diffMinutes <= 0 && diffMinutes > -30) return 'overdue';
    if (diffMinutes > 0 && diffMinutes <= 30) return 'soon';
    return null;
});

// Computed: reservation minutes until/overdue
const reservationMinutes = computed(() => {
    const res = props.table.next_reservation;
    if (!res) return 0;

    const now = new Date();
    const [hours, minutes] = (res.time_from || '00:00').split(':').map(Number);
    const resTime = new Date();
    resTime.setHours(hours, minutes, 0, 0);

    return Math.round((resTime - now) / 60000);
});

// Computed: show urgency badge
const showUrgencyBadge = computed(() => {
    return props.isFloorDateToday && props.table.next_reservation && !props.table.active_orders_total && urgency.value;
});

// Computed: show order total
const showOrderTotal = computed(() => {
    return props.isFloorDateToday && props.table.next_reservation &&
           (props.table.next_reservation.status === 'seated' || props.table.active_orders_total > 0) &&
           !props.isInLinkedGroup;
});

// Computed: show reservation badge (показываем для любой даты)
// Связанные брони уже отфильтрованы в tableReservations, поэтому проверяем только isInLinkedGroup (заказ)
const showReservationBadge = computed(() => {
    const firstReservation = effectiveReservations.value[0];
    const validStatuses = ['pending', 'confirmed'];
    const isValidStatus = firstReservation?.status && validStatuses.includes(firstReservation.status);
    return firstReservation &&
           isValidStatus &&
           !props.isInLinkedGroup;
});

// Computed: chair positions
const chairPositions = computed(() => {
    const chairs = [];
    const seats = props.table.seats || 4;
    const w = (props.table.width || 80) * props.scale;
    const h = (props.table.height || 80) * props.scale;
    const chairSize = Math.max(16, 18 * props.scale);
    const chairOffset = 4 * props.scale;

    if (props.table.shape === 'round' || props.table.shape === 'oval') {
        // Круговое расположение
        const centerX = w / 2;
        const centerY = h / 2;
        const radiusX = (w / 2) + chairSize / 2 + chairOffset;
        const radiusY = (h / 2) + chairSize / 2 + chairOffset;

        for (let i = 0; i < seats; i++) {
            const angle = (i / seats) * 2 * Math.PI - Math.PI / 2;
            const x = centerX + radiusX * Math.cos(angle) - chairSize / 2;
            const y = centerY + radiusY * Math.sin(angle) - chairSize / 2;
            const rotation = (angle * 180 / Math.PI) + 90;

            chairs.push({
                style: {
                    left: x + 'px',
                    top: y + 'px',
                    width: chairSize + 'px',
                    height: chairSize + 'px',
                    transform: `rotate(${rotation}deg)`
                }
            });
        }
    } else {
        // Прямоугольное расположение
        const seatsPerSide = Math.ceil(seats / 4);
        const sides = [
            { count: Math.min(seatsPerSide, seats), side: 'top' },
            { count: Math.min(seatsPerSide, Math.max(0, seats - seatsPerSide)), side: 'bottom' },
            { count: Math.min(seatsPerSide, Math.max(0, seats - seatsPerSide * 2)), side: 'left' },
            { count: Math.min(seatsPerSide, Math.max(0, seats - seatsPerSide * 3)), side: 'right' }
        ];

        sides.forEach(({ count, side }) => {
            for (let i = 0; i < count; i++) {
                let x, y, rotation;
                const offset = (i + 0.5) / count;

                switch (side) {
                    case 'top':
                        x = w * offset - chairSize / 2;
                        y = -chairSize - chairOffset;
                        rotation = 0;
                        break;
                    case 'bottom':
                        x = w * offset - chairSize / 2;
                        y = h + chairOffset;
                        rotation = 180;
                        break;
                    case 'left':
                        x = -chairSize - chairOffset;
                        y = h * offset - chairSize / 2;
                        rotation = -90;
                        break;
                    case 'right':
                        x = w + chairOffset;
                        y = h * offset - chairSize / 2;
                        rotation = 90;
                        break;
                }

                chairs.push({
                    style: {
                        left: x + 'px',
                        top: y + 'px',
                        width: chairSize + 'px',
                        height: chairSize + 'px',
                        transform: `rotate(${rotation}deg)`
                    }
                });
            }
        });
    }

    return chairs;
});

// Helper: format money (с учётом настройки округления)
const formatMoney = (amount) => {
    const rounded = formatAmount(amount);
    return new Intl.NumberFormat('ru-RU').format(rounded);
};

// Helper: format phone
const formatPhone = (phone) => {
    if (!phone) return '';
    const cleaned = phone.replace(/\D/g, '');
    if (cleaned.length === 11) {
        return `+${cleaned[0]} ${cleaned.slice(1, 4)} ${cleaned.slice(4, 7)}-${cleaned.slice(7, 9)}-${cleaned.slice(9)}`;
    }
    return phone;
};

// Helper: get initials from name
const getInitials = (name) => {
    if (!name || !name.trim()) return '??';
    const parts = name.trim().split(/\s+/);
    if (parts.length >= 2) {
        return (parts[0][0] + parts[1][0]).toUpperCase();
    }
    return name.substring(0, 2).toUpperCase();
};

// Helper: get avatar color based on name
const getAvatarColor = (name) => {
    if (!name) return '#64748b';
    const colors = [
        '#ef4444', '#f97316', '#f59e0b', '#84cc16',
        '#22c55e', '#14b8a6', '#06b6d4', '#3b82f6',
        '#6366f1', '#8b5cf6', '#a855f7', '#ec4899'
    ];
    let hash = 0;
    for (let i = 0; i < name.length; i++) {
        hash = name.charCodeAt(i) + ((hash << 5) - hash);
    }
    return colors[Math.abs(hash) % colors.length];
};

// Helper: truncate name
const truncateName = (name) => {
    if (!name || !name.trim()) return 'Гость';
    const maxLength = 12;
    if (name.length <= maxLength) return name;
    return name.substring(0, maxLength) + '...';
};

const truncateNotes = (notes) => {
    if (!notes || !notes.trim()) return '';
    const maxLength = 30;
    if (notes.length <= maxLength) return notes;
    return notes.substring(0, maxLength) + '...';
};
</script>
