/**
 * Realtime Misc Events — бронирования, стоп-лист, настройки
 *
 * Подписки: reservation_new, reservation_confirmed, reservation_cancelled,
 * reservation_seated, stop_list_changed, settings_changed
 */

import { usePosStore } from '../stores/pos';
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

    function setup() {
        // ===== Бронирования =====
        on('reservation_new', (data) => {
            log.debug('reservation_new', data);
            debouncedLoadReservations();
            window.$toast?.(`Новая бронь: ${data.customer_name || 'Гость'}`, 'info');
        });

        on('reservation_confirmed', (data) => {
            log.debug('reservation_confirmed', data);
            debouncedLoadReservations();
        });

        on('reservation_cancelled', (data) => {
            log.debug('reservation_cancelled', data);
            debouncedLoadReservations();
        });

        on('reservation_seated', (data) => {
            log.debug('reservation_seated', data);
            debouncedLoadReservations();
            debouncedLoadActiveOrders();
        });

        // ===== Стоп-лист =====
        on('stop_list_changed', async (data) => {
            log.debug('stop_list_changed', data);
            await posStore.loadStopList();
            window.$toast?.('Стоп-лист обновлён', 'warning');
        });

        // ===== Настройки =====
        on('settings_changed', async (data) => {
            log.debug('settings_changed', data);
            await posStore.loadInitialData();
        });
    }

    return { setup };
}
