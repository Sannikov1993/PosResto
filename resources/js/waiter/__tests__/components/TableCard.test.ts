/**
 * TableCard Component Unit Tests
 */

import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import TableCard from '@/waiter/components/tables/TableCard.vue';

// Mock formatters
vi.mock('@/waiter/utils/formatters', () => ({
  formatMoney: vi.fn((val) => `${val} ₽`),
}));

const createTable = (overrides = {}) => ({
  id: 1,
  number: '5',
  seats: 4,
  status: 'free' as const,
  zone_id: 1,
  current_order: null,
  ...overrides,
});

describe('TableCard Component', () => {
  describe('Rendering', () => {
    it('should render table number', () => {
      const table = createTable({ number: '12' });
      const wrapper = mount(TableCard, {
        props: { table },
      });

      expect(wrapper.text()).toContain('12');
    });

    it('should render seats count', () => {
      const table = createTable({ seats: 6 });
      const wrapper = mount(TableCard, {
        props: { table },
      });

      expect(wrapper.text()).toContain('6 мест');
    });

    it('should have correct data-testid', () => {
      const table = createTable({ number: '7' });
      const wrapper = mount(TableCard, {
        props: { table },
      });

      expect(wrapper.find('[data-testid="table-7"]').exists()).toBe(true);
    });
  });

  describe('Status Classes', () => {
    it('should apply free status class', () => {
      const table = createTable({ status: 'free' });
      const wrapper = mount(TableCard, {
        props: { table },
      });

      expect(wrapper.find('button').classes()).toContain('bg-dark-800');
    });

    it('should apply occupied status class', () => {
      const table = createTable({ status: 'occupied' });
      const wrapper = mount(TableCard, {
        props: { table },
      });

      const classes = wrapper.find('button').classes();
      expect(classes.some(c => c.includes('orange'))).toBe(true);
    });

    it('should apply reserved status class', () => {
      const table = createTable({ status: 'reserved' });
      const wrapper = mount(TableCard, {
        props: { table },
      });

      const classes = wrapper.find('button').classes();
      expect(classes.some(c => c.includes('blue'))).toBe(true);
    });

    it('should apply bill_requested status class', () => {
      const table = createTable({ status: 'bill_requested' });
      const wrapper = mount(TableCard, {
        props: { table },
      });

      const classes = wrapper.find('button').classes();
      expect(classes.some(c => c.includes('purple'))).toBe(true);
    });
  });

  describe('Order Display', () => {
    it('should show order total when table has current order', () => {
      const table = createTable({
        current_order: { id: 1, total: 2500, guests_count: 2 },
      });
      const wrapper = mount(TableCard, {
        props: { table },
      });

      expect(wrapper.text()).toContain('2500 ₽');
    });

    it('should not show total when no current order', () => {
      const table = createTable({ current_order: null });
      const wrapper = mount(TableCard, {
        props: { table },
      });

      expect(wrapper.text()).not.toContain('₽');
    });

    it('should show guests count when order has guests', () => {
      const table = createTable({
        current_order: { id: 1, total: 1000, guests_count: 4 },
      });
      const wrapper = mount(TableCard, {
        props: { table },
      });

      expect(wrapper.text()).toContain('4 гостей');
    });

    it('should not show guests count when no guests', () => {
      const table = createTable({
        current_order: { id: 1, total: 1000, guests_count: 0 },
      });
      const wrapper = mount(TableCard, {
        props: { table },
      });

      expect(wrapper.text()).not.toContain('гостей');
    });
  });

  describe('Events', () => {
    it('should emit select event on click', async () => {
      const table = createTable();
      const wrapper = mount(TableCard, {
        props: { table },
      });

      await wrapper.find('button').trigger('click');

      expect(wrapper.emitted('select')).toBeTruthy();
      expect(wrapper.emitted('select')![0]).toEqual([table]);
    });
  });

  describe('Button Styling', () => {
    it('should have aspect-square class', () => {
      const table = createTable();
      const wrapper = mount(TableCard, {
        props: { table },
      });

      expect(wrapper.find('button').classes()).toContain('aspect-square');
    });

    it('should have rounded corners', () => {
      const table = createTable();
      const wrapper = mount(TableCard, {
        props: { table },
      });

      expect(wrapper.find('button').classes()).toContain('rounded-2xl');
    });

    it('should have active scale animation', () => {
      const table = createTable();
      const wrapper = mount(TableCard, {
        props: { table },
      });

      expect(wrapper.find('button').classes()).toContain('active:scale-95');
    });
  });
});
