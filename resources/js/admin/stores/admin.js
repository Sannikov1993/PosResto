import { defineStore } from 'pinia';
import { ref } from 'vue';
import api from '../api/index.js';
import authService from '../../shared/services/auth.js';
import { createLogger } from '../../shared/services/logger.js';

const log = createLogger('Admin');

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
            const data = await api.auth.login(email, password);
            token.value = data.token;
            user.value = data.user;
            isAuthenticated.value = true;

            // Используем централизованный auth сервис
            authService.setSession({ token: data.token, user: data.user }, { app: 'admin' });

            return { success: true };
        } catch (e) {
            return { success: false, message: e.response?.data?.message || 'Ошибка входа' };
        } finally {
            loading.value = false;
        }
    }

    function logout() {
        authService.clearAuth();
        isAuthenticated.value = false;
        token.value = '';
        user.value = null;
    }

    function checkAuth() {
        const session = authService.getSession();
        if (session?.token && session?.user) {
            token.value = session.token;
            user.value = session.user;
            isAuthenticated.value = true;
            return true;
        }
        return false;
    }

    // Data loading
    async function loadStats() {
        try {
            stats.value = await api.admin.getStats();
        } catch (e) { log.error('Failed to load stats:', e.message); }
    }

    async function loadCategories() {
        try {
            categories.value = await api.menu.getCategories();
        } catch (e) { log.error('Failed to load categories:', e.message); }
    }

    async function loadDishes() {
        try {
            dishes.value = await api.menu.getDishes();
        } catch (e) { log.error('Failed to load dishes:', e.message); }
    }

    async function loadStaff() {
        try {
            staff.value = await api.staff.getAll();
        } catch (e) { log.error('Failed to load staff:', e.message); }
    }

    async function loadZones() {
        try {
            zones.value = await api.tables.getZones();
        } catch (e) { log.error('Failed to load zones:', e.message); }
    }

    async function loadTables() {
        try {
            tables.value = await api.tables.getAll();
        } catch (e) { log.error('Failed to load tables:', e.message); }
    }

    async function loadSettings() {
        try {
            settings.value = await api.settings.get();
        } catch (e) { log.error('Failed to load settings:', e.message); }
    }

    // CRUD
    async function saveCategory(data) {
        try {
            await api.menu.saveCategory(data);
            await loadCategories();
            showToast('Категория сохранена');
            return { success: true };
        } catch (e) {
            showToast('Ошибка сохранения', 'error');
            return { success: false };
        }
    }

    async function deleteCategory(id) {
        try {
            await api.menu.deleteCategory(id);
            await loadCategories();
            showToast('Категория удалена');
        } catch (e) {
            showToast('Ошибка удаления', 'error');
        }
    }

    async function saveDish(data) {
        try {
            await api.menu.saveDish(data);
            await loadDishes();
            showToast('Блюдо сохранено');
            return { success: true };
        } catch (e) {
            showToast('Ошибка сохранения', 'error');
            return { success: false };
        }
    }

    async function deleteDish(id) {
        try {
            await api.menu.deleteDish(id);
            await loadDishes();
            showToast('Блюдо удалено');
        } catch (e) {
            showToast('Ошибка удаления', 'error');
        }
    }

    async function saveStaffMember(data) {
        try {
            await api.staff.save(data);
            await loadStaff();
            showToast('Сотрудник сохранён');
            return { success: true };
        } catch (e) {
            showToast('Ошибка сохранения', 'error');
            return { success: false };
        }
    }

    async function saveSettings(data) {
        try {
            await api.settings.save(data);
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
