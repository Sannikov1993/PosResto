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

// ── Types ──

interface BackofficeUser {
    id: number;
    name: string;
    email: string;
    phone?: string | null;
    role: string;
    restaurant_id?: number | null;
    avatar?: string | null;
    [key: string]: unknown;
}

interface DashboardData {
    todayOrders: number;
    todayRevenue: number;
    avgCheck: number;
    activeStaff: number;
    recentOrders: unknown[];
    popularDishes: unknown[];
    salesByHour: unknown[];
}

interface LoadingStates {
    dashboard: boolean;
    menu: boolean;
    staff: boolean;
    hall: boolean;
    orders: boolean;
    customers: boolean;
    finance: boolean;
    settings: boolean;
    delivery: boolean;
    payroll: boolean;
    inventory: boolean;
    loyalty: boolean;
    analytics: boolean;
    attendance: boolean;
}

interface Toast {
    id: number;
    message: string;
    type: string;
}

interface MenuItem {
    id: string;
    name: string;
    icon: string;
    badge?: string | number;
}

interface MenuGroup {
    name: string;
    items: MenuItem[];
}

interface Restaurant {
    id: number;
    name: string;
    address?: string;
    is_current?: boolean;
    is_main?: boolean;
    [key: string]: unknown;
}

interface Tenant {
    id: number;
    name: string;
    is_on_trial?: boolean;
    days_until_expiration?: number;
    [key: string]: unknown;
}

interface ApiOptions extends RequestInit {
    headers?: Record<string, string>;
}

interface ApiResponse<T = unknown> {
    success: boolean;
    data?: T;
    message?: string;
    [key: string]: unknown;
}

interface LoginResponseData {
    token: string;
    user: BackofficeUser;
    permissions?: string[];
    limits?: Record<string, any>;
    interface_access?: Record<string, any>;
    pos_modules?: string[];
    backoffice_modules?: string[];
}

interface AuthCheckResponseData {
    user?: BackofficeUser;
    permissions?: string[];
    limits?: Record<string, any>;
    interface_access?: Record<string, any>;
    pos_modules?: string[];
    backoffice_modules?: string[];
}

interface SessionData {
    user: BackofficeUser | null;
    permissions: string[];
    limits: Record<string, any>;
    interfaceAccess: Record<string, any>;
}

// ── Helpers ──

function getInitialToken(): string {
    const unifiedToken = getUnifiedToken();
    if (unifiedToken) return unifiedToken;
    return localStorage.getItem('backoffice_token') || '';
}

function getInitialSessionData(): SessionData | null {
    const session = getUnifiedSession();
    if (session) {
        return {
            user: (session as Record<string, any>).user as BackofficeUser | null || null,
            permissions: (session as Record<string, any>).permissions as string[] || [],
            limits: (session as Record<string, any>).limits as Record<string, any> || {},
            interfaceAccess: (session as Record<string, any>).interfaceAccess as Record<string, any> || {},
        };
    }
    return null;
}

// ── Module permissions mapping ──

