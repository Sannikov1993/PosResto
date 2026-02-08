<template>
    <div class="h-full flex flex-col bg-dark-950" data-testid="cash-tab">
        <div class="flex-1 flex min-h-0">
            <!-- Список смен или детали смены -->
            <div class="flex-1 flex flex-col min-w-0">
                <!-- Header (только для списка смен) -->
                <div v-if="!selectedShift" class="flex items-center gap-4 px-5 py-4 border-b border-white/5">
                    <h1 class="text-base font-medium text-white/90 tracking-wide">Смены</h1>
                    <div class="ml-auto">
                        <select class="bg-white/5 border border-white/10 rounded-lg px-3 py-1.5 text-sm text-gray-400 hover:border-white/20 transition-colors cursor-pointer focus:outline-none focus:border-accent/50">
                            <option>Все точки продаж</option>
                        </select>
                    </div>
                </div>

                <!-- Список смен по датам -->
                <div v-if="!selectedShift" class="flex-1 overflow-y-auto" data-testid="shifts-list">
                    <!-- Заголовок таблицы -->
                    <div class="sticky top-0 z-10 flex items-center px-5 py-2 border-b border-white/5 bg-dark-950/95 backdrop-blur-sm text-[11px] text-gray-500 uppercase tracking-wider">
                        <div class="flex-1 min-w-0"></div>
                        <div class="flex items-center gap-6">
                            <span class="w-12 text-right">Чеков</span>
                            <span class="w-20 text-right">Выручка</span>
                            <span class="w-16 text-right">Ср.чек</span>
                            <span class="w-16 text-right">Нал</span>
                            <span class="w-16 text-right">Карта</span>
                            <span class="w-16 text-right">Онлайн</span>
                            <span class="w-16 text-right">Возврат</span>
                        </div>
                    </div>

                    <template v-for="(dayShifts, dateKey) in shiftsGroupedByDate" :key="dateKey">
                        <!-- Заголовок даты -->
                        <div
                            @click="toggleDateExpand(dateKey)"
                            class="group flex items-center px-5 py-3 border-b border-white/5 cursor-pointer hover:bg-white/[0.02] transition-all duration-200"
                        >
                            <div class="flex-1 min-w-0 flex items-center gap-3">
                                <svg
                                    class="w-4 h-4 flex-shrink-0 text-gray-500 group-hover:text-gray-400 transition-all duration-200"
                                    :class="{ 'rotate-90': expandedDates[dateKey] }"
                                    fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                                <span class="text-white/90 font-medium tabular-nums">{{ dateKey }}</span>
                                <span :class="['text-[11px] px-1.5 py-0.5 rounded font-medium uppercase tracking-wider', getDayClassByKey(dateKey)]">
                                    {{ getDayNameByKey(dateKey) }}
                                </span>
                                <span class="text-gray-600 text-xs">{{ dayShifts.length }} {{ getSmenWord(dayShifts.length) }}</span>
                            </div>
                            <div class="flex items-center gap-6">
                                <span class="w-12 text-right text-gray-400 tabular-nums text-sm">{{ getDayOrdersCount(dayShifts) }}</span>
                                <span class="w-20 text-right text-emerald-400/90 font-medium tabular-nums">{{ formatMoney(getDayTotal(dayShifts)) }}</span>
                                <span class="w-16 text-right text-gray-400 tabular-nums text-sm">{{ formatMoney(getDayAvgCheck(dayShifts)) }}</span>
                                <span class="w-16 text-right text-gray-500 tabular-nums text-sm">{{ formatMoney(getDayCash(dayShifts)) }}</span>
                                <span class="w-16 text-right text-gray-500 tabular-nums text-sm">{{ formatMoney(getDayCard(dayShifts)) }}</span>
                                <span class="w-16 text-right text-gray-500 tabular-nums text-sm">{{ formatMoney(getDayOnline(dayShifts)) }}</span>
                                <span class="w-16 text-right text-gray-500 tabular-nums text-sm">{{ formatMoney(getDayRefunds(dayShifts)) }}</span>
                            </div>
                        </div>

                        <!-- Смены за эту дату -->
                        <div v-if="expandedDates[dateKey]">
                            <template v-for="shift in dayShifts" :key="shift.id">
                                <div class="group flex items-center px-5 py-3 border-b border-white/[0.03] hover:bg-white/[0.02] transition-all duration-150">
                                    <div class="flex-1 min-w-0 flex items-center gap-3 pl-7">
                                        <span
                                            @click.stop="selectShift(shift)"
                                            class="text-accent/90 cursor-pointer hover:text-accent font-medium transition-colors"
                                        >
                                            #{{ shift.shift_number }}
                                        </span>
                                        <!-- Имя кассира -->
                                        <span v-if="shift.cashier" class="text-gray-400 text-sm">
                                            {{ shift.cashier.name }}
                                        </span>
                                        <span class="text-gray-500 text-sm tabular-nums">
                                            {{ formatTime(shift.opened_at) }}
                                            <template v-if="shift.closed_at">
                                                <span class="text-gray-600 mx-0.5">—</span>
                                                {{ formatTime(shift.closed_at) }}
                                            </template>
                                        </span>
                                        <span v-if="shift.status === 'open'" class="flex items-center gap-1.5 text-xs text-emerald-400/90">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                                            Открыта
                                        </span>
                                        <span v-else class="text-xs text-gray-600">Закрыта</span>
                                    </div>
                                    <div class="flex items-center gap-6">
                                        <span class="w-12 text-right text-gray-400 tabular-nums text-sm">{{ shift.orders_count || 0 }}</span>
                                        <span class="w-20 text-right text-white/80 font-medium tabular-nums">{{ formatMoney(shift.total_revenue || 0) }}</span>
                                        <span class="w-16 text-right text-gray-400 tabular-nums text-sm">{{ formatMoney(shift.avg_check || 0) }}</span>
                                        <span class="w-16 text-right text-gray-500 tabular-nums text-sm">{{ formatMoney(shift.total_cash || 0) }}</span>
                                        <span class="w-16 text-right text-gray-500 tabular-nums text-sm">{{ formatMoney(shift.total_card || 0) }}</span>
                                        <span class="w-16 text-right text-gray-500 tabular-nums text-sm">{{ formatMoney(shift.total_online || 0) }}</span>
                                        <span class="w-16 text-right text-gray-500 tabular-nums text-sm">{{ formatMoney(shift.refunds_amount || 0) }}</span>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>

                    <!-- Состояние загрузки -->
                    <div v-if="shiftsLoading" class="flex flex-col items-center justify-center py-20 text-gray-500">
                        <div class="animate-spin w-6 h-6 border-2 border-accent/30 border-t-accent rounded-full mb-3"></div>
                        <p class="text-sm text-gray-500">Загрузка...</p>
                    </div>

                    <!-- Пустое состояние -->
                    <div v-else-if="!shifts.length" class="flex flex-col items-center justify-center py-20">
                        <div class="w-12 h-12 rounded-full bg-white/5 flex items-center justify-center mb-3">
                            <svg class="w-6 h-6 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </div>
                        <p class="text-sm text-gray-500">Нет данных о сменах</p>
                    </div>
                </div>

                <!-- Детали смены -->
                <ShiftDetails
                    v-else
                    :shift="selectedShift"
                    :orders="shiftOrders"
                    :prepayments="prepayments"
                    @back="selectedShift = null"
                />
            </div>
        </div>

        <!-- Нижняя панель -->
        <div class="flex items-center justify-between px-5 py-3 border-t border-white/5 bg-dark-900/50 relative z-10" data-testid="cash-panel">
            <div class="flex items-center gap-2 text-sm" data-testid="current-cash">
                <span class="text-gray-500">В кассе:</span>
                <span class="text-white/90 font-medium tabular-nums">{{ formatMoney(currentCash) }} ₽</span>
            </div>
            <div class="flex items-center gap-2">
                <!-- Операции с кассой (только при открытой смене) -->
                <template v-if="hasOpenShift">
                    <button
                        @click="openDepositModal"
                        data-testid="deposit-btn"
                        class="px-3 py-1.5 text-sm text-emerald-400/90 hover:text-emerald-400 hover:bg-emerald-500/10 rounded-lg transition-all duration-150"
                    >
                        + Внести
                    </button>
                    <button
                        @click="openWithdrawalModal"
                        data-testid="withdrawal-btn"
                        class="px-3 py-1.5 text-sm text-red-400/90 hover:text-red-400 hover:bg-red-500/10 rounded-lg transition-all duration-150"
                    >
                        − Снять
                    </button>
                    <div class="w-px h-5 bg-white/10 mx-1"></div>
                </template>

                <button
                    v-if="!hasOpenShift"
                    @click="showOpenShiftModal = true"
                    data-testid="open-shift-btn"
                    class="px-4 py-1.5 bg-emerald-500 hover:bg-emerald-400 rounded-lg text-sm text-white font-medium transition-all duration-150"
                >
                    Открыть смену
                </button>
                <template v-else>
                    <span class="text-sm text-gray-400">
                        <span class="text-emerald-400/80">#{{ currentShift.shift_number }}</span>
                        <span v-if="currentShift.cashier" class="ml-2 text-gray-500">{{ currentShift.cashier.name }}</span>
                    </span>
                    <button
                        @click="openCloseShiftModal"
                        :disabled="shiftLoading"
                        data-testid="close-shift-btn"
                        class="px-4 py-1.5 bg-red-500/10 hover:bg-red-500/20 border border-red-500/20 hover:border-red-500/30 rounded-lg text-sm text-red-400 hover:text-red-300 transition-all duration-150 disabled:opacity-50"
                    >
                        Закрыть смену
                    </button>
                </template>
            </div>
        </div>

        <!-- Open Shift Modal -->
        <OpenShiftModal
            v-model:show="showOpenShiftModal"
            @opened="onShiftOpened"
        />

        <!-- Close Shift Modal -->
        <CloseShiftModal
            v-model:show="showCloseShiftModal"
            :shift="currentShift"
            @closed="onShiftClosed"
        />

        <!-- Cash Operation Modal -->
        <CashOperationModal
            v-model:show="showCashOperationModal"
            :type="cashOperationType"
            :currentCash="currentCash"
            @completed="onCashOperationCompleted"
        />
    </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { usePosStore } from '../../stores/pos';
