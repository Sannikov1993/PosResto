<template>
    <div class="h-full flex flex-col">
        <!-- Header -->
        <div class="flex items-center gap-4 px-4 py-3 border-b border-gray-800 bg-dark-900">
            <h1 class="text-lg font-semibold">Карта зала</h1>

            <!-- Zone Tabs -->
            <div class="flex gap-1 bg-dark-800 rounded-lg p-1">
                <button
                    v-for="zone in zones"
                    :key="zone.id"
                    @click="selectedZone = zone.id"
                    :class="[
                        'px-3 py-1.5 rounded-md text-sm font-medium transition-colors',
                        selectedZone === zone.id ? 'bg-accent text-white' : 'text-gray-400 hover:text-white'
                    ]"
                >
                    {{ zone.name }}
                </button>
            </div>

            <!-- Date Navigation with Calendar -->
            <div class="flex items-center gap-2">
                <button @click="changeDate(-1)" class="p-2 hover:bg-gray-800 rounded-lg text-gray-400 hover:text-white">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                </button>
                <ReservationCalendar
                    :modelValue="floorDate"
                    @change="handleDateChange"
                />
                <button @click="changeDate(1)" class="p-2 hover:bg-gray-800 rounded-lg text-gray-400 hover:text-white">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </button>
            </div>

            <button @click="refresh" class="ml-auto p-2 hover:bg-gray-800 rounded-lg text-gray-400 hover:text-white">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
            </button>

            <!-- Bar Button -->
            <button
                v-if="props.hasBar"
                @click="emit('open-bar')"
                class="relative p-2.5 bg-purple-600/20 hover:bg-purple-600/30 rounded-xl text-purple-400 hover:text-purple-300 transition-colors"
            >
                <span class="text-lg">🍸</span>
                <span
                    v-if="props.barItemsCount > 0"
                    class="absolute -top-1 -right-1 w-5 h-5 bg-orange-500 text-white text-xs font-bold rounded-full flex items-center justify-center"
                >
                    {{ props.barItemsCount > 9 ? '9+' : props.barItemsCount }}
                </span>
            </button>
        </div>

        <!-- Transfer Mode Banner -->
        <div v-if="transferMode" class="bg-orange-500/20 border-b border-orange-500/50 px-4 py-3 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <span class="text-2xl">🔄</span>
                <div>
                    <p class="text-orange-400 font-medium">Режим переноса заказа</p>
                    <p class="text-orange-300/70 text-sm">
                        Перенос со стола {{ sourceTableForTransfer?.number }} — выберите целевой стол
                    </p>
                </div>
            </div>
            <button
                @click="cancelTransfer"
                class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition-colors"
                :disabled="transferLoading"
            >
                {{ transferLoading ? 'Переносим...' : 'Отмена' }}
            </button>
        </div>

        <!-- Floor Map -->
        <div ref="floorContainer" class="flex-1 overflow-hidden p-4 bg-dark-950" :class="{ 'transfer-mode': transferMode }">
            <div v-if="tablesLoading" class="flex items-center justify-center h-full">
                <div class="animate-spin w-8 h-8 border-4 border-accent border-t-transparent rounded-full"></div>
            </div>

            <div v-else-if="zones.length === 0" class="flex flex-col items-center justify-center h-full text-gray-500">
                <svg class="w-16 h-16 mb-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21"/>
                </svg>
                <p class="text-lg font-medium text-gray-400 mb-1">Зал не настроен</p>
                <p class="text-sm text-gray-600">Создайте зоны и столы в редакторе зала (BackOffice)</p>
            </div>

            <FloorMap
                v-else
                :tables="zoneTables"
                :floorObjects="floorObjects"
                :floorScale="floorScale"
                :floorWidth="floorWidth"
                :floorHeight="floorHeight"
                :loading="tablesLoading"
                :selectedTable="selectedTable"
                :selectedTables="selectedTables"
                :multiSelectMode="multiSelectMode"
                :isFloorDateToday="isFloorDateToday"
                :linkedTablesMap="linkedTablesMap"
                :reservations="reservations"
                :barTable="barTable"
                @selectTable="selectTable"
                @showTableContextMenu="showTableContextMenu"
                @showGroupContextMenu="showGroupContextMenu"
                @openLinkedGroupOrder="openLinkedGroupOrder"
                @openLinkedGroupReservation="openLinkedGroupReservation"
                @openTodayReservationModal="openTodayReservationModal"
            />
        </div>

        <!-- Selected Table Panel -->
        <div v-if="selectedTable" class="flex-shrink-0 border-t border-gray-800 bg-dark-900 p-4">
            <div class="flex items-center gap-4">
                <div class="flex items-center gap-3">
                    <div :class="['w-12 h-12 rounded-xl flex items-center justify-center font-bold text-lg', getTableStatusClass(selectedTable.status)]">
                        {{ selectedTable.number }}
                    </div>
                    <div>
                        <p class="text-white font-medium">{{ selectedTable.name || 'Стол ' + selectedTable.number }}</p>
                        <p class="text-gray-500 text-sm">
                            {{ selectedTable.seats }} мест •
                            <template v-if="isFloorDateToday">{{ getTableStatusText(selectedTable.status) }}</template>
                            <template v-else-if="selectedTable.reservations_count > 0">{{ selectedTable.reservations_count }} {{ getReservationWord(selectedTable.reservations_count) }}</template>
                            <template v-else>Свободен</template>
                        </p>
                    </div>
                </div>

                <div class="ml-auto flex items-center gap-3">
                    <!-- Today actions -->
                    <template v-if="isFloorDateToday">
                        <button v-if="selectedTable.status === 'free'"
                                @click="guestCountTable = selectedTable; showGuestCountModal = true"
                                class="px-4 py-2 bg-accent text-white rounded-lg font-medium hover:bg-blue-600">
                            Новый заказ
                        </button>
                        <button v-else-if="selectedTable.status === 'occupied'"
                                @click="openTableOrder(selectedTable.id)"
                                class="px-4 py-2 bg-amber-600 text-white rounded-lg font-medium hover:bg-amber-500">
                            Открыть заказ
                        </button>
                        <button v-else-if="selectedTable.status === 'bill'"
                                @click="openTableOrder(selectedTable.id)"
                                class="px-4 py-2 bg-green-600 text-white rounded-lg font-medium hover:bg-green-500">
                            К оплате
                        </button>
                    </template>

                    <button @click="openReservationModal(selectedTable)"
                            class="px-4 py-2 bg-dark-800 text-gray-300 rounded-lg font-medium hover:bg-gray-700">
                        + Бронь
                    </button>

                    <button @click="selectedTable = null"
                            class="p-2 text-gray-500 hover:text-white">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Multi-Table Selection Panel -->
        <transition name="slide-up">
            <div v-if="multiSelectMode"
                 class="fixed bottom-0 left-64 right-0 bg-dark-800 border-t border-purple-500/50 shadow-2xl p-4"
                 style="z-index: 10000;">
                <div class="max-w-4xl mx-auto flex items-center justify-between gap-4">
                    <div class="flex items-center gap-4">
                        <div class="w-10 h-10 bg-purple-600 rounded-full flex items-center justify-center text-white font-bold animate-pulse">
                            {{ selectedTables.length }}
                        </div>
                        <div>
                            <p class="text-white font-medium flex items-center gap-2">
                                <span class="px-2 py-0.5 bg-purple-600/30 text-purple-300 text-xs rounded-full">МУЛЬТИВЫБОР</span>
                                Выбрано столов: {{ selectedTables.length }}
                            </p>
                            <p class="text-gray-400 text-sm">
                                {{ selectedTables.length > 0 ? `Столы: ${selectedTablesNumbers} • ${selectedTablesSeats} мест` : 'Кликните на столы для добавления' }}
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <button @click="openMultiTableReservation"
                                :disabled="selectedTables.length < 2"
                                :class="[
                                    'px-4 py-2 rounded-lg font-medium transition-colors',
                                    selectedTables.length >= 2
                                        ? 'bg-blue-600 text-white hover:bg-blue-500'
                                        : 'bg-gray-700 text-gray-500 cursor-not-allowed'
                                ]">
                            Бронь на {{ selectedTables.length }} {{ selectedTables.length === 1 ? 'стол' : 'стола' }}
                        </button>
                        <button v-if="isFloorDateToday"
                                @click="openMultiTableOrder"
                                :disabled="selectedTables.length < 2"
                                :class="[
                                    'px-4 py-2 rounded-lg font-medium transition-colors',
                                    selectedTables.length >= 2
                                        ? 'bg-amber-600 text-white hover:bg-amber-500'
                                        : 'bg-gray-700 text-gray-500 cursor-not-allowed'
                                ]">
                            Заказ на {{ selectedTables.length }} {{ selectedTables.length === 1 ? 'стол' : 'стола' }}
                        </button>
                        <button @click="clearTableSelection"
                                class="px-4 py-2 bg-gray-700 text-gray-300 rounded-lg font-medium hover:bg-gray-600">
                            Отмена
                        </button>
                    </div>
                </div>
            </div>
        </transition>

        <!-- Guest Count Modal (Numpad) -->
        <GuestCountModal
            v-model="showGuestCountModal"
            :table="guestCountTable"
            @confirm="handleGuestCountConfirm"
        />

        <!-- Order Modal -->
        <OrderModal
            v-model="showOrderModal"
            :table="orderModalTable"
            :order="orderModalOrder"
            @submit="handleOrderSubmitted"
        />

        <!-- Payment Modal -->
        <PaymentModal
            v-model="showPaymentModal"
            :order="paymentOrder"
            @paid="handlePaymentCompleted"
        />

        <!-- Cancel Order Modal -->
        <CancelOrderModal
            v-model="showCancelOrderModal"
            :order="cancelOrderData"
            :table="cancelOrderTable"
            :canCancelOrders="canCancelOrders"
            @cancelled="onOrderCancelled"
        />

        <!-- Reservation Modal -->
        <ReservationModal
            v-model="showReservationModal"
            :mode="reservationModalMode"
            :table="reservationModalTable"
            :tables="reservationModalTables"
            :reservation="reservationModalData"
            :existingReservations="reservationModalAllReservations"
            :initialDate="floorDate"
            @save="handleReservationSave"
            @seatGuest="handleModalSeatGuest"
            @createPreorder="handleModalCreatePreorder"
        />

        <!-- Table Context Menu (right-click) -->
        <TableContextMenu
            :show="contextMenu.show"
            :x="contextMenu.x"
            :y="contextMenu.y"
            :table="contextMenu.table"
            :isSelected="selectedTables.some(t => t.id === contextMenu.table?.id)"
            :isInLinkedGroup="!!getTableLinkedOrderGroup(contextMenu.table?.id)"
            @close="closeContextMenu"
            @newOrder="handleNewOrder"
            @newReservation="handleNewReservation"
            @openOrder="handleOpenOrder"
            @addItems="handleAddItems"
            @requestBill="handleRequestBill"
            @splitBill="handleSplitBill"
            @moveOrder="handleMoveOrder"
            @cancelOrder="handleCancelOrder"
            @processPayment="handleProcessPayment"
            @viewReservation="handleViewReservation"
            @seatGuests="handleContextMenuSeatGuests"
            @cancelReservation="handleContextMenuCancelReservation"
            @toggleMultiSelect="handleToggleMultiSelect"
        />

        <!-- Reservation Side Panel -->
        <ReservationSidePanel
            :show="showReservationPanel"
            :table="reservationPanelTable"
            :reservation="reservationPanelData"
            :allReservations="reservationPanelAllReservations"
            :preorderItems="reservationPanelPreorderItems"
            :loadingPreorder="loadingPreorder"
            :creatingPreorder="creatingPreorder"
            :seatingGuests="seatingGuests"
            :roundAmounts="posStore.roundAmounts"
            @close="showReservationPanel = false"
            @update="handleReservationUpdate"
            @seatGuests="handleSeatGuests"
            @unseatGuests="handleUnseatGuests"
            @createPreorder="handleCreatePreorder"
            @cancel="handleCancelReservation"
            @switchReservation="handleSwitchReservation"
        />

        <!-- Cancel Reservation Confirm Modal -->
        <ConfirmModal
            v-model="showCancelReservationConfirm"
            title="Отменить бронирование?"
            :message="cancelReservationData ? `Бронирование на ${cancelReservationData.guest_name || 'гостя'} будет отменено.` : 'Бронирование будет отменено.'"
            confirmText="Отменить"
            cancelText="Назад"
            type="danger"
            icon="📅"
            :loading="cancelReservationLoading"
            @confirm="confirmCancelReservation"
        />

        <!-- Table Order Modal (Full-screen order interface) -->
        <TableOrderModal
            v-if="tableOrderModalTableId"
            :show="showTableOrderModal"
            :tableId="tableOrderModalTableId"
            :guests="tableOrderModalGuests"
            :linkedTables="tableOrderModalLinkedTables"
            :reservationId="tableOrderModalReservationId"
            @close="closeTableOrder"
            @orderUpdated="handleTableOrderUpdated"
        />
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { usePosStore } from '../../stores/pos';
import { useAuthStore } from '../../stores/auth';
import FloorMap from '../floor/FloorMap.vue';
import TableContextMenu from '../floor/TableContextMenu.vue';
import GuestCountModal from '../modals/GuestCountModal.vue';
import OrderModal from '../modals/OrderModal.vue';
import PaymentModal from '../modals/PaymentModal.vue';
import ReservationModal from '../modals/ReservationModal.vue';
import ReservationSidePanel from '../floor/ReservationSidePanel.vue';
import ReservationCalendar from '../floor/ReservationCalendar.vue';
import CancelOrderModal from '../../../table-order/modals/CancelOrderModal.vue';
import ConfirmModal from '../modals/ConfirmModal.vue';
import TableOrderModal from '../floor/TableOrderModal.vue';

