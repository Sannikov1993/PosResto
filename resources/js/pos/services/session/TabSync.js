/**
 * TabSync - Cross-tab session synchronization
 *
 * Provides real-time synchronization between browser tabs using:
 * - BroadcastChannel API (modern browsers)
 * - localStorage events (fallback)
 *
 * Features:
 * - Leader election (one tab handles token refresh)
 * - Session state sync across tabs
 * - Logout propagation
 * - Activity aggregation
 *
 * @module services/session/TabSync
 */

import { TAB_SYNC_CONFIG } from './constants.js';

const { MESSAGE_TYPES } = TAB_SYNC_CONFIG;

/**
 * Generate unique tab ID
 */
function generateTabId() {
    return `tab_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
}

/**
 * TabSync class for cross-tab communication
 */
export class TabSync {
    /**
     * Creates a new TabSync instance
     * @param {Object} options - Configuration options
     */
    constructor(options = {}) {
        this._channelName = options.channelName || TAB_SYNC_CONFIG.CHANNEL_NAME;
        this._heartbeatInterval = options.heartbeatInterval || TAB_SYNC_CONFIG.HEARTBEAT_INTERVAL;
        this._leaderTimeout = options.leaderTimeout || TAB_SYNC_CONFIG.LEADER_TIMEOUT;
        this._debug = options.debug || false;

        // Tab identity
        this._tabId = generateTabId();
        this._isLeader = false;
        this._leaderId = null;
        this._leaderLastSeen = null;

        // Communication channel
        this._channel = null;
        this._useFallback = false;

        // Event handlers
        this._handlers = new Map();

        // Intervals
        this._heartbeatIntervalId = null;
        this._leaderCheckIntervalId = null;

        // Initialize
        this._initialize();
    }

    /**
     * Initialize the sync channel
     * @private
     */
    _initialize() {
        // Try BroadcastChannel first
        if (typeof BroadcastChannel !== 'undefined') {
            try {
                this._channel = new BroadcastChannel(this._channelName);
                this._channel.onmessage = (event) => this._handleMessage(event.data);
                this._channel.onmessageerror = (error) => {
                    console.error('[TabSync] Message error:', error);
                };
                this._log('Initialized with BroadcastChannel');
            } catch (error) {
                this._log('BroadcastChannel failed, using fallback');
                this._useFallback = true;
            }
        } else {
            this._log('BroadcastChannel not available, using fallback');
            this._useFallback = true;
        }

        // Setup fallback using localStorage
        if (this._useFallback) {
            this._setupLocalStorageFallback();
        }

        // Start leader election
        this._startLeaderElection();

        // Setup visibility change handler
        this._setupVisibilityHandler();

        // Announce presence
        this._broadcast(MESSAGE_TYPES.HEARTBEAT, { tabId: this._tabId });
    }

    /**
     * Setup localStorage fallback for cross-tab communication
     * @private
     */
    _setupLocalStorageFallback() {
        if (typeof window === 'undefined') {
            return;
        }

        const storageKey = `${this._channelName}_message`;

        window.addEventListener('storage', (event) => {
            if (event.key !== storageKey || !event.newValue) {
                return;
            }

            try {
                const message = JSON.parse(event.newValue);

                // Ignore own messages
                if (message.senderId === this._tabId) {
                    return;
                }

                this._handleMessage(message);
            } catch (error) {
                // Ignore parse errors
            }
        });

        this._log('Initialized with localStorage fallback');
    }

    /**
     * Setup visibility change handler
     * @private
     */
    _setupVisibilityHandler() {
        if (typeof document === 'undefined') {
            return;
        }

        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') {
                // Tab became visible - check leadership
                this._checkLeadership();

                // Notify listeners to sync from storage (session data may have changed)
                this._triggerHandler('visible', { tabId: this._tabId, timestamp: Date.now() });

                // Broadcast activity
                this._broadcast(MESSAGE_TYPES.ACTIVITY, {
                    tabId: this._tabId,
                    timestamp: Date.now(),
                });
            }
        });
    }

    /**
     * Start leader election process
     * @private
     */
    _startLeaderElection() {
        // Initial leader claim
        setTimeout(() => {
            this._claimLeadership();
        }, Math.random() * 1000); // Random delay to avoid conflicts

        // Periodic heartbeat
        this._heartbeatIntervalId = setInterval(() => {
            if (this._isLeader) {
                this._broadcast(MESSAGE_TYPES.HEARTBEAT, {
                    tabId: this._tabId,
                    isLeader: true,
                });
            }
        }, this._heartbeatInterval);

        // Check for dead leader
        this._leaderCheckIntervalId = setInterval(() => {
            this._checkLeadership();
        }, this._heartbeatInterval * 2);
    }

    /**
     * Attempt to claim leadership
     * @private
     */
    _claimLeadership() {
        this._broadcast(MESSAGE_TYPES.LEADER_CLAIM, {
            tabId: this._tabId,
            timestamp: Date.now(),
        });

        // Wait for acknowledgements
        setTimeout(() => {
            if (!this._leaderId || this._leaderId === this._tabId) {
                this._becomeLeader();
            }
        }, 500);
    }

    /**
     * Become the leader
     * @private
     */
    _becomeLeader() {
        this._isLeader = true;
        this._leaderId = this._tabId;
        this._leaderLastSeen = Date.now();

        this._log('Became leader');

        // Notify other tabs
        this._broadcast(MESSAGE_TYPES.LEADER_ACK, {
            leaderId: this._tabId,
            timestamp: Date.now(),
        });

        // Notify handlers
        this._triggerHandler('leader', { tabId: this._tabId });
    }

    /**
     * Check if current leader is still alive
     * @private
     */
    _checkLeadership() {
        if (this._isLeader) {
            return;
        }

        // Check if leader has timed out
        if (this._leaderLastSeen &&
            Date.now() - this._leaderLastSeen > this._leaderTimeout) {
            this._log('Leader timed out, starting election');
            this._leaderId = null;
            this._claimLeadership();
        }
    }

    /**
     * Handle incoming message
     * @private
     */
    _handleMessage(message) {
        if (!message || !message.type) {
            return;
        }

        // Ignore own messages (for fallback mode)
        if (message.senderId === this._tabId) {
            return;
        }

        this._log(`Received message: ${message.type}`, message.data);

        switch (message.type) {
            case MESSAGE_TYPES.HEARTBEAT:
                this._handleHeartbeat(message.data);
                break;

            case MESSAGE_TYPES.LEADER_CLAIM:
                this._handleLeaderClaim(message.data);
                break;

            case MESSAGE_TYPES.LEADER_ACK:
                this._handleLeaderAck(message.data);
                break;

            case MESSAGE_TYPES.SESSION_UPDATE:
                this._triggerHandler('sessionUpdate', message.data);
                break;

            case MESSAGE_TYPES.SESSION_CLEAR:
                this._triggerHandler('sessionClear', message.data);
                break;

            case MESSAGE_TYPES.LOGOUT:
                this._triggerHandler('logout', message.data);
                break;

            case MESSAGE_TYPES.TOKEN_REFRESH:
                this._triggerHandler('tokenRefresh', message.data);
                break;

            case MESSAGE_TYPES.ACTIVITY:
                this._triggerHandler('activity', message.data);
                break;

            default:
                // Custom message types
                this._triggerHandler(message.type, message.data);
        }
    }

    /**
     * Handle heartbeat message
     * @private
     */
    _handleHeartbeat(data) {
        if (data.isLeader) {
            this._leaderId = data.tabId;
            this._leaderLastSeen = Date.now();

            // If we thought we were leader, step down
            if (this._isLeader && this._tabId !== data.tabId) {
                this._log('Stepping down as leader');
                this._isLeader = false;
                this._triggerHandler('leaderLost', { newLeader: data.tabId });
            }
        }
    }

    /**
     * Handle leader claim message
     * @private
     */
    _handleLeaderClaim(data) {
        // If we're already leader and our ID is "lower", we win
        if (this._isLeader) {
            if (this._tabId < data.tabId) {
                // We keep leadership
                this._broadcast(MESSAGE_TYPES.LEADER_ACK, {
                    leaderId: this._tabId,
                    timestamp: Date.now(),
                });
            } else {
                // Step down
                this._isLeader = false;
            }
        }
    }

    /**
     * Handle leader acknowledgement
     * @private
     */
    _handleLeaderAck(data) {
        this._leaderId = data.leaderId;
        this._leaderLastSeen = Date.now();

        // If another tab became leader, we're not leader
        if (this._tabId !== data.leaderId) {
            this._isLeader = false;
        }
    }

    /**
     * Broadcast a message to all tabs
     * @param {string} type - Message type
     * @param {Object} data - Message data
     */
    _broadcast(type, data) {
        const message = {
            type,
            data,
            senderId: this._tabId,
            timestamp: Date.now(),
        };

        if (this._channel) {
            try {
                this._channel.postMessage(message);
            } catch (error) {
                console.error('[TabSync] Broadcast failed:', error);
            }
        }

        if (this._useFallback) {
            try {
                const storageKey = `${this._channelName}_message`;
                localStorage.setItem(storageKey, JSON.stringify(message));
                // Clear to allow same message again
                setTimeout(() => {
                    try {
                        localStorage.removeItem(storageKey);
                    } catch (e) {
                        // Ignore
                    }
                }, 100);
            } catch (error) {
                // Ignore storage errors
            }
        }
    }

    /**
     * Register an event handler
     * @param {string} event - Event name
     * @param {Function} callback - Callback function
     * @returns {Function} Unsubscribe function
     */
    on(event, callback) {
        if (!this._handlers.has(event)) {
            this._handlers.set(event, new Set());
        }

        this._handlers.get(event).add(callback);

        return () => {
            const handlers = this._handlers.get(event);
            if (handlers) {
                handlers.delete(callback);
            }
        };
    }

    /**
     * Trigger handlers for an event
     * @private
     */
    _triggerHandler(event, data) {
        const handlers = this._handlers.get(event);
        if (!handlers) {
            return;
        }

        handlers.forEach(callback => {
            try {
                callback(data);
            } catch (error) {
                console.error(`[TabSync] Handler error for "${event}":`, error);
            }
        });
    }

    /**
     * Broadcast session update to other tabs
     * @param {Object} sessionData - Session data
     */
    broadcastSessionUpdate(sessionData) {
        this._broadcast(MESSAGE_TYPES.SESSION_UPDATE, {
            session: sessionData,
            tabId: this._tabId,
            timestamp: Date.now(),
        });
    }

    /**
     * Broadcast session clear (logout) to other tabs
     * @param {Object} options - Options
     */
    broadcastLogout(options = {}) {
        this._broadcast(MESSAGE_TYPES.LOGOUT, {
            tabId: this._tabId,
            reason: options.reason || 'user_logout',
            timestamp: Date.now(),
        });
    }

    /**
     * Broadcast token refresh to other tabs
     * @param {Object} data - Refresh data
     */
    broadcastTokenRefresh(data) {
        this._broadcast(MESSAGE_TYPES.TOKEN_REFRESH, {
            ...data,
            tabId: this._tabId,
            timestamp: Date.now(),
        });
    }

    /**
     * Broadcast activity to other tabs
     */
    broadcastActivity() {
        this._broadcast(MESSAGE_TYPES.ACTIVITY, {
            tabId: this._tabId,
            timestamp: Date.now(),
        });
    }

    /**
     * Check if this tab is the leader
     * @returns {boolean}
     */
    isLeader() {
        return this._isLeader;
    }

    /**
     * Get current tab ID
     * @returns {string}
     */
    getTabId() {
        return this._tabId;
    }

    /**
     * Get leader tab ID
     * @returns {string|null}
     */
    getLeaderId() {
        return this._leaderId;
    }

    /**
     * Get sync status
     * @returns {Object}
     */
    getStatus() {
        return {
            tabId: this._tabId,
            isLeader: this._isLeader,
            leaderId: this._leaderId,
            leaderLastSeen: this._leaderLastSeen,
            usingFallback: this._useFallback,
        };
    }

    /**
     * Force this tab to become leader
     */
    forceLeadership() {
        this._becomeLeader();
    }

    /**
     * Debug logging
     * @private
     */
    _log(message, data) {
        if (this._debug) {
            const prefix = this._isLeader ? '[TabSync:LEADER]' : '[TabSync]';
            if (data) {
                console.debug(`${prefix} ${message}`, data);
            } else {
                console.debug(`${prefix} ${message}`);
            }
        }
    }

    /**
     * Destroy the instance and clean up
     */
    destroy() {
        // Clear intervals
        if (this._heartbeatIntervalId) {
            clearInterval(this._heartbeatIntervalId);
        }

        if (this._leaderCheckIntervalId) {
            clearInterval(this._leaderCheckIntervalId);
        }

        // Close channel
        if (this._channel) {
            try {
                this._channel.close();
            } catch (e) {
                // Ignore
            }
        }

        // Clear handlers
        this._handlers.clear();

        this._log('Destroyed');
    }
}

// Export factory function
export function createTabSync(options = {}) {
    return new TabSync(options);
}

export default TabSync;
