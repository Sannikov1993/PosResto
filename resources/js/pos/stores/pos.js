/**
 * POS Store - Главное хранилище данных POS
 */

import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import api from '../api';
import { setTimezone } from '../../utils/timezone';
import { setRoundAmounts } from '../../utils/formatAmount';

export const usePosStore = defineStore('pos', () => {
    // ==================== STATE ====================

    // Tables & Floor
    const tables = ref([]);
    const zones = ref([]);
    const floorObjects = ref([]); // Декоративные объекты (бар, двери и т.д.)
    const floorWidth = ref(1200);
    const floorHeight = ref(800);
    const tablesLoading = ref(false);

    // Orders
    const orders = ref([]);
    const activeOrders = ref([]);
    const paidOrders = ref([]);

    // Shifts
    const shifts = ref([]);
    const currentShift = ref(null);
    const shiftsLoading = ref(false);

    // Reservations
    const reservations = ref([]);
    // Используем локальную дату, а не UTC
    const getLocalDateString = () => {
        const now = new Date();
        const year = now.getFullYear();
        const month = String(now.getMonth() + 1).padStart(2, '0');
        const day = String(now.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    };
    const floorDate = ref(getLocalDateString());

    // Delivery
    const deliveryOrders = ref([]);
    const couriers = ref([]);

    // Customers
    const customers = ref([]);

    // Stop List
    const stopList = ref([]);
    const stopListDishIds = ref(new Set());

    // Write-offs & Cancellations
    const writeOffs = ref([]);
    const pendingCancellations = ref([]);

    // Menu
    const menuCategories = ref([]);
    const menuDishes = ref([]);

    // UI State
    const selectedTable = ref(null);
    const selectedZone = ref(null);

    // General Settings
    const roundAmounts = ref(false);
    const timezone = ref('Europe/Moscow');

    // ==================== COMPUTED ====================

    const pendingCancellationsCount = computed(() => pendingCancellations.value.length);

    const pendingDeliveryCount = computed(() => {
        return deliveryOrders.value.filter(o => o.delivery_status === 'pending').length;
    });

    const activeOrdersMap = computed(() => {
        const map = new Map();
        activeOrders.value.forEach(order => {
            if (order.table_id) {
                map.set(order.table_id, order);
            }
        });
        return map;
    });

    const tableReservationsMap = computed(() => {
        const map = new Map();
        reservations.value
            .filter(r => ['pending', 'confirmed'].includes(r.status))
            .forEach(r => {
                if (!map.has(r.table_id)) {
                    map.set(r.table_id, []);
                }
                map.get(r.table_id).push(r);
            });
        return map;
    });

    // ==================== ACTIONS ====================

    // Load all initial data
    const loadInitialData = async () => {
        tablesLoading.value = true;
        shiftsLoading.value = true;

        try {
            const [
                tablesRes,
                zonesRes,
                shiftsRes,
                paidOrdersRes,
                currentShiftRes,
                activeOrdersRes,
                deliveryRes
            ] = await Promise.all([
                api.tables.getAll().catch(() => []),
                api.zones.getAll().catch(() => []),
                api.shifts.getAll().catch(() => []),
                api.orders.getPaidToday().catch(() => []),
                api.shifts.getCurrent().catch(() => null),
                api.orders.getActive().catch(() => []),
                api.orders.getDelivery().catch(() => [])
            ]);

            tables.value = tablesRes;
            zones.value = zonesRes;

            // Обновляем объекты зала для первой зоны
            if (zonesRes.length > 0) {
                updateFloorObjects(zonesRes[0]);
            }
            shifts.value = shiftsRes;
            paidOrders.value = paidOrdersRes;
            currentShift.value = currentShiftRes;
            activeOrders.value = activeOrdersRes;
            deliveryOrders.value = Array.isArray(deliveryRes) ? deliveryRes : (deliveryRes?.data || []);

            // Load reservations for today
            await loadReservations(floorDate.value);

            // Load stop list (needed for blocking dishes in orders/delivery)
            try {
                const stopListRes = await api.stopList.getAll();
                stopList.value = Array.isArray(stopListRes) ? stopListRes : (stopListRes?.data || []);
                stopListDishIds.value = new Set(stopList.value.map(item => item.dish_id));
            } catch (e) {
                console.warn('[POS] Failed to load stop list:', e);
            }

            // Load general settings (rounding, timezone, etc.)
            try {
                const response = await fetch('/api/settings/general');
                const data = await response.json();
                if (data.success && data.data) {
                    roundAmounts.value = data.data.round_amounts || false;
                    // Синхронизируем с утилитой форматирования сумм
                    setRoundAmounts(roundAmounts.value);
                    if (data.data.timezone) {
                        timezone.value = data.data.timezone;
                        setTimezone(data.data.timezone);
                    }
                }
            } catch (e) {
                console.warn('[POS] Failed to load general settings:', e);
            }
        } catch (error) {
            console.error('[POS] Error loading initial data:', error);
        } finally {
            tablesLoading.value = false;
            shiftsLoading.value = false;
        }
    };

    // Tables
    const loadTables = async () => {
        tablesLoading.value = true;
        try {
            tables.value = await api.tables.getAll();
        } finally {
            tablesLoading.value = false;
        }
    };

    // Обновить объекты зала (декор) для выбранной зоны
    const updateFloorObjects = (zone) => {
        if (!zone) {
            floorObjects.value = [];
            return;
        }
        const layout = zone.floor_layout || {};
        floorObjects.value = layout.objects || [];
        floorWidth.value = layout.width || 1200;
        floorHeight.value = layout.height || 800;
    };

    // Orders
    const loadActiveOrders = async () => {
        activeOrders.value = await api.orders.getActive();
    };

    const loadPaidOrders = async () => {
        paidOrders.value = await api.orders.getPaidToday();
    };

    // Reservations
    const loadReservations = async (date) => {
        try {
            reservations.value = await api.reservations.getByDate(date);
        } catch (error) {
            console.error('[POS] Error loading reservations:', error);
            reservations.value = [];
        }
    };

    const setFloorDate = async (date) => {
        floorDate.value = date;
        await loadReservations(date);
    };

    // Shifts
    const loadShifts = async () => {
        shiftsLoading.value = true;
        try {
            shifts.value = await api.shifts.getAll();
        } finally {
            shiftsLoading.value = false;
        }
    };

    const loadCurrentShift = async () => {
        currentShift.value = await api.shifts.getCurrent();
    };

    // Delivery
    const loadDeliveryOrders = async () => {
        deliveryOrders.value = await api.orders.getDelivery();
    };

    const loadCouriers = async () => {
        couriers.value = await api.couriers.getAll();
    };

    // Customers
    const loadCustomers = async () => {
        customers.value = await api.customers.getAll();
    };

    // Stop List
    const loadStopList = async () => {
        stopList.value = await api.stopList.getAll();
        stopListDishIds.value = new Set(stopList.value.map(item => item.dish_id));
    };

    // Write-offs
    const loadWriteOffs = async (dateFrom = null, dateTo = null) => {
        const params = {};
        if (dateFrom) params.date_from = dateFrom;
        if (dateTo) params.date_to = dateTo;

        // Загружаем и новые списания, и отменённые заказы
        const [newWriteOffs, cancelledOrders] = await Promise.all([
            api.writeOffs.getAll(params).catch(() => []),
            api.writeOffs.getCancelledOrders(params).catch(() => [])
        ]);

        // Объединяем и сортируем по дате
        const combined = [...(newWriteOffs || []), ...(cancelledOrders || [])];
        combined.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
        writeOffs.value = combined;
    };

    const loadPendingCancellations = async () => {
        pendingCancellations.value = await api.cancellations.getPending();
    };

    // Menu
    const loadMenu = async () => {
        const [categories, dishes] = await Promise.all([
            api.menu.getCategories(),
            api.menu.getDishes()
        ]);
        menuCategories.value = categories;
        menuDishes.value = dishes;
    };

    // Get table status
    const getTableStatus = (table) => {
        const activeOrder = activeOrdersMap.value.get(table.id);
        if (activeOrder) {
            if (activeOrder.bill_requested) return 'bill';
            if (activeOrder.status === 'ready') return 'ready';
            return 'occupied';
        }

        const tableReservations = tableReservationsMap.value.get(table.id);
        if (tableReservations && tableReservations.length > 0) {
            return 'reserved';
        }

        return 'free';
    };

    // Get table order
    const getTableOrder = (tableId) => {
        return activeOrdersMap.value.get(tableId) || null;
    };

    // Get table reservations
    const getTableReservations = (tableId) => {
        return tableReservationsMap.value.get(tableId) || [];
    };

    return {
        // State
        tables,
        zones,
        floorObjects,
        floorWidth,
        floorHeight,
        tablesLoading,
        orders,
        activeOrders,
        paidOrders,
        shifts,
        currentShift,
        shiftsLoading,
        reservations,
        floorDate,
        deliveryOrders,
        couriers,
        customers,
        stopList,
        stopListDishIds,
        writeOffs,
        pendingCancellations,
        menuCategories,
        menuDishes,
        selectedTable,
        selectedZone,
        roundAmounts,
        timezone,

        // Computed
        pendingCancellationsCount,
        pendingDeliveryCount,
        activeOrdersMap,
        tableReservationsMap,

        // Actions
        loadInitialData,
        loadTables,
        updateFloorObjects,
        loadActiveOrders,
        loadPaidOrders,
        loadReservations,
        setFloorDate,
        loadShifts,
        loadCurrentShift,
        loadDeliveryOrders,
        loadCouriers,
        loadCustomers,
        loadStopList,
        loadWriteOffs,
        loadPendingCancellations,
        loadMenu,
        getTableStatus,
        getTableOrder,
        getTableReservations
    };
});