const props = defineProps({
    hasBar: {
        type: Boolean,
        default: false
    },
    barItemsCount: {
        type: Number,
        default: 0
    }
});

const emit = defineEmits(['open-bar']);

const posStore = usePosStore();
const authStore = useAuthStore();

// Helper для локальной даты (не UTC!)
const getLocalDateString = (date = new Date()) => {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
};

// Local state
// Floor container ref for auto-scaling
const floorContainer = ref(null);
let resizeObserver = null;

// Base floor dimensions (design size)
const BASE_FLOOR_WIDTH = 1200;
const BASE_FLOOR_HEIGHT = 800;

const selectedZone = ref(null);
const selectedTable = ref(null);
const selectedTables = ref([]);
const multiSelectMode = ref(false); // Режим мультивыбора столов
const floorScale = ref(1);
const floorWidth = ref(BASE_FLOOR_WIDTH);
const floorHeight = ref(BASE_FLOOR_HEIGHT);

// Floor objects из store (бар, двери и т.д.)
const floorObjects = computed(() => posStore.floorObjects || []);

// Modal states
const showGuestCountModal = ref(false);
const guestCountTable = ref(null);
const showOrderModal = ref(false);
const orderModalTable = ref(null);
const orderModalOrder = ref(null);
const showPaymentModal = ref(false);
const paymentOrder = ref(null);
const showCancelOrderModal = ref(false);
const cancelOrderTable = ref(null);
const cancelOrderData = ref(null);
const showReservationModal = ref(false);
const reservationModalMode = ref('view');
const reservationModalTable = ref(null);
const reservationModalTables = ref([]); // Для мультивыбора столов
const reservationModalData = ref(null);
const reservationModalAllReservations = ref([]);

