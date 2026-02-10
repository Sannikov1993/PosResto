/**
 * Realtime Order Events — обработка WebSocket событий заказов
 */

import { usePosStore } from '../stores/pos.js';
import { useRealtimeEvents } from '../../shared/composables/useRealtimeEvents.js';
import { debounce, DEBOUNCE_CONFIG } from '../../shared/config/realtimeConfig.js';
import { playSound } from '../../shared/services/notificationSound.js';
import { createLogger } from '../../shared/services/logger.js';

const log = createLogger('RT:Orders');

export function useRealtimeOrderEvents() {
    const posStore = usePosStore();
    const { on } = useRealtimeEvents();

    const debouncedLoadActiveOrders = debounce(
        () => posStore.loadActiveOrders(),
        DEBOUNCE_CONFIG.apiRefresh
    );

    const debouncedLoadCancellations = debounce(
        () => posStore.loadPendingCancellations(),
        DEBOUNCE_CONFIG.apiRefresh
    );

    function setup(): void {
        on('new_order', ((data: Record<string, any>) => {
            log.debug('new_order', data);
            debouncedLoadActiveOrders();
            if (data.type === 'delivery') {
                window.$toast?.('Новый заказ на доставку', 'info');
                playSound('newOrder');
            }
        }) as any);

        on('order_status', ((data: Record<string, any>) => {
            log.debug('order_status', data);
            debouncedLoadActiveOrders();
        }) as any);

        on('order_updated', ((data: Record<string, any>) => {
            log.debug('order_updated', data);
            debouncedLoadActiveOrders();
        }) as any);

        on('order_paid', (async (data: Record<string, any>) => {
            log.debug('order_paid', data);
            await Promise.all([
                posStore.loadCurrentShift(),
                posStore.loadShifts(),
                posStore.loadPaidOrders(),
                posStore.loadActiveOrders()
            ]);
        }) as any);

        on('order_transferred', (async (data: Record<string, any>) => {
            log.debug('order_transferred', data);
            await Promise.all([
                posStore.loadActiveOrders(),
                posStore.loadTables(),
            ]);
            window.$toast?.(
                `Заказ перенесён со стола ${data.from_table_id} на стол ${data.to_table_id}`,
                'info'
            );
        }) as any);

        on('kitchen_ready', ((data: Record<string, any>) => {
            log.debug('kitchen_ready', data);
            debouncedLoadActiveOrders();
            const tableNum = data.table_number || data.table_id || '';
            const orderNum = data.order_number || '';
            window.$toast?.(
                `Заказ готов${tableNum ? ` (стол ${tableNum})` : orderNum ? ` #${orderNum}` : ''}`,
                'success'
            );
            playSound('ready');
        }) as any);

        on('item_cancelled', ((data: Record<string, any>) => {
            log.debug('item_cancelled', data);
            debouncedLoadActiveOrders();
        }) as any);

        on('table_status', ((data: Record<string, any>) => {
            log.debug('table_status', data);
            debouncedLoadActiveOrders();
        }) as any);

        on('cancellation_requested', ((data: Record<string, any>) => {
            log.debug('cancellation_requested', data);
            debouncedLoadCancellations();
        }) as any);

        on('item_cancellation_requested', ((data: Record<string, any>) => {
            log.debug('item_cancellation_requested', data);
            debouncedLoadCancellations();
        }) as any);
    }

    return { setup };
}
