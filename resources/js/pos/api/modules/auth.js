import { axios, API_BASE } from '../httpClient';

const auth = {
    async loginWithPin(pin, userId = null) {
        const { data } = await axios.post(`${API_BASE}/auth/login-pin`, {
            pin,
            app_type: 'pos',
            user_id: userId,
        });
        return data;
    },

    async checkAuth(token) {
        const { data } = await axios.get(`${API_BASE}/auth/check`, {
            headers: { Authorization: `Bearer ${token}` }
        });
        return data;
    },

    async logout(token) {
        await axios.post(`${API_BASE}/auth/logout`, {}, {
            headers: { Authorization: `Bearer ${token}` }
        });
    }
};

export default auth;
