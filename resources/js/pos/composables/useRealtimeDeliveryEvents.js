/**
 * Realtime Delivery Events — обработка WebSocket событий доставки
 *
 * Подписки: delivery_new, delivery_status, courier_assigned,
 * delivery_problem_created, delivery_problem_resolved, new_order (delivery)
 */

import { usePosStore } from '../stores/pos';
import { useRealtimeEvents } from '../../shared/composables/useRealtimeEvents.js';
import { debounce, DEBOUNCE_CONFIG } from '../../shared/config/realtimeConfig.js';
import { createLogger } from '../../shared/services/logger.js';

const log = createLogger('RT:Delivery');

export function useRealtimeDeliveryEvents() {
    const posStore = usePosStore();
    const { on } = useRealtimeEvents();

    const debouncedLoadDeliveryOrders = debounce(
        () => posStore.loadDeliveryOrders(),
        DEBOUNCE_CONFIG.apiRefresh
    );

    function setup() {
        on('new_order', (data) => {
            if (data.type === 'delivery') {
                debouncedLoadDeliveryOrders();
            }
        });

        on('delivery_new', (data) => {
            log.debug('delivery_new', data);
            debouncedLoadDeliveryOrders();
        });

        on('delivery_status', (data) => {
            log.debug('delivery_status', data);
            debouncedLoadDeliveryOrders();
        });

        on('courier_assigned', (data) => {
            log.debug('courier_assigned', data);
            debouncedLoadDeliveryOrders();
        });

        on('delivery_problem_created', (data) => {
            log.debug('delivery_problem_created', data);
            debouncedLoadDeliveryOrders();
            window.$toast?.(`Проблема с доставкой: ${data.problem_type || ''}`, 'warning');
        });

        on('delivery_problem_resolved', (data) => {
            log.debug('delivery_problem_resolved', data);
            debouncedLoadDeliveryOrders();
        });
    }

    return { setup };
}