const modulePermissions: Record<string, string | null> = {
    dashboard: null as any,
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

// ── Store ──

export const useBackofficeStore = defineStore('backoffice', () => {
    const initialSession = getInitialSessionData();

    // Auth
    const isAuthenticated = ref(false);
    const user = ref<BackofficeUser | null>(initialSession?.user || null);
    const token = ref(getInitialToken());
    const permissions = ref<string[]>(initialSession?.permissions || JSON.parse(localStorage.getItem('backoffice_permissions') || '[]'));
    const limits = ref<Record<string, any>>(initialSession?.limits || JSON.parse(localStorage.getItem('backoffice_limits') || '{}'));
    const interfaceAccess = ref<Record<string, any>>(initialSession?.interfaceAccess || JSON.parse(localStorage.getItem('backoffice_interface_access') || '{}'));
    const posModules = ref<string[]>(JSON.parse(localStorage.getItem('backoffice_pos_modules') || '[]'));
    const backofficeModules = ref<string[]>(JSON.parse(localStorage.getItem('backoffice_backoffice_modules') || '[]'));

    const hasPermission = (perm: string): boolean => {
        if (!user.value) return false;
        const role = user.value.role;
        if (role === 'super_admin' || role === 'owner') return true;
        return permissions.value.includes('*') || permissions.value.includes(perm);
    };

    // UI State
    const sidebarCollapsed = ref(false);
    const getInitialModule = (): string => {
        const urlParams = new URLSearchParams(window.location.search);
        const tabFromUrl = urlParams.get('tab');
        if (tabFromUrl) return tabFromUrl;

        const hashModule = window.location.hash.replace('#', '');
        if (hashModule) return hashModule;

        return localStorage.getItem('backoffice_module') || 'dashboard';
    };
    const currentModule = ref(getInitialModule());
    const notifications = ref<any[]>([]);
    const toasts = ref<Toast[]>([]);

    // Loading states
    const loading = ref<LoadingStates>({
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
    const dashboard = ref<DashboardData>({
        todayOrders: 0,
        todayRevenue: 0,
        avgCheck: 0,
        activeStaff: 0,
        recentOrders: [] as any[],
        popularDishes: [] as any[],
        salesByHour: [] as any[]
    });

    // Menu data
    const categories = shallowRef<any[]>([]);
    const dishes = shallowRef<any[]>([]);

    // Staff data
    const staff = shallowRef<any[]>([]);
    const roles = ref<any[]>([]);

    // Hall data
    const zones = ref<any[]>([]);
    const tables = shallowRef<any[]>([]);

    // Customers data
    const customers = shallowRef<any[]>([]);

    // Finance data
    const transactions = shallowRef<any[]>([]);
    const cashBalance = ref(0);

    // Inventory data
    const ingredients = shallowRef<any[]>([]);
    const warehouses = ref<any[]>([]);
    const suppliers = shallowRef<any[]>([]);

    // Loyalty data
    const promotions = shallowRef<any[]>([]);
    const promoCodes = shallowRef<any[]>([]);

    // Settings
    const settings = ref<Record<string, any>>({});
    const restaurant = ref<Record<string, any>>({});

    // Tenant & Restaurants
    const tenant = ref<Tenant | null>(null);
    const restaurants = ref<Restaurant[]>([]);
    const currentRestaurantId = ref<number | string | null>(getRestaurantId());

    // Menu groups for navigation
    const menuGroups: MenuGroup[] = [
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
            const item = group.items.find((i: any) => i.id === currentModule.value);
            if (item) return item.name;
        }
        return 'Дашборд';
    });

    // Current restaurant
    const currentRestaurant = computed(() => {
        if (!restaurants.value.length) return null;
        const id = currentRestaurantId.value ? parseInt(String(currentRestaurantId.value)) : null;
        return restaurants.value.find((r: any) => r.id === id) || restaurants.value.find((r: any) => r.is_current) || restaurants.value[0];
    });

    // Has multiple restaurants
    const hasMultipleRestaurants = computed(() => restaurants.value.length > 1);

    // Filtered menu groups by modules and permissions (3-level access)
    const filteredMenuGroups = computed(() => {
        const permissionsStore = usePermissionsStore();

        return menuGroups.map((group: any) => ({
            ...group,
            items: group.items.filter((item: any) => {
                if (!permissionsStore.canAccessBackofficeModule(item.id)) {
                    return false;
                }
                const perm = modulePermissions[item.id];
                return !perm || hasPermission(perm);
            })
        })).filter((group: any) => group.items.length > 0);
    });

    // API helper
    const api = async <T = unknown>(endpoint: string, options: ApiOptions = {}): Promise<ApiResponse<T>> => {
        const headers: Record<string, string> = {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            ...options.headers
        };

        if (token.value) {
            headers['Authorization'] = `Bearer ${token.value}`;
        }

        const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content;
        if (csrfToken) {
            headers['X-CSRF-TOKEN'] = csrfToken;
        }

        const response = await fetch(`/api${endpoint}`, {
            ...options,
            headers
        });

        const data = await response.json() as ApiResponse<T>;

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
    const savePermissions = (perms: string[], lim: Record<string, any>, access: Record<string, any>, posMods: string[], boMods: string[]) => {
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

    const login = async (email: string, password: string): Promise<{ success: boolean; message?: string }> => {
        try {
            const data = await api<LoginResponseData>('/backoffice/login', {
                method: 'POST',
                body: JSON.stringify({ login: email, password, app_type: 'backoffice' })
            });

            if (data.success && data.data) {
                token.value = data.data.token;
                user.value = data.data.user;
                isAuthenticated.value = true;

                setUnifiedSession({
                    token: data.data.token,
                    user: data.data.user,
                    permissions: data.data.permissions || [],
                    limits: data.data.limits || {},
                    interfaceAccess: data.data.interface_access || {},
                }, { app: 'backoffice' });

                localStorage.setItem('backoffice_token', data.data.token);
                savePermissions(
                    data.data.permissions || [],
                    data.data.limits || {},
                    data.data.interface_access || {},
                    data.data.pos_modules || [],
                    data.data.backoffice_modules || []
                );

                const permissionsStore = usePermissionsStore();

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
        } catch (e: unknown) {
            return { success: false, message: (e as Error).message };
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

        clearUnifiedAuth();

        localStorage.removeItem('backoffice_permissions');
        localStorage.removeItem('backoffice_pos_modules');
        localStorage.removeItem('backoffice_backoffice_modules');

        const permissionsStore = usePermissionsStore();
        permissionsStore.reset();
    };

    const checkAuth = async (): Promise<boolean> => {
        if (!token.value) return false;

        try {
            const data = await api<AuthCheckResponseData>('/backoffice/me');
            if (data.success && data.data) {
                const userData = (data.data.user || data.data) as BackofficeUser;
                user.value = userData;
                isAuthenticated.value = true;

                if (data.data.permissions) {
                    savePermissions(
                        data.data.permissions,
                        data.data.limits || {},
                        data.data.interface_access || {},
                        data.data.pos_modules || [],
                        data.data.backoffice_modules || []
                    );
                }

                setUnifiedSession({
                    token: token.value,
                    user: userData,
                    permissions: data.data.permissions || permissions.value,
                    limits: data.data.limits || limits.value,
                    interfaceAccess: data.data.interface_access || interfaceAccess.value,
                }, { app: 'backoffice' });

                const permissionsStore = usePermissionsStore();
                permissionsStore.init({
                    permissions: data.data.permissions || permissions.value,
                    limits: data.data.limits || limits.value,
                    interfaceAccess: data.data.interface_access || interfaceAccess.value,
                    posModules: data.data.pos_modules || posModules.value,
                    backofficeModules: data.data.backoffice_modules || backofficeModules.value,
                    role: userData?.role || null,
                });

                if (userData?.restaurant_id) {
                    currentRestaurantId.value = userData.restaurant_id;
                    permissionsStore.setRestaurantId(userData.restaurant_id);
                }

                return true;
            }
        } catch (e: any) {
            logout();
        }
        return false;
    };

    // Navigation
    const navigateTo = (moduleId: string) => {
        currentModule.value = moduleId;
        localStorage.setItem('backoffice_module', moduleId);
        window.history.replaceState(null, '', `/backoffice#${moduleId}`);
    };

    // Toast notifications
    const showToast = (message: string, type: string = 'success') => {
        const id = Date.now();
        toasts.value.push({ id, message, type });
        setTimeout(() => {
            toasts.value = toasts.value.filter((t: any) => t.id !== id);
        }, 3000);
    };

    // Dashboard actions
    const loadDashboard = async () => {
        loading.value.dashboard = true;
        try {
            const data = await api<DashboardData>('/backoffice/dashboard');
            if (data.success) {
                dashboard.value = { ...dashboard.value, ...data.data };
            }
        } catch (e: any) {
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
                categories.value = (data.data as any[] || (data as Record<string, any>).categories as any[] || []);
            }
        } catch (e: any) {
            log.error('Failed to load categories', e);
        }
    };

    const loadDishes = async () => {
        loading.value.menu = true;
        try {
            const data = await api('/backoffice/menu/dishes');
            if (data.success) {
                dishes.value = (data.data as any[] || (data as Record<string, any>).dishes as any[] || []);
            }
        } catch (e: any) {
            log.error('Failed to load dishes', e);
        } finally {
            loading.value.menu = false;
        }
    };

    const saveCategory = async (category: Record<string, any>) => {
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

    const saveDish = async (dish: Record<string, any>) => {
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

    const deleteDish = async (id: number | string) => {
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
                staff.value = (data.data as any[] || (data as Record<string, any>).staff as any[] || []);
                log.debug('Staff loaded:', staff.value.length, 'employees');
                log.debug('Roles:', (staff.value as Array<Record<string, any>>).map((s: any) => s.role));
            } else {
                log.warn('API returned success: false');
            }
        } catch (e: any) {
            log.error('Failed to load staff', e);
        } finally {
            loading.value.staff = false;
        }
    };

    const saveStaff = async (staffMember: Record<string, any>) => {
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
                zones.value = (data.data as any[] || (data as Record<string, any>).zones as any[] || []);
            }
        } catch (e: any) {
            log.error('Failed to load zones', e);
        }
    };

    const loadTables = async () => {
        loading.value.hall = true;
        try {
            const data = await api('/backoffice/tables');
            if (data.success) {
                tables.value = (data.data as any[] || (data as Record<string, any>).tables as any[] || []);
            }
        } catch (e: any) {
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
                customers.value = (data.data as any[] || (data as Record<string, any>).customers as any[] || []);
            }
        } catch (e: any) {
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
                ingredients.value = (data.data as any[] || (data as Record<string, any>).ingredients as any[] || []);
            }
        } catch (e: any) {
            log.error('Failed to load ingredients', e);
        }
    };

    const loadWarehouses = async () => {
        try {
            const data = await api('/warehouses');
            if (data.success) {
                warehouses.value = (data.data as any[] || (data as Record<string, any>).warehouses as any[] || []);
            }
        } catch (e: any) {
            log.error('Failed to load warehouses', e);
        }
    };

    // Loyalty actions
    const loadPromotions = async () => {
        try {
            const data = await api('/promotions');
            if (data.success) {
                promotions.value = (data.data as any[] || (data as Record<string, any>).promotions as any[] || []);
            }
        } catch (e: any) {
            log.error('Failed to load promotions', e);
        }
    };

    const loadPromoCodes = async () => {
        try {
            const data = await api('/promo-codes');
            if (data.success) {
                promoCodes.value = (data.data as any[] || (data as Record<string, any>).promoCodes as any[] || []);
            }
        } catch (e: any) {
            log.error('Failed to load promo codes', e);
        }
    };

    // Settings actions
    const loadSettings = async () => {
        loading.value.settings = true;
        try {
            const data = await api('/settings');
            if (data.success) {
                settings.value = (data.data as Record<string, any> || (data as Record<string, any>).settings as Record<string, any> || {});
            }
        } catch (e: any) {
            log.error('Failed to load settings', e);
        } finally {
            loading.value.settings = false;
        }
    };

    const loadRestaurant = async () => {
        try {
            const data = await api('/restaurant');
            if (data.success) {
                restaurant.value = (data.data as Record<string, any> || (data as Record<string, any>).restaurant as Record<string, any> || {});
            }
        } catch (e: any) {
            log.error('Failed to load restaurant', e);
        }
    };

    // Tenant actions
    const loadTenant = async () => {
        try {
            const data = await api<Tenant>('/tenant');
            if (data.success) {
                tenant.value = data.data || null;
            }
        } catch (e: any) {
            log.error('Failed to load tenant', e);
        }
    };

    const loadRestaurants = async () => {
        try {
            const data = await api<Restaurant[]>('/tenant/restaurants');
            if (data.success) {
                restaurants.value = data.data || [];
                const current = restaurants.value.find((r: any) => r.is_current);
                if (current) {
                    currentRestaurantId.value = current.id;
                    const permissionsStore = usePermissionsStore();
                    permissionsStore.setRestaurantId(current.id);
                }
            }
        } catch (e: any) {
            log.error('Failed to load restaurants', e);
        }
    };

    const switchRestaurant = async (restaurantId: number): Promise<ApiResponse> => {
        try {
            const data = await api(`/tenant/restaurants/${restaurantId}/switch`, {
                method: 'POST'
            });
            if (data.success) {
                currentRestaurantId.value = restaurantId;
                const permissionsStore = usePermissionsStore();
                permissionsStore.setRestaurantId(restaurantId);
                await loadDashboard();
                showToast(data.message || 'Точка переключена');
                await loadRestaurants();
            }
            return data;
        } catch (e: unknown) {
            showToast((e as Error).message, 'error');
            return { success: false, message: (e as Error).message };
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
