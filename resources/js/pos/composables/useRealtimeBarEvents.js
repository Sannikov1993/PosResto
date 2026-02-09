/**
 * Realtime Bar Events — обработка WebSocket событий бара
 *
 * Подписки: bar_order_created, bar_order_updated, bar_order_completed
 */

import { useRealtimeEvents } from '../../shared/composables/useRealtimeEvents.js';
import { debounce, DEBOUNCE_CONFIG } from '../../shared/config/realtimeConfig.js';
import { createLogger } from '../../shared/services/logger.js';

const log = createLogger('RT:Bar');

/**
 * @param {Object} options
 * @param {Function} options.refreshBarCount — callback для обновления счётчика бара
 */
export function useRealtimeBarEvents({ refreshBarCount }) {
    const { on } = useRealtimeEvents();

    // Единый debounced обработчик для всех bar-событий
    const debouncedBarRefresh = debounce(async () => {
        await refreshBarCount();
        window.dispatchEvent(new Event('bar-refresh'));
    }, DEBOUNCE_CONFIG.apiRefresh);

    function setup() {
        on('bar_order_created', (data) => {
            log.debug('bar_order_created', data);
            debouncedBarRefresh();
        });

        on('bar_order_updated', (data) => {
            log.debug('bar_order_updated', data);
            debouncedBarRefresh();
        });

        on('bar_order_completed', (data) => {
            log.debug('bar_order_completed', data);
            debouncedBarRefresh();
        });
    }

    return { setup };
}
