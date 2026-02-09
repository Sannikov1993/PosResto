/**
 * Realtime Order Events — обработка WebSocket событий заказов
 *
 * Подписки: new_order, order_status, order_updated, order_paid,
 * order_transferred, kitchen_ready, item_cancelled, table_status,
 * cancellation_requested, item_cancellation_requested
 */

import { usePosStore } from '../stores/pos';
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

    function setup() {
        // ===== Заказы =====
        on('new_order', (data) => {
            log.debug('new_order', data);
            debouncedLoadActiveOrders();
            if (data.type === 'delivery') {
                window.$toast?.('Новый заказ на доставку', 'info');
                playSound('newOrder');
            }
        });

        on('order_status', (data) => {
            log.debug('order_status', data);
            debouncedLoadActiveOrders();
        });

        on('order_updated', (data) => {
            log.debug('order_updated', data);
            debouncedLoadActiveOrders();
        });

        // order_paid — без debounce (критично для кассы)
        on('order_paid', async (data) => {
            log.debug('order_paid', data);
            await Promise.all([
                posStore.loadCurrentShift(),
                posStore.loadShifts(),
                posStore.loadPaidOrders(),
                posStore.loadActiveOrders()
            ]);
        });

        on('order_transferred', async (data) => {
            log.debug('order_transferred', data);
            await Promise.all([
                posStore.loadActiveOrders(),
                posStore.loadTables(),
            ]);
            window.$toast?.(
                `Заказ перенесён со стола ${data.from_table_id} на стол ${data.to_table_id}`,
                'info'
            );
        });

        // ===== Кухня =====
        on('kitchen_ready', (data) => {
            log.debug('kitchen_ready', data);
            debouncedLoadActiveOrders();
            const tableNum = data.table_number || data.table_id || '';
            const orderNum = data.order_number || '';
            window.$toast?.(
                `Заказ готов${tableNum ? ` (стол ${tableNum})` : orderNum ? ` #${orderNum}` : ''}`,
                'success'
            );
            playSound('ready');
        });

        on('item_cancelled', (data) => {
            log.debug('item_cancelled', data);
            debouncedLoadActiveOrders();
        });

        // ===== Столы =====
        on('table_status', (data) => {
            log.debug('table_status', data);
            debouncedLoadActiveOrders();
        });

        // ===== Отмены =====
        on('cancellation_requested', (data) => {
            log.debug('cancellation_requested', data);
            debouncedLoadCancellations();
        });

        on('item_cancellation_requested', (data) => {
            log.debug('item_cancellation_requested', data);
            debouncedLoadCancellations();
        });
    }

    return { setup };
}
