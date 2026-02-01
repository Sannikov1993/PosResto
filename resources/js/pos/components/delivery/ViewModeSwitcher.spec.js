import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import ViewModeSwitcher from './ViewModeSwitcher.vue'

describe('ViewModeSwitcher', () => {
  it('рендерит две кнопки режимов', () => {
    const wrapper = mount(ViewModeSwitcher)

    const buttons = wrapper.findAll('button')
    expect(buttons).toHaveLength(2)
    expect(buttons[0].text()).toBe('Kanban')
    expect(buttons[1].text()).toBe('Таблица')
  })

  it('применяет активный стиль к выбранному режиму', () => {
    const wrapper = mount(ViewModeSwitcher, {
      props: { modelValue: 'kanban' }
    })

    const buttons = wrapper.findAll('button')
    expect(buttons[0].classes()).toContain('bg-accent')
    expect(buttons[1].classes()).not.toContain('bg-accent')
  })

  it('эмитит событие update:modelValue при клике', async () => {
    const wrapper = mount(ViewModeSwitcher, {
      props: { modelValue: 'kanban' }
    })

    await wrapper.findAll('button')[1].trigger('click')

    expect(wrapper.emitted('update:modelValue')).toBeTruthy()
    expect(wrapper.emitted('update:modelValue')[0]).toEqual(['table'])
  })

  it('отображает table как активный когда выбран', () => {
    const wrapper = mount(ViewModeSwitcher, {
      props: { modelValue: 'table' }
    })

    const buttons = wrapper.findAll('button')
    expect(buttons[1].classes()).toContain('bg-accent')
    expect(buttons[0].classes()).not.toContain('bg-accent')
  })

  it('использует kanban по умолчанию', () => {
    const wrapper = mount(ViewModeSwitcher)

    // По умолчанию modelValue = 'kanban'
    const buttons = wrapper.findAll('button')
    expect(buttons[0].classes()).toContain('bg-accent')
  })
})
