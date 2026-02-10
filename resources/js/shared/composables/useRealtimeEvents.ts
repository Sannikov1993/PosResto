/**
 * useRealtimeEvents - Composable for components to subscribe to real-time events
 *
 * @module shared/composables/useRealtimeEvents
 */

import { onBeforeUnmount, getCurrentInstance } from 'vue';
import { storeToRefs } from 'pinia';
import { useRealtimeStore } from '../stores/realtime.js';
import { debounce } from '../config/realtimeConfig.js';

type EventHandler = (data: unknown) => void;

interface DebouncedHandler extends EventHandler {
    cancel?: () => void;
}

interface OnOptions {
    debounce?: number;
}

export function useRealtimeEvents() {
    const store = useRealtimeStore();
    const { connected, connecting, latency, isReady } = storeToRefs(store);

    const unsubscribers: Array<() => void> = [];
    const debouncedHandlers = new Map<EventHandler, DebouncedHandler>();

    const instance = getCurrentInstance();

    function on(event: string, handler: EventHandler, options: OnOptions = {}): () => void {
        let wrappedHandler: DebouncedHandler = handler;

        if (options.debounce && options.debounce > 0) {
            wrappedHandler = debounce(handler, options.debounce) as DebouncedHandler;
            debouncedHandlers.set(handler, wrappedHandler);
        }

        const unsub = store.on(event, wrappedHandler);

        const cleanup = () => {
            unsub();
            const debounced = debouncedHandlers.get(handler);
            if (debounced?.cancel) {
                debounced.cancel();
            }
            debouncedHandlers.delete(handler);
        };

        unsubscribers.push(cleanup);
        return cleanup;
    }

    function off(event: string, handler: EventHandler): void {
        const wrappedHandler = debouncedHandlers.get(handler) || handler;

        if ((wrappedHandler as DebouncedHandler).cancel) {
            (wrappedHandler as DebouncedHandler).cancel!();
        }
        debouncedHandlers.delete(handler);

        store.off(event, wrappedHandler);
    }

    function once(event: string, handler: EventHandler): void {
        store.once(event, handler);
    }

    function cleanup(): void {
        unsubscribers.forEach((unsub: any) => {
            try {
                unsub();
            } catch {
                // Ignore cleanup errors
            }
        });
        unsubscribers.length = 0;
        debouncedHandlers.clear();
    }

    if (instance) {
        onBeforeUnmount(() => {
            cleanup();
        });
    }

    return {
        connected,
        connecting,
        latency,
        isReady,
        on,
        off,
        once,
        cleanup,
    };
}
