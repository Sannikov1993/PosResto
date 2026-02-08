<template>
    <Teleport to="body">
        <div v-if="show" class="fixed inset-0 z-[9998]" @click.stop="$emit('close')" @contextmenu.prevent.stop="$emit('close')"></div>
        <div v-if="show"
             class="fixed z-[9999] bg-dark-800 rounded-xl shadow-2xl border border-gray-700 py-2 min-w-[200px]"
             :style="{ left: x + 'px', top: y + 'px' }">

            <!-- Table Info -->
            <div class="px-4 py-2 border-b border-gray-700">
                <p class="font-bold text-white">Стол {{ table?.number }}</p>
                <p class="text-xs text-gray-500">{{ table?.seats }} мест • {{ statusText }}</p>
            </div>

            <!-- Actions -->
            <div class="py-1">
                <!-- Reserved table (by status OR has active reservation) -->
                <template v-if="table?.status === 'reserved' || hasActiveReservation">
                    <button @click="$emit('viewReservation')" class="menu-item">
                        <span class="icon">📅</span> Детали брони
                    </button>
                    <button v-if="isTodayReservation" @click="$emit('seatGuests')" class="menu-item text-green-400">
                        <span class="icon">✓</span> Посадить гостей
                    </button>
                    <div class="border-t border-gray-700 my-1"></div>
                    <button @click="$emit('newOrder')" class="menu-item">
                        <span class="icon">🍽️</span> Новый заказ
                    </button>
                    <button @click="$emit('newReservation')" class="menu-item">
                        <span class="icon">➕</span> Добавить бронь
                    </button>
                    <div class="border-t border-gray-700 my-1"></div>
                    <button @click="$emit('cancelReservation')" class="menu-item text-red-400">
                        <span class="icon">✕</span> Отменить бронь
                    </button>
                </template>

                <!-- Free table actions -->
                <template v-else-if="table?.status === 'free' || !table?.status">
                    <button @click="$emit('newOrder')" class="menu-item">
                        <span class="icon">🍽️</span> Новый заказ
                    </button>
                    <div class="border-t border-gray-700 my-1"></div>
                    <button @click="$emit('newReservation')" class="menu-item">
                        <span class="icon">📅</span> Забронировать
                    </button>
                </template>

                <!-- Occupied table actions -->
                <template v-else-if="table?.status === 'occupied'">
                    <button @click="$emit('requestBill')" class="menu-item">
                        <span class="icon">🧾</span> Счёт
                    </button>
                    <div class="border-t border-gray-700 my-1"></div>
                    <button @click="$emit('newReservation')" class="menu-item">
                        <span class="icon">📅</span> Забронировать
                    </button>
                    <button @click="$emit('moveOrder')" class="menu-item">
                        <span class="icon">🔄</span> Перенести заказ
                    </button>
                    <div class="border-t border-gray-700 my-1"></div>
                    <button @click="$emit('cancelOrder')" class="menu-item text-red-400">
                        <span class="icon">✕</span> Отменить заказ
                    </button>
                </template>

                <!-- Bill status actions -->
                <template v-else-if="table?.status === 'bill'">
                    <button @click="$emit('processPayment')" class="menu-item text-green-400">
                        <span class="icon">💳</span> Принять оплату
                    </button>
                    <button @click="$emit('openOrder')" class="menu-item">
                        <span class="icon">📋</span> Открыть заказ
                    </button>
                    <div class="border-t border-gray-700 my-1"></div>
                    <button @click="$emit('cancelOrder')" class="menu-item text-red-400">
                        <span class="icon">✕</span> Отменить заказ
                    </button>
                </template>

                <!-- (reserved actions handled above, before free) -->

                <!-- Common actions -->
                <div class="border-t border-gray-700 my-1"></div>
                <button @click="toggleMultiSelect" class="menu-item">
                    <span class="icon">☑️</span> {{ isSelected ? 'Убрать из выбора' : 'Мультивыбор' }}
                </button>
            </div>
        </div>
    </Teleport>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    x: { type: Number, default: 0 },
    y: { type: Number, default: 0 },
    table: { type: Object, default: null },
    isSelected: { type: Boolean, default: false },
    isInLinkedGroup: { type: Boolean, default: false }
});

const emit = defineEmits([
    'close',
    'newOrder',
    'newReservation',
    'openOrder',
    'addItems',
    'requestBill',
    'splitBill',
    'moveOrder',
    'cancelOrder',
    'processPayment',
    'viewReservation',
    'seatGuests',
    'cancelReservation',
    'toggleMultiSelect'
]);

const statusText = computed(() => {
    if (hasActiveReservation.value && props.table?.status === 'free') return 'Забронирован';
    const texts = {
        free: 'Свободен',
        occupied: 'Занят',
        reserved: 'Забронирован',
        bill: 'Ожидает оплаты'
    };
    return texts[props.table?.status] || 'Свободен';
});

// Есть ли активная бронь (pending или confirmed)
const hasActiveReservation = computed(() => {
    const res = props.table?.next_reservation;
    return res && ['pending', 'confirmed'].includes(res.status);
});

// Бронь на сегодня (можно посадить)
const isTodayReservation = computed(() => {
    const res = props.table?.next_reservation;
    if (!res) return false;
    // Используем локальную дату (не UTC!) — иначе в UTC+3 после 21:00 дата сдвигается
    const d = new Date();
    const today = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
    return res.date === today && ['pending', 'confirmed'].includes(res.status);
});

const toggleMultiSelect = () => {
    emit('toggleMultiSelect');
    emit('close');
};
</script>

<style scoped>
.menu-item {
    width: 100%;
    padding: 0.5rem 1rem;
    text-align: left;
    font-size: 0.875rem;
    color: #d1d5db;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    transition: background-color 0.15s;
}
.menu-item:hover {
    background-color: rgba(55, 65, 81, 0.5);
}
.menu-item .icon {
    width: 1.25rem;
    text-align: center;
}
</style>