import api from '../../api';
import ShiftDetails from './cash/ShiftDetails.vue';
import OpenShiftModal from '../modals/OpenShiftModal.vue';
import CloseShiftModal from '../modals/CloseShiftModal.vue';
import CashOperationModal from '../modals/CashOperationModal.vue';

const posStore = usePosStore();

// State
const selectedShift = ref(null);
const shiftOrders = ref([]);
const prepayments = ref([]);
const expandedDates = ref({});
const shiftLoading = ref(false);
const showOpenShiftModal = ref(false);
const showCloseShiftModal = ref(false);
const showCashOperationModal = ref(false);
const cashOperationType = ref('deposit');

// Computed
const shifts = computed(() => posStore.shifts);
const shiftsLoading = computed(() => posStore.shiftsLoading);
const currentShift = computed(() => posStore.currentShift);
const hasOpenShift = computed(() => currentShift.value && currentShift.value.status === 'open');
const currentCash = computed(() => {
    if (!currentShift.value) return 0;
    return currentShift.value.current_cash || 0;
});

const shiftsGroupedByDate = computed(() => {
    const groups = {};
    shifts.value.forEach(shift => {
        const dateKey = getShiftDateKey(shift);
        if (!groups[dateKey]) {
            groups[dateKey] = [];
        }
        groups[dateKey].push(shift);
    });
    return groups;
});

