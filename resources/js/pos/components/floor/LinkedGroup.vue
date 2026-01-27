<template>
    <div :class="['linked-group-frame', group.type === 'order' ? 'order-group' : 'reservation-group']"
         :style="frameStyle">

        <!-- SVG рамка с convex hull -->
        <svg v-if="group.svgPath">
            <path :d="group.svgPath" class="frame-path" />
        </svg>

        <!-- Бейдж сверху - Заказ (минималистичный: сумма + количество столов) -->
        <div v-if="group.type === 'order'" class="linked-group-label order-badge clickable"
             @click="$emit('click', group)"
             @contextmenu.prevent="$emit('contextmenu', $event, group)">
            <span class="badge-sum">{{ formatMoney(groupOrdersTotal) }} ₽</span>
            <span class="badge-divider"></span>
            <span class="badge-tables">{{ group.tablesCount }}</span>
        </div>

        <!-- Бейдж сверху - Бронь (с выпадающим списком как на одиночных столах) -->
        <div v-else
             ref="badgeRef"
             class="reservation-badge clickable"
             :class="{ 'has-multiple': effectiveReservations.length > 1, 'expanded': reservationsExpanded }"
             :style="badgeStyle"
             @click.stop="handleBadgeClick"
             @contextmenu.prevent="$emit('contextmenu', $event, group)">

            <!-- Номера столов -->
            <span class="rb-tables">{{ formatTableNumbers }}</span>
            <span class="rb-divider"></span>
            <!-- Время -->
            <span class="rb-time">{{ effectiveReservations[0]?.time_from?.substring(0,5) }}</span>
            <span class="rb-divider"></span>
            <!-- Гости -->
            <span class="rb-guests">{{ effectiveReservations[0]?.guests_count || group.totalSeats }}</span>

            <!-- Индикатор дополнительных броней -->
            <span v-if="effectiveReservations.length > 1" class="rb-more">
                +{{ effectiveReservations.length - 1 }}
            </span>

            <!-- Выпадающий список всех броней -->
            <div v-if="reservationsExpanded" class="rb-dropdown" @contextmenu.stop>
                <div v-for="(res, idx) in effectiveReservations"
                     :key="res.id || idx"
                     class="rb-dropdown-item"
                     @click.stop="handleReservationClick(res)">
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
                            <span class="rb-item-tables">ст. {{ getReservationTables(res) }}</span>
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

        <!-- Тултип при наведении - Заказ -->
        <div class="group-tooltip" :class="{ 'tooltip-bottom': isNearTop }" v-if="group.type === 'order'">
            <div class="group-tooltip-header">
                <span class="title">Столы {{ group.tableNumbers }}</span>
                <span class="badge">Занят</span>
            </div>
            <div class="group-tooltip-body">
                <div class="group-tooltip-row">
                    <span class="label">Гостей</span>
                    <span class="value">{{ group.order?.guests_count || group.totalSeats }} чел.</span>
                </div>
                <div class="group-tooltip-row">
                    <span class="label">Сумма заказа</span>
                    <span class="value highlight">{{ formatMoney(groupOrdersTotal) }}</span>
                </div>
            </div>
        </div>

        <!-- Тултип при наведении - Бронь (только если одна бронь и dropdown не открыт) -->
        <div class="group-tooltip" :class="{ 'tooltip-bottom': isNearTop }" v-if="group.type === 'reservation' && effectiveReservations.length === 1 && !reservationsExpanded">
            <div class="group-tooltip-header">
                <span class="title">Столы {{ group.tableNumbers }}</span>
                <span class="badge" style="background: #3B82F6;">Бронь</span>
            </div>
            <div class="group-tooltip-body">
                <div class="group-tooltip-row">
                    <span class="label">Время</span>
                    <span class="value">{{ group.reservation?.time_from?.substring(0,5) }} - {{ group.reservation?.time_to?.substring(0,5) }}</span>
                </div>
                <div class="group-tooltip-row" v-if="group.reservation?.guest_name">
                    <span class="label">Гость</span>
                    <span class="value">{{ group.reservation.guest_name }}</span>
                </div>
                <div class="group-tooltip-row" v-if="group.reservation?.guest_phone">
                    <span class="label">Телефон</span>
                    <span class="value">{{ formatPhone(group.reservation.guest_phone) }}</span>
                </div>
                <div class="group-tooltip-row">
                    <span class="label">Гостей</span>
                    <span class="value">{{ group.reservation?.guests_count || group.totalSeats }} чел.</span>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';

const props = defineProps({
    group: { type: Object, required: true },
    isFloorDateToday: { type: Boolean, default: true }
});

const emit = defineEmits(['click', 'clickReservation', 'contextmenu', 'openReservation']);

// State for expanded reservations list
const reservationsExpanded = ref(false);
const badgeRef = ref(null);

// Effective reservations list
const effectiveReservations = computed(() => {
    const reservations = props.group.reservations || [];
    if (reservations.length > 0) {
        return reservations.filter(r => ['pending', 'confirmed'].includes(r.status));
    }
    // Fallback to single reservation
    if (props.group.reservation && ['pending', 'confirmed'].includes(props.group.reservation.status)) {
        return [props.group.reservation];
    }
    return [];
});

// Toggle reservations list
const toggleReservationsList = () => {
    reservationsExpanded.value = !reservationsExpanded.value;
};

// Handle badge click
const handleBadgeClick = () => {
    if (effectiveReservations.value.length === 1) {
        // Одна бронь - сразу открываем её
        emit('openReservation', effectiveReservations.value[0]);
    } else if (effectiveReservations.value.length > 1) {
        // Несколько броней - показываем/скрываем список
        toggleReservationsList();
    }
};

// Handle reservation click in dropdown
const handleReservationClick = (res) => {
    reservationsExpanded.value = false;
    emit('openReservation', res);
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

// Frame style
const frameStyle = computed(() => ({
    left: props.group.x + 'px',
    top: props.group.y + 'px',
    width: props.group.width + 'px',
    height: props.group.height + 'px'
}));

// Badge style - позиционирование на верхней точке hull
const badgeStyle = computed(() => ({
    left: (props.group.badgeX ?? props.group.width / 2) + 'px',
    top: (props.group.badgeY ?? 0) + 'px'
}));

// Is near top
const isNearTop = computed(() => {
    return props.group.y < 100;
});

// Group orders total
const groupOrdersTotal = computed(() => {
    return props.group.order?.total || 0;
});

// Format table numbers (11, 12 -> 11+12)
const formatTableNumbers = computed(() => {
    if (props.group.tableNumbers) {
        return props.group.tableNumbers.replace(/, /g, '+');
    }
    return '';
});

// Format money
const formatMoney = (amount) => {
    return new Intl.NumberFormat('ru-RU').format(amount || 0);
};

// Format phone
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

// Helper: truncate notes
const truncateNotes = (notes) => {
    if (!notes || !notes.trim()) return '';
    const maxLength = 30;
    if (notes.length <= maxLength) return notes;
    return notes.substring(0, maxLength) + '...';
};

// Helper: get reservation tables as string
const getReservationTables = (res) => {
    // Если есть tables (загружены через accessor)
    if (res.tables && res.tables.length > 0) {
        return res.tables.map(t => t.number).join('+');
    }
    // Если есть linked_table_ids - ищем номера в группе
    if (res.linked_table_ids && res.linked_table_ids.length > 0) {
        // Объединённая бронь
        return props.group.tableNumbers?.replace(/, /g, '+') || '?';
    }
    // Одиночная бронь - ищем номер стола по table_id
    // Можем взять из group.tableNumbers первый подходящий
    return res.table_id || '?';
};
</script>
