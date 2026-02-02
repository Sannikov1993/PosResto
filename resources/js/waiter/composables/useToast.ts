/**
 * Waiter App - useToast Composable
 * Toast notifications helper
 */

import { storeToRefs } from 'pinia';
import { useUiStore } from '@/waiter/stores/ui';
import type { ToastType } from '@/waiter/stores/ui';

export function useToast() {
  const uiStore = useUiStore();
  const { toasts, activeToasts } = storeToRefs(uiStore);

  /**
   * Show toast notification
   */
  function show(message: string, type: ToastType = 'info', duration = 3000): number {
    return uiStore.showToast(message, type, duration);
  }

  /**
   * Show success toast
   */
  function success(message: string, duration = 3000): number {
    return uiStore.showSuccess(message, duration);
  }

  /**
   * Show error toast
   */
  function error(message: string, duration = 5000): number {
    return uiStore.showError(message, duration);
  }

  /**
   * Show warning toast
   */
  function warning(message: string, duration = 4000): number {
    return uiStore.showWarning(message, duration);
  }

  /**
   * Show info toast
   */
  function info(message: string, duration = 3000): number {
    return uiStore.showInfo(message, duration);
  }

  /**
   * Remove toast by ID
   */
  function remove(id: number): void {
    uiStore.removeToast(id);
  }

  /**
   * Clear all toasts
   */
  function clear(): void {
    uiStore.clearToasts();
  }

  /**
   * Show loading toast (no auto-dismiss)
   */
  function loading(message: string): number {
    return uiStore.showToast(message, 'info', 0);
  }

  /**
   * Update loading toast to success
   */
  function loadingSuccess(loadingId: number, message: string): void {
    remove(loadingId);
    success(message);
  }

  /**
   * Update loading toast to error
   */
  function loadingError(loadingId: number, message: string): void {
    remove(loadingId);
    error(message);
  }

  /**
   * Show promise-based toast
   * Shows loading while promise is pending, then success or error
   */
  async function promise<T>(
    promise: Promise<T>,
    messages: {
      loading: string;
      success: string;
      error: string;
    }
  ): Promise<T> {
    const loadingId = loading(messages.loading);

    try {
      const result = await promise;
      loadingSuccess(loadingId, messages.success);
      return result;
    } catch (e) {
      loadingError(loadingId, messages.error);
      throw e;
    }
  }

  return {
    // State
    toasts,
    activeToasts,

    // Methods
    show,
    success,
    error,
    warning,
    info,
    remove,
    clear,
    loading,
    loadingSuccess,
    loadingError,
    promise,
  };
}
