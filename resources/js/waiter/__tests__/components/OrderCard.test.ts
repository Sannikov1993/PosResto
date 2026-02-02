/**
 * OrderCard Component Unit Tests
 */

import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import OrderCard from '@/waiter/components/orders/OrderCard.vue';

// Mock formatters
vi.mock('@/waiter/utils/formatters', () => ({
  formatMoney: vi.fn((val) => `${val} ₽`),
  formatRelativeTime: vi.fn(() => '5 мин назад'),
  formatItemsCount: vi.fn((count) => `${count} позиций`),
}));

// Mock child component
vi.mock('@/waiter/components/orders/OrderStatusBadge.vue', () => ({
  default: {
    template: '<span data-testid="status-badge">{{ status }}</span>',
    props: ['status'],
  },
}));

const createOrder = (overrides = {}) => ({
  id: 1,
  table: { id: 1, number: '5' },
  status: 'active',
  total: 1500,
  items: [
    { id: 1, dish: { name: 'Пицца' }, name: 'Пицца', status: 'new' },
    { id: 2, dish: { name: 'Паста' }, name: 'Паста', status: 'ready' },
  ],
  created_at: '2024-01-15T10:00:00Z',
  ...overrides,
});

describe('OrderCard Component', () => {
  describe('Rendering', () => {
    it('should render table number', () => {
      const order = createOrder();
      const wrapper = mount(OrderCard, {
        props: { order },
      });

      expect(wrapper.text()).toContain('Стол 5');
    });

    it('should render order id', () => {
      const order = createOrder({ id: 123 });
      const wrapper = mount(OrderCard, {
        props: { order },
      });

      expect(wrapper.text()).toContain('#123');
    });

    it('should render formatted total', () => {
      const order = createOrder({ total: 2500 });
      const wrapper = mount(OrderCard, {
        props: { order },
      });

      expect(wrapper.text()).toContain('2500 ₽');
    });

    it('should render items count', () => {
      const order = createOrder();
      const wrapper = mount(OrderCard, {
        props: { order },
      });

      expect(wrapper.text()).toContain('2 позиций');
    });

    it('should render items preview', () => {
      const order = createOrder();
      const wrapper = mount(OrderCard, {
        props: { order },
      });

      expect(wrapper.text()).toContain('Пицца, Паста');
    });

    it('should truncate items preview for more than 3 items', () => {
      const order = createOrder({
        items: [
          { id: 1, dish: { name: 'Item 1' }, status: 'new' },
          { id: 2, dish: { name: 'Item 2' }, status: 'new' },
          { id: 3, dish: { name: 'Item 3' }, status: 'new' },
          { id: 4, dish: { name: 'Item 4' }, status: 'new' },
        ],
      });
      const wrapper = mount(OrderCard, {
        props: { order },
      });

      expect(wrapper.text()).toContain('...');
    });

    it('should render relative time', () => {
      const order = createOrder();
      const wrapper = mount(OrderCard, {
        props: { order },
      });

      expect(wrapper.text()).toContain('5 мин назад');
    });

    it('should have correct data-testid', () => {
      const order = createOrder({ id: 42 });
      const wrapper = mount(OrderCard, {
        props: { order },
      });

      expect(wrapper.find('[data-testid="order-42"]').exists()).toBe(true);
    });
  });

  describe('Ready Items Indicator', () => {
    it('should show ready items count when there are ready items', () => {
      const order = createOrder({
        items: [
          { id: 1, status: 'ready', dish: { name: 'Ready 1' } },
          { id: 2, status: 'ready', dish: { name: 'Ready 2' } },
          { id: 3, status: 'cooking', dish: { name: 'Cooking' } },
        ],
      });
      const wrapper = mount(OrderCard, {
        props: { order },
      });

      expect(wrapper.text()).toContain('2 готово к подаче');
    });

    it('should not show ready indicator when no ready items', () => {
      const order = createOrder({
        items: [
          { id: 1, status: 'new', dish: { name: 'New' } },
          { id: 2, status: 'cooking', dish: { name: 'Cooking' } },
        ],
      });
      const wrapper = mount(OrderCard, {
        props: { order },
      });

      expect(wrapper.text()).not.toContain('готово к подаче');
    });
  });

  describe('Edge Cases', () => {
    it('should handle order without table', () => {
      const order = createOrder({ table: null });
      const wrapper = mount(OrderCard, {
        props: { order },
      });

      expect(wrapper.text()).toContain('Стол ?');
    });

    it('should handle order without items', () => {
      const order = createOrder({ items: [] });
      const wrapper = mount(OrderCard, {
        props: { order },
      });

      expect(wrapper.text()).toContain('0 позиций');
    });

    it('should handle items without dish object', () => {
      const order = createOrder({
        items: [{ id: 1, name: 'Direct Name', status: 'new' }],
      });
      const wrapper = mount(OrderCard, {
        props: { order },
      });

      expect(wrapper.text()).toContain('Direct Name');
    });
  });

  describe('Events', () => {
    it('should emit select event on click', async () => {
      const order = createOrder();
      const wrapper = mount(OrderCard, {
        props: { order },
      });

      await wrapper.find('button').trigger('click');

      expect(wrapper.emitted('select')).toBeTruthy();
      expect(wrapper.emitted('select')![0]).toEqual([order]);
    });
  });
});