// Methods
const formatTime = (dt) => {
    if (!dt) return '';
    return new Date(dt).toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' });
};

const formatMoney = (n) => {
    const num = parseFloat(n);
    if (!num || isNaN(num)) return '0';
    return Math.floor(num).toLocaleString('ru-RU');
};

const getShiftDateKey = (shift) => {
    if (!shift.opened_at) return '';
    const d = new Date(shift.opened_at);
    return `${String(d.getDate()).padStart(2, '0')}.${String(d.getMonth() + 1).padStart(2, '0')}`;
};

const getDayNameByKey = (dateKey) => {
    const [day, month] = dateKey.split('.');
    const year = new Date().getFullYear();
    const date = new Date(year, parseInt(month) - 1, parseInt(day));
    const days = ['вс', 'пн', 'вт', 'ср', 'чт', 'пт', 'сб'];
    return days[date.getDay()];
};

const getDayClassByKey = (dateKey) => {
    const [day, month] = dateKey.split('.');
    const year = new Date().getFullYear();
    const date = new Date(year, parseInt(month) - 1, parseInt(day));
    const dayOfWeek = date.getDay();
    if (dayOfWeek === 0) return 'bg-red-500/10 text-red-400/80';
    if (dayOfWeek === 6) return 'bg-blue-500/10 text-blue-400/80';
    return 'bg-white/5 text-gray-500';
};

const getSmenWord = (count) => {
    const lastDigit = count % 10;
    const lastTwoDigits = count % 100;
    if (lastTwoDigits >= 11 && lastTwoDigits <= 14) return 'смен';
    if (lastDigit === 1) return 'смена';
    if (lastDigit >= 2 && lastDigit <= 4) return 'смены';
    return 'смен';
};

