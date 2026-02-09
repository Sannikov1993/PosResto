/**
 * POS Store — фасад, композирующий доменные store-ы
 *
 * Обратная совместимость: все потребители продолжают использовать usePosStore().
 * Новый код может импортировать доменные store-ы напрямую.
 */

import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import api from '../api';
import { setTimezone } from '../../utils/timezone';
import { setRoundAmounts } from '../../utils/formatAmount';
import { createLogger } from '../../shared/services/logger.js';

import { useTablesStore } from './tables';
import { useOrdersStore } from './orders';
import { useShiftsStore } from './shifts';
import { useReservationsStore } from './reservations';
import { useDeliveryStore } from './delivery';
import { useMenuStore } from './menu';
import { useWriteOffsStore } from './writeoffs';

const log = createLogger('POS:Store');

export const usePosStore = defineStore('pos', () => {
    const tablesStore = useTablesStore();
    const ordersStore = useOrdersStore();
    const shiftsStore = useShiftsStore();
    const reservationsStore = useReservationsStore();
    const deliveryStore = useDeliveryStore();
    const menuStore = useMenuStore();
    const writeOffsStore = useWriteOffsStore();

    // Settings (остаётся в фасаде — глобальные настройки)
    const roundAmounts = ref(false);
    const timezone = ref('Europe/Moscow');

    // ==================== COMPUTED ====================

    const getTableStatus = (table) => {
        const activeOrder = ordersStore.activeOrdersMap.get(table.id);
        if (activeOrder) {
            if (activeOrder.bill_requested) return 'bill';
            if (activeOrder.status === 'ready') return 'ready';
            return 'occupied';
        }

        const tableReservations = reservationsStore.tableReservationsMap.get(table.id);
        if (tableReservations && tableReservations.length > 0) {
            return 'reserved';
        }

        return 'free';
    };

    // ==================== ACTIONS ====================

    const loadInitialData = async () => {
        tablesStore.tablesLoading = true;
        shiftsStore.shiftsLoading = true;

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

            tablesStore.tables = tablesRes;
            tablesStore.zones = zonesRes;

            if (zonesRes.length > 0) {
                tablesStore.updateFloorObjects(zonesRes[0]);
            }

            shiftsStore.shifts = shiftsRes;
            ordersStore.paidOrders = paidOrdersRes;
            shiftsStore.currentShift = currentShiftRes;
            ordersStore.activeOrders = activeOrdersRes;
            deliveryStore.deliveryOrders = Array.isArray(deliveryRes) ? deliveryRes : (deliveryRes?.data || []);

            const [reservationsRes, priceListsRes, stopListRes, settingsData] = await Promise.all([
                api.reservations.getByDate(reservationsStore.floorDate).catch(() => []),
                api.priceLists.getAll().catch(() => []),
                api.stopList.getAll().catch(() => []),
                api.settings.getGeneral().catch(() => null),
            ]);

            reservationsStore.reservations = reservationsRes;

            menuStore.availablePriceLists = (Array.isArray(priceListsRes) ? priceListsRes : []).filter(pl => pl.is_active);

            const stopListData = Array.isArray(stopListRes) ? stopListRes : (stopListRes?.data || []);
            menuStore.stopList = stopListData;
            menuStore.stopListDishIds = new Set(stopListData.map(item => item.dish_id));

            if (settingsData) {
                roundAmounts.value = settingsData.round_amounts || false;
                setRoundAmounts(roundAmounts.value);
                if (settingsData.timezone) {
                    timezone.value = settingsData.timezone;
                    setTimezone(settingsData.timezone);
                }
            }
        } catch (error) {
            log.error('Error loading initial data:', error);
        } finally {
            tablesStore.tablesLoading = false;
            shiftsStore.shiftsLoading = false;
        }
    };

    // ==================== BACKWARD-COMPATIBLE RETURN ====================
    // Все свойства и методы делегируются в доменные store-ы

    return {
        // Tables
        tables: computed(() => tablesStore.tables),
        zones: computed(() => tablesStore.zones),
        floorObjects: computed(() => tablesStore.floorObjects),
        floorWidth: computed(() => tablesStore.floorWidth),
        floorHeight: computed(() => tablesStore.floorHeight),
        tablesLoading: computed(() => tablesStore.tablesLoading),
        selectedTable: computed({
            get: () => tablesStore.selectedTable,
            set: (v) => { tablesStore.selectedTable = v; }
        }),
        selectedZone: computed({
            get: () => tablesStore.selectedZone,
            set: (v) => { tablesStore.selectedZone = v; }
        }),
        loadTables: (...args) => tablesStore.loadTables(...args),
        updateFloorObjects: (...args) => tablesStore.updateFloorObjects(...args),

        // Orders
        orders: computed(() => ordersStore.orders),
        activeOrders: computed(() => ordersStore.activeOrders),
        paidOrders: computed(() => ordersStore.paidOrders),
        activeOrdersMap: computed(() => ordersStore.activeOrdersMap),
        loadActiveOrders: (...args) => ordersStore.loadActiveOrders(...args),
        loadPaidOrders: (...args) => ordersStore.loadPaidOrders(...args),
        getTableOrder: (...args) => ordersStore.getTableOrder(...args),

        // Shifts
        shifts: computed(() => shiftsStore.shifts),
        currentShift: computed({
            get: () => shiftsStore.currentShift,
            set: (v) => { shiftsStore.currentShift = v; }
        }),
        shiftsLoading: computed(() => shiftsStore.shiftsLoading),
        shiftsVersion: computed(() => shiftsStore.shiftsVersion),
        loadShifts: (...args) => shiftsStore.loadShifts(...args),
        loadCurrentShift: (...args) => shiftsStore.loadCurrentShift(...args),

        // Reservations
        reservations: computed(() => reservationsStore.reservations),
        floorDate: computed({
            get: () => reservationsStore.floorDate,
            set: (v) => { reservationsStore.floorDate = v; }
        }),
        tableReservationsMap: computed(() => reservationsStore.tableReservationsMap),
        loadReservations: (...args) => reservationsStore.loadReservations(...args),
        setFloorDate: (...args) => reservationsStore.setFloorDate(...args),
        getTableReservations: (...args) => reservationsStore.getTableReservations(...args),

        // Delivery
        deliveryOrders: computed(() => deliveryStore.deliveryOrders),
        couriers: computed(() => deliveryStore.couriers),
        pendingDeliveryCount: computed(() => deliveryStore.pendingDeliveryCount),
        loadDeliveryOrders: (...args) => deliveryStore.loadDeliveryOrders(...args),
        loadCouriers: (...args) => deliveryStore.loadCouriers(...args),

        // Menu & Products
        menuCategories: computed(() => menuStore.menuCategories),
        menuDishes: computed(() => menuStore.menuDishes),
        availablePriceLists: computed(() => menuStore.availablePriceLists),
        selectedPriceListId: computed({
            get: () => menuStore.selectedPriceListId,
            set: (v) => { menuStore.selectedPriceListId = v; }
        }),
        stopList: computed(() => menuStore.stopList),
        stopListDishIds: computed(() => menuStore.stopListDishIds),
        customers: computed(() => menuStore.customers),
        loadMenu: (...args) => menuStore.loadMenu(...args),
        loadPriceLists: (...args) => menuStore.loadPriceLists(...args),
        setPriceList: (...args) => menuStore.setPriceList(...args),
        loadStopList: (...args) => menuStore.loadStopList(...args),
        loadCustomers: (...args) => menuStore.loadCustomers(...args),

        // Write-offs & Cancellations
        writeOffs: computed(() => writeOffsStore.writeOffs),
        pendingCancellations: computed(() => writeOffsStore.pendingCancellations),
        pendingCancellationsCount: computed(() => writeOffsStore.pendingCancellationsCount),
        loadWriteOffs: (...args) => writeOffsStore.loadWriteOffs(...args),
        loadPendingCancellations: (...args) => writeOffsStore.loadPendingCancellations(...args),

        // Settings
        roundAmounts,
        timezone,

        // Composite actions
        loadInitialData,
        getTableStatus,
    };
});
