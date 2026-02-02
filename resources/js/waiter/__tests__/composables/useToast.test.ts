/**
 * useToast Composable Unit Tests
 */

import { describe, it, expect, beforeEach, vi } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';

// Mock UI store
vi.mock('@/waiter/stores/ui', () => ({
  useUiStore: vi.fn(() => ({
    toasts: [],
    activeToasts: [],
    showToast: vi.fn((msg, type, duration) => Math.random()),
    showSuccess: vi.fn((msg, duration) => Math.random()),
    showError: vi.fn((msg, duration) => Math.random()),
    showWarning: vi.fn((msg, duration) => Math.random()),
    showInfo: vi.fn((msg, duration) => Math.random()),
    removeToast: vi.fn(),
    clearToasts: vi.fn(),
  })),
}));

import { useToast } from '@/waiter/composables/useToast';
import { useUiStore } from '@/waiter/stores/ui';

describe('useToast Composable', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
    vi.clearAllMocks();
  });

  describe('show', () => {
    it('should call showToast with correct params', () => {
      const uiStore = useUiStore();

      const { show } = useToast();
      show('Test message', 'success', 5000);

      expect(uiStore.showToast).toHaveBeenCalledWith('Test message', 'success', 5000);
    });

    it('should use default values', () => {
      const uiStore = useUiStore();

      const { show } = useToast();
      show('Test');

      expect(uiStore.showToast).toHaveBeenCalledWith('Test', 'info', 3000);
    });
  });

  describe('success', () => {
    it('should call showSuccess', () => {
      const uiStore = useUiStore();

      const { success } = useToast();
      success('Success!');

      expect(uiStore.showSuccess).toHaveBeenCalledWith('Success!', 3000);
    });
  });

  describe('error', () => {
    it('should call showError with longer duration', () => {
      const uiStore = useUiStore();

      const { error } = useToast();
      error('Error!');

      expect(uiStore.showError).toHaveBeenCalledWith('Error!', 5000);
    });
  });

  describe('warning', () => {
    it('should call showWarning', () => {
      const uiStore = useUiStore();

      const { warning } = useToast();
      warning('Warning!');

      expect(uiStore.showWarning).toHaveBeenCalledWith('Warning!', 4000);
    });
  });

  describe('info', () => {
    it('should call showInfo', () => {
      const uiStore = useUiStore();

      const { info } = useToast();
      info('Info');

      expect(uiStore.showInfo).toHaveBeenCalledWith('Info', 3000);
    });
  });

  describe('remove', () => {
    it('should call removeToast', () => {
      const uiStore = useUiStore();

      const { remove } = useToast();
      remove(5);

      expect(uiStore.removeToast).toHaveBeenCalledWith(5);
    });
  });

  describe('clear', () => {
    it('should call clearToasts', () => {
      const uiStore = useUiStore();

      const { clear } = useToast();
      clear();

      expect(uiStore.clearToasts).toHaveBeenCalled();
    });
  });

  describe('loading', () => {
    it('should show toast with duration 0', () => {
      const uiStore = useUiStore();

      const { loading } = useToast();
      loading('Loading...');

      expect(uiStore.showToast).toHaveBeenCalledWith('Loading...', 'info', 0);
    });
  });

  describe('loadingSuccess', () => {
    it('should remove loading and show success', () => {
      const uiStore = useUiStore();

      const { loadingSuccess } = useToast();
      loadingSuccess(5, 'Done!');

      expect(uiStore.removeToast).toHaveBeenCalledWith(5);
      expect(uiStore.showSuccess).toHaveBeenCalled();
    });
  });

  describe('loadingError', () => {
    it('should remove loading and show error', () => {
      const uiStore = useUiStore();

      const { loadingError } = useToast();
      loadingError(5, 'Failed!');

      expect(uiStore.removeToast).toHaveBeenCalledWith(5);
      expect(uiStore.showError).toHaveBeenCalled();
    });
  });

  describe('promise', () => {
    it('should show loading then success on resolve', async () => {
      const uiStore = useUiStore();
      vi.mocked(uiStore.showToast).mockReturnValue(123);

      const { promise } = useToast();

      const result = await promise(
        Promise.resolve('data'),
        {
          loading: 'Loading...',
          success: 'Done!',
          error: 'Failed!',
        }
      );

      expect(result).toBe('data');
      expect(uiStore.showToast).toHaveBeenCalledWith('Loading...', 'info', 0);
      expect(uiStore.removeToast).toHaveBeenCalledWith(123);
      expect(uiStore.showSuccess).toHaveBeenCalled();
    });

    it('should show loading then error on reject', async () => {
      const uiStore = useUiStore();
      vi.mocked(uiStore.showToast).mockReturnValue(123);

      const { promise } = useToast();

      await expect(
        promise(
          Promise.reject(new Error('test')),
          {
            loading: 'Loading...',
            success: 'Done!',
            error: 'Failed!',
          }
        )
      ).rejects.toThrow('test');

      expect(uiStore.showToast).toHaveBeenCalledWith('Loading...', 'info', 0);
      expect(uiStore.removeToast).toHaveBeenCalledWith(123);
      expect(uiStore.showError).toHaveBeenCalled();
    });
  });
});
