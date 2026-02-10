/**
 * Realtime Misc Events — бронирования, стоп-лист, настройки
 */

import { usePosStore } from '../stores/pos.js';
import { useRealtimeEvents } from '../../shared/composables/useRealtimeEvents.js';
import { debounce, DEBOUNCE_CONFIG } from '../../shared/config/realtimeConfig.js';
import { createLogger } from '../../shared/services/logger.js';

const log = createLogger('RT:Misc');

export function useRealtimeMiscEvents() {
    const posStore = usePosStore();
    const { on } = useRealtimeEvents();

    const debouncedLoadReservations = debounce(
        () => posStore.loadReservations(posStore.floorDate),
        DEBOUNCE_CONFIG.apiRefresh
    );

    const debouncedLoadActiveOrders = debounce(
        () => posStore.loadActiveOrders(),
        DEBOUNCE_CONFIG.apiRefresh
    );

    function setup(): void {
        on('reservation_new', (data: any) => {
            log.debug('reservation_new', data);
            debouncedLoadReservations();
            window.$toast?.(`Новая бронь: ${data.customer_name || 'Гость'}`, 'info');
        });

        on('reservation_confirmed', (data: any) => {
            log.debug('reservation_confirmed', data);
            debouncedLoadReservations();
        });

        on('reservation_cancelled', (data: any) => {
            log.debug('reservation_cancelled', data);
            debouncedLoadReservations();
        });

        on('reservation_seated', (data: any) => {
            log.debug('reservation_seated', data);
            debouncedLoadReservations();
            debouncedLoadActiveOrders();
        });

        on('stop_list_changed', async (data: any) => {
            log.debug('stop_list_changed', data);
            await posStore.loadStopList();
            window.$toast?.('Стоп-лист обновлён', 'warning');
        });

        on('settings_changed', async (data: any) => {
            log.debug('settings_changed', data);
            await posStore.loadInitialData();
        });
    }

    return { setup };
}
