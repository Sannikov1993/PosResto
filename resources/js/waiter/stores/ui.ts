/**
 * Waiter App - UI Store
 * Manages UI state, navigation, modals, and toasts
 */

import { defineStore } from 'pinia';
import { ref, computed, watch } from 'vue';

// === Types ===

export type Tab = 'tables' | 'orders' | 'table-order' | 'profile';

export type ToastType = 'success' | 'error' | 'warning' | 'info';

export interface Toast {
  id: number;
  message: string;
  type: ToastType;
  duration: number;
  createdAt: number;
}

export interface ConfirmOptions {
  title: string;
  message: string;
  confirmText?: string;
  cancelText?: string;
  type?: 'danger' | 'warning' | 'info';
}

// === Store ===

export const useUiStore = defineStore('waiter-ui', () => {
  // === State ===
  const currentTab = ref<Tab>('tables');
  const previousTab = ref<Tab | null>(null);
  const isSideMenuOpen = ref(false);
  const isPaymentModalOpen = ref(false);
  const isGuestCountModalOpen = ref(false);
  const isConfirmModalOpen = ref(false);
  const confirmOptions = ref<ConfirmOptions | null>(null);
  const confirmResolve = ref<((value: boolean) => void) | null>(null);
  const toasts = ref<Toast[]>([]);
  const isOnline = ref(navigator.onLine);
  const isDarkMode = ref(false);
  const isRefreshing = ref(false);

  let toastIdCounter = 0;

  // === Getters ===

  /**
   * Is on tables tab
   */
  const isTablesTab = computed((): boolean => currentTab.value === 'tables');

  /**
   * Is on orders tab
   */
  const isOrdersTab = computed((): boolean => currentTab.value === 'orders');

  /**
   * Is on table order tab
   */
  const isTableOrderTab = computed((): boolean => currentTab.value === 'table-order');

  /**
   * Is on profile tab
   */
  const isProfileTab = computed((): boolean => currentTab.value === 'profile');

  /**
   * Has any modal open
   */
  const hasModalOpen = computed((): boolean => {
    return isSideMenuOpen.value ||
           isPaymentModalOpen.value ||
           isGuestCountModalOpen.value ||
           isConfirmModalOpen.value;
  });

  /**
   * Active toasts
   */
  const activeToasts = computed((): Toast[] => toasts.value);

  // === Navigation Actions ===

  /**
   * Navigate to tab
   */
  function setTab(tab: Tab): void {
    if (currentTab.value !== tab) {
      previousTab.value = currentTab.value;
      currentTab.value = tab;
    }
    // Close side menu on navigation
    closeSideMenu();
  }

  /**
   * Go to tables tab
   */
  function goToTables(): void {
    setTab('tables');
  }

  /**
   * Go to orders tab
   */
  function goToOrders(): void {
    setTab('orders');
  }

  /**
   * Go to table order tab
   */
  function goToTableOrder(): void {
    setTab('table-order');
  }

  /**
   * Go to profile tab
   */
  function goToProfile(): void {
    setTab('profile');
  }

  /**
   * Go back to previous tab
   */
  function goBack(): void {
    if (previousTab.value) {
      setTab(previousTab.value);
    } else {
      setTab('tables');
    }
  }

  // === Side Menu ===

  /**
   * Toggle side menu
   */
  function toggleSideMenu(): void {
    isSideMenuOpen.value = !isSideMenuOpen.value;
  }

  /**
   * Open side menu
   */
  function openSideMenu(): void {
    isSideMenuOpen.value = true;
  }

  /**
   * Close side menu
   */
  function closeSideMenu(): void {
    isSideMenuOpen.value = false;
  }

  // === Payment Modal ===

  /**
   * Open payment modal
   */
  function openPaymentModal(): void {
    isPaymentModalOpen.value = true;
  }

  /**
   * Close payment modal
   */
  function closePaymentModal(): void {
    isPaymentModalOpen.value = false;
  }

  // === Guest Count Modal ===

  /**
   * Open guest count modal
   */
  function openGuestCountModal(): void {
    isGuestCountModalOpen.value = true;
  }

  /**
   * Close guest count modal
   */
  function closeGuestCountModal(): void {
    isGuestCountModalOpen.value = false;
  }

  // === Confirm Modal ===

  /**
   * Show confirm dialog
   */
  function confirm(options: ConfirmOptions): Promise<boolean> {
    confirmOptions.value = {
      confirmText: 'Да',
      cancelText: 'Отмена',
      type: 'info',
      ...options,
    };
    isConfirmModalOpen.value = true;

    return new Promise((resolve) => {
      confirmResolve.value = resolve;
    });
  }

  /**
   * Resolve confirm dialog
   */
  function resolveConfirm(result: boolean): void {
    if (confirmResolve.value) {
      confirmResolve.value(result);
      confirmResolve.value = null;
    }
    isConfirmModalOpen.value = false;
    confirmOptions.value = null;
  }

  // === Toasts ===

  /**
   * Show toast notification
   */
  function showToast(
    message: string,
    type: ToastType = 'info',
    duration = 3000
  ): number {
    const id = ++toastIdCounter;
    const toast: Toast = {
      id,
      message,
      type,
      duration,
      createdAt: Date.now(),
    };

    toasts.value.push(toast);

    // Auto remove if duration > 0
    if (duration > 0) {
      setTimeout(() => {
        removeToast(id);
      }, duration);
    }

    return id;
  }

  /**
   * Show success toast
   */
  function showSuccess(message: string, duration = 3000): number {
    return showToast(message, 'success', duration);
  }

  /**
   * Show error toast
   */
  function showError(message: string, duration = 5000): number {
    return showToast(message, 'error', duration);
  }

  /**
   * Show warning toast
   */
  function showWarning(message: string, duration = 4000): number {
    return showToast(message, 'warning', duration);
  }

  /**
   * Show info toast
   */
  function showInfo(message: string, duration = 3000): number {
    return showToast(message, 'info', duration);
  }

  /**
   * Remove toast by ID
   */
  function removeToast(id: number): void {
    toasts.value = toasts.value.filter((t: any) => t.id !== id);
  }

  /**
   * Clear all toasts
   */
  function clearToasts(): void {
    toasts.value = [];
  }

  // === Online Status ===

  /**
   * Update online status
   */
  function setOnlineStatus(online: boolean): void {
    const wasOffline = !isOnline.value;
    isOnline.value = online;

    if (!online) {
      showWarning('Нет соединения с сервером', 0); // Permanent toast
    } else if (wasOffline) {
      // Remove offline toast and show connected
      toasts.value = toasts.value.filter((t: any) =>
        t.message !== 'Нет соединения с сервером'
      );
      showSuccess('Соединение восстановлено');
    }
  }

  // === Dark Mode ===

  /**
   * Toggle dark mode
   */
  function toggleDarkMode(): void {
    isDarkMode.value = !isDarkMode.value;
    localStorage.setItem('waiter-dark-mode', isDarkMode.value ? 'true' : 'false');
  }

  /**
   * Set dark mode
   */
  function setDarkMode(enabled: boolean): void {
    isDarkMode.value = enabled;
    localStorage.setItem('waiter-dark-mode', enabled ? 'true' : 'false');
  }

  // === Refresh ===

  /**
   * Set refreshing state
   */
  function setRefreshing(value: boolean): void {
    isRefreshing.value = value;
  }

  // === Close All Modals ===

  /**
   * Close all modals
   */
  function closeAllModals(): void {
    isSideMenuOpen.value = false;
    isPaymentModalOpen.value = false;
    isGuestCountModalOpen.value = false;
    isConfirmModalOpen.value = false;
    confirmOptions.value = null;
    if (confirmResolve.value) {
      confirmResolve.value(false);
      confirmResolve.value = null;
    }
  }

  // === Initialize ===

  /**
   * Initialize UI store
   */
  function init(): void {
    // Load dark mode preference
    const savedDarkMode = localStorage.getItem('waiter-dark-mode');
    if (savedDarkMode === 'true') {
      isDarkMode.value = true;
    }

    // Listen for online/offline events
    window.addEventListener('online', () => setOnlineStatus(true));
    window.addEventListener('offline', () => setOnlineStatus(false));

    // Listen for auth logout event
    window.addEventListener('auth:logout', () => {
      closeAllModals();
      setTab('tables');
    });
  }

  /**
   * Reset store
   */
  function $reset(): void {
    currentTab.value = 'tables';
    previousTab.value = null;
    closeAllModals();
    clearToasts();
    isRefreshing.value = false;
  }

  return {
    // State
    currentTab,
    previousTab,
    isSideMenuOpen,
    isPaymentModalOpen,
    isGuestCountModalOpen,
    isConfirmModalOpen,
    confirmOptions,
    toasts,
    isOnline,
    isDarkMode,
    isRefreshing,

    // Getters
    isTablesTab,
    isOrdersTab,
    isTableOrderTab,
    isProfileTab,
    hasModalOpen,
    activeToasts,

    // Navigation
    setTab,
    goToTables,
    goToOrders,
    goToTableOrder,
    goToProfile,
    goBack,

    // Side Menu
    toggleSideMenu,
    openSideMenu,
    closeSideMenu,

    // Payment Modal
    openPaymentModal,
    closePaymentModal,

    // Guest Count Modal
    openGuestCountModal,
    closeGuestCountModal,

    // Confirm Modal
    confirm,
    resolveConfirm,

    // Toasts
    showToast,
    showSuccess,
    showError,
    showWarning,
    showInfo,
    removeToast,
    clearToasts,

    // Online Status
    setOnlineStatus,

    // Dark Mode
    toggleDarkMode,
    setDarkMode,

    // Refresh
    setRefreshing,

    // Utils
    closeAllModals,
    init,
    $reset,
  };
});
