import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useAuthStore } from './auth'

// Mock localStorage
const localStorageMock = {
  store: {},
  getItem: vi.fn((key) => localStorageMock.store[key] || null),
  setItem: vi.fn((key, value) => { localStorageMock.store[key] = value }),
  removeItem: vi.fn((key) => { delete localStorageMock.store[key] }),
  clear: vi.fn(() => { localStorageMock.store = {} })
}

// Mock API
vi.mock('../api', () => ({
  default: {
    post: vi.fn(),
    get: vi.fn(),
  }
}))

Object.defineProperty(window, 'localStorage', { value: localStorageMock })

describe('Auth Store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    localStorageMock.clear()
    vi.clearAllMocks()
  })

  describe('начальное состояние', () => {
    it('user равен null по умолчанию', () => {
      const store = useAuthStore()
      expect(store.user).toBeNull()
    })

    it('isLoggedIn равен false по умолчанию', () => {
      const store = useAuthStore()
      expect(store.isLoggedIn).toBe(false)
    })

    it('token равен null по умолчанию', () => {
      const store = useAuthStore()
      expect(store.token).toBeNull()
    })
  })

  describe('userInitials computed', () => {
    it('возвращает ? когда нет пользователя', () => {
      const store = useAuthStore()
      expect(store.userInitials).toBe('?')
    })

    it('возвращает инициалы из имени и фамилии', () => {
      const store = useAuthStore()
      store.user = { name: 'Иван Петров' }
      expect(store.userInitials).toBe('ИП')
    })

    it('возвращает первые 2 символа если только имя', () => {
      const store = useAuthStore()
      store.user = { name: 'Администратор' }
      expect(store.userInitials).toBe('АД')
    })
  })

  describe('hasPermission', () => {
    it('возвращает false когда нет пользователя', () => {
      const store = useAuthStore()
      expect(store.hasPermission('orders.create')).toBe(false)
    })

    it('возвращает true для super_admin', () => {
      const store = useAuthStore()
      store.user = { role: 'super_admin' }
      expect(store.hasPermission('any.permission')).toBe(true)
    })

    it('возвращает true для owner', () => {
      const store = useAuthStore()
      store.user = { role: 'owner' }
      expect(store.hasPermission('any.permission')).toBe(true)
    })

    it('проверяет конкретные права для обычных пользователей', () => {
      const store = useAuthStore()
      store.user = { role: 'waiter' }
      store.permissions = ['orders.create', 'orders.view']

      expect(store.hasPermission('orders.create')).toBe(true)
      expect(store.hasPermission('orders.delete')).toBe(false)
    })

    it('возвращает true если есть wildcard право', () => {
      const store = useAuthStore()
      store.user = { role: 'admin' }
      store.permissions = ['*']

      expect(store.hasPermission('anything')).toBe(true)
    })
  })

  describe('currentRestaurant computed', () => {
    it('возвращает null когда нет ресторанов', () => {
      const store = useAuthStore()
      expect(store.currentRestaurant).toBeNull()
    })

    it('возвращает первый ресторан если нет выбранного', () => {
      const store = useAuthStore()
      store.restaurants = [
        { id: 1, name: 'Ресторан 1' },
        { id: 2, name: 'Ресторан 2' }
      ]
      expect(store.currentRestaurant.id).toBe(1)
    })

    it('возвращает ресторан с is_current если есть', () => {
      const store = useAuthStore()
      store.restaurants = [
        { id: 1, name: 'Ресторан 1', is_current: false },
        { id: 2, name: 'Ресторан 2', is_current: true }
      ]
      expect(store.currentRestaurant.id).toBe(2)
    })
  })

  describe('hasMultipleRestaurants computed', () => {
    it('возвращает false для одного ресторана', () => {
      const store = useAuthStore()
      store.restaurants = [{ id: 1 }]
      expect(store.hasMultipleRestaurants).toBe(false)
    })

    it('возвращает true для нескольких ресторанов', () => {
      const store = useAuthStore()
      store.restaurants = [{ id: 1 }, { id: 2 }]
      expect(store.hasMultipleRestaurants).toBe(true)
    })
  })
})
