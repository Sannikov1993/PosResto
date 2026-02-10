/**
 * Realtime Finance Events — обработка WebSocket событий смен и кассы
 */

import { usePosStore } from '../stores/pos.js';
import { useRealtimeEvents } from '../../shared/composables/useRealtimeEvents.js';
import { debounce, DEBOUNCE_CONFIG } from '../../shared/config/realtimeConfig.js';
import { createLogger } from '../../shared/services/logger.js';

const log = createLogger('RT:Finance');

export function useRealtimeFinanceEvents() {
    const posStore = usePosStore();
    const { on } = useRealtimeEvents();

    const debouncedLoadShiftData = debounce(async () => {
        await Promise.all([
            posStore.loadCurrentShift(),
            posStore.loadShifts(),
        ]);
    }, DEBOUNCE_CONFIG.apiRefresh);

    function setup(): void {
        on('cash_operation_created', (data: any) => {
            log.debug('cash_operation_created', data);
            debouncedLoadShiftData();
        });

        on('shift_opened', (data: any) => {
            log.debug('shift_opened', data);
            debouncedLoadShiftData();
        });

        on('shift_closed', (data: any) => {
            log.debug('shift_closed', data);
            debouncedLoadShiftData();
        });
    }

    return { setup };
}
