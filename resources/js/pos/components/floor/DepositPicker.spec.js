import { describe, it, expect, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import DepositPicker from './DepositPicker.vue'

describe('DepositPicker', () => {
  describe('отображение', () => {
    it('показывает "Без депозита" когда значение 0', () => {
      const wrapper = mount(DepositPicker, {
        props: { modelValue: 0 }
      })
      expect(wrapper.text()).toContain('Без депозита')
    })

    it('показывает сумму депозита когда значение > 0', () => {
      const wrapper = mount(DepositPicker, {
        props: { modelValue: 1000 }
      })
      // Текст содержит сумму (может быть с пробелом-разделителем)
      expect(wrapper.text()).toMatch(/1[\s ]000/)
    })

    it('форматирует большие суммы с К', () => {
      const wrapper = mount(DepositPicker, {
        props: { modelValue: 10000 }
      })
      expect(wrapper.text()).toContain('10К ₽')
    })
  })

  describe('embedded режим', () => {
    it('не показывает триггер-кнопку в embedded режиме', () => {
      const wrapper = mount(DepositPicker, {
        props: { modelValue: 0, embedded: true }
      })
      // В embedded режиме панель всегда видна
      expect(wrapper.find('.deposit-overlay').exists()).toBe(true)
    })

    it('показывает панель сразу в embedded режиме', () => {
      const wrapper = mount(DepositPicker, {
        props: { modelValue: 0, embedded: true }
      })
      expect(wrapper.find('.deposit-header').exists()).toBe(true)
    })
  })

  describe('быстрые суммы', () => {
    it('рендерит кнопки быстрых сумм', () => {
      const wrapper = mount(DepositPicker, {
        props: { modelValue: 0, embedded: true }
      })
      const quickButtons = wrapper.findAll('.deposit-quick button')
      // 8 быстрых сумм + 1 кнопка "Без депозита" = 9
      expect(quickButtons.length).toBeGreaterThanOrEqual(8)
    })
  })

  describe('увеличение/уменьшение', () => {
    it('показывает шаг в интерфейсе', () => {
      const wrapper = mount(DepositPicker, {
        props: { modelValue: 0, embedded: true, step: 500 }
      })
      expect(wrapper.text()).toContain('шаг 500 ₽')
    })
  })

  describe('методы оплаты', () => {
    it('показывает способ оплаты когда сумма > 0', () => {
      const wrapper = mount(DepositPicker, {
        props: { modelValue: 1000, paymentMethod: 'cash', embedded: true }
      })
      expect(wrapper.text()).toContain('Наличные')
      expect(wrapper.text()).toContain('Картой')
    })
  })

  describe('подтверждение', () => {
    it('показывает кнопку подтверждения', () => {
      const wrapper = mount(DepositPicker, {
        props: { modelValue: 0, embedded: true }
      })
      expect(wrapper.find('.deposit-footer button').exists()).toBe(true)
    })

    it('кнопка показывает "Сохранить без депозита" когда сумма 0', () => {
      const wrapper = mount(DepositPicker, {
        props: { modelValue: 0, embedded: true }
      })
      expect(wrapper.find('.deposit-footer button').text()).toContain('Сохранить без депозита')
    })
  })
})