// Table order modal (full-screen order interface)
const showTableOrderModal = ref(false);
const tableOrderModalTableId = ref(null);
const tableOrderModalGuests = ref(null);
const tableOrderModalLinkedTables = ref(null);
const tableOrderModalReservationId = ref(null);

const openTableOrder = (tableId, options = {}) => {
    tableOrderModalTableId.value = tableId;
    tableOrderModalGuests.value = options.guests || null;
    tableOrderModalLinkedTables.value = options.linkedTables || null;
    tableOrderModalReservationId.value = options.reservationId || null;
    showTableOrderModal.value = true;
};

const closeTableOrder = () => {
    showTableOrderModal.value = false;
    tableOrderModalTableId.value = null;
    tableOrderModalGuests.value = null;
    tableOrderModalLinkedTables.value = null;
    tableOrderModalReservationId.value = null;
};

const handleTableOrderUpdated = () => {
    // Refresh floor data when order is updated
    posStore.loadTables();
    posStore.loadActiveOrders();
};

// Side panel for viewing reservations
const showReservationPanel = ref(false);
const reservationPanelTable = ref(null);
const reservationPanelData = ref(null);
const reservationPanelAllReservations = ref([]);
const reservationPanelPreorderItems = ref([]);
const loadingPreorder = ref(false);
const creatingPreorder = ref(false);
const seatingGuests = ref(false);

