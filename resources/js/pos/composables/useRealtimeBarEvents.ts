/**
 * Realtime Bar Events — обработка WebSocket событий бара
 */

import { useRealtimeEvents } from '../../shared/composables/useRealtimeEvents.js';
import { debounce, DEBOUNCE_CONFIG } from '../../shared/config/realtimeConfig.js';
import { createLogger } from '../../shared/services/logger.js';

const log = createLogger('RT:Bar');

interface BarEventsOptions {
    refreshBarCount: () => Promise<void>;
}

export function useRealtimeBarEvents({ refreshBarCount }: BarEventsOptions) {
    const { on } = useRealtimeEvents();

    const debouncedBarRefresh = debounce(async () => {
        await refreshBarCount();
        window.dispatchEvent(new Event('bar-refresh'));
    }, DEBOUNCE_CONFIG.apiRefresh);

    function setup(): void {
        on('bar_order_created', (data: any) => {
            log.debug('bar_order_created', data);
            debouncedBarRefresh();
        });

        on('bar_order_updated', (data: any) => {
            log.debug('bar_order_updated', data);
            debouncedBarRefresh();
        });

        on('bar_order_completed', (data: any) => {
            log.debug('bar_order_completed', data);
            debouncedBarRefresh();
        });
    }

    return { setup };
}
