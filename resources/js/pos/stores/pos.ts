/**
 * POS Store — фасад, композирующий доменные store-ы
 *
 * Обратная совместимость: все потребители продолжают использовать usePosStore().
 * Новый код может импортировать доменные store-ы напрямую.
 */

import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import api from '../api/index.js';
import { setTimezone } from '../../utils/timezone.js';
import { setRoundAmounts } from '../../utils/formatAmount.js';
import { createLogger } from '../../shared/services/logger.js';

import { useTablesStore } from './tables.js';
import { useOrdersStore } from './orders.js';
import { useShiftsStore } from './shifts.js';
import { useReservationsStore } from './reservations.js';
import { useDeliveryStore } from './delivery.js';
import { useMenuStore } from './menu.js';
import { useWriteOffsStore } from './writeoffs.js';
import type { Table, Order, DeliveryOrder, PriceList, StopListItem } from '@/shared/types';

const log = createLogger('POS:Store');

type TableStatusType = 'bill' | 'ready' | 'occupied' | 'reserved' | 'free';

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

    const getTableStatus = (table: Table): TableStatusType => {
        const activeOrder = ordersStore.activeOrdersMap.get(table.id);
        if (activeOrder) {
            if ((activeOrder as Record<string, any>).bill_requested) return 'bill';
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

    const loadInitialData = async (): Promise<void> => {
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
            deliveryStore.deliveryOrders = (Array.isArray(deliveryRes) ? deliveryRes : ((deliveryRes as Record<string, any>)?.data || [])) as any;

            const [reservationsRes, priceListsRes, stopListRes, settingsData] = await Promise.all([
                api.reservations.getByDate(reservationsStore.floorDate).catch(() => []),
                api.priceLists.getAll().catch(() => []),
                api.stopList.getAll().catch(() => []),
                api.settings.getGeneral().catch(() => null),
            ]);

            reservationsStore.reservations = reservationsRes;

            menuStore.availablePriceLists = (Array.isArray(priceListsRes) ? priceListsRes : []).filter((pl: PriceList) => pl.is_active);

            const stopListData = Array.isArray(stopListRes) ? stopListRes : ((stopListRes as Record<string, any>)?.data || []) as StopListItem[];
            menuStore.stopList = stopListData;
            menuStore.stopListDishIds = new Set(stopListData.map((item: StopListItem) => item.dish_id));

            if (settingsData) {
                const settings = settingsData as Record<string, any>;
                roundAmounts.value = (settings.round_amounts || false) as boolean;
                setRoundAmounts(roundAmounts.value);
                if (settings.timezone) {
                    timezone.value = settings.timezone as string;
                    setTimezone(settings.timezone as string);
                }
            }
        } catch (error: any) {
            log.error('Error loading initial data:', error);
        } finally {
            tablesStore.tablesLoading = false;
            shiftsStore.shiftsLoading = false;
        }
    };

    // ==================== BACKWARD-COMPATIBLE RETURN ====================

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
        loadTables: (...args: unknown[]) => tablesStore.loadTables(),
        updateFloorObjects: (...args: unknown[]) => tablesStore.updateFloorObjects(args[0] as any),

        // Orders
        orders: computed(() => ordersStore.orders),
        activeOrders: computed(() => ordersStore.activeOrders),
        paidOrders: computed(() => ordersStore.paidOrders),
        activeOrdersMap: computed(() => ordersStore.activeOrdersMap),
        loadActiveOrders: (...args: unknown[]) => ordersStore.loadActiveOrders(args[0] as boolean),
        loadPaidOrders: (...args: unknown[]) => ordersStore.loadPaidOrders(args[0] as boolean),
        getTableOrder: (...args: unknown[]) => ordersStore.getTableOrder(args[0] as number),

        // Shifts
        shifts: computed(() => shiftsStore.shifts),
        currentShift: computed({
            get: () => shiftsStore.currentShift,
            set: (v) => { shiftsStore.currentShift = v; }
        }),
        shiftsLoading: computed(() => shiftsStore.shiftsLoading),
        shiftsVersion: computed(() => shiftsStore.shiftsVersion),
        loadShifts: (...args: unknown[]) => shiftsStore.loadShifts(args[0] as boolean),
        loadCurrentShift: () => shiftsStore.loadCurrentShift(),

        // Reservations
        reservations: computed(() => reservationsStore.reservations),
        floorDate: computed({
            get: () => reservationsStore.floorDate,
            set: (v) => { reservationsStore.floorDate = v; }
        }),
        tableReservationsMap: computed(() => reservationsStore.tableReservationsMap),
        loadReservations: (...args: unknown[]) => reservationsStore.loadReservations(args[0] as string, args[1] as boolean),
        setFloorDate: (...args: unknown[]) => reservationsStore.setFloorDate(args[0] as string),
        getTableReservations: (...args: unknown[]) => reservationsStore.getTableReservations(args[0] as number),

        // Delivery
        deliveryOrders: computed(() => deliveryStore.deliveryOrders),
        couriers: computed(() => deliveryStore.couriers),
        pendingDeliveryCount: computed(() => deliveryStore.pendingDeliveryCount),
        loadDeliveryOrders: (...args: unknown[]) => deliveryStore.loadDeliveryOrders(args[0] as boolean),
        loadCouriers: () => deliveryStore.loadCouriers(),

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
        loadMenu: () => menuStore.loadMenu(),
        loadPriceLists: () => menuStore.loadPriceLists(),
        setPriceList: (...args: unknown[]) => menuStore.setPriceList(args[0] as number | null),
        loadStopList: () => menuStore.loadStopList(),
        loadCustomers: () => menuStore.loadCustomers(),

        // Write-offs & Cancellations
        writeOffs: computed(() => writeOffsStore.writeOffs),
        pendingCancellations: computed(() => writeOffsStore.pendingCancellations),
        pendingCancellationsCount: computed(() => writeOffsStore.pendingCancellationsCount),
        loadWriteOffs: (...args: unknown[]) => writeOffsStore.loadWriteOffs(args[0] as string | null, args[1] as string | null),
        loadPendingCancellations: () => writeOffsStore.loadPendingCancellations(),

        // Settings
        roundAmounts,
        timezone,

        // Composite actions
        loadInitialData,
        getTableStatus,
    };
});
