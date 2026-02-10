/**
 * Logger Service Unit Tests
 */

import { describe, it, expect, beforeEach, vi } from 'vitest';

// We need to test the actual logger, not a mock.
// But the logger reads import.meta.env.DEV at module load time.
// We test both dev and production behavior by directly importing and
// testing what levels are actually active.

describe('Logger Service', () => {
    let consoleSpy: {
        log: ReturnType<typeof vi.spyOn>;
        warn: ReturnType<typeof vi.spyOn>;
        error: ReturnType<typeof vi.spyOn>;
    };

    beforeEach(() => {
        consoleSpy = {
            log: vi.spyOn(console, 'log').mockImplementation(() => {}),
            warn: vi.spyOn(console, 'warn').mockImplementation(() => {}),
            error: vi.spyOn(console, 'error').mockImplementation(() => {}),
        };
    });

    // The test environment has DEV=true (vitest sets it), so all log levels are active.
    describe('createLogger', () => {
        it('should create a logger with debug, info, warn, error methods', async () => {
            const { createLogger } = await import('@/shared/services/logger.js');
            const log = createLogger('TestModule');

            expect(typeof log.debug).toBe('function');
            expect(typeof log.info).toBe('function');
            expect(typeof log.warn).toBe('function');
            expect(typeof log.error).toBe('function');
        });

        it('should prefix log messages with the module name in brackets', async () => {
            const { createLogger } = await import('@/shared/services/logger.js');
            const log = createLogger('MyModule');

            log.warn('test message');

            expect(consoleSpy.warn).toHaveBeenCalledWith('[MyModule]', 'test message');
        });
    });

    describe('debug', () => {
        it('should call console.log with prefix and arguments', async () => {
            const { createLogger } = await import('@/shared/services/logger.js');
            const log = createLogger('Debug');

            log.debug('hello', 'world');

            expect(consoleSpy.log).toHaveBeenCalledWith('[Debug]', 'hello', 'world');
        });

        it('should pass multiple arguments through', async () => {
            const { createLogger } = await import('@/shared/services/logger.js');
            const log = createLogger('Test');

            const obj = { key: 'value' };
            log.debug('data:', obj, 42);

            expect(consoleSpy.log).toHaveBeenCalledWith('[Test]', 'data:', obj, 42);
        });
    });

    describe('info', () => {
        it('should call console.log with prefix', async () => {
            const { createLogger } = await import('@/shared/services/logger.js');
            const log = createLogger('Info');

            log.info('information');

            expect(consoleSpy.log).toHaveBeenCalledWith('[Info]', 'information');
        });
    });

    describe('warn', () => {
        it('should call console.warn with prefix', async () => {
            const { createLogger } = await import('@/shared/services/logger.js');
            const log = createLogger('Warn');

            log.warn('warning message');

            expect(consoleSpy.warn).toHaveBeenCalledWith('[Warn]', 'warning message');
        });

        it('should pass multiple arguments to console.warn', async () => {
            const { createLogger } = await import('@/shared/services/logger.js');
            const log = createLogger('W');

            log.warn('code:', 404, { detail: 'not found' });

            expect(consoleSpy.warn).toHaveBeenCalledWith('[W]', 'code:', 404, { detail: 'not found' });
        });
    });

    describe('error', () => {
        it('should call console.error with prefix', async () => {
            const { createLogger } = await import('@/shared/services/logger.js');
            const log = createLogger('Err');

            log.error('something failed');

            expect(consoleSpy.error).toHaveBeenCalledWith('[Err]', 'something failed');
        });

        it('should pass error objects through', async () => {
            const { createLogger } = await import('@/shared/services/logger.js');
            const log = createLogger('ErrTest');

            const err = new Error('test error');
            log.error('caught:', err);

            expect(consoleSpy.error).toHaveBeenCalledWith('[ErrTest]', 'caught:', err);
        });
    });

    describe('default export', () => {
        it('should export a default logger with App prefix', async () => {
            const loggerModule = await import('@/shared/services/logger.js');
            const defaultLogger = loggerModule.default;

            expect(typeof defaultLogger.debug).toBe('function');
            expect(typeof defaultLogger.info).toBe('function');
            expect(typeof defaultLogger.warn).toBe('function');
            expect(typeof defaultLogger.error).toBe('function');

            defaultLogger.warn('default test');
            expect(consoleSpy.warn).toHaveBeenCalledWith('[App]', 'default test');
        });
    });

    describe('Logger interface', () => {
        it('should return the same interface shape for different module names', async () => {
            const { createLogger } = await import('@/shared/services/logger.js');
            const log1 = createLogger('Module1');
            const log2 = createLogger('Module2');

            expect(Object.keys(log1).sort()).toEqual(Object.keys(log2).sort());
        });

        it('should keep different module prefixes separate', async () => {
            const { createLogger } = await import('@/shared/services/logger.js');
            const log1 = createLogger('A');
            const log2 = createLogger('B');

            log1.warn('from A');
            log2.warn('from B');

            expect(consoleSpy.warn).toHaveBeenCalledWith('[A]', 'from A');
            expect(consoleSpy.warn).toHaveBeenCalledWith('[B]', 'from B');
        });
    });
});