// Confirm modal for reservation cancellation
const showCancelReservationConfirm = ref(false);
const cancelReservationData = ref(null);
const cancelReservationLoading = ref(false);

// Context menu state
const contextMenu = ref({
    show: false,
    x: 0,
    y: 0,
    table: null
});

// Transfer mode state (перенос заказа)
const transferMode = ref(false);
const orderToTransfer = ref(null);
const sourceTableForTransfer = ref(null);
const transferLoading = ref(false);

// Store state
const tables = computed(() => posStore.tables);
const zones = computed(() => posStore.zones?.length ? posStore.zones : []);
const tablesLoading = computed(() => posStore.tablesLoading);
const floorDate = computed(() => posStore.floorDate);
const reservations = computed(() => posStore.reservations);

// Computed: current zone id
const currentZoneId = computed(() => {
    if (selectedZone.value !== null && selectedZone.value !== undefined) {
        return selectedZone.value;
    }
    return zones.value.length > 0 ? zones.value[0].id : null;
});

// Computed: zone tables - фильтруем по выбранной зоне (исключаем бар-столы — они рендерятся отдельно)
const zoneTables = computed(() => {
    if (currentZoneId.value === null) {
        return [];
    }
    return tables.value.filter(t => t.zone_id === currentZoneId.value && !t.is_bar);
});

// Computed: bar table for current zone (with position from floor object)
const barTable = computed(() => {
    if (currentZoneId.value === null) return null;

    const bt = tables.value.find(t => t.is_bar && t.zone_id === currentZoneId.value);
    if (!bt) return null;

    // Override position/size from floor object if available
    const barObj = floorObjects.value.find(o => o.type === 'bar');
    if (barObj) {
        return {
            ...bt,
            position_x: barObj.x,
            position_y: barObj.y,
            width: barObj.width,
            height: barObj.height,
        };
    }
    return bt;
});

// Computed: is floor date today
const isFloorDateToday = computed(() => {
    return floorDate.value === getLocalDateString();
});

// Computed: can cancel orders (по правам из auth store)
const canCancelOrders = computed(() => authStore.canCancelOrders);

// Computed: format floor date
const formatFloorDate = computed(() => {
    const date = new Date(floorDate.value);
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    const dateOnly = new Date(date);
    dateOnly.setHours(0, 0, 0, 0);

    if (dateOnly.getTime() === today.getTime()) return 'Сегодня';

    const tomorrow = new Date(today);
    tomorrow.setDate(tomorrow.getDate() + 1);
    if (dateOnly.getTime() === tomorrow.getTime()) return 'Завтра';

    const yesterday = new Date(today);
    yesterday.setDate(yesterday.getDate() - 1);
    if (dateOnly.getTime() === yesterday.getTime()) return 'Вчера';

    return date.toLocaleDateString('ru-RU', { day: 'numeric', month: 'short' });
});

// Computed: linked tables map (for reservations and orders with multiple tables)
const linkedTablesMap = computed(() => {
    const map = {};

    // Group reservations with multiple tables
    // API возвращает linked_table_ids (дополнительные столы) + table_id (основной стол)
    reservations.value.forEach(res => {
        // Проверяем наличие связанных столов и активный статус брони
        if (res.linked_table_ids && res.linked_table_ids.length > 0) {
            // Статусы, при которых показываем рамку
            const activeStatuses = ['pending', 'confirmed', 'seated'];
            if (!activeStatuses.includes(res.status)) return;

            // Собираем все ID столов: основной + связанные
            const allTableIds = [res.table_id, ...res.linked_table_ids];
            map['res-' + res.id] = {
                type: 'reservation',
                tableIds: allTableIds,
                reservation: res
            };
        }
    });

    // Group orders with multiple tables
    // This would come from active orders with linked_table_ids
    tables.value.forEach(table => {
        if (table.active_order?.linked_table_ids?.length > 1) {
            const orderId = table.active_order.id;
            if (!map['order-' + orderId]) {
                map['order-' + orderId] = {
                    type: 'order',
                    tableIds: table.active_order.linked_table_ids,
                    order: table.active_order
                };
            }
        }
    });

    return map;
});

// Helper: get linked order group for a table
const getTableLinkedOrderGroup = (tableId) => {
    for (const [key, group] of Object.entries(linkedTablesMap.value)) {
        if (group.type === 'order' && group.tableIds.includes(tableId)) {
            return group;
        }
    }
    return null;
};

// Computed: selected tables info
const selectedTablesNumbers = computed(() => {
    return selectedTables.value.map(t => t.number).join(', ');
});

const selectedTablesSeats = computed(() => {
    return selectedTables.value.reduce((sum, t) => sum + (t.seats || 4), 0);
});

// Methods
// Calculate floor scale based on container size
const calculateFloorScale = () => {
    if (!floorContainer.value) return;
    const container = floorContainer.value;
    const containerWidth = container.clientWidth - 32; // padding
    const containerHeight = container.clientHeight - 32;
    
    // Calculate scale to fit the floor in container
    const scaleX = containerWidth / BASE_FLOOR_WIDTH;
    const scaleY = containerHeight / BASE_FLOOR_HEIGHT;
    const scale = Math.min(scaleX, scaleY, 1.5); // max scale 1.5
    
    floorScale.value = Math.max(0.5, scale); // min scale 0.5
    floorWidth.value = BASE_FLOOR_WIDTH * floorScale.value;
    floorHeight.value = BASE_FLOOR_HEIGHT * floorScale.value;
};


