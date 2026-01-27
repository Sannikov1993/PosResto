import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import axios from 'axios';

const API_URL = '/api';

export const useReservationsStore = defineStore('reservations', () => {
    // Helper для локальной даты (не UTC!)
    const getLocalDateString = (date = new Date()) => {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    };

    // State
    const reservations = ref([]);
    const tables = ref([]);
    const stats = ref({});
    const currentMonth = ref(new Date().getMonth() + 1);
    const currentYear = ref(new Date().getFullYear());
    const selectedDate = ref(getLocalDateString());
    const calendarData = ref([]);
    const viewMode = ref('list');
    const activeFilters = ref([]);
    const loading = ref(false);
    const toast = ref(null);

    // Selected/editing
    const selectedReservation = ref(null);
    const editingReservation = ref(null);
    const showModal = ref(false);

    // Preorder state
    const showPreorderModal = ref(false);
    const preorderReservation = ref(null);
    const preorderItems = ref([]);
    const preorderTotal = ref(0);
    const menuCategories = ref([]);
    const selectedCategory = ref(null);
    const categoryDishes = ref([]);
    const preorderCart = ref([]);

    // Constants
    const statuses = [
        { value: 'pending', label: 'Ожидают', icon: '🕐', activeClass: 'bg-yellow-100 text-yellow-700' },
        { value: 'confirmed', label: 'Подтверждено', icon: '✓', activeClass: 'bg-green-100 text-green-700' },
        { value: 'seated', label: 'Гости сели', icon: '🪑', activeClass: 'bg-blue-100 text-blue-700' },
        { value: 'completed', label: 'Завершено', icon: '✅', activeClass: 'bg-gray-100 text-gray-700' },
        { value: 'cancelled', label: 'Отменено', icon: '✗', activeClass: 'bg-red-100 text-red-700' },
    ];

    const workHours = Array.from({ length: 13 }, (_, i) => i + 10);

    // Computed
    const monthName = computed(() => {
        return new Date(currentYear.value, currentMonth.value - 1).toLocaleString('ru', { month: 'long' });
    });

    const firstDayOffset = computed(() => {
        const first = new Date(currentYear.value, currentMonth.value - 1, 1);
        return (first.getDay() + 6) % 7;
    });

    const calendarDays = computed(() => {
        const days = [];
        const daysInMonth = new Date(currentYear.value, currentMonth.value, 0).getDate();
        const todayStr = getLocalDateString();

        for (let d = 1; d <= daysInMonth; d++) {
            const dateStr = `${currentYear.value}-${String(currentMonth.value).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
            const calData = calendarData.value.find(c => c.date === dateStr);
            days.push({
                day: d,
                date: dateStr,
                isToday: dateStr === todayStr,
                isPast: dateStr < todayStr,
                reservations_count: calData?.reservations_count || 0,
            });
        }
        return days;
    });

    const selectedDateReservations = computed(() => {
        return reservations.value.filter(r => r.date === selectedDate.value);
    });

    const filteredReservations = computed(() => {
        let result = selectedDateReservations.value;
        if (activeFilters.value.length > 0) {
            result = result.filter(r => activeFilters.value.includes(r.status));
        }
        return result.sort((a, b) => a.time_from.localeCompare(b.time_from));
    });

    const totalGuestsForDate = computed(() => {
        return selectedDateReservations.value
            .filter(r => ['pending', 'confirmed', 'seated'].includes(r.status))
            .reduce((sum, r) => sum + r.guests_count, 0);
    });

    const preorderCartTotal = computed(() => {
        return preorderCart.value.reduce((sum, item) => sum + (item.price * item.quantity), 0);
    });

    const timeSlots = computed(() => {
        const slots = [];
        for (let h = 10; h <= 22; h++) {
            slots.push(`${String(h).padStart(2, '0')}:00`);
            if (h < 22) slots.push(`${String(h).padStart(2, '0')}:30`);
        }
        return slots;
    });

    // API Methods

    // Загрузка "рабочей даты" (учитывает работу после полуночи)
    async function loadBusinessDate() {
        try {
            const res = await axios.get(`${API_URL}/reservations/business-date`);
            if (res.data.success && res.data.data?.business_date) {
                selectedDate.value = res.data.data.business_date;
                return res.data.data.business_date;
            }
        } catch (e) {
            console.warn('Failed to load business date:', e);
        }
        return getLocalDateString();
    }

    async function loadCalendar() {
        try {
            const res = await axios.get(`${API_URL}/reservations/calendar?month=${currentMonth.value}&year=${currentYear.value}`);
            if (res.data.success) calendarData.value = res.data.data.days;
        } catch (e) { console.error(e); }
    }

    async function loadReservations() {
        try {
            loading.value = true;
            const res = await axios.get(`${API_URL}/reservations?date=${selectedDate.value}`);
            if (res.data.success) reservations.value = res.data.data;
        } catch (e) { console.error(e); }
        finally { loading.value = false; }
    }

    async function loadTables() {
        try {
            const res = await axios.get(`${API_URL}/tables`);
            if (res.data.success) tables.value = res.data.data;
        } catch (e) { console.error(e); }
    }

    async function loadStats() {
        try {
            const res = await axios.get(`${API_URL}/reservations/stats`);
            if (res.data.success) stats.value = res.data.data;
        } catch (e) { console.error(e); }
    }

    async function saveReservation(form) {
        try {
            const url = editingReservation.value
                ? `${API_URL}/reservations/${editingReservation.value.id}`
                : `${API_URL}/reservations`;

            const res = await axios({
                method: editingReservation.value ? 'PUT' : 'POST',
                url,
                data: form
            });

            if (res.data.success) {
                showToast(editingReservation.value ? 'Бронирование обновлено' : 'Бронирование создано');
                showModal.value = false;
                editingReservation.value = null;
                await loadReservations();
                await loadCalendar();
                await loadStats();
                return { success: true };
            } else {
                showToast(res.data.message, 'error');
                return { success: false };
            }
        } catch (e) {
            showToast('Ошибка сохранения', 'error');
            return { success: false };
        }
    }

    async function updateStatus(reservation, action) {
        try {
            const res = await axios.post(`${API_URL}/reservations/${reservation.id}/${action}`);
            if (res.data.success) {
                const messages = {
                    confirm: 'Бронирование подтверждено',
                    seat: 'Гости сели за стол',
                    complete: 'Бронирование завершено',
                    cancel: 'Бронирование отменено'
                };
                showToast(messages[action] || 'Статус обновлен');
                await loadReservations();
                await loadStats();
                selectedReservation.value = null;
            }
        } catch (e) {
            showToast('Ошибка', 'error');
        }
    }

    // Preorder
    async function loadPreorderItems(reservationId) {
        try {
            const res = await axios.get(`${API_URL}/reservations/${reservationId}/preorder-items`);
            if (res.data.success) {
                preorderItems.value = res.data.items || [];
                preorderTotal.value = res.data.total || 0;
            }
        } catch (e) { console.error(e); }
    }

    async function loadMenuCategories() {
        try {
            const res = await axios.get(`${API_URL}/menu/categories`);
            if (res.data.success) menuCategories.value = res.data.data || [];
        } catch (e) { console.error(e); }
    }

    async function loadCategoryDishes(categoryId) {
        try {
            const res = await axios.get(`${API_URL}/menu/dishes?category_id=${categoryId}`);
            if (res.data.success) categoryDishes.value = res.data.data || [];
        } catch (e) { console.error(e); }
    }

    async function savePreorder() {
        if (!preorderReservation.value || preorderCart.value.length === 0) return;
        try {
            await axios.post(`${API_URL}/reservations/${preorderReservation.value.id}/preorder`);
            for (const item of preorderCart.value) {
                if (!item.isExisting) {
                    await axios.post(`${API_URL}/reservations/${preorderReservation.value.id}/preorder-items`, {
                        dish_id: item.dish_id,
                        quantity: item.quantity
                    });
                }
            }
            showToast('Предзаказ сохранён');
            showPreorderModal.value = false;
            preorderReservation.value = null;
            preorderCart.value = [];
            if (selectedReservation.value) await loadPreorderItems(selectedReservation.value.id);
        } catch (e) {
            showToast('Ошибка сохранения', 'error');
        }
    }

    // Navigation
    function prevMonth() {
        if (currentMonth.value === 1) {
            currentMonth.value = 12;
            currentYear.value--;
        } else {
            currentMonth.value--;
        }
        loadCalendar();
    }

    function nextMonth() {
        if (currentMonth.value === 12) {
            currentMonth.value = 1;
            currentYear.value++;
        } else {
            currentMonth.value++;
        }
        loadCalendar();
    }

    function selectDate(date) {
        selectedDate.value = date;
        loadReservations();
    }

    function toggleFilter(status) {
        const idx = activeFilters.value.indexOf(status);
        if (idx >= 0) activeFilters.value.splice(idx, 1);
        else activeFilters.value.push(status);
    }

    // Formatters
    function formatTime(t) { return t ? t.substring(0, 5) : ''; }
    function formatMoney(a) { return new Intl.NumberFormat('ru-RU', { style: 'currency', currency: 'RUB', minimumFractionDigits: 0 }).format(a || 0); }
    function formatDateShort(d) { return new Date(d).toLocaleDateString('ru-RU', { day: 'numeric', month: 'short' }); }
    function formatDateFull(d) { return new Date(d).toLocaleDateString('ru-RU', { weekday: 'long', day: 'numeric', month: 'long' }); }

    function getStatusColor(s) {
        return { pending: 'bg-yellow-400', confirmed: 'bg-green-500', seated: 'bg-blue-500', completed: 'bg-gray-400', cancelled: 'bg-red-400' }[s] || 'bg-gray-300';
    }

    function getStatusBadge(s) {
        return { pending: 'bg-yellow-100 text-yellow-700', confirmed: 'bg-green-100 text-green-700', seated: 'bg-blue-100 text-blue-700', completed: 'bg-gray-100 text-gray-700', cancelled: 'bg-red-100 text-red-700' }[s] || 'bg-gray-100';
    }

    function showToast(message, type = 'success') {
        toast.value = { message, type };
        setTimeout(() => { toast.value = null; }, 3000);
    }

    return {
        // State
        reservations, tables, stats, currentMonth, currentYear, selectedDate, calendarData,
        viewMode, activeFilters, loading, toast,
        selectedReservation, editingReservation, showModal,
        showPreorderModal, preorderReservation, preorderItems, preorderTotal,
        menuCategories, selectedCategory, categoryDishes, preorderCart,
        statuses, workHours,

        // Computed
        monthName, firstDayOffset, calendarDays, selectedDateReservations, filteredReservations,
        totalGuestsForDate, preorderCartTotal, timeSlots,

        // Methods
        loadBusinessDate, loadCalendar, loadReservations, loadTables, loadStats, saveReservation, updateStatus,
        loadPreorderItems, loadMenuCategories, loadCategoryDishes, savePreorder,
        prevMonth, nextMonth, selectDate, toggleFilter,
        formatTime, formatMoney, formatDateShort, formatDateFull, getStatusColor, getStatusBadge, showToast
    };
});
