import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import api from '../api/index.js';
import { createLogger } from '../../shared/services/logger.js';

const log = createLogger('Reservations');

interface Reservation {
    id: number;
    date: string;
    time_from: string;
    time_to?: string;
    guests_count: number;
    status: string;
    [key: string]: unknown;
}

interface CalendarDay {
    day: number;
    date: string;
    isToday: boolean;
    isPast: boolean;
    reservations_count: number;
}

interface StatusConfig {
    value: string;
    label: string;
    icon: string;
    activeClass: string;
}

interface ToastMessage {
    message: string;
    type: string;
}

interface PreorderCartItem {
    dish_id: number;
    quantity: number;
    price: number;
    isExisting?: boolean;
    [key: string]: unknown;
}

export const useReservationsStore = defineStore('reservations', () => {
    const getLocalDateString = (date = new Date()): string => {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    };

    // State
    const reservations = ref<Reservation[]>([]);
    const tables = ref<any[]>([]);
    const stats = ref<Record<string, any>>({});
    const currentMonth = ref(new Date().getMonth() + 1);
    const currentYear = ref(new Date().getFullYear());
    const selectedDate = ref(getLocalDateString());
    const calendarData = ref<Array<{ date: string; reservations_count: number }>>([]);
    const viewMode = ref('list');
    const activeFilters = ref<string[]>([]);
    const loading = ref(false);
    const toast = ref<ToastMessage | null>(null);

    const selectedReservation = ref<Reservation | null>(null);
    const editingReservation = ref<Reservation | null>(null);
    const showModal = ref(false);

    const showPreorderModal = ref(false);
    const preorderReservation = ref<Reservation | null>(null);
    const preorderItems = ref<any[]>([]);
    const preorderTotal = ref(0);
    const menuCategories = ref<any[]>([]);
    const selectedCategory = ref<number | null>(null);
    const categoryDishes = ref<any[]>([]);
    const preorderCart = ref<PreorderCartItem[]>([]);

    const statuses: StatusConfig[] = [
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

    const calendarDays = computed((): CalendarDay[] => {
        const days: CalendarDay[] = [];
        const daysInMonth = new Date(currentYear.value, currentMonth.value, 0).getDate();
        const todayStr = getLocalDateString();

        for (let d = 1; d <= daysInMonth; d++) {
            const dateStr = `${currentYear.value}-${String(currentMonth.value).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
            const calData = calendarData.value.find((c: any) => c.date === dateStr);
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
        return reservations.value.filter((r: any) => r.date === selectedDate.value);
    });

    const filteredReservations = computed(() => {
        let result = selectedDateReservations.value;
        if (activeFilters.value.length > 0) {
            result = result.filter((r: any) => activeFilters.value.includes(r.status));
        }
        return result.sort((a: any, b: any) => a.time_from.localeCompare(b.time_from));
    });

    const totalGuestsForDate = computed(() => {
        return selectedDateReservations.value
            .filter((r: any) => ['pending', 'confirmed', 'seated'].includes(r.status))
            .reduce((sum: any, r: any) => sum + r.guests_count, 0);
    });

    const preorderCartTotal = computed(() => {
        return preorderCart.value.reduce((sum: any, item: any) => sum + (item.price * item.quantity), 0);
    });

    const timeSlots = computed(() => {
        const slots: string[] = [];
        for (let h = 10; h <= 22; h++) {
            slots.push(`${String(h).padStart(2, '0')}:00`);
            if (h < 22) slots.push(`${String(h).padStart(2, '0')}:30`);
        }
        return slots;
    });

    // API Methods
    async function loadBusinessDate(): Promise<string> {
        try {
            const data = await api.reservations.getBusinessDate() as Record<string, any> | null;
            if (data?.business_date) {
                selectedDate.value = data.business_date as string;
                return data.business_date as string;
            }
        } catch (e: unknown) {
            log.warn('Failed to load business date:', (e as Error).message);
        }
        return getLocalDateString();
    }

    async function loadCalendar() {
        try {
            const data = await api.reservations.getCalendar(currentMonth.value, currentYear.value) as Record<string, any> | null;
            calendarData.value = (data?.days as typeof calendarData.value) || [];
        } catch (e: unknown) { log.error('Failed to load calendar:', (e as Error).message); }
    }

    async function loadReservations() {
        try {
            loading.value = true;
            reservations.value = (await api.reservations.getByDate(selectedDate.value)) as Reservation[];
        } catch (e: unknown) { log.error('Failed to load reservations:', (e as Error).message); }
        finally { loading.value = false; }
    }

    async function loadTables() {
        try {
            tables.value = await api.tables.getAll();
        } catch (e: unknown) { log.error('Failed to load tables:', (e as Error).message); }
    }

    async function loadStats() {
        try {
            stats.value = (await api.reservations.getStats()) as Record<string, any>;
        } catch (e: unknown) { log.error('Failed to load stats:', (e as Error).message); }
    }

    async function saveReservation(form: Record<string, any>): Promise<{ success: boolean }> {
        try {
            if (editingReservation.value) {
                await api.reservations.update(editingReservation.value.id, form);
                showToast('Бронирование обновлено');
            } else {
                await api.reservations.create(form);
                showToast('Бронирование создано');
            }

            showModal.value = false;
            editingReservation.value = null;
            await loadReservations();
            await loadCalendar();
            await loadStats();
            return { success: true };
        } catch (e: unknown) {
            showToast((e as Record<string, Record<string, Record<string, string>>>).response?.data?.message || 'Ошибка сохранения', 'error');
            return { success: false };
        }
    }

    async function updateStatus(reservation: Reservation, action: string) {
        try {
            await api.reservations.updateStatus(reservation.id, action);
            const messages: Record<string, string> = {
                confirm: 'Бронирование подтверждено',
                seat: 'Гости сели за стол',
                complete: 'Бронирование завершено',
                cancel: 'Бронирование отменено'
            };
            showToast(messages[action] || 'Статус обновлен');
            await loadReservations();
            await loadStats();
            selectedReservation.value = null;
        } catch (e: any) {
            showToast('Ошибка', 'error');
        }
    }

    // Preorder
    async function loadPreorderItems(reservationId: number) {
        try {
            const res = await api.reservations.getPreorderItems(reservationId) as Record<string, any>;
            preorderItems.value = (res?.items as any[]) || [];
            preorderTotal.value = (res?.total as number) || 0;
        } catch (e: unknown) { log.error('Failed to load preorder items:', (e as Error).message); }
    }

    async function loadMenuCategories() {
        try {
            menuCategories.value = await api.menu.getCategories();
        } catch (e: unknown) { log.error('Failed to load menu categories:', (e as Error).message); }
    }

    async function loadCategoryDishes(categoryId: number) {
        try {
            categoryDishes.value = await api.menu.getDishes({ category_id: categoryId });
        } catch (e: unknown) { log.error('Failed to load dishes:', (e as Error).message); }
    }

    async function savePreorder() {
        if (!preorderReservation.value || preorderCart.value.length === 0) return;
        try {
            await api.reservations.savePreorder(preorderReservation.value.id);
            for (const item of preorderCart.value) {
                if (!item.isExisting) {
                    await api.reservations.addPreorderItem(preorderReservation.value.id, {
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
        } catch (e: any) {
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

    function selectDate(date: string) {
        selectedDate.value = date;
        loadReservations();
    }

    function toggleFilter(status: string) {
        const idx = activeFilters.value.indexOf(status);
        if (idx >= 0) activeFilters.value.splice(idx, 1);
        else activeFilters.value.push(status);
    }

    // Formatters
    function formatTime(t: string | null): string { return t ? t.substring(0, 5) : ''; }
    function formatMoney(a: number): string { return new Intl.NumberFormat('ru-RU', { style: 'currency', currency: 'RUB', minimumFractionDigits: 0 }).format(a || 0); }
    function formatDateShort(d: string): string { return new Date(d).toLocaleDateString('ru-RU', { day: 'numeric', month: 'short' }); }
    function formatDateFull(d: string): string { return new Date(d).toLocaleDateString('ru-RU', { weekday: 'long', day: 'numeric', month: 'long' }); }

    function getStatusColor(s: string): string {
        return ({ pending: 'bg-yellow-400', confirmed: 'bg-green-500', seated: 'bg-blue-500', completed: 'bg-gray-400', cancelled: 'bg-red-400' } as Record<string, string>)[s] || 'bg-gray-300';
    }

    function getStatusBadge(s: string): string {
        return ({ pending: 'bg-yellow-100 text-yellow-700', confirmed: 'bg-green-100 text-green-700', seated: 'bg-blue-100 text-blue-700', completed: 'bg-gray-100 text-gray-700', cancelled: 'bg-red-100 text-red-700' } as Record<string, string>)[s] || 'bg-gray-100';
    }

    function showToast(message: string, type: string = 'success') {
        toast.value = { message, type };
        setTimeout(() => { toast.value = null; }, 3000);
    }

    return {
        reservations, tables, stats, currentMonth, currentYear, selectedDate, calendarData,
        viewMode, activeFilters, loading, toast,
        selectedReservation, editingReservation, showModal,
        showPreorderModal, preorderReservation, preorderItems, preorderTotal,
        menuCategories, selectedCategory, categoryDishes, preorderCart,
        statuses, workHours,
        monthName, firstDayOffset, calendarDays, selectedDateReservations, filteredReservations,
        totalGuestsForDate, preorderCartTotal, timeSlots,
        loadBusinessDate, loadCalendar, loadReservations, loadTables, loadStats, saveReservation, updateStatus,
        loadPreorderItems, loadMenuCategories, loadCategoryDishes, savePreorder,
        prevMonth, nextMonth, selectDate, toggleFilter,
        formatTime, formatMoney, formatDateShort, formatDateFull, getStatusColor, getStatusBadge, showToast
    };
});