const getDayTotal = (dayShifts) => {
    return dayShifts.reduce((sum, s) => sum + (parseFloat(s.total_revenue) || 0), 0);
};

const getDayOrdersCount = (dayShifts) => {
    return dayShifts.reduce((sum, s) => sum + (parseInt(s.orders_count) || 0), 0);
};

const getDayAvgCheck = (dayShifts) => {
    const total = getDayTotal(dayShifts);
    const count = getDayOrdersCount(dayShifts);
    return count > 0 ? Math.round(total / count) : 0;
};

const getDayCash = (dayShifts) => {
    return dayShifts.reduce((sum, s) => sum + (parseFloat(s.total_cash) || 0), 0);
};

const getDayCard = (dayShifts) => {
    return dayShifts.reduce((sum, s) => sum + (parseFloat(s.total_card) || 0), 0);
};

const getDayOnline = (dayShifts) => {
    return dayShifts.reduce((sum, s) => sum + (parseFloat(s.total_online) || 0), 0);
};

const getDayRefunds = (dayShifts) => {
    return dayShifts.reduce((sum, s) => sum + (parseFloat(s.refunds_amount) || 0), 0);
};

const toggleDateExpand = (dateKey) => {
    expandedDates.value = { ...expandedDates.value, [dateKey]: !expandedDates.value[dateKey] };
};

const selectShift = async (shift) => {
    shiftLoading.value = true;
    try {
        const [shiftRes, ordersRes] = await Promise.all([
            api.shifts.get(shift.id),
            api.shifts.getOrders(shift.id)
        ]);
        selectedShift.value = shiftRes;
        shiftOrders.value = ordersRes;

        // Load prepayments
        try {
            const prepRes = await api.shifts.getPrepayments(shift.id);
            prepayments.value = prepRes;
        } catch {
            prepayments.value = [];
        }
    } catch (e) {
        console.error('Error loading shift:', e);
        const msg = e.response?.data?.message || e.message || 'Ошибка загрузки данных смены';
        window.$toast?.(msg, 'error');
        selectedShift.value = shift;
        shiftOrders.value = [];
    } finally {
        shiftLoading.value = false;
    }
};

const openCloseShiftModal = async () => {
    await posStore.loadCurrentShift();
    if (hasOpenShift.value) {
        showCloseShiftModal.value = true;
    } else {
        window.$toast?.('Нет открытой смены', 'error');
    }
};

const onShiftOpened = async () => {
    await posStore.loadShifts();
    await posStore.loadCurrentShift();
};

const onShiftClosed = async () => {
    await posStore.loadShifts();
    await posStore.loadCurrentShift();
    selectedShift.value = null;
};

// Операции с кассой
const openDepositModal = () => {
    if (!hasOpenShift.value) {
        window.$toast?.('Сначала откройте смену', 'error');
        return;
    }
    cashOperationType.value = 'deposit';
    showCashOperationModal.value = true;
};

const openWithdrawalModal = () => {
    if (!hasOpenShift.value) {
        window.$toast?.('Сначала откройте смену', 'error');
        return;
    }
    cashOperationType.value = 'withdrawal';
    showCashOperationModal.value = true;
};

const onCashOperationCompleted = async () => {
    await posStore.loadCurrentShift();
    await posStore.loadShifts();
};

// Expand today's date by default and refresh shift data
onMounted(async () => {
    const today = new Date();
    const todayKey = `${String(today.getDate()).padStart(2, '0')}.${String(today.getMonth() + 1).padStart(2, '0')}`;
    expandedDates.value[todayKey] = true;

    console.log('[CashTab] onMounted - loading shifts data...');
    // Загружаем актуальные данные о сменах при каждом открытии вкладки
    await posStore.loadCurrentShift();
    console.log('[CashTab] currentShift after load:', posStore.currentShift);
    await posStore.loadShifts();
    console.log('[CashTab] shifts count:', posStore.shifts.length);
});

// Watch for shifts updates (triggered by order_paid event)
watch(() => posStore.shiftsVersion, async (newVersion) => {
    if (newVersion > 0 && selectedShift.value) {
        console.log('[CashTab] shiftsVersion changed, reloading selected shift orders...');
        // Перезагружаем заказы выбранной смены
        try {
            const ordersRes = await api.shifts.getOrders(selectedShift.value.id);
            shiftOrders.value = ordersRes;
            // Также обновляем данные самой смены
            const shiftRes = await api.shifts.get(selectedShift.value.id);
            selectedShift.value = shiftRes;
        } catch (e) {
            console.error('[CashTab] Error reloading shift orders:', e);
        }
    }
});
</script>
