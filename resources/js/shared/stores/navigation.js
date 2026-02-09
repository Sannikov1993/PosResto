/**
 * NavigationStore - Enterprise-level tab navigation management
 *
 * Features:
 * - Centralized tab state management
 * - Proper initialization order (after auth)
 * - Tab access validation based on permissions
 * - Persistent state with fallback
 * - Navigation history tracking
 * - Deep linking support
 * - beforeunload save guarantee
 *
 * @module shared/stores/navigation
 */

import { defineStore } from 'pinia';
import { ref, computed, watch } from 'vue';
import { createLogger } from '../services/logger.js';
import { usePermissionsStore } from './permissions.js';

const log = createLogger('Navigation');

// ═══════════════════════════════════════════════════════════════════════════
// CONSTANTS
// ═══════════════════════════════════════════════════════════════════════════

const STORAGE_KEY = 'menulab_navigation';
const HISTORY_MAX_SIZE = 50;

/**
 * Tab definitions with metadata
 * @type {Object.<string, TabDefinition>}
 */
const TAB_DEFINITIONS = {
    cash: {
        id: 'cash',
        label: 'Касса',
        icon: 'cash-register',
        default: true,
        permissions: ['orders.view', 'orders.create'],
        requiresRestaurant: true,
    },
    orders: {
        id: 'orders',
        label: 'Заказы',
        icon: 'list',
        permissions: ['orders.view'],
        requiresRestaurant: true,
    },
    delivery: {
        id: 'delivery',
        label: 'Доставка',
        icon: 'truck',
        permissions: ['orders.view'],
        requiresInterface: 'delivery',
        requiresRestaurant: true,
    },
    customers: {
        id: 'customers',
        label: 'Клиенты',
        icon: 'users',
        permissions: ['customers.view'],
        requiresRestaurant: true,
    },
    warehouse: {
        id: 'warehouse',
        label: 'Склад',
        icon: 'warehouse',
        permissions: ['inventory.view'],
        requiresRestaurant: true,
    },
    stoplist: {
        id: 'stoplist',
        label: 'Стоп-лист',
        icon: 'ban',
        permissions: ['menu.view'],
        requiresRestaurant: true,
    },
    writeoffs: {
        id: 'writeoffs',
        label: 'Списания',
        icon: 'trash',
        permissions: ['orders.cancel'],
        requiresRestaurant: true,
    },
    settings: {
        id: 'settings',
        label: 'Настройки',
        icon: 'cog',
        permissions: ['settings.view'],
        requiresRestaurant: false,
    },
};

/**
 * URL hash to tab mapping (for deep linking)
 */