const refresh = () => {
    posStore.loadTables();
    posStore.loadActiveOrders();
    posStore.loadReservations(floorDate.value);
};

const changeDate = (days) => {
    const date = new Date(floorDate.value);
    date.setDate(date.getDate() + days);
    const dateStr = getLocalDateString(date);
    posStore.setFloorDate(dateStr);
    posStore.loadReservations(dateStr);
};

const goToToday = async () => {
    // Получаем "рабочую дату" (учитывает работу после полуночи)
    try {
        const response = await fetch('/api/reservations/business-date');
        const data = await response.json();
        if (data.success && data.data?.business_date) {
            posStore.setFloorDate(data.data.business_date);
            posStore.loadReservations(data.data.business_date);
            return;
        }
    } catch (e) {
        console.warn('Failed to get business date:', e);
    }
    // Fallback на календарную дату
    const today = getLocalDateString();
    posStore.setFloorDate(today);
    posStore.loadReservations(today);
};
const handleDateChange = (dateStr) => {
    posStore.setFloorDate(dateStr);
    posStore.loadReservations(dateStr);
};

const selectTable = async (table) => {
    // Если режим переноса включен - выполняем перенос
    if (transferMode.value) {
        await handleTransferToTable(table);
        return;
    }

    // Если режим мультивыбора включен - добавляем/убираем стол из выбора
    if (multiSelectMode.value) {
        const idx = selectedTables.value.findIndex(t => t.id === table.id);
        if (idx >= 0) {
            selectedTables.value.splice(idx, 1);
            // Если больше нет выбранных столов - выключаем режим
            if (selectedTables.value.length === 0) {
                multiSelectMode.value = false;
            }
        } else {
            selectedTables.value.push(table);
        }
        return;
    }

    // Для будущих дат - показываем панель для бронирования
    if (!isFloorDateToday.value) {
        selectedTable.value = table;
        return;
    }

    // Если стол входит в связанную группу (объединенный заказ) - открываем модальное окно заказа
    const linkedOrderGroup = getTableLinkedOrderGroup(table.id);
    if (linkedOrderGroup) {
        openTableOrder(table.id);
        return;
    }

    // Если гости сидят по брони (seated) - открываем модальное окно заказа
    if (table.next_reservation?.status === 'seated') {
        openTableOrder(table.id);
        return;
    }

    // Если на столе есть активный заказ - открываем модальное окно заказа
    if (table.active_orders_total > 0 || table.status === 'occupied' || table.status === 'bill') {
        openTableOrder(table.id);
        return;
    }

    // Для нового заказа - показываем модал выбора гостей (нумпад)
    guestCountTable.value = table;
    showGuestCountModal.value = true;
};

const toggleTableSelection = (table) => {
    const idx = selectedTables.value.findIndex(t => t.id === table.id);
    if (idx >= 0) {
        selectedTables.value.splice(idx, 1);
    } else {
        selectedTables.value.push(table);
    }
};

const clearTableSelection = () => {
    selectedTables.value = [];
    multiSelectMode.value = false;
};

const showTableContextMenu = (event, table) => {
    // Позиционируем меню с учетом границ экрана
    const menuWidth = 220;
    const menuHeight = 300;
    let x = event.clientX;
    let y = event.clientY;

    // Корректировка по горизонтали
    if (x + menuWidth > window.innerWidth) {
        x = window.innerWidth - menuWidth - 10;
    }

    // Корректировка по вертикали
    if (y + menuHeight > window.innerHeight) {
        y = window.innerHeight - menuHeight - 10;
    }

    contextMenu.value = {
        show: true,
        x,
        y,
        table
    };
};

const closeContextMenu = () => {
    contextMenu.value.show = false;
};

// Context menu handlers
const handleNewOrder = () => {
    closeContextMenu();
    guestCountTable.value = contextMenu.value.table;
    showGuestCountModal.value = true;
};

const handleNewReservation = () => {
    closeContextMenu();
    const table = contextMenu.value.table;
    // Открываем пустую форму для НОВОЙ брони
    reservationModalTable.value = table;
    reservationModalMode.value = 'today';
    reservationModalData.value = null; // Важно: null для новой брони
    // Передаём существующие брони для отображения занятых слотов на таймлайне
    reservationModalAllReservations.value = table.all_reservations ||
        table.reservations ||
        posStore.getTableReservations(table.id) ||
        [];
    showReservationModal.value = true;
};

const handleOpenOrder = () => {
    closeContextMenu();
    openTableOrder(contextMenu.value.table.id);
};

const handleAddItems = () => {
    closeContextMenu();
    openTableOrder(contextMenu.value.table.id);
};

const handleRequestBill = () => {
    closeContextMenu();
    openTableOrder(contextMenu.value.table.id);
};

const handleSplitBill = () => {
    closeContextMenu();
    openTableOrder(contextMenu.value.table.id);
};

const handleMoveOrder = () => {
    const table = contextMenu.value.table;
    closeContextMenu();

    // Проверяем что на столе есть заказ
    if (!table.active_order && !table.active_orders_total) {
        alert('На этом столе нет активного заказа');
        return;
    }

    // Активируем режим переноса
    transferMode.value = true;
    sourceTableForTransfer.value = table;
    orderToTransfer.value = table.active_order;
    selectedTable.value = null; // Сбрасываем выбранный стол
};

// Отмена режима переноса
const cancelTransfer = () => {
    transferMode.value = false;
    orderToTransfer.value = null;
    sourceTableForTransfer.value = null;
};

