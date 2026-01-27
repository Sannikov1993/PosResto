import { defineStore } from 'pinia';
import { ref } from 'vue';
import axios from 'axios';

const API_URL = '/api';

export const useAdminStore = defineStore('admin', () => {
    // State
    const isAuthenticated = ref(false);
    const user = ref(null);
    const token = ref('');
    const loading = ref(false);
    const activeModule = ref('dashboard');
    const toast = ref(null);

    // Data
    const stats = ref({});
    const categories = ref([]);
    const dishes = ref([]);
    const staff = ref([]);
    const zones = ref([]);
    const tables = ref([]);
    const settings = ref({});

    // Auth
    async function login(email, password) {
        loading.value = true;
        try {
            const res = await axios.post(`${API_URL}/auth/login`, { email, password });
            if (res.data.success) {
                token.value = res.data.data.token;
                user.value = res.data.data.user;
                isAuthenticated.value = true;
                localStorage.setItem('admin_token', token.value);
                localStorage.setItem('admin_user', JSON.stringify(user.value));
                axios.defaults.headers.common['Authorization'] = `Bearer ${token.value}`;
                return { success: true };
            }
        } catch (e) {
            return { success: false, message: e.response?.data?.message || 'Ошибка входа' };
        } finally {
            loading.value = false;
        }
    }

    function logout() {
        localStorage.removeItem('admin_token');
        localStorage.removeItem('admin_user');
        isAuthenticated.value = false;
        token.value = '';
        user.value = null;
    }

    function checkAuth() {
        const savedToken = localStorage.getItem('admin_token');
        const savedUser = localStorage.getItem('admin_user');
        if (savedToken && savedUser) {
            token.value = savedToken;
            user.value = JSON.parse(savedUser);
            isAuthenticated.value = true;
            axios.defaults.headers.common['Authorization'] = `Bearer ${savedToken}`;
            return true;
        }
        return false;
    }

    // Data loading
    async function loadStats() {
        try {
            const res = await axios.get(`${API_URL}/admin/stats`);
            if (res.data.success) stats.value = res.data.data;
        } catch (e) { console.error(e); }
    }

    async function loadCategories() {
        try {
            const res = await axios.get(`${API_URL}/menu/categories`);
            if (res.data.success) categories.value = res.data.data;
        } catch (e) { console.error(e); }
    }

    async function loadDishes() {
        try {
            const res = await axios.get(`${API_URL}/menu/dishes`);
            if (res.data.success) dishes.value = res.data.data;
        } catch (e) { console.error(e); }
    }

    async function loadStaff() {
        try {
            const res = await axios.get(`${API_URL}/staff`);
            if (res.data.success) staff.value = res.data.data;
        } catch (e) { console.error(e); }
    }

    async function loadZones() {
        try {
            const res = await axios.get(`${API_URL}/tables/zones`);
            if (res.data.success) zones.value = res.data.data;
        } catch (e) { console.error(e); }
    }

    async function loadTables() {
        try {
            const res = await axios.get(`${API_URL}/tables`);
            if (res.data.success) tables.value = res.data.data;
        } catch (e) { console.error(e); }
    }

    async function loadSettings() {
        try {
            const res = await axios.get(`${API_URL}/settings`);
            if (res.data.success) settings.value = res.data.data;
        } catch (e) { console.error(e); }
    }

    // CRUD
    async function saveCategory(data) {
        try {
            const url = data.id ? `${API_URL}/menu/categories/${data.id}` : `${API_URL}/menu/categories`;
            const res = await axios({ method: data.id ? 'PUT' : 'POST', url, data });
            if (res.data.success) {
                await loadCategories();
                showToast('Категория сохранена');
                return { success: true };
            }
        } catch (e) {
            showToast('Ошибка сохранения', 'error');
            return { success: false };
        }
    }

    async function deleteCategory(id) {
        try {
            await axios.delete(`${API_URL}/menu/categories/${id}`);
            await loadCategories();
            showToast('Категория удалена');
        } catch (e) {
            showToast('Ошибка удаления', 'error');
        }
    }

    async function saveDish(data) {
        try {
            const url = data.id ? `${API_URL}/menu/dishes/${data.id}` : `${API_URL}/menu/dishes`;
            const res = await axios({ method: data.id ? 'PUT' : 'POST', url, data });
            if (res.data.success) {
                await loadDishes();
                showToast('Блюдо сохранено');
                return { success: true };
            }
        } catch (e) {
            showToast('Ошибка сохранения', 'error');
            return { success: false };
        }
    }

    async function deleteDish(id) {
        try {
            await axios.delete(`${API_URL}/menu/dishes/${id}`);
            await loadDishes();
            showToast('Блюдо удалено');
        } catch (e) {
            showToast('Ошибка удаления', 'error');
        }
    }

    async function saveStaffMember(data) {
        try {
            const url = data.id ? `${API_URL}/staff/${data.id}` : `${API_URL}/staff`;
            const res = await axios({ method: data.id ? 'PUT' : 'POST', url, data });
            if (res.data.success) {
                await loadStaff();
                showToast('Сотрудник сохранён');
                return { success: true };
            }
        } catch (e) {
            showToast('Ошибка сохранения', 'error');
            return { success: false };
        }
    }

    async function saveSettings(data) {
        try {
            await axios.put(`${API_URL}/settings`, data);
            await loadSettings();
            showToast('Настройки сохранены');
            return { success: true };
        } catch (e) {
            showToast('Ошибка сохранения', 'error');
            return { success: false };
        }
    }

    function showToast(message, type = 'success') {
        toast.value = { message, type };
        setTimeout(() => { toast.value = null; }, 3000);
    }

    function formatMoney(a) {
        return new Intl.NumberFormat('ru-RU', { style: 'currency', currency: 'RUB', maximumFractionDigits: 0 }).format(a || 0);
    }

    return {
        isAuthenticated, user, token, loading, activeModule, toast,
        stats, categories, dishes, staff, zones, tables, settings,
        login, logout, checkAuth,
        loadStats, loadCategories, loadDishes, loadStaff, loadZones, loadTables, loadSettings,
        saveCategory, deleteCategory, saveDish, deleteDish, saveStaffMember, saveSettings,
        showToast, formatMoney
    };
});
