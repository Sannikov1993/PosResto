/**
 * Realtime Finance Events — обработка WebSocket событий смен и кассы
 *
 * Подписки: shift_opened, shift_closed, cash_operation_created
 */

import { usePosStore } from '../stores/pos';
import { useRealtimeEvents } from '../../shared/composables/useRealtimeEvents.js';
import { debounce, DEBOUNCE_CONFIG } from '../../shared/config/realtimeConfig.js';
import { createLogger } from '../../shared/services/logger.js';

const log = createLogger('RT:Finance');

export function useRealtimeFinanceEvents() {
    const posStore = usePosStore();
    const { on } = useRealtimeEvents();

    // Единый debounced загрузчик для всех событий смены
    const debouncedLoadShiftData = debounce(async () => {
        await Promise.all([
            posStore.loadCurrentShift(),
            posStore.loadShifts(),
        ]);
    }, DEBOUNCE_CONFIG.apiRefresh);

    function setup() {
        on('cash_operation_created', (data) => {
            log.debug('cash_operation_created', data);
            debouncedLoadShiftData();
        });

        on('shift_opened', (data) => {
            log.debug('shift_opened', data);
            debouncedLoadShiftData();
        });

        on('shift_closed', (data) => {
            log.debug('shift_closed', data);
            debouncedLoadShiftData();
        });
    }

    return { setup };
}
