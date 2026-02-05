/**
 * Session Management Module
 *
 * Enterprise-grade session management for MenuLab POS.
 *
 * Main components:
 * - SessionManager: Main orchestrator for session lifecycle
 * - TokenStorage: Secure token persistence with dual-layer caching
 * - NetworkRetry: Resilient HTTP requests with exponential backoff
 * - TabSync: Cross-tab session synchronization
 * - EventEmitter: Pub/sub for session events
 *
 * Usage:
 * ```javascript
 * import { getSessionManager, SESSION_EVENTS, SESSION_STATES } from './services/session';
 *
 * const session = getSessionManager({ debug: true });
 *
 * // Create session on login
 * session.createSession({
 *   user: { id: 1, name: 'John' },
 *   token: 'abc123',
 *   permissions: ['orders.create'],
 * });
 *
 * // Restore session on page load
 * const data = await session.restoreSession();
 * if (data) {
 *   console.log('Session restored for', data.user.name);
 * }
 *
 * // Subscribe to events
 * session.on(SESSION_EVENTS.EXPIRING_SOON, ({ timeUntilExpiry, critical }) => {
 *   if (critical) {
 *     showWarning('Session expiring in 5 minutes!');
 *   }
 * });
 *
 * // Logout
 * await session.logout();
 * ```
 *
 * @module services/session
 */

// Main components
export { SessionManager, getSessionManager, resetSessionManager } from './SessionManager.js';
export { TokenStorage, createTokenStorage } from './TokenStorage.js';
export { NetworkRetry, NetworkRetryError, createNetworkRetry } from './NetworkRetry.js';
export { TabSync, createTabSync } from './TabSync.js';
export { EventEmitter, getSessionEventEmitter } from './EventEmitter.js';

// Constants
export {
    STORAGE_KEYS,
    SESSION_TIMING,
    RETRY_CONFIG,
    TAB_SYNC_CONFIG,
    SESSION_STATES,
    SESSION_EVENTS,
    VALIDATION_ERRORS,
    DEFAULT_SESSION,
} from './constants.js';

// Default export
export { getSessionManager as default } from './SessionManager.js';
