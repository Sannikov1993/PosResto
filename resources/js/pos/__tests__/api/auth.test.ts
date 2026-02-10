/**
 * POS Auth API Module Unit Tests
 */

import { describe, it, expect, beforeEach, vi } from 'vitest';

// Mock httpClient — auth.ts imports { axios, API_BASE } from '../httpClient.js'
const { mockAxios } = vi.hoisted(() => ({
    mockAxios: {
        get: vi.fn(),
        post: vi.fn(),
    },
}));

vi.mock('@/pos/api/httpClient.js', () => ({
    axios: mockAxios,
    API_BASE: '/api',
    default: mockAxios,
    extractArray: vi.fn((res: any) => res?.data || []),
    extractData: vi.fn((res: any) => res?.data?.data || res?.data || res),
}));

import auth from '@/pos/api/modules/auth.js';

describe('POS Auth API', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    describe('auth.loginWithPin', () => {
        it('should POST pin login with pin and app_type', async () => {
            const mockResponse = { data: { success: true, token: 'abc123', user: { id: 1 } } };
            mockAxios.post.mockResolvedValue(mockResponse);

            const result = await auth.loginWithPin('1234');

            expect(mockAxios.post).toHaveBeenCalledWith('/api/auth/login-pin', {
                pin: '1234',
                app_type: 'pos',
                user_id: null,
            });
            expect(result).toEqual(mockResponse.data);
        });

        it('should include user_id when provided', async () => {
            const mockResponse = { data: { success: true, token: 'abc123' } };
            mockAxios.post.mockResolvedValue(mockResponse);

            const result = await auth.loginWithPin('5678', 42);

            expect(mockAxios.post).toHaveBeenCalledWith('/api/auth/login-pin', {
                pin: '5678',
                app_type: 'pos',
                user_id: 42,
            });
            expect(result).toEqual(mockResponse.data);
        });

        it('should propagate errors on failed login', async () => {
            mockAxios.post.mockRejectedValue(new Error('Invalid PIN'));

            await expect(auth.loginWithPin('0000')).rejects.toThrow('Invalid PIN');
        });

        it('should return response data with user object', async () => {
            const userData = { id: 5, name: 'Иван', role: 'cashier' };
            mockAxios.post.mockResolvedValue({
                data: { success: true, token: 'token123', user: userData },
            });

            const result = await auth.loginWithPin('9999', 5);

            expect(result.success).toBe(true);
            expect(result.token).toBe('token123');
            expect(result.user).toEqual(userData);
        });
    });

    describe('auth.checkAuth', () => {
        it('should GET /auth/check with Bearer token', async () => {
            const mockResponse = { data: { success: true, user: { id: 1 } } };
            mockAxios.get.mockResolvedValue(mockResponse);

            const result = await auth.checkAuth('my-token');

            expect(mockAxios.get).toHaveBeenCalledWith('/api/auth/check', {
                headers: { Authorization: 'Bearer my-token' },
            });
            expect(result).toEqual(mockResponse.data);
        });

        it('should propagate errors when token is invalid', async () => {
            mockAxios.get.mockRejectedValue(new Error('Unauthorized'));

            await expect(auth.checkAuth('bad-token')).rejects.toThrow('Unauthorized');
        });
    });

    describe('auth.logout', () => {
        it('should POST /auth/logout with Bearer token', async () => {
            mockAxios.post.mockResolvedValue({ data: {} });

            await auth.logout('my-token');

            expect(mockAxios.post).toHaveBeenCalledWith('/api/auth/logout', {}, {
                headers: { Authorization: 'Bearer my-token' },
            });
        });

        it('should not return data', async () => {
            mockAxios.post.mockResolvedValue({ data: {} });

            const result = await auth.logout('my-token');

            expect(result).toBeUndefined();
        });

        it('should propagate errors on logout failure', async () => {
            mockAxios.post.mockRejectedValue(new Error('Network error'));

            await expect(auth.logout('my-token')).rejects.toThrow('Network error');
        });
    });
});
