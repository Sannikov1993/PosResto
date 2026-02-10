/**
 * UI Store Unit Tests
 *
 * @group unit
 * @group kitchen
 * @group stores
 */

import { describe, it, expect, beforeEach, vi, afterEach } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';
import { useUiStore } from '../../stores/ui.js';
import { ALERT_CONFIG } from '../../constants/thresholds.js';

describe('UI Store', () => {
    let store: ReturnType<typeof useUiStore>;

    beforeEach(() => {
        vi.useFakeTimers();
        setActivePinia(createPinia());
        store = useUiStore();
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    // ==================== Initial State ====================

    describe('initial state', () => {
        it('should have all alerts hidden', () => {
            expect(store.showNewOrderAlert).toBe(false);
            expect(store.showCancellationAlert).toBe(false);
            expect(store.showStopListAlert).toBe(false);
            expect(store.showOverdueAlert).toBe(false);
        });

        it('should have empty order number', () => {
            expect(store.newOrderNumber).toBe('');
        });

        it('should have no selected dish', () => {
            expect(store.selectedDish).toBeNull();
            expect(store.showDishModal).toBe(false);
        });

        it('should have dropdowns closed', () => {
            expect(store.showCalendarPicker).toBe(false);
            expect(store.showStopListDropdown).toBe(false);
        });

        it('should have empty stop list', () => {
            expect(store.stopList).toEqual([]);
        });

        it('should have empty time and date', () => {
            expect(store.currentTime).toBe('');
            expect(store.currentDate).toBe('');
        });

        it('should have waiter call toast hidden', () => {
            expect(store.showWaiterCallSuccess).toBe(false);
        });

        it('should have lastOverdueAlertTime as 0', () => {
            expect(store.lastOverdueAlertTime).toBe(0);
        });
    });

    // ==================== New Order Alert ====================

    describe('showNewOrder / dismissNewOrderAlert', () => {
        it('should show new order alert with order number as string', () => {
            store.showNewOrder('A-001');

            expect(store.showNewOrderAlert).toBe(true);
            expect(store.newOrderNumber).toBe('A-001');
        });

        it('should convert numeric order number to string', () => {
            store.showNewOrder(42);

            expect(store.newOrderNumber).toBe('42');
        });

        it('should auto-dismiss after NEW_ORDER_ALERT_DURATION', () => {
            store.showNewOrder('A-001');
            expect(store.showNewOrderAlert).toBe(true);

            vi.advanceTimersByTime(ALERT_CONFIG.NEW_ORDER_ALERT_DURATION);

            expect(store.showNewOrderAlert).toBe(false);
        });

        it('should allow manual dismissal before timeout', () => {
            store.showNewOrder('A-001');
            store.dismissNewOrderAlert();

            expect(store.showNewOrderAlert).toBe(false);
        });
    });

    // ==================== Cancellation Alert ====================

    describe('showCancellation / dismissCancellationAlert', () => {
        it('should show cancellation alert with data', () => {
            const data = {
                item_name: 'Pizza',
                quantity: 2,
                order_number: 'A-001',
                reason_label: 'Out of stock',
            };

            store.showCancellation(data);

            expect(store.showCancellationAlert).toBe(true);
            expect(store.cancellationData).toEqual(data);
        });

        it('should dismiss cancellation alert', () => {
            store.showCancellation({ item_name: 'Pizza' });
            store.dismissCancellationAlert();

            expect(store.showCancellationAlert).toBe(false);
        });
    });

    // ==================== Stop List Alert ====================

    describe('showStopListChange / dismissStopListAlert', () => {
        it('should show stop list alert with data', () => {
            const data = { dish_name: 'Burger', action: 'added' };

            store.showStopListChange(data);

            expect(store.showStopListAlert).toBe(true);
            expect(store.stopListData).toEqual(data);
        });

        it('should dismiss stop list alert', () => {
            store.showStopListChange({ dish_name: 'Burger' });
            store.dismissStopListAlert();

            expect(store.showStopListAlert).toBe(false);
        });
    });

    // ==================== Stop List Dropdown ====================

    describe('toggleStopListDropdown', () => {
        it('should toggle stop list dropdown', () => {
            store.toggleStopListDropdown();
            expect(store.showStopListDropdown).toBe(true);

            store.toggleStopListDropdown();
            expect(store.showStopListDropdown).toBe(false);
        });
    });

    describe('setStopList', () => {
        it('should set the stop list items', () => {
            const items = [
                { id: 1, dish: { name: 'Pizza' }, reason: 'Out of stock' },
                { id: 2, dish: { name: 'Burger' }, reason: 'Sold out' },
            ];

            store.setStopList(items);

            expect(store.stopList).toHaveLength(2);
            expect(store.stopList[0].dish.name).toBe('Pizza');
        });
    });

    // ==================== Overdue Alert ====================

    describe('showOverdue / dismissOverdueAlert', () => {
        it('should show overdue alert', () => {
            const orderData = { order_id: 1, order_number: 'A-001' };

            store.showOverdue(orderData);

            expect(store.showOverdueAlert).toBe(true);
            expect(store.overdueAlertData).toEqual(orderData);
        });

        it('should auto-dismiss after OVERDUE_ALERT_DURATION', () => {
            store.showOverdue({ order_id: 1 });
            expect(store.showOverdueAlert).toBe(true);

            vi.advanceTimersByTime(ALERT_CONFIG.OVERDUE_ALERT_DURATION);

            expect(store.showOverdueAlert).toBe(false);
        });

        it('should throttle overdue alerts based on OVERDUE_ALERT_INTERVAL', () => {
            store.showOverdue({ order_id: 1 });
            expect(store.showOverdueAlert).toBe(true);

            // Dismiss it manually
            store.dismissOverdueAlert();
            expect(store.showOverdueAlert).toBe(false);

            // Try to show again immediately -- should be throttled
            store.showOverdue({ order_id: 2 });
            expect(store.showOverdueAlert).toBe(false);
        });

        it('should allow overdue alert after interval has elapsed', () => {
            store.showOverdue({ order_id: 1 });
            store.dismissOverdueAlert();

            // Advance past the interval
            vi.advanceTimersByTime(ALERT_CONFIG.OVERDUE_ALERT_INTERVAL + 1);

            store.showOverdue({ order_id: 2 });
            expect(store.showOverdueAlert).toBe(true);
            expect(store.overdueAlertData).toEqual({ order_id: 2 });
        });

        it('should dismiss overdue alert manually', () => {
            store.showOverdue({ order_id: 1 });
            store.dismissOverdueAlert();

            expect(store.showOverdueAlert).toBe(false);
        });
    });

    // ==================== Waiter Call Toast ====================

    describe('showWaiterCallToast / dismissWaiterCallToast', () => {
        it('should show waiter call toast with data', () => {
            const data = { waiterName: 'John', orderNumber: 'A-001' };

            store.showWaiterCallToast(data);

            expect(store.showWaiterCallSuccess).toBe(true);
            expect(store.waiterCallData).toEqual(data);
        });

        it('should auto-dismiss after TOAST_DURATION', () => {
            store.showWaiterCallToast({ waiterName: 'John' });
            expect(store.showWaiterCallSuccess).toBe(true);

            vi.advanceTimersByTime(ALERT_CONFIG.TOAST_DURATION);

            expect(store.showWaiterCallSuccess).toBe(false);
        });

        it('should allow manual dismissal', () => {
            store.showWaiterCallToast({ waiterName: 'John' });
            store.dismissWaiterCallToast();

            expect(store.showWaiterCallSuccess).toBe(false);
        });
    });

    // ==================== Dish Modal ====================

    describe('openDishModal / closeDishModal', () => {
        it('should open dish modal with dish data', () => {
            const dish = { id: 1, name: 'Pizza', description: 'Tasty' };

            store.openDishModal(dish);

            expect(store.showDishModal).toBe(true);
            expect(store.selectedDish).toEqual(dish);
            expect(store.selectedItemModifiers).toEqual([]);
            expect(store.selectedItemComment).toBe('');
        });

        it('should open dish modal with modifiers and comment', () => {
            const dish = { id: 1, name: 'Burger' };
            const modifiers = [{ id: 1, name: 'Extra cheese', price: 50, quantity: 1 }];
            const comment = 'No onions please';

            store.openDishModal(dish, modifiers, comment);

            expect(store.showDishModal).toBe(true);
            expect(store.selectedDish).toEqual(dish);
            expect(store.selectedItemModifiers).toEqual(modifiers);
            expect(store.selectedItemComment).toBe('No onions please');
        });

        it('should close dish modal and clear all data', () => {
            store.openDishModal(
                { id: 1, name: 'Pizza' },
                [{ id: 1, name: 'Extra', price: 50, quantity: 1 }],
                'Comment'
            );

            store.closeDishModal();

            expect(store.showDishModal).toBe(false);
            expect(store.selectedDish).toBeNull();
            expect(store.selectedItemModifiers).toEqual([]);
            expect(store.selectedItemComment).toBe('');
        });
    });

    // ==================== Calendar Picker ====================

    describe('toggleCalendarPicker / closeCalendarPicker', () => {
        it('should toggle calendar picker', () => {
            store.toggleCalendarPicker();
            expect(store.showCalendarPicker).toBe(true);

            store.toggleCalendarPicker();
            expect(store.showCalendarPicker).toBe(false);
        });

        it('should close calendar picker', () => {
            store.showCalendarPicker = true;
            store.closeCalendarPicker();

            expect(store.showCalendarPicker).toBe(false);
        });
    });

    // ==================== Time Display ====================

    describe('updateTimeDisplay', () => {
        it('should update time and date', () => {
            store.updateTimeDisplay('14:30', '2024-01-15');

            expect(store.currentTime).toBe('14:30');
            expect(store.currentDate).toBe('2024-01-15');
        });
    });

    // ==================== Bulk Actions ====================

    describe('closeAllDropdowns', () => {
        it('should close all dropdowns', () => {
            store.showCalendarPicker = true;
            store.showStopListDropdown = true;

            store.closeAllDropdowns();

            expect(store.showCalendarPicker).toBe(false);
            expect(store.showStopListDropdown).toBe(false);
        });
    });

    describe('dismissAllAlerts', () => {
        it('should dismiss all alerts', () => {
            store.showNewOrderAlert = true;
            store.showCancellationAlert = true;
            store.showStopListAlert = true;
            store.showOverdueAlert = true;

            store.dismissAllAlerts();

            expect(store.showNewOrderAlert).toBe(false);
            expect(store.showCancellationAlert).toBe(false);
            expect(store.showStopListAlert).toBe(false);
            expect(store.showOverdueAlert).toBe(false);
        });

        it('should work when no alerts are showing', () => {
            store.dismissAllAlerts();

            expect(store.showNewOrderAlert).toBe(false);
            expect(store.showCancellationAlert).toBe(false);
            expect(store.showStopListAlert).toBe(false);
            expect(store.showOverdueAlert).toBe(false);
        });
    });
});
