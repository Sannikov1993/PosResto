import { defineStore } from 'pinia';
import { ref } from 'vue';
import api from '../api/index.js';
import authService from '../../shared/services/auth.js';
import { createLogger } from '../../shared/services/logger.js';

const log = createLogger('Admin');

interface ToastMessage {
    message: string;
    type: string;
}

export const useAdminStore = defineStore('admin', () => {
    // State
    const isAuthenticated = ref(false);
    const user = ref<Record<string, any> | null>(null);
    const token = ref('');
    const loading = ref(false);
    const activeModule = ref('dashboard');
    const toast = ref<ToastMessage | null>(null);

    // Data
    const stats = ref<Record<string, any>>({});
    const categories = ref<any[]>([]);
    const dishes = ref<any[]>([]);
    const staff = ref<any[]>([]);
    const zones = ref<any[]>([]);
    const tables = ref<any[]>([]);
    const settings = ref<Record<string, any>>({});

    // Auth
    async function login(email: string, password: string): Promise<{ success: boolean; message?: string }> {
        loading.value = true;
        try {
            const data = await api.auth.login(email, password) as Record<string, any>;
            token.value = data.token as string;
            user.value = data.user as Record<string, any>;
            isAuthenticated.value = true;

            authService.setSession({ token: data.token, user: data.user }, { app: 'admin' });

            return { success: true };
        } catch (e: unknown) {
            return { success: false, message: (e as Record<string, Record<string, Record<string, string>>>).response?.data?.message || 'Ошибка входа' };
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

    function checkAuth(): boolean {
        const session = authService.getSession();
        if (session?.token && session?.user) {
            token.value = session.token as string;
            user.value = session.user as Record<string, any>;
            isAuthenticated.value = true;
            return true;
        }
        return false;
    }

    // Data loading
    async function loadStats() {
        try {
            stats.value = (await api.admin.getStats()) as Record<string, any>;
        } catch (e: unknown) { log.error('Failed to load stats:', (e as Error).message); }
    }

    async function loadCategories() {
        try {
            categories.value = await api.menu.getCategories();
        } catch (e: unknown) { log.error('Failed to load categories:', (e as Error).message); }
    }

    async function loadDishes() {
        try {
            dishes.value = await api.menu.getDishes();
        } catch (e: unknown) { log.error('Failed to load dishes:', (e as Error).message); }
    }

    async function loadStaff() {
        try {
            staff.value = await api.staff.getAll();
        } catch (e: unknown) { log.error('Failed to load staff:', (e as Error).message); }
    }

    async function loadZones() {
        try {
            zones.value = await api.tables.getZones();
        } catch (e: unknown) { log.error('Failed to load zones:', (e as Error).message); }
    }

    async function loadTables() {
        try {
            tables.value = await api.tables.getAll();
        } catch (e: unknown) { log.error('Failed to load tables:', (e as Error).message); }
    }

    async function loadSettings() {
        try {
            settings.value = (await api.settings.get()) as Record<string, any>;
        } catch (e: unknown) { log.error('Failed to load settings:', (e as Error).message); }
    }

    // CRUD
    async function saveCategory(data: Record<string, any>): Promise<{ success: boolean }> {
        try {
            await api.menu.saveCategory(data);
            await loadCategories();
            showToast('Категория сохранена');
            return { success: true };
        } catch (e: any) {
            showToast('Ошибка сохранения', 'error');
            return { success: false };
        }
    }

    async function deleteCategory(id: number) {
        try {
            await api.menu.deleteCategory(id);
            await loadCategories();
            showToast('Категория удалена');
        } catch (e: any) {
            showToast('Ошибка удаления', 'error');
        }
    }

    async function saveDish(data: Record<string, any>): Promise<{ success: boolean }> {
        try {
            await api.menu.saveDish(data);
            await loadDishes();
            showToast('Блюдо сохранено');
            return { success: true };
        } catch (e: any) {
            showToast('Ошибка сохранения', 'error');
            return { success: false };
        }
    }

    async function deleteDish(id: number) {
        try {
            await api.menu.deleteDish(id);
            await loadDishes();
            showToast('Блюдо удалено');
        } catch (e: any) {
            showToast('Ошибка удаления', 'error');
        }
    }

    async function saveStaffMember(data: Record<string, any>): Promise<{ success: boolean }> {
        try {
            await api.staff.save(data);
            await loadStaff();
            showToast('Сотрудник сохранён');
            return { success: true };
        } catch (e: any) {
            showToast('Ошибка сохранения', 'error');
            return { success: false };
        }
    }

    async function saveSettings(data: Record<string, any>): Promise<{ success: boolean }> {
        try {
            await api.settings.save(data);
            await loadSettings();
            showToast('Настройки сохранены');
            return { success: true };
        } catch (e: any) {
            showToast('Ошибка сохранения', 'error');
            return { success: false };
        }
    }

    function showToast(message: string, type: string = 'success') {
        toast.value = { message, type };
        setTimeout(() => { toast.value = null; }, 3000);
    }

    function formatMoney(a: number): string {
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
