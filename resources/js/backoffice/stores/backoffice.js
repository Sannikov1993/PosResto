import { defineStore } from 'pinia';
import { ref, shallowRef, computed } from 'vue';
import { usePermissionsStore } from '@/shared/stores/permissions.js';
import { getRestaurantId } from '@/shared/constants/storage.js';
import {
    getSession as getUnifiedSession,
    setSession as setUnifiedSession,
    clearAuth as clearUnifiedAuth,
    getToken as getUnifiedToken,
} from '@/shared/services/auth.js';
import { createLogger } from '@/shared/services/logger.js';

const log = createLogger('BackofficeStore');

/**
 * Get initial token from unified auth or legacy storage
 */
function getInitialToken() {
    // Try unified auth first (SSO)
    const unifiedToken = getUnifiedToken();
    if (unifiedToken) return unifiedToken;
    // Fallback to legacy
    return localStorage.getItem('backoffice_token') || '';
}

/**
 * Get initial session data from unified auth
 */
function getInitialSessionData() {
    const session = getUnifiedSession();
    if (session) {
        return {
            user: session.user || null,
            permissions: session.permissions || [],
            limits: session.limits || {},
            interfaceAccess: session.interfaceAccess || {},
        };
    }
    return null;
}

export const useBackofficeStore = defineStore('backoffice', () => {
    // Try to restore from unified session (SSO)
    const initialSession = getInitialSessionData();

    // Auth
    const isAuthenticated = ref(false);
    const user = ref(initialSession?.user || null);
    const token = ref(getInitialToken());
    const permissions = ref(initialSession?.permissions || JSON.parse(localStorage.getItem('backoffice_permissions') || '[]'));
    const limits = ref(initialSession?.limits || JSON.parse(localStorage.getItem('backoffice_limits') || '{}'));
    const interfaceAccess = ref(initialSession?.interfaceAccess || JSON.parse(localStorage.getItem('backoffice_interface_access') || '{}'));
    const posModules = ref(JSON.parse(localStorage.getItem('backoffice_pos_modules') || '[]'));
    const backofficeModules = ref(JSON.parse(localStorage.getItem('backoffice_backoffice_modules') || '[]'));

    // Проверка прав
    const hasPermission = (perm) => {
        if (!user.value) return false;
        const role = user.value.role;
        if (role === 'super_admin' || role === 'owner') return true;
        return permissions.value.includes('*') || permissions.value.includes(perm);
    };

    // UI State
    const sidebarCollapsed = ref(false);
    // Восстанавливаем модуль из URL или localStorage
    const getInitialModule = () => {
        const urlParams = new URLSearchParams(window.location.search);
        const tabFromUrl = urlParams.get('tab');
        if (tabFromUrl) return tabFromUrl;

        const hashModule = window.location.hash.replace('#', '');
        if (hashModule) return hashModule;

        return localStorage.getItem('backoffice_module') || 'dashboard';
    };
    const currentModule = ref(getInitialModule());
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

    // Menu data — shallowRef for large arrays
    const categories = shallowRef([]);
    const dishes = shallowRef([]);

    // Staff data
    const staff = shallowRef([]);
    const roles = ref([]);

    // Hall data
    const zones = ref([]);
    const tables = shallowRef([]);

    // Customers data
    const customers = shallowRef([]);

    // Finance data
    const transactions = shallowRef([]);
    const cashBalance = ref(0);

    // Inventory data
    const ingredients = shallowRef([]);
    const warehouses = ref([]);
    const suppliers = shallowRef([]);

    // Loyalty data
    const promotions = shallowRef([]);
    const promoCodes = shallowRef([]);

    // Settings
    const settings = ref({});
    const restaurant = ref({});

    // Tenant & Restaurants
    const tenant = ref(null);
    const restaurants = ref([]);
    // Use centralized restaurant ID from PermissionsStore (with legacy fallback)
    const currentRestaurantId = ref(getRestaurantId());

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
                { id: 'pricelists', name: 'Прайс-листы', icon: '💲' },
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
                { id: 'integrations', name: 'Интеграции', icon: '🔗' },
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

    // Current restaurant
    const currentRestaurant = computed(() => {
        if (!restaurants.value.length) return null;
        const id = currentRestaurantId.value ? parseInt(currentRestaurantId.value) : null;
        return restaurants.value.find(r => r.id === id) || restaurants.value.find(r => r.is_current) || restaurants.value[0];
    });

    // Has multiple restaurants
    const hasMultipleRestaurants = computed(() => restaurants.value.length > 1);

    // Маппинг модулей навигации → требуемые разрешения
    const modulePermissions = {
        dashboard: null, // всегда доступен
        menu: 'menu.view',
        pricelists: 'menu.view',
        hall: 'settings.edit',
        staff: 'staff.view',
        attendance: 'staff.view',
        inventory: 'inventory.view',
        customers: 'customers.view',
        loyalty: 'loyalty.view',
        delivery: 'orders.view',
        finance: 'finance.view',
        analytics: 'reports.view',
        integrations: 'settings.view',
        settings: 'settings.view',
    };

    // Фильтрованные группы навигации по модулям и правам (3-level access)
    const filteredMenuGroups = computed(() => {
        const permissionsStore = usePermissionsStore();

        return menuGroups.map(group => ({
            ...group,
            items: group.items.filter(item => {
                // Level 2: Проверка доступа к модулю backoffice
                if (!permissionsStore.canAccessBackofficeModule(item.id)) {
                    return false;
                }

                // Level 3: Проверка функциональных прав
                const perm = modulePermissions[item.id];
                return !perm || hasPermission(perm);
            })
        })).filter(group => group.items.length > 0);
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
            if (response.status === 401) {
                showToast('Сессия истекла. Перезагрузите страницу.', 'error');
            } else if (response.status === 403) {
                showToast('Недостаточно прав для этого действия', 'error');
            }
            throw new Error(data.message || 'Ошибка API');
        }

        return data;
    };

    // Auth actions
    const savePermissions = (perms, lim, access, posMods, boMods) => {
        permissions.value = perms || [];
        limits.value = lim || {};
        interfaceAccess.value = access || {};
        posModules.value = posMods || [];
        backofficeModules.value = boMods || [];
        localStorage.setItem('backoffice_permissions', JSON.stringify(permissions.value));
        localStorage.setItem('backoffice_limits', JSON.stringify(limits.value));
        localStorage.setItem('backoffice_interface_access', JSON.stringify(interfaceAccess.value));
        localStorage.setItem('backoffice_pos_modules', JSON.stringify(posModules.value));
        localStorage.setItem('backoffice_backoffice_modules', JSON.stringify(backofficeModules.value));
    };

    const login = async (email, password) => {
        try {
            const data = await api('/backoffice/login', {
                method: 'POST',
                body: JSON.stringify({ login: email, password, app_type: 'backoffice' })
            });

            if (data.success && data.data) {
                token.value = data.data.token;
                user.value = data.data.user;
                isAuthenticated.value = true;

                // Save to unified auth (enables SSO with POS)
                setUnifiedSession({
                    token: data.data.token,
                    user: data.data.user,
                    permissions: data.data.permissions || [],
                    limits: data.data.limits || {},
                    interfaceAccess: data.data.interface_access || {},
                }, { app: 'backoffice' });

                // Also save to legacy keys for backward compatibility
                localStorage.setItem('backoffice_token', data.data.token);
                savePermissions(
                    data.data.permissions,
                    data.data.limits,
                    data.data.interface_access,
                    data.data.pos_modules,
                    data.data.backoffice_modules
                );

                // Initialize PermissionsStore with all access levels
                const permissionsStore = usePermissionsStore();

                // Set restaurant ID via centralized store (syncs to all apps automatically)
                if (data.data.user?.restaurant_id) {
                    currentRestaurantId.value = data.data.user.restaurant_id;
                    permissionsStore.setRestaurantId(data.data.user.restaurant_id);
                }
                permissionsStore.init({
                    permissions: data.data.permissions || [],
                    limits: data.data.limits || {},
                    interfaceAccess: data.data.interface_access || {},
                    posModules: data.data.pos_modules || [],
                    backofficeModules: data.data.backoffice_modules || [],
                    role: data.data.user?.role || null,
                });

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
        permissions.value = [];
        limits.value = {};
        interfaceAccess.value = {};
        posModules.value = [];
        backofficeModules.value = [];

        // Clear unified auth (clears all legacy keys too)
        clearUnifiedAuth();

        // Also explicitly clear backoffice-specific keys
        localStorage.removeItem('backoffice_permissions');
        localStorage.removeItem('backoffice_pos_modules');
        localStorage.removeItem('backoffice_backoffice_modules');

        // Reset PermissionsStore
        const permissionsStore = usePermissionsStore();
        permissionsStore.reset();
    };

    const checkAuth = async () => {
        if (!token.value) return false;

        try {
            const data = await api('/backoffice/me');
            if (data.success && data.data) {
                const userData = data.data.user || data.data;
                user.value = userData;
                isAuthenticated.value = true;

                // Обновляем права из ответа сервера
                if (data.data.permissions) {
                    savePermissions(
                        data.data.permissions,
                        data.data.limits,
                        data.data.interface_access,
                        data.data.pos_modules,
                        data.data.backoffice_modules
                    );
                }

                // Update unified session (keeps SSO in sync)
                setUnifiedSession({
                    token: token.value,
                    user: userData,
                    permissions: data.data.permissions || permissions.value,
                    limits: data.data.limits || limits.value,
                    interfaceAccess: data.data.interface_access || interfaceAccess.value,
                }, { app: 'backoffice' });

                // Initialize PermissionsStore with all access levels
                const permissionsStore = usePermissionsStore();
                permissionsStore.init({
                    permissions: data.data.permissions || permissions.value,
                    limits: data.data.limits || limits.value,
                    interfaceAccess: data.data.interface_access || interfaceAccess.value,
                    posModules: data.data.pos_modules || posModules.value,
                    backofficeModules: data.data.backoffice_modules || backofficeModules.value,
                    role: userData?.role || null,
                });

                // Set restaurant ID via centralized store (syncs to all apps automatically)
                if (userData?.restaurant_id) {
                    currentRestaurantId.value = userData.restaurant_id;
                    permissionsStore.setRestaurantId(userData.restaurant_id);
                }

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
        // Сохраняем в localStorage и URL
        localStorage.setItem('backoffice_module', moduleId);
        window.history.replaceState(null, '', `/backoffice#${moduleId}`);
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
            log.error('Failed to load dashboard', e);
        } finally {
            loading.value.dashboard = false;
        }
    };

    // Menu actions
    const loadCategories = async () => {
        try {
            const data = await api('/backoffice/menu/categories');
            if (data.success) {
                categories.value = data.data || data.categories || [];
            }
        } catch (e) {
            log.error('Failed to load categories', e);
        }
    };

    const loadDishes = async () => {
        loading.value.menu = true;
        try {
            const data = await api('/backoffice/menu/dishes');
            if (data.success) {
                dishes.value = data.data || data.dishes || [];
            }
        } catch (e) {
            log.error('Failed to load dishes', e);
        } finally {
            loading.value.menu = false;
        }
    };

    const saveCategory = async (category) => {
        const method = category.id ? 'PUT' : 'POST';
        const endpoint = category.id
            ? `/backoffice/menu/categories/${category.id}`
            : '/backoffice/menu/categories';

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
        const endpoint = dish.id
            ? `/backoffice/menu/dishes/${dish.id}`
            : '/backoffice/menu/dishes';

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
        const data = await api(`/backoffice/menu/dishes/${id}`, { method: 'DELETE' });
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
            const data = await api('/backoffice/staff');
            log.debug('API response:', data);
            if (data.success) {
                staff.value = data.data || data.staff || [];
                log.debug('Staff loaded:', staff.value.length, 'employees');
                log.debug('Roles:', staff.value.map(s => s.role));
            } else {
                log.warn('API returned success: false');
            }
        } catch (e) {
            log.error('Failed to load staff', e);
        } finally {
            loading.value.staff = false;
        }
    };

    const saveStaff = async (staffMember) => {
        const method = staffMember.id ? 'PUT' : 'POST';
        const endpoint = staffMember.id ? `/backoffice/staff/${staffMember.id}` : '/backoffice/staff';

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
            const data = await api('/backoffice/zones');
            if (data.success) {
                zones.value = data.data || data.zones || [];
            }
        } catch (e) {
            log.error('Failed to load zones', e);
        }
    };

    const loadTables = async () => {
        loading.value.hall = true;
        try {
            const data = await api('/backoffice/tables');
            if (data.success) {
                tables.value = data.data || data.tables || [];
            }
        } catch (e) {
            log.error('Failed to load tables', e);
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
            log.error('Failed to load customers', e);
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
            log.error('Failed to load ingredients', e);
        }
    };

    const loadWarehouses = async () => {
        try {
            const data = await api('/warehouses');
            if (data.success) {
                warehouses.value = data.data || data.warehouses || [];
            }
        } catch (e) {
            log.error('Failed to load warehouses', e);
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
            log.error('Failed to load promotions', e);
        }
    };

    const loadPromoCodes = async () => {
        try {
            const data = await api('/promo-codes');
            if (data.success) {
                promoCodes.value = data.data || data.promoCodes || [];
            }
        } catch (e) {
            log.error('Failed to load promo codes', e);
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
            log.error('Failed to load settings', e);
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
            log.error('Failed to load restaurant', e);
        }
    };

    // Tenant actions
    const loadTenant = async () => {
        try {
            const data = await api('/tenant');
            if (data.success) {
                tenant.value = data.data;
            }
        } catch (e) {
            log.error('Failed to load tenant', e);
        }
    };

    const loadRestaurants = async () => {
        try {
            const data = await api('/tenant/restaurants');
            if (data.success) {
                restaurants.value = data.data || [];
                // Set current restaurant from server response
                const current = restaurants.value.find(r => r.is_current);
                if (current) {
                    currentRestaurantId.value = current.id;
                    // Use centralized store (syncs to all apps automatically)
                    const permissionsStore = usePermissionsStore();
                    permissionsStore.setRestaurantId(current.id);
                }
            }
        } catch (e) {
            log.error('Failed to load restaurants', e);
        }
    };

    const switchRestaurant = async (restaurantId) => {
        try {
            const data = await api(`/tenant/restaurants/${restaurantId}/switch`, {
                method: 'POST'
            });
            if (data.success) {
                currentRestaurantId.value = restaurantId;
                // Use centralized store (syncs to all apps automatically)
                const permissionsStore = usePermissionsStore();
                permissionsStore.setRestaurantId(restaurantId);
                // Reload data for new restaurant
                await loadDashboard();
                showToast(data.message || 'Точка переключена');
                // Reload restaurants to update is_current flag
                await loadRestaurants();
            }
            return data;
        } catch (e) {
            showToast(e.message, 'error');
            return { success: false, message: e.message };
        }
    };

    return {
        // State
        isAuthenticated,
        user,
        token,
        permissions,
        limits,
        interfaceAccess,
        posModules,
        backofficeModules,
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
        filteredMenuGroups,
        tenant,
        restaurants,
        currentRestaurantId,

        // Computed
        currentModuleName,
        currentRestaurant,
        hasMultipleRestaurants,

        // Actions
        api,
        login,
        logout,
        checkAuth,
        hasPermission,
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
        loadRestaurant,
        loadTenant,
        loadRestaurants,
        switchRestaurant
    };
});