const HASH_TO_TAB = {
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

// ═══════════════════════════════════════════════════════════════════════════
// STORE DEFINITION
// ═══════════════════════════════════════════════════════════════════════════

export const useNavigationStore = defineStore('navigation', () => {
    // ─────────────────────────────────────────────────────────────────────────
    // STATE
    // ─────────────────────────────────────────────────────────────────────────

    const activeTab = ref(null);
    const previousTab = ref(null);
    const initialized = ref(false);
    const navigationHistory = ref([]);

    // Context for access validation
    const context = ref({
        restaurantId: null,
        permissions: [],
        features: [],
    });

    // ─────────────────────────────────────────────────────────────────────────
    // GETTERS
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Get all available tabs for current context
     */
    const availableTabs = computed(() => {
        return Object.values(TAB_DEFINITIONS).filter(tab => {
            return canAccessTab(tab.id);
        });
    });

    /**
     * Get current tab definition
     */
    const currentTabDefinition = computed(() => {
        return activeTab.value ? TAB_DEFINITIONS[activeTab.value] : null;
    });

    /**
     * Get default tab for current context
     */
    const defaultTab = computed(() => {
        const available = availableTabs.value;
        const defaultDef = available.find(t => t.default);
        return defaultDef?.id || available[0]?.id || 'cash';
    });

    /**
     * Check if can go back in history
     */
    const canGoBack = computed(() => {
        return navigationHistory.value.length > 1;
    });

    // ─────────────────────────────────────────────────────────────────────────
    // ACCESS VALIDATION
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Check if user can access a specific tab
     * Uses 3-level access control:
     * - Level 1: Interface access (can_access_pos, can_access_backoffice, etc.)
     * - Level 2: Module access (pos_modules, backoffice_modules arrays)
     * - Level 3: Functional permissions (orders.view, orders.create, etc.)
     *
     * @param {string} tabId - Tab identifier
     * @returns {boolean}
     */
    function canAccessTab(tabId) {
        const tab = TAB_DEFINITIONS[tabId];
        if (!tab) return false;

        // Check restaurant requirement
        if (tab.requiresRestaurant && !context.value.restaurantId) {
            return false;
        }

        // Check feature flag
        if (tab.requiresFeature && !context.value.features.includes(tab.requiresFeature)) {
            return false;
        }

        // Use PermissionsStore for permission checks
        const permissionsStore = usePermissionsStore();

        // Level 1: Check interface access requirement
        if (tab.requiresInterface) {
            if (!permissionsStore.canAccessInterface(tab.requiresInterface)) {
                return false;
            }
        }

        // Level 2: Check module access (tab IDs match module names)
        // Cash tab is always accessible — needed for shift management
        if (tabId !== 'cash' && !permissionsStore.canAccessPosModule(tabId)) {
            return false;
        }

        // Level 3: Check functional permissions using PermissionsStore
        if (tab.permissions.length > 0) {
            if (!permissionsStore.canAny(tab.permissions)) {
                return false;
            }
        }

        return true;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // NAVIGATION ACTIONS
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Navigate to a tab
     * @param {string} tabId - Tab to navigate to
     * @param {Object} options - Navigation options
     * @param {boolean} options.replace - Replace current history entry
     * @param {boolean} options.skipValidation - Skip access check (internal use)
     * @returns {boolean} Success
     */
    function navigateTo(tabId, options = {}) {
        const { replace = false, skipValidation = false } = options;

        // Validate tab exists
        if (!TAB_DEFINITIONS[tabId]) {
            log.warn(`Unknown tab: ${tabId}`);
            return false;
        }

        // Validate access
        if (!skipValidation && !canAccessTab(tabId)) {
            log.warn(`Access denied to tab: ${tabId}`);
            return false;
        }

        // Skip if already on this tab
        if (activeTab.value === tabId) {
            return true;
        }

        // Update state
        previousTab.value = activeTab.value;
        activeTab.value = tabId;

        // Update history
        if (replace && navigationHistory.value.length > 0) {
            navigationHistory.value[navigationHistory.value.length - 1] = tabId;
        } else {
            navigationHistory.value.push(tabId);
            if (navigationHistory.value.length > HISTORY_MAX_SIZE) {
                navigationHistory.value.shift();
            }
        }

        // Persist
        persistState();

        // Update URL hash (optional, for deep linking)
        updateUrlHash(tabId);

        return true;
    }

    /**
     * Go back to previous tab
     * @returns {boolean} Success
     */
    function goBack() {
        if (navigationHistory.value.length <= 1) {
            return false;
        }

        // Remove current
        navigationHistory.value.pop();

        // Get previous
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

    /**
     * Navigate to default tab
     */
    function navigateToDefault() {
        return navigateTo(defaultTab.value);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // INITIALIZATION
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Initialize navigation after auth is ready
     * MUST be called after session is validated
     *
     * @param {Object} authContext - Auth context
     * @param {number} authContext.restaurantId - Current restaurant
     * @param {string[]} authContext.permissions - User permissions
     * @param {string[]} authContext.features - Enabled features
     */
    function init(authContext = {}) {
        if (initialized.value) {
            // Just update context if already initialized
            updateContext(authContext);
            return;
        }

        // Set context first
        updateContext(authContext);

        // Determine initial tab (priority order)
        let initialTab = null;

        // 1. Check URL hash (deep linking / payment redirect)
        const hashTab = getTabFromHash();
        if (hashTab && canAccessTab(hashTab)) {
            initialTab = hashTab;
            // Clear hash to prevent re-triggering
            clearUrlHash();
        }

        // 2. Check persisted state
        if (!initialTab) {
            const persisted = loadPersistedState();
            if (persisted?.activeTab && canAccessTab(persisted.activeTab)) {
                initialTab = persisted.activeTab;
                // Restore history if available
                if (persisted.history?.length > 0) {
                    navigationHistory.value = persisted.history.filter(t => canAccessTab(t));
                }
            }
        }

        // 3. Fall back to default
        if (!initialTab) {
            initialTab = defaultTab.value;
        }

        // Set initial tab
        activeTab.value = initialTab;
        if (navigationHistory.value.length === 0) {
            navigationHistory.value.push(initialTab);
        }

        initialized.value = true;
        persistState();

        log.debug(`Initialized with tab: ${initialTab}`);
    }

    /**
     * Update auth context (when restaurant changes, etc.)
     * @param {Object} authContext
     */
    function updateContext(authContext) {
        context.value = {
            restaurantId: authContext.restaurantId || null,
            permissions: authContext.permissions || [],
            features: authContext.features || [],
        };

        // Validate current tab is still accessible
        if (initialized.value && activeTab.value && !canAccessTab(activeTab.value)) {
            log.warn(`Current tab ${activeTab.value} no longer accessible, redirecting`);
            navigateToDefault();
        }
    }

    /**
     * Reset navigation state (on logout)
     */
    function reset() {
        activeTab.value = null;
        previousTab.value = null;
        navigationHistory.value = [];
        initialized.value = false;
        context.value = {
            restaurantId: null,
            permissions: [],
            features: [],
        };
        clearPersistedState();
        clearUrlHash();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PERSISTENCE
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Save state to localStorage
     */
    function persistState() {
        try {
            const state = {
                activeTab: activeTab.value,
                history: navigationHistory.value.slice(-10), // Keep last 10 only
                timestamp: Date.now(),
            };
            localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
        } catch (e) {
            // Handle QuotaExceededError
            if (e.name === 'QuotaExceededError') {
                try {
                    // Try minimal save
                    localStorage.setItem(STORAGE_KEY, JSON.stringify({
                        activeTab: activeTab.value,
                        timestamp: Date.now(),
                    }));
                } catch {
                    log.warn('localStorage quota exceeded');
                }
            }
        }
    }

    /**
     * Load persisted state from localStorage
     * @returns {Object|null}
     */
    function loadPersistedState() {
        try {
            const stored = localStorage.getItem(STORAGE_KEY);
            if (!stored) return null;

            const state = JSON.parse(stored);

            // Validate age (max 7 days)
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

    /**
     * Clear persisted state
     */
    function clearPersistedState() {
        try {
            localStorage.removeItem(STORAGE_KEY);
        } catch {
            // Ignore
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // URL HASH HANDLING
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Get tab ID from current URL hash
     * @returns {string|null}
     */
    function getTabFromHash() {
        const hash = window.location.hash;
        return HASH_TO_TAB[hash] || null;
    }

    /**
     * Update URL hash to match current tab
     * @param {string} tabId
     */
    function updateUrlHash(tabId) {
        // Only update if we want deep linking (можно отключить)
        // history.replaceState(null, '', `#${tabId}`);
    }

    /**
     * Clear URL hash
     */
    function clearUrlHash() {
        if (window.location.hash) {
            history.replaceState(null, '', window.location.pathname + window.location.search);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // BEFOREUNLOAD HANDLER
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Setup beforeunload handler to guarantee state persistence
     */
    function setupBeforeUnload() {
        window.addEventListener('beforeunload', () => {
            if (initialized.value && activeTab.value) {
                persistState();
            }
        });
    }

    // Setup on store creation
    setupBeforeUnload();

    // ─────────────────────────────────────────────────────────────────────────
    // RETURN
    // ─────────────────────────────────────────────────────────────────────────

    return {
        // State
        activeTab,
        previousTab,
        initialized,
        navigationHistory,

        // Getters
        availableTabs,
        currentTabDefinition,
        defaultTab,
        canGoBack,

        // Actions
        init,
        reset,
        navigateTo,
        goBack,
        navigateToDefault,
        updateContext,
        canAccessTab,

        // For debugging
        getTabDefinitions: () => TAB_DEFINITIONS,
    };
});

// ═══════════════════════════════════════════════════════════════════════════
// COMPOSABLE FOR COMPONENTS
// ═══════════════════════════════════════════════════════════════════════════

/**
 * Composable for using navigation in components
 *
 * @example
 * const { activeTab, navigateTo, availableTabs } = useNavigation();
 *
 * // Navigate
 * navigateTo('delivery');
 *
 * // Check current tab
 * if (activeTab.value === 'orders') { ... }
 */
export function useNavigation() {
    const store = useNavigationStore();

    return {
        // Reactive state
        activeTab: computed(() => store.activeTab),
        previousTab: computed(() => store.previousTab),
        availableTabs: computed(() => store.availableTabs),
        currentTab: computed(() => store.currentTabDefinition),
        canGoBack: computed(() => store.canGoBack),
        initialized: computed(() => store.initialized),

        // Actions
        navigateTo: store.navigateTo,
        goBack: store.goBack,
        canAccessTab: store.canAccessTab,
    };
}
