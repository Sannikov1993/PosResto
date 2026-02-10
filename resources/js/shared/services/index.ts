/**
 * Shared Services Index
 *
 * @module shared/services
 */

export { EventBus } from './EventBus.js';
export { WebSocketManager } from './WebSocketManager.js';
export type { WebSocketManagerOptions } from './WebSocketManager.js';
export { playSound } from './notificationSound.js';
export { createLogger } from './logger.js';
export type { Logger } from './logger.js';
export { createHttpClient } from './httpClient.js';
export type { HttpClientOptions, HttpClientResult } from './httpClient.js';