// Выполнить перенос заказа на целевой стол
const handleTransferToTable = async (targetTable) => {
    // Нельзя перенести на тот же стол
    if (targetTable.id === sourceTableForTransfer.value?.id) {
        alert('Выберите другой стол');
        return;
    }

    transferLoading.value = true;

    try {
        const orderId = orderToTransfer.value?.id || sourceTableForTransfer.value?.active_order?.id;

        if (!orderId) {
            // Если нет ID заказа, пробуем получить его через API
            const tableData = await fetch(`/api/tables/${sourceTableForTransfer.value.id}`).then(r => r.json());
            if (!tableData.data?.active_order?.id) {
                throw new Error('Не удалось найти заказ для переноса');
            }
        }

        const response = await fetch(`/api/orders/${orderId}/transfer`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
            },
            body: JSON.stringify({
                target_table_id: targetTable.id
            })
        });

        const data = await response.json();

        if (data.success) {
            // Успех - обновляем данные
            await posStore.loadTables(true);
            alert(data.message);
        } else {
            alert(data.message || 'Ошибка при переносе заказа');
        }
    } catch (error) {
        console.error('Transfer error:', error);
        alert('Ошибка при переносе заказа: ' + error.message);
    } finally {
        transferLoading.value = false;
        cancelTransfer();
    }
};

// Отмена заказа через контекстное меню
const handleCancelOrder = async () => {
    const table = contextMenu.value.table;
    closeContextMenu();

    // Проверяем что на столе есть заказ
    if (!table.active_order && !table.active_orders_total) {
        alert('На этом столе нет активного заказа');
        return;
    }

    // Загружаем полные данные всех заказов на столе
    try {
        const response = await fetch(`/api/tables/${table.id}/orders`);
        const data = await response.json();

        if (data.success && data.data?.length > 0) {
            // Сохраняем все заказы для отмены
            cancelOrderTable.value = table;
            cancelOrderData.value = data.data; // Массив всех заказов
            showCancelOrderModal.value = true;
        } else {
            alert('Не удалось загрузить заказы');
        }
    } catch (error) {
        console.error('Error loading orders:', error);
        alert('Ошибка загрузки заказов');
    }
};

// Обработчик успешной отмены заказа
const onOrderCancelled = async () => {
    showCancelOrderModal.value = false;
    cancelOrderTable.value = null;
    cancelOrderData.value = null;
    // Обновляем данные столов
    await posStore.loadTables(true);
};

const handleProcessPayment = () => {
    closeContextMenu();
    if (contextMenu.value.table.active_order) {
        paymentOrder.value = contextMenu.value.table.active_order;
        showPaymentModal.value = true;
    } else {
        openTableOrder(contextMenu.value.table.id);
    }
};

const handleViewReservation = () => {
    closeContextMenu();
    const table = contextMenu.value.table;
    // Открываем существующую бронь для просмотра/редактирования
    openReservationModal(table, table.next_reservation);
};

const handleContextMenuSeatGuests = async () => {
    closeContextMenu();
    const table = contextMenu.value.table;
    if (table.next_reservation) {
        // Создаём заказ через API
        try {
            const response = await fetch(`/api/reservations/${table.next_reservation.id}/seat-with-order`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
                }
            });
            const data = await response.json();
            // После создания заказа открываем модальное окно
            openTableOrder(table.id, { reservationId: table.next_reservation.id });
        } catch (e) {
            console.error('Failed to seat guests', e);
        }
    }
};

// Обработчики для ReservationModal
const handleModalSeatGuest = (reservation) => {
    handleSeatGuests(reservation, reservationModalTable.value);
};

const handleModalCreatePreorder = (reservation) => {
    showReservationModal.value = false;
    openTableOrder(reservationModalTable.value.id, { reservationId: reservation.id });
};

const handleReservationSave = (savedReservation) => {
    // Обновляем список бронирований после создания/редактирования
    refresh();
};

const handleContextMenuCancelReservation = () => {
    const table = contextMenu.value.table;
    closeContextMenu();
    if (table.next_reservation) {
        cancelReservationData.value = table.next_reservation;
        showCancelReservationConfirm.value = true;
    }
};

const handleToggleMultiSelect = () => {
    const table = contextMenu.value.table;

    // Если стол уже в выборе - убираем его
    const idx = selectedTables.value.findIndex(t => t.id === table.id);
    if (idx >= 0) {
        selectedTables.value.splice(idx, 1);
        // Если больше нет выбранных столов - выключаем режим
        if (selectedTables.value.length === 0) {
            multiSelectMode.value = false;
        }
    } else {
        // Включаем режим мультивыбора и добавляем стол
        multiSelectMode.value = true;
        selectedTables.value.push(table);
        // Сбрасываем одиночный выбор
        selectedTable.value = null;
    }
};

const showGroupContextMenu = (event, group) => {
    // TODO: Implement group context menu
    // TODO: Implement group context menu
};

const openLinkedGroupOrder = (group) => {
    // Открываем заказ по первому столу из группы
    if (group.tableIds && group.tableIds.length > 0) {
        openTableOrder(group.tableIds[0]);
    }
};

const openLinkedGroupReservation = (group) => {
    // Открываем бронь в панели просмотра
    if (group.reservation) {
        const reservation = group.reservation;
        const table = tables.value.find(t => t.id === reservation.table_id) || { id: reservation.table_id };
        openTodayReservationModal(reservation);
    }
};

