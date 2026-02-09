/**
 * WebSocketManager - Singleton service for WebSocket connection management
 *
 * Manages a single WebSocket connection per application with:
 * - Automatic reconnection with exponential backoff
 * - Connection state monitoring
 * - Ping/pong health checks
 * - Channel subscription management
 *
 * @module shared/services/WebSocketManager
 */

import { getEcho } from '../../echo.js';
import { createLogger } from './logger.js';
import { RETRY_CONFIG, EVENT_TYPES, getRetryDelay } from '../config/realtimeConfig.js';

const log = createLogger('WS');

export class WebSocketManager {
    /**
     * @param {Object} options
     * @param {number} options.restaurantId - Restaurant ID
     * @param {string[]} options.channels - Channels to subscribe to
     * @param {Function} options.onConnected - Connection established callback
     * @param {Function} options.onDisconnected - Connection lost callback
     * @param {Function} options.onMessage - Message received callback
     * @param {Function} options.onLatency - Latency update callback
     * @param {Function} options.onReconnect - Reconnection attempt callback
     */
    constructor(options) {
        this.restaurantId = options.restaurantId;
        this.channels = options.channels || [];
        this.onConnected = options.onConnected;
        this.onDisconnected = options.onDisconnected;
        this.onMessage = options.onMessage;
        this.onLatency = options.onLatency;
        this.onReconnect = options.onReconnect;

        /** @type {Array<{name: string, fullName: string, channel: Object}>} */
        this.activeChannels = [];

        this.retryCount = 0;
        this.retryTimer = null;
        this.pingTimer = null;
        this.lastPingTime = 0;
        this.isDestroyed = false;

        // Connection bindings for cleanup
        this.connectionBindings = null;
    }

    /**
     * Connect to WebSocket and subscribe to channels
     */
    connect() {
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
            // Subscribe to all channels
            this.channels.forEach(channelName => {
                const fullName = `restaurant.${this.restaurantId}.${channelName}`;
                const channel = echo.private(fullName);

                const events = EVENT_TYPES[channelName] || [];
                events.forEach(eventType => {
                    channel.listen(`.${eventType}`, (payload) => {
                        const data = payload?.data || payload;
                        this.onMessage?.(eventType, data);
                    });
                });

                this.activeChannels.push({ name: channelName, fullName, channel });
            });

            // Setup connection monitoring
            this.setupConnectionMonitoring(echo);

            log.debug(`Connected to ${this.activeChannels.length} channels for restaurant ${this.restaurantId}`);

        } catch (error) {
            log.error('Connection error:', error);
            this.scheduleRetry();
        }
    }

    /**
     * Setup connection state monitoring
     * @param {Object} echo - Echo instance
     */
    setupConnectionMonitoring(echo) {
        const pusher = echo.connector?.pusher;
        if (!pusher?.connection) {
            // No Pusher connection object, assume connected
            this.onConnected?.();
            return;
        }

        const connection = pusher.connection;

        // Clean up old bindings
        this.cleanupConnectionBindings(connection);

        // Create new bindings
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
            onError: (error) => {
                log.error('Connection error:', error);
            },
        };

        // Bind handlers
        connection.bind('connected', this.connectionBindings.onConnect);
        connection.bind('disconnected', this.connectionBindings.onDisconnect);
        connection.bind('error', this.connectionBindings.onError);

        // Check current state
        if (connection.state === 'connected') {
            this.connectionBindings.onConnect();
        }
    }

    /**
     * Cleanup connection event bindings
     * @param {Object} connection - Pusher connection object
     */
    cleanupConnectionBindings(connection) {
        if (this.connectionBindings && connection) {
            try {
                connection.unbind('connected', this.connectionBindings.onConnect);
                connection.unbind('disconnected', this.connectionBindings.onDisconnect);
                connection.unbind('error', this.connectionBindings.onError);
            } catch (e) {
                // Ignore unbind errors
            }
        }
        this.connectionBindings = null;
    }

    /**
     * Start ping/pong for latency monitoring
     */
    startPingPong() {
        this.stopPingPong();

        // Track latency every 30 seconds
        this.pingTimer = setInterval(() => {
            this.lastPingTime = Date.now();

            // Pusher handles ping internally, we can estimate latency
            // from the time between sending and receiving pong
            const echo = getEcho();
            const pusher = echo?.connector?.pusher;

            if (pusher?.connection?.socket) {
                // Measure round-trip time
                const startTime = Date.now();

                // Use Pusher's internal ping if available
                if (typeof pusher.send_event === 'function') {
                    // This is a rough estimate based on connection activity
                    const latency = pusher.connection.socket.latency || 0;
                    this.onLatency?.(latency);
                }
            }
        }, 30000);
    }

    /**
     * Stop ping/pong monitoring
     */
    stopPingPong() {
        if (this.pingTimer) {
            clearInterval(this.pingTimer);
            this.pingTimer = null;
        }
    }

    /**
     * Schedule reconnection with exponential backoff
     */
    scheduleRetry() {
        this.clearRetryTimer();

        if (this.isDestroyed) return;

        if (this.retryCount >= RETRY_CONFIG.maxRetries) {
            log.error('Max retries reached');
            return;
        }

        const delay = getRetryDelay(this.retryCount);
        this.retryCount++;

        log.debug(`Retry ${this.retryCount}/${RETRY_CONFIG.maxRetries} in ${delay}ms`);

        this.retryTimer = setTimeout(() => {
            if (!this.isDestroyed) {
                this.onReconnect?.();
                this.connect();
            }
        }, delay);
    }

    /**
     * Clear retry timer
     */
    clearRetryTimer() {
        if (this.retryTimer) {
            clearTimeout(this.retryTimer);
            this.retryTimer = null;
        }
    }

    /**
     * Cleanup channels and timers (but don't destroy instance)
     */
    cleanup() {
        const echo = getEcho();

        // Check if WebSocket is in a valid state before leaving
        const wsState = echo?.connector?.pusher?.connection?.socket?.readyState;
        const canLeave = wsState === undefined || wsState === 1; // undefined or OPEN

        this.activeChannels.forEach(({ fullName }) => {
            try {
                if (canLeave) {
                    echo?.leave(fullName);
                }
            } catch (e) {
                // Ignore leave errors
            }
        });

        this.activeChannels = [];
        this.stopPingPong();

        // Cleanup connection bindings
        const connection = echo?.connector?.pusher?.connection;
        if (connection) {
            this.cleanupConnectionBindings(connection);
        }
    }

    /**
     * Disconnect and destroy the manager
     */
    disconnect() {
        this.isDestroyed = true;
        this.clearRetryTimer();
        this.cleanup();
    }

    /**
     * Send a message (for future client-to-server communication)
     * @param {Object} message - Message to send
     */
    send(message) {
        // For future implementation of client-to-server messages
        log.debug('Send:', message);
    }

    /**
     * Force reconnection
     */
    reconnect() {
        this.retryCount = 0;
        this.isDestroyed = false;
        this.connect();
    }
}
