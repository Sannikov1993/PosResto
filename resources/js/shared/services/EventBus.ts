/**
 * EventBus - Typed event emitter for real-time events
 *
 * @module shared/services/EventBus
 */

import { createLogger } from './logger.js';

const log = createLogger('EventBus');

type EventHandler<T = unknown> = (data: T) => void;

export class EventBus {
    private handlers: Map<string, Set<EventHandler>>;

    constructor() {
        this.handlers = new Map();
    }

    on<T = unknown>(event: string, handler: EventHandler<T>): () => void {
        if (!this.handlers.has(event)) {
            this.handlers.set(event, new Set());
        }
        this.handlers.get(event)!.add(handler as EventHandler);

        return () => this.off(event, handler);
    }

    off<T = unknown>(event: string, handler: EventHandler<T>): void {
        const handlers = this.handlers.get(event);
        if (handlers) {
            handlers.delete(handler as EventHandler);
            if (handlers.size === 0) {
                this.handlers.delete(event);
            }
        }
    }

    emit(event: string, data?: unknown): void {
        const handlers = this.handlers.get(event);
        if (handlers) {
            handlers.forEach((handler: any) => {
                try {
                    handler(data);
                } catch (err: any) {
                    log.error(`Error in handler for ${event}:`, err);
                }
            });
        }
    }

    clear(): void {
        this.handlers.clear();
    }

    getSubscriptions(): Record<string, number> {
        const result: Record<string, number> = {};
        this.handlers.forEach((handlers: any, event: any) => {
            result[event] = handlers.size;
        });
        return result;
    }

    getSubscriptionCount(): number {
        let count = 0;
        this.handlers.forEach((handlers: any) => {
            count += handlers.size;
        });
        return count;
    }

    hasSubscribers(event: string): boolean {
        const handlers = this.handlers.get(event);
        return handlers ? handlers.size > 0 : false;
    }
}
