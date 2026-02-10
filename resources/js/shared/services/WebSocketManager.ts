/**
 * WebSocketManager - Singleton service for WebSocket connection management
 *
 * @module shared/services/WebSocketManager
 */

import { getEcho } from '../../echo.js';
import { createLogger } from './logger.js';
import { RETRY_CONFIG, EVENT_TYPES, getRetryDelay } from '../config/realtimeConfig.js';

const log = createLogger('WS');

interface ActiveChannel {
    name: string;
    fullName: string;
    channel: unknown;
}

interface ConnectionBindings {
    onConnect: () => void;
    onDisconnect: () => void;
    onError: (error: unknown) => void;
}

export interface WebSocketManagerOptions {
    restaurantId: number;
    channels?: string[];
    onConnected?: () => void;
    onDisconnected?: () => void;
    onMessage?: (eventType: string, data: unknown) => void;
    onLatency?: (latency: number) => void;
    onReconnect?: () => void;
}

export class WebSocketManager {
    private restaurantId: number;
    private channels: string[];
    private onConnected?: () => void;
    private onDisconnected?: () => void;
    private onMessage?: (eventType: string, data: unknown) => void;
    private onLatency?: (latency: number) => void;
    private onReconnect?: () => void;

    private activeChannels: ActiveChannel[];
    private retryCount: number;
    private retryTimer: ReturnType<typeof setTimeout> | null;
    private pingTimer: ReturnType<typeof setInterval> | null;
    private lastPingTime: number;
    private isDestroyed: boolean;
    private connectionBindings: ConnectionBindings | null;

    constructor(options: WebSocketManagerOptions) {
        this.restaurantId = options.restaurantId;
        this.channels = options.channels || [];
        this.onConnected = options.onConnected;
        this.onDisconnected = options.onDisconnected;
        this.onMessage = options.onMessage;
        this.onLatency = options.onLatency;
        this.onReconnect = options.onReconnect;

        this.activeChannels = [];
        this.retryCount = 0;
        this.retryTimer = null;
        this.pingTimer = null;
        this.lastPingTime = 0;
        this.isDestroyed = false;
        this.connectionBindings = null;
    }

    connect(): void {
        if (this.isDestroyed) {
            log.warn('Instance destroyed, cannot connect');
            return;
        }

        this.cleanup();

        const echo = getEcho();
        if (!echo) {
            log.warn('Echo not available, scheduling retry');
            this.scheduleRetry();
            return;
        }

        try {
            this.channels.forEach((channelName: any) => {
                const fullName = `restaurant.${this.restaurantId}.${channelName}`;
                const channel = (echo as any).private(fullName);

                const events = (EVENT_TYPES as Record<string, string[]>)[channelName] || [];
                events.forEach((eventType: string) => {
                    channel.listen(`.${eventType}`, (payload: any) => {
                        const data = payload?.data || payload;
                        this.onMessage?.(eventType, data);
                    });
                });

                this.activeChannels.push({ name: channelName, fullName, channel });
            });

            this.setupConnectionMonitoring(echo);

            log.debug(`Connected to ${this.activeChannels.length} channels for restaurant ${this.restaurantId}`);

        } catch (error: any) {
            log.error('Connection error:', error);
            this.scheduleRetry();
        }
    }

    private setupConnectionMonitoring(echo: any): void {
        const pusher = echo.connector?.pusher;
        if (!pusher?.connection) {
            this.onConnected?.();
            return;
        }

        const connection = pusher.connection;
        this.cleanupConnectionBindings(connection);

        this.connectionBindings = {
            onConnect: () => {
                this.retryCount = 0;
                this.clearRetryTimer();
                this.startPingPong();
                this.onConnected?.();
            },
            onDisconnect: () => {
                this.stopPingPong();
                this.onDisconnected?.();
                if (!this.isDestroyed) {
                    this.scheduleRetry();
                }
            },
            onError: (error: unknown) => {
                log.error('Connection error:', error);
            },
        };

        connection.bind('connected', this.connectionBindings.onConnect);
        connection.bind('disconnected', this.connectionBindings.onDisconnect);
        connection.bind('error', this.connectionBindings.onError);

        if (connection.state === 'connected') {
            this.connectionBindings.onConnect();
        }
    }

    private cleanupConnectionBindings(connection: any): void {
        if (this.connectionBindings && connection) {
            try {
                connection.unbind('connected', this.connectionBindings.onConnect);
                connection.unbind('disconnected', this.connectionBindings.onDisconnect);
                connection.unbind('error', this.connectionBindings.onError);
            } catch {
                // Ignore unbind errors
            }
        }
        this.connectionBindings = null;
    }

    private startPingPong(): void {
        this.stopPingPong();

        this.pingTimer = setInterval(() => {
            this.lastPingTime = Date.now();

            const echo = getEcho();
            const pusher = (echo as any)?.connector?.pusher;

            if (pusher?.connection?.socket) {
                if (typeof pusher.send_event === 'function') {
                    const latency = pusher.connection.socket.latency || 0;
                    this.onLatency?.(latency);
                }
            }
        }, 30000);
    }

    private stopPingPong(): void {
        if (this.pingTimer) {
            clearInterval(this.pingTimer);
            this.pingTimer = null;
        }
    }

    private scheduleRetry(): void {
        this.clearRetryTimer();

        if (this.isDestroyed) return;

        if (this.retryCount >= (RETRY_CONFIG as any).maxRetries) {
            log.error('Max retries reached');
            return;
        }

        const delay = getRetryDelay(this.retryCount);
        this.retryCount++;

        log.debug(`Retry ${this.retryCount}/${(RETRY_CONFIG as any).maxRetries} in ${delay}ms`);

        this.retryTimer = setTimeout(() => {
            if (!this.isDestroyed) {
                this.onReconnect?.();
                this.connect();
            }
        }, delay);
    }

    private clearRetryTimer(): void {
        if (this.retryTimer) {
            clearTimeout(this.retryTimer);
            this.retryTimer = null;
        }
    }

    cleanup(): void {
        const echo = getEcho();

        const wsState = (echo as any)?.connector?.pusher?.connection?.socket?.readyState;
        const canLeave = wsState === undefined || wsState === 1;

        this.activeChannels.forEach(({ fullName }) => {
            try {
                if (canLeave) {
                    (echo as any)?.leave(fullName);
                }
            } catch {
                // Ignore leave errors
            }
        });

        this.activeChannels = [];
        this.stopPingPong();

        const connection = (echo as any)?.connector?.pusher?.connection;
        if (connection) {
            this.cleanupConnectionBindings(connection);
        }
    }

    disconnect(): void {
        this.isDestroyed = true;
        this.clearRetryTimer();
        this.cleanup();
    }

    send(message: unknown): void {
        log.debug('Send:', message);
    }

    reconnect(): void {
        this.retryCount = 0;
        this.isDestroyed = false;
        this.connect();
    }
}