const openTodayReservationModal = async (tableOrReservation) => {
    // Определяем что пришло - table или reservation
    // Если есть table_id - это reservation, если есть seats - это table
    const isReservation = tableOrReservation.table_id && !tableOrReservation.seats;

    let table, reservation;

    if (isReservation) {
        // Пришла бронь - находим стол
        reservation = tableOrReservation;
        table = tables.value.find(t => t.id === reservation.table_id) || { id: reservation.table_id };
    } else {
        // Пришёл стол
        table = tableOrReservation;
        reservation = table.next_reservation;
    }

    reservationPanelTable.value = table;

    // Активные статусы броней (включая seated - гости за столом)
    const activeStatuses = ['pending', 'confirmed', 'seated'];
    const currentDate = floorDate.value;

    // Собираем брони для этого стола: только активные и на текущую дату
    const tableReservations = reservations.value.filter(r =>
        r.table_id === table.id &&
        activeStatuses.includes(r.status) &&
        r.date === currentDate
    );

    const allTableRes = tableReservations.length > 0
        ? tableReservations
        : (table.all_reservations || table.reservations || [reservation].filter(Boolean))
            .filter(r => r && activeStatuses.includes(r.status) && r.date === currentDate);

    // Сортировка: будущие брони первыми, затем прошедшие
    const now = new Date();
    const currentMinutes = now.getHours() * 60 + now.getMinutes();
    const getMinutes = (timeStr) => {
        if (!timeStr) return 0;
        const [h, m] = timeStr.split(':').map(Number);
        return h * 60 + m;
    };

    const sortedReservations = [...allTableRes].sort((a, b) => {
        const aMinutes = getMinutes(a.time_from);
        const bMinutes = getMinutes(b.time_from);
        const aIsPast = aMinutes < currentMinutes;
        const bIsPast = bMinutes < currentMinutes;

        if (!aIsPast && !bIsPast) return aMinutes - bMinutes;
        if (aIsPast && bIsPast) return aMinutes - bMinutes;
        return aIsPast ? 1 : -1;
    });

    // Если кликнули на конкретную бронь - добавляем её в список если её там нет
    if (isReservation && reservation && !sortedReservations.find(r => r.id === reservation.id)) {
        sortedReservations.unshift(reservation);
    }

    // Если нет броней вообще - открываем модальное окно заказа
    if (sortedReservations.length === 0 && !reservation) {
        openTableOrder(table.id);
        return;
    }

    reservationPanelAllReservations.value = sortedReservations.length > 0 ? sortedReservations : [reservation].filter(Boolean);
    // Если кликнули на конкретную бронь - используем её, иначе первую отсортированную (ближайшую)
    reservationPanelData.value = isReservation ? reservation : (sortedReservations[0] || reservation);

    reservationPanelPreorderItems.value = [];
    showReservationPanel.value = true;

    // Load preorder items if reservation has an order
    const activeReservation = reservationPanelData.value;
    if (activeReservation?.order_id) {
        loadingPreorder.value = true;
        try {
            const response = await fetch(`/api/orders/${activeReservation.order_id}`);
            const data = await response.json();
            if (data.success && data.data?.items) {
                reservationPanelPreorderItems.value = data.data.items.map(item => ({
                    id: item.id,
                    name: item.dish?.name || item.name,
                    quantity: item.quantity,
                    price: item.price,
                    total: item.price * item.quantity,
                    comment: item.comment
                }));
            }
        } catch (e) {
            console.error('Failed to load preorder', e);
        } finally {
            loadingPreorder.value = false;
        }
    }
};

const handleSeatGuests = async (reservation, table) => {
    seatingGuests.value = true;
    try {
        // Создаём заказ и конвертируем предзаказ если есть
        const response = await fetch(`/api/reservations/${reservation.id}/seat-with-order`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
            }
        });
        const data = await response.json();

        showReservationPanel.value = false;
        showReservationModal.value = false;

        // Открываем модальное окно заказа
        openTableOrder(table.id, { reservationId: reservation.id });
    } catch (e) {
        console.error('Failed to seat guests', e);
    } finally {
        seatingGuests.value = false;
    }
};

const handleUnseatGuests = async (reservation, table) => {
    seatingGuests.value = true;
    try {
        const response = await fetch(`/api/reservations/${reservation.id}/unseat`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
            }
        });
        const data = await response.json();

        if (data.success || response.ok) {
            // Обновляем данные
            await posStore.loadReservations(floorDate.value);
            await posStore.loadTables();

            // Обновляем текущую бронь в панели
            if (data.data?.reservation) {
                reservationPanelData.value = data.data.reservation;
            }
        }
    } catch (e) {
        console.error('Failed to unseat guests', e);
    } finally {
        seatingGuests.value = false;
    }
};

const handleCreatePreorder = (reservation) => {
    creatingPreorder.value = true;
    showReservationPanel.value = false;
    // Открываем модальное окно заказа с бронью
    openTableOrder(reservationPanelTable.value.id, { reservationId: reservation.id });
};

const handleCancelReservation = (reservation) => {
    cancelReservationData.value = reservation;
    showCancelReservationConfirm.value = true;
};

