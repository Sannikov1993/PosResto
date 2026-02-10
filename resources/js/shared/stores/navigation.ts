/**
 * NavigationStore - Enterprise-level tab navigation management
 *
 * @module shared/stores/navigation
 */

import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import { createLogger } from '../services/logger.js';
import { usePermissionsStore } from './permissions.js';

const log = createLogger('Navigation');

const STORAGE_KEY = 'menulab_navigation';
const HISTORY_MAX_SIZE = 50;

interface TabDefinition {
    id: string;
    label: string;
    icon: string;
    default?: boolean;
    permissions: string[];
    requiresRestaurant: boolean;
    requiresInterface?: string;
    requiresFeature?: string;
}

interface NavigationContext {
    restaurantId: string | number | null;
    permissions: string[];
    features: string[];
}

interface PersistedState {
    activeTab: string;
    history?: string[];
    timestamp?: number;
}

const TAB_DEFINITIONS: Record<string, TabDefinition> = {
    cash: { id: 'cash', label: 'Касса', icon: 'cash-register', default: true, permissions: ['orders.view', 'orders.create'], requiresRestaurant: true },
    orders: { id: 'orders', label: 'Заказы', icon: 'list', permissions: ['orders.view'], requiresRestaurant: true },
    delivery: { id: 'delivery', label: 'Доставка', icon: 'truck', permissions: ['orders.view'], requiresInterface: 'delivery', requiresRestaurant: true },
    customers: { id: 'customers', label: 'Клиенты', icon: 'users', permissions: ['customers.view'], requiresRestaurant: true },
    warehouse: { id: 'warehouse', label: 'Склад', icon: 'warehouse', permissions: ['inventory.view'], requiresRestaurant: true },
    stoplist: { id: 'stoplist', label: 'Стоп-лист', icon: 'ban', permissions: ['menu.view'], requiresRestaurant: true },
    writeoffs: { id: 'writeoffs', label: 'Списания', icon: 'trash', permissions: ['orders.cancel'], requiresRestaurant: true },
    settings: { id: 'settings', label: 'Настройки', icon: 'cog', permissions: ['settings.view'], requiresRestaurant: false },
};

const HASH_TO_TAB: Record<string, string> = {
    '#cash': 'cash',
    '#orders': 'orders',
    '#hall': 'orders',
    '#delivery': 'delivery',
    '#customers': 'customers',
    '#warehouse': 'warehouse',
    '#stoplist': 'stoplist',
    '#writeoffs': 'writeoffs',
    '#settings': 'settings',
};

