import { defineStore } from 'pinia';
import { ref, computed } from 'vue';

export const useBackofficeStore = defineStore('backoffice', () => {
    // Auth
    const isAuthenticated = ref(false);
    const user = ref(null);
    const token = ref(localStorage.getItem('backoffice_token') || '');

    // UI State
    const sidebarCollapsed = ref(false);
    const currentModule = ref('dashboard');
    const notifications = ref([]);
    const toasts = ref([]);

    // Loading states
    const loading = ref({
        dashboard: false,
        menu: false,
        staff: false,
        hall: false,
        orders: false,
        customers: false,
        finance: false,
        settings: false,
        delivery: false,
        payroll: false,
        inventory: false,
        loyalty: false,
        analytics: false,
        attendance: false
    });

    // Dashboard data
    const dashboard = ref({
        todayOrders: 0,
        todayRevenue: 0,
        avgCheck: 0,
        activeStaff: 0,
        recentOrders: [],
        popularDishes: [],
        salesByHour: []
    });

    // Menu data
    const categories = ref([]);
    const dishes = ref([]);

    // Staff data
    const staff = ref([]);
    const roles = ref([]);

    // Hall data
    const zones = ref([]);
    const tables = ref([]);

    // Customers data
    const customers = ref([]);

    // Finance data
    const transactions = ref([]);
    const cashBalance = ref(0);

    // Inventory data
    const ingredients = ref([]);
    const warehouses = ref([]);
    const suppliers = ref([]);

    // Loyalty data
    const promotions = ref([]);
    const promoCodes = ref([]);

    // Settings
    const settings = ref({});
    const restaurant = ref({});

    // Menu groups for navigation
    const menuGroups = [
        {
            name: 'Главное',
            items: [
                { id: 'dashboard', name: 'Дашборд', icon: '📊' },
            ]
        },
        {
            name: 'Управление',
            items: [
                { id: 'menu', name: 'Меню', icon: '🍽️' },
                { id: 'hall', name: 'Зал', icon: '🪑' },
                { id: 'staff', name: 'Персонал', icon: '👥' },
                { id: 'attendance', name: 'Учёт времени', icon: '⏱️' },
                { id: 'inventory', name: 'Склад', icon: '📦' },
            ]
        },
        {
            name: 'Продажи',
            items: [
                { id: 'customers', name: 'Клиенты', icon: '👤' },
                { id: 'loyalty', name: 'Лояльность', icon: '🎁' },
                { id: 'delivery', name: 'Доставка', icon: '🚚' },
                { id: 'finance', name: 'Финансы', icon: '💰' },
                { id: 'analytics', name: 'Аналитика', icon: '📈' },
            ]
        },
        {
            name: 'Система',
            items: [
                { id: 'settings', name: 'Настройки', icon: '⚙️' },
            ]
        }
    ];

    // Current module name
    const currentModuleName = computed(() => {
        for (const group of menuGroups) {
            const item = group.items.find(i => i.id === currentModule.value);
            if (item) return item.name;
        }
        return 'Дашборд';
    });

    // API helper
    const api = async (endpoint, options = {}) => {
        const headers = {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            ...options.headers
        };

        if (token.value) {
            headers['Authorization'] = `Bearer ${token.value}`;
        }

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
        if (csrfToken) {
            headers['X-CSRF-TOKEN'] = csrfToken;
        }

        const response = await fetch(`/api${endpoint}`, {
            ...options,
            headers
        });

        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.message || 'API Error');
        }

        return data;
    };

    // Auth actions
    const login = async (email, password) => {
        try {
            const data = await api('/backoffice/login', {
                method: 'POST',
                body: JSON.stringify({ login: email, password })
            });

            if (data.success && data.data) {
                token.value = data.data.token;
                user.value = data.data.user;
                isAuthenticated.value = true;
                localStorage.setItem('backoffice_token', data.data.token);
                return { success: true };
            }
            return { success: false, message: data.message };
        } catch (e) {
            return { success: false, message: e.message };
        }
    };

    const logout = () => {
        token.value = '';
        user.value = null;
        isAuthenticated.value = false;
        localStorage.removeItem('backoffice_token');
    };

    const checkAuth = async () => {
        if (!token.value) return false;

        try {
            const data = await api('/backoffice/me');
            if (data.success && data.data) {
                user.value = data.data.user || data.data;
                isAuthenticated.value = true;
                return true;
            }
        } catch (e) {
            logout();
        }
        return false;
    };

    // Navigation
    const navigateTo = (moduleId) => {
        currentModule.value = moduleId;
    };

    // Toast notifications
    const showToast = (message, type = 'success') => {
        const id = Date.now();
        toasts.value.push({ id, message, type });
        setTimeout(() => {
            toasts.value = toasts.value.filter(t => t.id !== id);
        }, 3000);
    };

    // Dashboard actions
    const loadDashboard = async () => {
        loading.value.dashboard = true;
        try {
            const data = await api('/backoffice/dashboard');
            if (data.success) {
                dashboard.value = { ...dashboard.value, ...data.data };
            }
        } catch (e) {
            console.error('Failed to load dashboard', e);
        } finally {
            loading.value.dashboard = false;
        }
    };

    // Menu actions
    const loadCategories = async () => {
        try {
            const data = await api('/categories');
            if (data.success) {
                categories.value = data.data || data.categories || [];
            }
        } catch (e) {
            console.error('Failed to load categories', e);
        }
    };

    const loadDishes = async () => {
        loading.value.menu = true;
        try {
            const data = await api('/dishes');
            if (data.success) {
                dishes.value = data.data || data.dishes || [];
            }
        } catch (e) {
            console.error('Failed to load dishes', e);
        } finally {
            loading.value.menu = false;
        }
    };

    const saveCategory = async (category) => {
        const method = category.id ? 'PUT' : 'POST';
        const endpoint = category.id ? `/categories/${category.id}` : '/categories';

        const data = await api(endpoint, {
            method,
            body: JSON.stringify(category)
        });

        if (data.success) {
            await loadCategories();
            showToast(category.id ? 'Категория обновлена' : 'Категория создана');
        }
        return data;
    };

    const saveDish = async (dish) => {
        const method = dish.id ? 'PUT' : 'POST';
        const endpoint = dish.id ? `/dishes/${dish.id}` : '/dishes';

        const data = await api(endpoint, {
            method,
            body: JSON.stringify(dish)
        });

        if (data.success) {
            await loadDishes();
            showToast(dish.id ? 'Блюдо обновлено' : 'Блюдо создано');
        }
        return data;
    };

    const deleteDish = async (id) => {
        const data = await api(`/dishes/${id}`, { method: 'DELETE' });
        if (data.success) {
            await loadDishes();
            showToast('Блюдо удалено');
        }
        return data;
    };

    // Staff actions
    const loadStaff = async () => {
        loading.value.staff = true;
        try {
            const data = await api('/staff');
            if (data.success) {
                staff.value = data.data || data.staff || [];
            }
        } catch (e) {
            console.error('Failed to load staff', e);
        } finally {
            loading.value.staff = false;
        }
    };

    const saveStaff = async (staffMember) => {
        const method = staffMember.id ? 'PUT' : 'POST';
        const endpoint = staffMember.id ? `/staff/${staffMember.id}` : '/staff';

        const data = await api(endpoint, {
            method,
            body: JSON.stringify(staffMember)
        });

        if (data.success) {
            await loadStaff();
            showToast(staffMember.id ? 'Сотрудник обновлен' : 'Сотрудник добавлен');
        }
        return data;
    };

    // Hall actions
    const loadZones = async () => {
        try {
            const data = await api('/zones');
            if (data.success) {
                zones.value = data.data || data.zones || [];
            }
        } catch (e) {
            console.error('Failed to load zones', e);
        }
    };

    const loadTables = async () => {
        loading.value.hall = true;
        try {
            const data = await api('/tables');
            if (data.success) {
                tables.value = data.data || data.tables || [];
            }
        } catch (e) {
            console.error('Failed to load tables', e);
        } finally {
            loading.value.hall = false;
        }
    };

    // Customers actions
    const loadCustomers = async () => {
        loading.value.customers = true;
        try {
            const data = await api('/customers');
            if (data.success) {
                customers.value = data.data || data.customers || [];
            }
        } catch (e) {
            console.error('Failed to load customers', e);
        } finally {
            loading.value.customers = false;
        }
    };

    // Inventory actions
    const loadIngredients = async () => {
        try {
            const data = await api('/ingredients');
            if (data.success) {
                ingredients.value = data.data || data.ingredients || [];
            }
        } catch (e) {
            console.error('Failed to load ingredients', e);
        }
    };

    const loadWarehouses = async () => {
        try {
            const data = await api('/warehouses');
            if (data.success) {
                warehouses.value = data.data || data.warehouses || [];
            }
        } catch (e) {
            console.error('Failed to load warehouses', e);
        }
    };

    // Loyalty actions
    const loadPromotions = async () => {
        try {
            const data = await api('/promotions');
            if (data.success) {
                promotions.value = data.data || data.promotions || [];
            }
        } catch (e) {
            console.error('Failed to load promotions', e);
        }
    };

    const loadPromoCodes = async () => {
        try {
            const data = await api('/promo-codes');
            if (data.success) {
                promoCodes.value = data.data || data.promoCodes || [];
            }
        } catch (e) {
            console.error('Failed to load promo codes', e);
        }
    };

    // Settings actions
    const loadSettings = async () => {
        loading.value.settings = true;
        try {
            const data = await api('/settings');
            if (data.success) {
                settings.value = data.data || data.settings || {};
            }
        } catch (e) {
            console.error('Failed to load settings', e);
        } finally {
            loading.value.settings = false;
        }
    };

    const loadRestaurant = async () => {
        try {
            const data = await api('/restaurant');
            if (data.success) {
                restaurant.value = data.data || data.restaurant || {};
            }
        } catch (e) {
            console.error('Failed to load restaurant', e);
        }
    };

    return {
        // State
        isAuthenticated,
        user,
        token,
        sidebarCollapsed,
        currentModule,
        notifications,
        toasts,
        loading,
        dashboard,
        categories,
        dishes,
        staff,
        roles,
        zones,
        tables,
        customers,
        transactions,
        cashBalance,
        ingredients,
        warehouses,
        suppliers,
        promotions,
        promoCodes,
        settings,
        restaurant,
        menuGroups,

        // Computed
        currentModuleName,

        // Actions
        api,
        login,
        logout,
        checkAuth,
        navigateTo,
        showToast,
        loadDashboard,
        loadCategories,
        loadDishes,
        saveCategory,
        saveDish,
        deleteDish,
        loadStaff,
        saveStaff,
        loadZones,
        loadTables,
        loadCustomers,
        loadIngredients,
        loadWarehouses,
        loadPromotions,
        loadPromoCodes,
        loadSettings,
        loadRestaurant
    };
});