const confirmCancelReservation = async () => {
    if (!cancelReservationData.value) return;

    cancelReservationLoading.value = true;
    try {
        const response = await fetch(`/api/reservations/${cancelReservationData.value.id}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                'Accept': 'application/json'
            }
        });
        const data = await response.json();

        if (!response.ok || !data.success) {
            // Показываем ошибку от сервера (например, про депозит)
            window.$toast?.(data.message || 'Ошибка при удалении бронирования', 'error');
            return;
        }

        showCancelReservationConfirm.value = false;
        showReservationPanel.value = false;
        cancelReservationData.value = null;
        refresh();
        window.$toast?.('Бронирование удалено', 'success');
    } catch (e) {
        console.error('Failed to cancel reservation', e);
        window.$toast?.('Ошибка при удалении бронирования', 'error');
    } finally {
        cancelReservationLoading.value = false;
    }
};

const handleReservationUpdate = (updatedReservation) => {
    reservationPanelData.value = updatedReservation;
    refresh();
};

const handleSwitchReservation = (newReservation) => {
    reservationPanelData.value = newReservation;
    // Можно подгрузить предзаказ для новой брони если нужно
};

// viewTableOrder removed - use openTableOrder instead

const showTableBill = (table) => {
    if (table.active_order) {
        paymentOrder.value = table.active_order;
        showPaymentModal.value = true;
    }
};

const openReservationModal = (table, existingReservation = null) => {
    reservationModalTable.value = table;
    reservationModalTables.value = []; // Сбрасываем мультивыбор
    reservationModalMode.value = 'today';
    // Если передана бронь - редактируем её, иначе создаём новую
    reservationModalData.value = existingReservation;
    // Передаём все бронирования на стол для переключения
    reservationModalAllReservations.value = table.all_reservations ||
        table.reservations ||
        (table.next_reservation ? [table.next_reservation] : []);
    showReservationModal.value = true;
};

const openMultiTableReservation = () => {
    // Бронь на несколько столов
    if (selectedTables.value.length < 2) return;

    // Устанавливаем столы для модала
    reservationModalTables.value = [...selectedTables.value];
    reservationModalTable.value = selectedTables.value[0]; // Основной стол
    reservationModalMode.value = 'today';
    reservationModalData.value = null; // Новая бронь
    reservationModalAllReservations.value = [];

    // Сбрасываем мультиселект
    clearTableSelection();

    // Открываем модал
    showReservationModal.value = true;
};

const openMultiTableOrder = () => {
    // Проверяем, есть ли среди выбранных столов занятые (с активными заказами)
    const occupiedTables = selectedTables.value.filter(table => {
        // Проверяем статус стола
        if (table.status === 'occupied' || table.status === 'bill') return true;
        // Проверяем наличие активного заказа
        if (table.active_orders_total > 0) return true;
        // Проверяем, входит ли стол в связанную группу с заказом
        if (getTableLinkedOrderGroup(table.id)) return true;
        return false;
    });

    if (occupiedTables.length > 0) {
        const tableNames = occupiedTables.map(t => t.name || t.number).join(', ');
        window.$toast?.(`Столы ${tableNames} уже заняты. Выберите свободные столы для заказа.`, 'error');
        return;
    }

    // Заказ на несколько столов - показываем numpad для выбора количества гостей
    const firstTable = selectedTables.value[0];
    guestCountTable.value = firstTable;
    showGuestCountModal.value = true;
};

// Handler for guest count confirmation
const handleGuestCountConfirm = ({ table, guests }) => {
    // Проверяем, есть ли мультивыбор столов
    if (multiSelectMode.value && selectedTables.value.length > 1) {
        // Открываем модальное окно заказа с несколькими столами
        const tableIds = selectedTables.value.map(t => t.id).join(',');
        clearTableSelection(); // Сбрасываем мультиселект
        openTableOrder(table.id, { guests, linkedTables: tableIds });
    } else {
        // Открываем модальное окно заказа с одним столом
        openTableOrder(table.id, { guests });
    }
};

// Handler for order submitted
const handleOrderSubmitted = (order) => {
    showOrderModal.value = false;
    selectedTable.value = null;
    refresh();
};

// Handler for payment completed
const handlePaymentCompleted = ({ order }) => {
    showPaymentModal.value = false;
    selectedTable.value = null;
    refresh();
};

const getTableStatusClass = (status) => {
    const classes = {
        free: 'table-free',
        occupied: 'table-occupied',
        reserved: 'table-reserved',
        bill: 'table-bill',
        ready: 'table-ready'
    };
    return classes[status] || classes.free;
};

const getTableStatusText = (status) => {
    const texts = {
        free: 'Свободен',
        occupied: 'Занят',
        reserved: 'Бронь',
        bill: 'Счёт',
        ready: 'Готов'
    };
    return texts[status] || 'Свободен';
};

const getReservationWord = (count) => {
    if (count === 1) return 'бронь';
    if (count >= 2 && count <= 4) return 'брони';
    return 'броней';
};

// Watch zones and set default
watch(zones, (newZones) => {
    if (newZones.length > 0 && selectedZone.value === null) {
        // Выбираем первую зону по умолчанию
        selectedZone.value = newZones[0].id;
        // Обновляем объекты зала для этой зоны
        posStore.updateFloorObjects(newZones[0]);
    }
}, { immediate: true });

// Watch selected zone and update floor objects
watch(selectedZone, (newZoneId) => {
    if (newZoneId) {
        const zone = zones.value.find(z => z.id === newZoneId);
        if (zone) {
            posStore.updateFloorObjects(zone);
        }
    }
});

// Lifecycle
onMounted(async () => {
    // Если нет столов или зон - загружаем всё через loadInitialData
    if (!tables.value.length || !zones.value.length) {
        await posStore.loadInitialData();
    }
    posStore.loadReservations(floorDate.value);

    // Setup ResizeObserver for auto-scaling
    if (floorContainer.value) {
        calculateFloorScale();
        resizeObserver = new ResizeObserver(() => {
            calculateFloorScale();
        });
        resizeObserver.observe(floorContainer.value);
    }
});

onUnmounted(() => {
    if (resizeObserver) {
        resizeObserver.disconnect();
    }
});
</script>

<style scoped>
.slide-up-enter-active,
.slide-up-leave-active {
    transition: transform 0.3s ease, opacity 0.3s ease;
}
.slide-up-enter-from,
.slide-up-leave-to {
    transform: translateY(100%);
    opacity: 0;
}
</style>