export const useNavigationStore = defineStore('navigation', () => {
    const activeTab = ref<string | null>(null);
    const previousTab = ref<string | null>(null);
    const initialized = ref(false);
    const navigationHistory = ref<string[]>([]);

    const context = ref<NavigationContext>({
        restaurantId: null as any,
        permissions: [] as any[],
        features: [] as any[],
    });

    const availableTabs = computed(() => {
        return Object.values(TAB_DEFINITIONS).filter((tab: any) => canAccessTab(tab.id));
    });

    const currentTabDefinition = computed(() => {
        return activeTab.value ? TAB_DEFINITIONS[activeTab.value] : null;
    });

    const defaultTab = computed(() => {
        const available = availableTabs.value;
        const defaultDef = available.find((t: any) => t.default);
        return defaultDef?.id || available[0]?.id || 'cash';
    });

    const canGoBack = computed(() => {
        return navigationHistory.value.length > 1;
    });

    function canAccessTab(tabId: string): boolean {
        const tab = TAB_DEFINITIONS[tabId];
        if (!tab) return false;

        if (tab.requiresRestaurant && !context.value.restaurantId) return false;
        if (tab.requiresFeature && !context.value.features.includes(tab.requiresFeature)) return false;

        const permissionsStore = usePermissionsStore();

        if (tab.requiresInterface) {
            if (!permissionsStore.canAccessInterface(tab.requiresInterface)) return false;
        }

        if (tabId !== 'cash' && !permissionsStore.canAccessPosModule(tabId)) return false;

        if (tab.permissions.length > 0) {
            if (!permissionsStore.canAny(tab.permissions)) return false;
        }

        return true;
    }

    function navigateTo(tabId: string, options: { replace?: boolean; skipValidation?: boolean } = {}): boolean {
        const { replace = false, skipValidation = false } = options;

        if (!TAB_DEFINITIONS[tabId]) {
            log.warn(`Unknown tab: ${tabId}`);
            return false;
        }

        if (!skipValidation && !canAccessTab(tabId)) {
            log.warn(`Access denied to tab: ${tabId}`);
            return false;
        }

        if (activeTab.value === tabId) return true;

        previousTab.value = activeTab.value;
        activeTab.value = tabId;

        if (replace && navigationHistory.value.length > 0) {
            navigationHistory.value[navigationHistory.value.length - 1] = tabId;
        } else {
            navigationHistory.value.push(tabId);
            if (navigationHistory.value.length > HISTORY_MAX_SIZE) {
                navigationHistory.value.shift();
            }
        }

        persistState();
        updateUrlHash(tabId);

        return true;
    }

    function goBack(): boolean {
        if (navigationHistory.value.length <= 1) return false;

        navigationHistory.value.pop();
        const prevTab = navigationHistory.value[navigationHistory.value.length - 1];

        if (prevTab && canAccessTab(prevTab)) {
            previousTab.value = activeTab.value;
            activeTab.value = prevTab;
            persistState();
            updateUrlHash(prevTab);
            return true;
        }

        return false;
    }

    function navigateToDefault(): boolean {
        return navigateTo(defaultTab.value);
    }

    function init(authContext: Partial<NavigationContext> = {}): void {
        if (initialized.value) {
            updateContext(authContext);
            return;
        }

        updateContext(authContext);

        let initialTab: string | null = null;

        const hashTab = getTabFromHash();
        if (hashTab && canAccessTab(hashTab)) {
            initialTab = hashTab;
            clearUrlHash();
        }

        if (!initialTab) {
            const persisted = loadPersistedState();
            if (persisted?.activeTab && canAccessTab(persisted.activeTab)) {
                initialTab = persisted.activeTab;
                if (persisted.history?.length) {
                    navigationHistory.value = persisted.history.filter((t: any) => canAccessTab(t));
                }
            }
        }

        if (!initialTab) {
            initialTab = defaultTab.value;
        }

        activeTab.value = initialTab;
        if (navigationHistory.value.length === 0) {
            navigationHistory.value.push(initialTab);
        }

        initialized.value = true;
        persistState();
        log.debug(`Initialized with tab: ${initialTab}`);
    }

    function updateContext(authContext: Partial<NavigationContext>): void {
        context.value = {
            restaurantId: authContext.restaurantId || null,
            permissions: authContext.permissions || [],
            features: authContext.features || [],
        };

        if (initialized.value && activeTab.value && !canAccessTab(activeTab.value)) {
            log.warn(`Current tab ${activeTab.value} no longer accessible, redirecting`);
            navigateToDefault();
        }
    }

    function reset(): void {
        activeTab.value = null;
        previousTab.value = null;
        navigationHistory.value = [];
        initialized.value = false;
        context.value = { restaurantId: null, permissions: [], features: [] };
        clearPersistedState();
        clearUrlHash();
    }

    function persistState(): void {
        try {
            const state: PersistedState = {
                activeTab: activeTab.value!,
                history: navigationHistory.value.slice(-10),
                timestamp: Date.now(),
            };
            localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
        } catch (e: any) {
            if (e.name === 'QuotaExceededError') {
                try {
                    localStorage.setItem(STORAGE_KEY, JSON.stringify({ activeTab: activeTab.value, timestamp: Date.now() }));
                } catch {
                    log.warn('localStorage quota exceeded');
                }
            }
        }
    }

    function loadPersistedState(): PersistedState | null {
        try {
            const stored = localStorage.getItem(STORAGE_KEY);
            if (!stored) return null;

            const state = JSON.parse(stored) as PersistedState;
            const maxAge = 7 * 24 * 60 * 60 * 1000;
            if (state.timestamp && Date.now() - state.timestamp > maxAge) {
                clearPersistedState();
                return null;
            }

            return state;
        } catch {
            return null;
        }
    }

    function clearPersistedState(): void {
        try {
            localStorage.removeItem(STORAGE_KEY);
        } catch {
            // Ignore
        }
    }

    function getTabFromHash(): string | null {
        const hash = window.location.hash;
        return HASH_TO_TAB[hash] || null;
    }

    function updateUrlHash(_tabId: string): void {
        // Optional deep linking
    }

    function clearUrlHash(): void {
        if (window.location.hash) {
            history.replaceState(null, '', window.location.pathname + window.location.search);
        }
    }

    function setupBeforeUnload(): void {
        window.addEventListener('beforeunload', () => {
            if (initialized.value && activeTab.value) {
                persistState();
            }
        });
    }

    setupBeforeUnload();

    return {
        activeTab,
        previousTab,
        initialized,
        navigationHistory,
        availableTabs,
        currentTabDefinition,
        defaultTab,
        canGoBack,
        init,
        reset,
        navigateTo,
        goBack,
        navigateToDefault,
        updateContext,
        canAccessTab,
        getTabDefinitions: () => TAB_DEFINITIONS,
    };
});

export function useNavigation() {
    const store = useNavigationStore();

    return {
        activeTab: computed(() => store.activeTab),
        previousTab: computed(() => store.previousTab),
        availableTabs: computed(() => store.availableTabs),
        currentTab: computed(() => store.currentTabDefinition),
        canGoBack: computed(() => store.canGoBack),
        initialized: computed(() => store.initialized),
        navigateTo: store.navigateTo,
        goBack: store.goBack,
        canAccessTab: store.canAccessTab,
    };
}
