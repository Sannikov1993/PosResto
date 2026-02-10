/**
 * Orders Store
 *
 * Pinia store for managing kitchen orders state.
 *
 * @module kitchen/stores/orders
 */

import { defineStore } from 'pinia';
import { orderApi } from '../services/api/orderApi.js';
import {
    ACTIVE_ORDER_STATUSES,
    ITEM_STATUS,
    isPreorder,
    getOrderPriority,
} from '../constants/orderStatus.js';
import {
    getTimeSlotKey,
    getTimeSlotLabel,
    getSlotUrgency,
    getCookingMinutes,
    getTodayString,
} from '../utils/time.js';
import { OVERDUE_THRESHOLDS } from '../constants/thresholds.js';
import { createLogger } from '../../shared/services/logger.js';
import type { Order, ProcessedOrder, TimeSlot, ApiResponse, OrderCountsByDate } from '../types/index.js';

const log = createLogger('KitchenOrders');

export const useOrdersStore = defineStore('kitchen-orders', {
    state: () => ({
        orders: [] as Order[],
        _selectedDate: null as string | null,
        _dateExplicitlySet: false,
        orderCountsByDate: {} as OrderCountsByDate,
        seenOrderIds: new Set<number>(),
        itemDoneState: {} as Record<string, boolean>,
        waiterCalledOrders: new Set<number>(),
        isLoading: false,
        error: null as Error | null,
        lastFetchTime: null as number | null,
    }),

    getters: {
        newOrders: (state): ProcessedOrder[] => {
            return state.orders
                .filter((o: any) => ACTIVE_ORDER_STATUSES.includes(o.status))
                .filter((o: any) => !isPreorder(o))
                .map((o: any) => ({
                    ...o,
                    items: (o.items || []).filter((i: any) =>
                        i.status === ITEM_STATUS.COOKING && !i.cooking_started_at
                    ),
                }))
                .filter((o: any) => o.items.length > 0)
                .sort((a: any, b: any) => {
                    const waitA = a.created_at ? Date.now() - new Date(a.created_at).getTime() : 0;
                    const waitB = b.created_at ? Date.now() - new Date(b.created_at).getTime() : 0;

                    const waitDiffMinutes = Math.abs(waitA - waitB) / 60000;
                    if (waitDiffMinutes > 5) {
                        return waitB - waitA;
                    }

                    const priorityA = getOrderPriority(a);
                    const priorityB = getOrderPriority(b);
                    if (priorityA !== priorityB) {
                        return priorityB - priorityA;
                    }

                    return waitB - waitA;
                });
        },

        cookingOrders: (state): ProcessedOrder[] => {
            return state.orders
                .filter((o: any) => ACTIVE_ORDER_STATUSES.includes(o.status))
                .map((o: any) => ({
                    ...o,
                    items: (o.items || [])
                        .filter((i: any) => i.status === ITEM_STATUS.COOKING && i.cooking_started_at)
                        .map((item: any) => ({
                            ...item,
                            done: state.itemDoneState[`${o.id}-${item.id}`] || false,
                        })),
                    cookingMinutes: getCookingMinutes(o),
                }))
                .filter((o: any) => o.items.length > 0)
                .sort((a: any, b: any) => {
                    const startA = a.cooking_started_at || a.updated_at;
                    const startB = b.cooking_started_at || b.updated_at;
                    if (!startA || !startB) return 0;
                    return new Date(startA).getTime() - new Date(startB).getTime();
                });
        },

        readyOrders: (state): ProcessedOrder[] => {
            return state.orders
                .filter((o: any) => ACTIVE_ORDER_STATUSES.includes(o.status))
                .map((o: any) => ({
                    ...o,
                    items: (o.items || []).filter((i: any) => i.status === ITEM_STATUS.READY),
                }))
                .filter((o: any) => o.items.length > 0);
        },

        preorderOrders: (state): Order[] => {
            return state.orders
                .filter((o: any) => isPreorder(o))
                .filter((o: any) => !['completed', 'cancelled'].includes(o.status))
                .filter((o: any) => {
                    const items = o.items || [];
                    if (items.length === 0) return true;
                    const cookingStarted = items.some((i: any) => i.cooking_started_at);
                    const allDone = items.length > 0 &&
                        items.every((i: any) => ['ready', 'served', 'cancelled'].includes(i.status));
                    return !cookingStarted && !allDone;
                })
                .map((o: any) => ({
                    ...o,
                    items: (o.items || []).filter((i: any) => i.status !== ITEM_STATUS.CANCELLED),
                }))
                .filter((o: any) => !o.items || o.items.length > 0)
                .sort((a: any, b: any) => {
                    const timeA = a.scheduled_at || '';
                    const timeB = b.scheduled_at || '';
                    return timeA.localeCompare(timeB);
                });
        },

        totalNewOrders(): number {
            return this.preorderOrders.length + this.newOrders.length;
        },

        preorderTimeSlots(): TimeSlot[] {
            const slots: Record<string, TimeSlot> = {};

            this.preorderOrders.forEach((order: any) => {
                const slotKey = getTimeSlotKey(order.scheduled_at);
                if (!slotKey) return;

                if (!slots[slotKey]) {
                    slots[slotKey] = {
                        key: slotKey,
                        label: getTimeSlotLabel(slotKey),
                        orders: [] as any[],
                        urgency: 'normal',
                    };
                }
                slots[slotKey].orders.push(order);
            });

            return Object.values(slots)
                .map((slot: any) => ({
                    ...slot,
                    urgency: getSlotUrgency(slot.key),
                }))
                .sort((a: any, b: any) => a.key.localeCompare(b.key));
        },

        overdueOrders(): ProcessedOrder[] {
            return this.cookingOrders
                .filter((o: any) => (o.cookingMinutes ?? 0) >= OVERDUE_THRESHOLDS.WARNING)
                .map((o: any) => ({
                    ...o,
                    isWarning: (o.cookingMinutes ?? 0) >= OVERDUE_THRESHOLDS.WARNING &&
                               (o.cookingMinutes ?? 0) < OVERDUE_THRESHOLDS.CRITICAL,
                    isCritical: (o.cookingMinutes ?? 0) >= OVERDUE_THRESHOLDS.CRITICAL &&
                                (o.cookingMinutes ?? 0) < OVERDUE_THRESHOLDS.ALERT,
                    isAlert: (o.cookingMinutes ?? 0) >= OVERDUE_THRESHOLDS.ALERT,
                }));
        },

        selectedDate: (state): string => {
            if (state._dateExplicitlySet && state._selectedDate) {
                return state._selectedDate;
            }
            return state._selectedDate || getTodayString();
        },

        isSelectedDateToday(): boolean {
            return this.selectedDate === getTodayString();
        },
    },

    actions: {
        async fetchOrders(deviceId: string, stationSlug?: string) {
            this.isLoading = true;
            this.error = null;

            try {
                const orders = await orderApi.getOrders({
                    deviceId,
                    date: this.selectedDate,
                    station: stationSlug,
                });

                const result = this._processOrders(orders);
                this.lastFetchTime = Date.now();
                return result;
            } catch (error: any) {
                this.error = error as Error;
                throw error;
            } finally {
                this.isLoading = false;
            }
        },

        _processOrders(allOrders: Order[]): { newOrders: Order[] } {
            const newOrdersDetected: Order[] = [];

            const filtered = allOrders.filter((o: any) => {
                if (isPreorder(o) && !['completed', 'cancelled'].includes(o.status)) {
                    return true;
                }
                return ACTIVE_ORDER_STATUSES.includes(o.status);
            });

            filtered.forEach((order: any) => {
                if (order.status === 'confirmed' && !this.seenOrderIds.has(order.id)) {
                    newOrdersDetected.push(order);
                }
                this.seenOrderIds.add(order.id);
            });

            this.orders = filtered;

            return { newOrders: newOrdersDetected };
        },

        async fetchOrderCounts(deviceId: string, startDate: string, endDate: string, stationSlug?: string) {
            try {
                const counts = await orderApi.getOrderCountsByDate({
                    deviceId,
                    startDate,
                    endDate,
                    station: stationSlug,
                });
                this.orderCountsByDate = counts;
            } catch (error: any) {
                log.error('Failed to fetch order counts:', error);
            }
        },

        async startCooking(orderId: number, deviceId: string, stationSlug?: string) {
            await orderApi.startCooking(orderId, deviceId, stationSlug);
            await this.fetchOrders(deviceId, stationSlug);
        },

        async markReady(orderId: number, deviceId: string, stationSlug?: string) {
            await orderApi.markReady(orderId, deviceId, stationSlug);
            this._clearItemDoneState(orderId);
            await this.fetchOrders(deviceId, stationSlug);
        },

        async returnToNew(orderId: number, deviceId: string, stationSlug?: string) {
            await orderApi.returnToNew(orderId, deviceId, stationSlug);
            this._clearItemDoneState(orderId);
            await this.fetchOrders(deviceId, stationSlug);
        },

        async returnToCooking(orderId: number, deviceId: string, stationSlug?: string) {
            await orderApi.returnToCooking(orderId, deviceId, stationSlug);
            await this.fetchOrders(deviceId, stationSlug);
        },

        async markItemReady(orderId: number, itemId: number, deviceId: string, stationSlug?: string) {
            await orderApi.markItemReady(itemId, deviceId);
            this.itemDoneState[`${orderId}-${itemId}`] = true;
            await this.fetchOrders(deviceId, stationSlug);
        },

        toggleItemDone(orderId: number, itemId: number) {
            const key = `${orderId}-${itemId}`;
            this.itemDoneState[key] = !this.itemDoneState[key];
        },

        _clearItemDoneState(orderId: number) {
            Object.keys(this.itemDoneState).forEach((key: any) => {
                if (key.startsWith(`${orderId}-`)) {
                    delete this.itemDoneState[key];
                }
            });
        },

        async callWaiter(orderId: number, deviceId: string) {
            const response = await orderApi.callWaiter(orderId, deviceId);
            this.waiterCalledOrders.add(orderId);
            return response;
        },

        setSelectedDate(date: string) {
            this._selectedDate = date;
            this._dateExplicitlySet = true;
        },

        goToPreviousDay() {
            const currentDate = this.selectedDate;
            const [year, month, day] = currentDate.split('-').map(Number);
            const date = new Date(year, month - 1, day - 1);
            const newYear = date.getFullYear();
            const newMonth = String(date.getMonth() + 1).padStart(2, '0');
            const newDay = String(date.getDate()).padStart(2, '0');
            this._selectedDate = `${newYear}-${newMonth}-${newDay}`;
            this._dateExplicitlySet = true;
        },

        goToNextDay() {
            const currentDate = this.selectedDate;
            const [year, month, day] = currentDate.split('-').map(Number);
            const date = new Date(year, month - 1, day + 1);
            const newYear = date.getFullYear();
            const newMonth = String(date.getMonth() + 1).padStart(2, '0');
            const newDay = String(date.getDate()).padStart(2, '0');
            this._selectedDate = `${newYear}-${newMonth}-${newDay}`;
            this._dateExplicitlySet = true;
        },

        resetToToday() {
            this._selectedDate = null;
            this._dateExplicitlySet = false;
        },
    },
});
