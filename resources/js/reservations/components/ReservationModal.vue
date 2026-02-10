<template>
    <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
        <div class="bg-white rounded-2xl w-[600px] max-h-[90vh] overflow-hidden flex flex-col">
            <div class="px-6 py-4 border-b flex justify-between items-center bg-orange-500 text-white">
                <h3 class="text-xl font-bold">{{ store.editingReservation ? 'Редактировать' : 'Новое бронирование' }}</h3>
                <button @click="close" class="text-white/80 hover:text-white text-2xl">&times;</button>
            </div>

            <div class="p-6 overflow-y-auto space-y-4">
                <!-- Date & Time -->
                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">Дата *</label>
                        <input type="date" v-model="form.date" :min="today" class="w-full border rounded-lg px-3 py-2">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">С *</label>
                        <select v-model="form.time_from" class="w-full border rounded-lg px-3 py-2" :class="{ 'text-gray-400': !form.time_from }">
                            <option value="" disabled>Выберите</option>
                            <option v-for="t in availableTimeSlots" :key="t" :value="t">{{ t }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">До *</label>
                        <select v-model="form.time_to" class="w-full border rounded-lg px-3 py-2" :class="{ 'text-gray-400': !form.time_to }">
                            <option value="" disabled>Выберите</option>
                            <option v-for="t in endTimeSlots" :key="t.value" :value="t.value">
                                {{ t.label }}
                            </option>
                        </select>
                    </div>
                </div>

                <!-- Midnight crossing indicator -->
                <div v-if="crossesMidnight" class="flex items-center gap-2 p-3 bg-purple-50 border border-purple-200 rounded-lg">
                    <svg class="w-5 h-5 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
                    </svg>
                    <span class="text-sm text-purple-700">
                        Бронирование переходит через полночь и заканчивается <strong>{{ nextDayDisplay }}</strong>
                    </span>
                </div>

                <!-- Table & Guests -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">Стол *</label>
                        <select v-model="form.table_id" class="w-full border rounded-lg px-3 py-2">
                            <option value="">Выберите стол</option>
                            <option v-for="table in store.tables" :key="table.id" :value="table.id">
                                Стол {{ table.number }} ({{ table.seats }} мест)
                            </option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Гостей *</label>
                        <input type="number" v-model.number="form.guests_count" min="1" max="20" class="w-full border rounded-lg px-3 py-2">
                    </div>
                </div>

                <!-- Guest Info -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">Имя гостя *</label>
                        <input type="text" v-model="form.guest_name" class="w-full border rounded-lg px-3 py-2" placeholder="Иван Иванов">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Телефон *</label>
                        <input type="tel" v-model="form.guest_phone" class="w-full border rounded-lg px-3 py-2" placeholder="+7 (999) 123-45-67">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">Email</label>
                    <input type="email" v-model="form.guest_email" class="w-full border rounded-lg px-3 py-2" placeholder="email@example.com">
                </div>

                <!-- Notes -->
                <div>
                    <label class="block text-sm font-medium mb-1">Комментарий</label>
                    <textarea v-model="form.notes" rows="2" class="w-full border rounded-lg px-3 py-2" placeholder="Дополнительная информация..."></textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">Особые пожелания</label>
                    <textarea v-model="form.special_requests" rows="2" class="w-full border rounded-lg px-3 py-2" placeholder="Детский стул, вид из окна..."></textarea>
                </div>

                <!-- Deposit -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">Депозит</label>
                        <input type="number" v-model.number="form.deposit" min="0" class="w-full border rounded-lg px-3 py-2" placeholder="0">
                    </div>
                    <div class="flex items-end">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" v-model="form.deposit_paid" class="w-5 h-5">
                            <span>Депозит оплачен</span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="px-6 py-4 border-t bg-gray-50 flex gap-3">
                <button @click="close" class="flex-1 py-3 rounded-xl bg-gray-200 font-medium">Отмена</button>
                <button @click="save" class="flex-1 py-3 rounded-xl bg-orange-500 text-white font-medium">
                    {{ store.editingReservation ? 'Сохранить' : 'Создать' }}
                </button>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import { useReservationsStore } from '../stores/reservations';

// Helper для локальной даты (не UTC!)
const getLocalDateString = (date = new Date()) => {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
};

// Helper для текущего времени (HH:MM)
const getCurrentTime = () => {
    const now = new Date();
    return `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`;
};

const store = useReservationsStore();
const today = getLocalDateString();

// Фильтруем временные слоты - для сегодня показываем только будущее время
const availableTimeSlots = computed(() => {
    if (form.value.date !== today) {
        return store.timeSlots;
    }
    // Для сегодняшней даты фильтруем прошедшее время
    const currentTime = getCurrentTime();
    return store.timeSlots.filter((slot: any) => slot > currentTime);
});

// Helper to convert time to minutes
const timeToMinutes = (time: any) => {
    if (!time) return 0;
    const [h, m] = time.split(':').map(Number);
    return h * 60 + m;
};

// Check if time_to crosses midnight relative to time_from
const crossesMidnight = computed(() => {
    if (!form.value.time_from || !form.value.time_to) return false;
    const startMinutes = timeToMinutes(form.value.time_from);
    const endMinutes = timeToMinutes(form.value.time_to);
    return endMinutes <= startMinutes;
});

// Display text for next day
const nextDayDisplay = computed(() => {
    if (!form.value.date || !crossesMidnight.value) return '';
    const date = new Date(form.value.date);
    date.setDate(date.getDate() + 1);
    const day = date.getDate();
    const months = ['янв', 'фев', 'мар', 'апр', 'мая', 'июн', 'июл', 'авг', 'сен', 'окт', 'ноя', 'дек'];
    return `${day} ${months[date.getMonth()]} в ${form.value.time_to}`;
});

// Generate end time slots - includes times after midnight for overnight reservations
const endTimeSlots = computed(() => {
    if (!form.value.time_from) {
        return availableTimeSlots.value.map((t: any) => ({ value: t, label: t }));
    }

    const startMinutes = timeToMinutes(form.value.time_from);
    const slots: any = [];

    // First: times after the start time on the same day
    availableTimeSlots.value.forEach((t: any) => {
        const minutes = timeToMinutes(t);
        if (minutes > startMinutes) {
            slots.push({ value: t, label: t });
        }
    });

    // Add early morning times (next day) for overnight reservations
    // Only if we're starting in the evening (after 18:00)
    if (startMinutes >= 18 * 60) {
        const earlyMorningSlots = ['00:00', '00:30', '01:00', '01:30', '02:00', '02:30', '03:00', '03:30', '04:00'];
        earlyMorningSlots.forEach((t: any) => {
            slots.push({ value: t, label: `${t} (+1 день)` });
        });
    }

    return slots;
});

// Вычисляем время окончания (+2 часа от начала, with midnight handling)
const getEndTime = (startTime: any) => {
    if (!startTime) return '';
    const [h, m] = startTime.split(':').map(Number);
    let endHour = h + 2;
    // If it would go past midnight, cap at 23:00 for default
    if (endHour >= 24) {
        endHour = 23;
    }
    return `${String(endHour).padStart(2, '0')}:${String(m).padStart(2, '0')}`;
};

const form = ref({
    date: store.selectedDate,
    time_from: '',
    time_to: '',
    table_id: '',
    guests_count: 2,
    guest_name: '',
    guest_phone: '',
    guest_email: '',
    notes: '',
    special_requests: '',
    deposit: null as any,
    deposit_paid: false,
});

// Следим за изменением даты - сбрасываем время если оно в прошлом
watch(() => form.value.date, (newDate) => {
    if (newDate === today && form.value.time_from) {
        const currentTime = getCurrentTime();
        // Если выбранное время в прошлом - сбрасываем
        if (form.value.time_from <= currentTime) {
            form.value.time_from = '';
            form.value.time_to = '';
        }
    }
});

// Следим за изменением времени начала - автоматически устанавливаем время окончания
watch(() => form.value.time_from, (newTime) => {
    if (newTime) {
        // Если время окончания не выбрано или меньше/равно времени начала - устанавливаем +2 часа
        if (!form.value.time_to || newTime >= form.value.time_to) {
            form.value.time_to = getEndTime(newTime);
        }
    }
});

onMounted(() => {
    if (store.editingReservation) {
        const res = store.editingReservation;
        // Нормализуем дату (убираем время если есть)
        const normalizeDate = (d: any) => d ? d.substring(0, 10) : null;
        form.value = {
            date: normalizeDate(res.date) as any,
            time_from: res.time_from.substring(0, 5) as any,
            time_to: res.time_to!.substring(0, 5) as any,
            table_id: res.table_id as any,
            guests_count: res.guests_count as any,
            guest_name: res.guest_name as any,
            guest_phone: res.guest_phone as any,
            guest_email: res.guest_email || '' as any,
            notes: res.notes || '' as any,
            special_requests: res.special_requests || '' as any,
            deposit: res.deposit as any,
            deposit_paid: res.deposit_paid as any,
        };
    }
});

function close() {
    store.showModal = false;
    store.editingReservation = null;
}

async function save() {
    if (!form.value.time_from || !form.value.time_to) {
        store.showToast('Выберите время бронирования', 'error');
        return;
    }
    if (!form.value.guest_name || !form.value.guest_phone || !form.value.table_id) {
        store.showToast('Заполните обязательные поля', 'error');
        return;
    }
    await store.saveReservation(form.value);
}
</script>
