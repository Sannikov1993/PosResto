import { axios, API_BASE } from '../httpClient.js';
import type { ApiResponse } from '@/shared/types';

interface LoginResponse {
    success: boolean;
    token?: string;
    user?: Record<string, any>;
    [key: string]: unknown;
}

const auth = {
    async loginWithPin(pin: string, userId: number | null = null): Promise<LoginResponse> {
        const { data } = await axios.post(`${API_BASE}/auth/login-pin`, {
            pin,
            app_type: 'pos',
            user_id: userId,
        });
        return data;
    },

    async checkAuth(token: string): Promise<ApiResponse<unknown>> {
        const { data } = await axios.get(`${API_BASE}/auth/check`, {
            headers: { Authorization: `Bearer ${token}` }
        });
        return data;
    },

    async logout(token: string): Promise<void> {
        await axios.post(`${API_BASE}/auth/logout`, {}, {
            headers: { Authorization: `Bearer ${token}` }
        });
    }
};

export default auth;
