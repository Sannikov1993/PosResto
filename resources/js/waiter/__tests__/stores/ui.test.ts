/**
 * UI Store Unit Tests
 */

import { describe, it, expect, beforeEach, vi, afterEach } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';
import { useUiStore } from '@/waiter/stores/ui';

describe('UI Store', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
    vi.useFakeTimers();
    localStorage.clear();
  });

  afterEach(() => {
    vi.useRealTimers();
    localStorage.clear();
  });

  describe('Initial State', () => {
    it('should have correct initial state', () => {
      const store = useUiStore();

      expect(store.currentTab).toBe('tables');
      expect(store.previousTab).toBeNull();
      expect(store.isSideMenuOpen).toBe(false);
      expect(store.isPaymentModalOpen).toBe(false);
      expect(store.isGuestCountModalOpen).toBe(false);
      expect(store.isConfirmModalOpen).toBe(false);
      expect(store.toasts).toEqual([]);
      expect(store.isDarkMode).toBe(false);
      expect(store.isRefreshing).toBe(false);
    });
  });

  describe('Navigation', () => {
    it('setTab should change current tab', () => {
      const store = useUiStore();

      store.setTab('orders');

      expect(store.currentTab).toBe('orders');
      expect(store.previousTab).toBe('tables');
    });

    it('setTab should close side menu', () => {
      const store = useUiStore();
      store.isSideMenuOpen = true;

      store.setTab('orders');

      expect(store.isSideMenuOpen).toBe(false);
    });

    it('goToTables should navigate to tables', () => {
      const store = useUiStore();
      store.currentTab = 'orders';

      store.goToTables();

      expect(store.currentTab).toBe('tables');
    });

    it('goToOrders should navigate to orders', () => {
      const store = useUiStore();

      store.goToOrders();

      expect(store.currentTab).toBe('orders');
    });

    it('goToTableOrder should navigate to table-order', () => {
      const store = useUiStore();

      store.goToTableOrder();

      expect(store.currentTab).toBe('table-order');
    });

    it('goToProfile should navigate to profile', () => {
      const store = useUiStore();

      store.goToProfile();

      expect(store.currentTab).toBe('profile');
    });

    it('goBack should return to previous tab', () => {
      const store = useUiStore();
      store.currentTab = 'table-order';
      store.previousTab = 'orders';

      store.goBack();

      expect(store.currentTab).toBe('orders');
    });

    it('goBack should go to tables if no previous tab', () => {
      const store = useUiStore();
      store.currentTab = 'orders';
      store.previousTab = null;

      store.goBack();

      expect(store.currentTab).toBe('tables');
    });
  });

  describe('Getters', () => {
    it('isTablesTab should be true when on tables', () => {
      const store = useUiStore();
      store.currentTab = 'tables';

      expect(store.isTablesTab).toBe(true);
      expect(store.isOrdersTab).toBe(false);
    });

    it('isOrdersTab should be true when on orders', () => {
      const store = useUiStore();
      store.currentTab = 'orders';

      expect(store.isOrdersTab).toBe(true);
      expect(store.isTablesTab).toBe(false);
    });

    it('hasModalOpen should be true when any modal is open', () => {
      const store = useUiStore();

      expect(store.hasModalOpen).toBe(false);

      store.isSideMenuOpen = true;
      expect(store.hasModalOpen).toBe(true);

      store.isSideMenuOpen = false;
      store.isPaymentModalOpen = true;
      expect(store.hasModalOpen).toBe(true);
    });
  });

  describe('Side Menu', () => {
    it('toggleSideMenu should toggle state', () => {
      const store = useUiStore();

      store.toggleSideMenu();
      expect(store.isSideMenuOpen).toBe(true);

      store.toggleSideMenu();
      expect(store.isSideMenuOpen).toBe(false);
    });

    it('openSideMenu should open menu', () => {
      const store = useUiStore();

      store.openSideMenu();

      expect(store.isSideMenuOpen).toBe(true);
    });

    it('closeSideMenu should close menu', () => {
      const store = useUiStore();
      store.isSideMenuOpen = true;

      store.closeSideMenu();

      expect(store.isSideMenuOpen).toBe(false);
    });
  });

  describe('Payment Modal', () => {
    it('openPaymentModal should open modal', () => {
      const store = useUiStore();

      store.openPaymentModal();

      expect(store.isPaymentModalOpen).toBe(true);
    });

    it('closePaymentModal should close modal', () => {
      const store = useUiStore();
      store.isPaymentModalOpen = true;

      store.closePaymentModal();

      expect(store.isPaymentModalOpen).toBe(false);
    });
  });

  describe('Toasts', () => {
    it('showToast should add toast to array', () => {
      const store = useUiStore();

      const id = store.showToast('Test message', 'success', 3000);

      expect(store.toasts).toHaveLength(1);
      expect(store.toasts[0].message).toBe('Test message');
      expect(store.toasts[0].type).toBe('success');
      expect(store.toasts[0].id).toBe(id);
    });

    it('showToast should auto-remove after duration', () => {
      const store = useUiStore();

      store.showToast('Test', 'info', 3000);
      expect(store.toasts).toHaveLength(1);

      vi.advanceTimersByTime(3000);
      expect(store.toasts).toHaveLength(0);
    });

    it('showToast with duration 0 should not auto-remove', () => {
      const store = useUiStore();

      store.showToast('Permanent', 'warning', 0);
      expect(store.toasts).toHaveLength(1);

      vi.advanceTimersByTime(10000);
      expect(store.toasts).toHaveLength(1);
    });

    it('showSuccess should create success toast', () => {
      const store = useUiStore();

      store.showSuccess('Success!');

      expect(store.toasts[0].type).toBe('success');
    });

    it('showError should create error toast', () => {
      const store = useUiStore();

      store.showError('Error!');

      expect(store.toasts[0].type).toBe('error');
    });

    it('showWarning should create warning toast', () => {
      const store = useUiStore();

      store.showWarning('Warning!');

      expect(store.toasts[0].type).toBe('warning');
    });

    it('showInfo should create info toast', () => {
      const store = useUiStore();

      store.showInfo('Info');

      expect(store.toasts[0].type).toBe('info');
    });

    it('removeToast should remove toast by id', () => {
      const store = useUiStore();

      const id1 = store.showToast('One', 'info', 0);
      const id2 = store.showToast('Two', 'info', 0);

      store.removeToast(id1);

      expect(store.toasts).toHaveLength(1);
      expect(store.toasts[0].id).toBe(id2);
    });

    it('clearToasts should remove all toasts', () => {
      const store = useUiStore();

      store.showToast('One', 'info', 0);
      store.showToast('Two', 'info', 0);
      store.showToast('Three', 'info', 0);

      store.clearToasts();

      expect(store.toasts).toHaveLength(0);
    });
  });

  describe('Online Status', () => {
    it('setOnlineStatus should update status and show toast', () => {
      const store = useUiStore();

      store.setOnlineStatus(false);

      expect(store.isOnline).toBe(false);
      expect(store.toasts.some(t => t.message === 'Нет соединения с сервером')).toBe(true);
    });

    it('setOnlineStatus should show connected toast when coming back online', () => {
      const store = useUiStore();
      store.isOnline = false;

      store.setOnlineStatus(true);

      expect(store.isOnline).toBe(true);
      expect(store.toasts.some(t => t.message === 'Соединение восстановлено')).toBe(true);
    });
  });

  describe('Dark Mode', () => {
    it('toggleDarkMode should toggle state', () => {
      const store = useUiStore();

      store.toggleDarkMode();
      expect(store.isDarkMode).toBe(true);
      expect(localStorage.getItem('waiter-dark-mode')).toBe('true');

      store.toggleDarkMode();
      expect(store.isDarkMode).toBe(false);
      expect(localStorage.getItem('waiter-dark-mode')).toBe('false');
    });

    it('setDarkMode should set specific state', () => {
      const store = useUiStore();

      store.setDarkMode(true);
      expect(store.isDarkMode).toBe(true);

      store.setDarkMode(false);
      expect(store.isDarkMode).toBe(false);
    });
  });

  describe('Confirm Modal', () => {
    it('confirm should open modal and return promise', async () => {
      const store = useUiStore();

      const promise = store.confirm({
        title: 'Delete?',
        message: 'Are you sure?',
      });

      expect(store.isConfirmModalOpen).toBe(true);
      expect(store.confirmOptions?.title).toBe('Delete?');

      store.resolveConfirm(true);

      const result = await promise;
      expect(result).toBe(true);
      expect(store.isConfirmModalOpen).toBe(false);
    });

    it('resolveConfirm should close modal and clear options', () => {
      const store = useUiStore();
      store.isConfirmModalOpen = true;
      store.confirmOptions = { title: 'Test', message: 'Test' };

      store.resolveConfirm(false);

      expect(store.isConfirmModalOpen).toBe(false);
      expect(store.confirmOptions).toBeNull();
    });
  });

  describe('closeAllModals', () => {
    it('should close all modals', () => {
      const store = useUiStore();

      store.isSideMenuOpen = true;
      store.isPaymentModalOpen = true;
      store.isGuestCountModalOpen = true;
      store.isConfirmModalOpen = true;

      store.closeAllModals();

      expect(store.isSideMenuOpen).toBe(false);
      expect(store.isPaymentModalOpen).toBe(false);
      expect(store.isGuestCountModalOpen).toBe(false);
      expect(store.isConfirmModalOpen).toBe(false);
    });
  });

  describe('$reset', () => {
    it('should reset to initial state', () => {
      const store = useUiStore();

      store.currentTab = 'orders';
      store.isSideMenuOpen = true;
      store.toasts = [{ id: 1, message: 'Test', type: 'info', duration: 0, createdAt: Date.now() }];

      store.$reset();

      expect(store.currentTab).toBe('tables');
      expect(store.isSideMenuOpen).toBe(false);
      expect(store.toasts).toEqual([]);
    });
  });
});
