import { test as base, expect, Page } from '@playwright/test';

// ============================================
// ТИПЫ
// ============================================

interface TestUser {
  id?: number;
  email: string;
  password: string;
  pin?: string;
  role: 'admin' | 'manager' | 'waiter' | 'cashier' | 'cook';
}

interface TestTable {
  id: number;
  number: string;
  hall: string;
}

interface TestDish {
  id: number;
  name: string;
  price: number;
  category: string;
}

// ============================================
// ТЕСТОВЫЕ ДАННЫЕ
// ============================================

// Данные из MenuLabSeeder.php
export const TEST_USERS: Record<string, TestUser> = {
  admin: {
    id: 1,
    email: 'admin@menulab.local',
    password: 'password',
    pin: '1234',
    role: 'admin',
  },
  manager: {
    id: 2, // В MenuLabSeeder нет manager, это запасной вариант
    email: 'admin@menulab.local',
    password: 'password',
    pin: '1234',
    role: 'manager',
  },
  waiter: {
    id: 2,
    email: 'anna@menulab.local',
    password: 'password',
    pin: '1111',
    role: 'waiter',
  },
  cashier: {
    id: 4,
    email: 'elena@menulab.local',
    password: 'password',
    pin: '3333',
    role: 'cashier',
  },
};

// ============================================
// PAGE OBJECTS
// ============================================

export class PosPage {
  constructor(private page: Page) {}

  // --- Навигация ---
  async goto() {
    await this.page.goto('/pos');
    // Ждём загрузки - либо экран логина, либо главный экран
    await this.page.waitForSelector('[data-testid="login-screen"], [data-testid="pos-main"], [data-testid="user-selector"]', { timeout: 15000 });
  }

  async gotoTable(tableId: number) {
    await this.page.goto(`/pos/table/${tableId}`);
  }

  // --- Авторизация ---

  /**
   * Логин через PIN-код (нумпад)
   * 1. Выбираем пользователя из списка
   * 2. Вводим PIN по одной цифре через нумпад
   */
  async loginWithPin(pin: string, userId?: number) {
    // Если видим список пользователей - выбираем нужного
    const userSelector = this.page.getByTestId('user-selector');
    if (await userSelector.isVisible({ timeout: 2000 }).catch(() => false)) {
      if (userId) {
        await this.page.getByTestId(`user-${userId}`).click();
      } else {
        // Кликаем первого пользователя (ищем внутри user-selector кнопки с data-testid="user-{число}")
        await userSelector.locator('button[data-testid^="user-"]').first().click();
      }
    }

    // Ждём появления PIN-нумпада
    await this.page.getByTestId('pin-numpad').waitFor({ timeout: 5000 });

    // Вводим PIN по одной цифре
    for (const digit of pin) {
      await this.page.getByTestId(`pin-key-${digit}`).click();
      await this.page.waitForTimeout(100); // Небольшая задержка между нажатиями
    }

    // PIN автоматически отправляется после 4 цифр, ждём результат
    await this.page.getByTestId('pos-main').waitFor({ timeout: 10000 });
  }

  /**
   * Логин через email и пароль
   */
  async loginWithPassword(email: string, password: string) {
    // Если видим список пользователей - переключаемся на форму логина
    const userSelector = this.page.getByTestId('user-selector');
    if (await userSelector.isVisible({ timeout: 2000 }).catch(() => false)) {
      await this.page.getByTestId('show-password-login').click();
    }

    // Если видим PIN нумпад - переключаемся на форму пароля
    const pinNumpad = this.page.getByTestId('pin-numpad');
    if (await pinNumpad.isVisible({ timeout: 1000 }).catch(() => false)) {
      await this.page.getByTestId('switch-to-password').click();
    }

    // Заполняем форму
    await this.page.getByTestId('email-input').fill(email);
    await this.page.getByTestId('password-input').fill(password);
    await this.page.getByTestId('login-submit').click();

    // Ждём главный экран
    await this.page.getByTestId('pos-main').waitFor({ timeout: 10000 });
  }

  async logout() {
    await this.page.getByTestId('logout-btn').click();
    // Ждём экран логина
    await this.page.waitForSelector('[data-testid="login-screen"], [data-testid="user-selector"]', { timeout: 5000 });
  }

  // --- Навигация по вкладкам ---
  async goToTab(tabId: string) {
    await this.page.getByTestId(`tab-${tabId}`).click();
    await this.page.getByTestId(`${tabId}-tab`).waitFor({ timeout: 5000 });
  }

  async goToCash() {
    await this.goToTab('cash');
  }

  async goToOrders() {
    await this.goToTab('orders');
  }

  async goToDelivery() {
    await this.goToTab('delivery');
  }

  async goToCustomers() {
    await this.goToTab('customers');
  }

  async goToWarehouse() {
    await this.goToTab('warehouse');
  }

  async goToStopList() {
    await this.goToTab('stoplist');
  }

  async goToWriteOffs() {
    await this.goToTab('writeoffs');
  }

  async goToSettings() {
    await this.goToTab('settings');
  }

  // --- Смена ---
  async openShift(initialCash: number = 5000) {
    await this.goToCash();

    // Кликаем открыть смену
    const openBtn = this.page.getByTestId('open-shift-btn');
    if (await openBtn.isVisible().catch(() => false)) {
      await openBtn.click();

      // Ждём модальное окно
      await this.page.getByTestId('open-shift-modal').waitFor({ timeout: 5000 });

      // Вводим сумму
      await this.page.getByTestId('opening-amount-input').fill(String(initialCash));

      // Подтверждаем
      await this.page.getByTestId('open-shift-submit-btn').click();

      // Ждём закрытия модалки и появления кнопки закрытия смены
      await this.page.getByTestId('close-shift-btn').waitFor({ timeout: 15000 });
    }
  }

  async closeShift() {
    await this.goToCash();
    await this.page.getByTestId('close-shift-btn').click();
    // Ждём модалку закрытия смены
    await this.page.getByTestId('close-shift-modal').waitFor({ timeout: 5000 });
  }

  async isShiftOpen(): Promise<boolean> {
    const closeBtn = this.page.getByTestId('close-shift-btn');
    return await closeBtn.isVisible().catch(() => false);
  }

  // --- Зал и столы ---
  async selectZone(zoneId: number) {
    await this.page.getByTestId(`zone-tab-${zoneId}`).click();
  }

  async clickTable(tableId: number) {
    await this.page.getByTestId(`table-${tableId}`).click();
  }

  async getTableStatus(tableId: number): Promise<string> {
    const table = this.page.getByTestId(`table-${tableId}`);
    return await table.getAttribute('data-status') || 'unknown';
  }

  async getTablesCount(): Promise<number> {
    const tables = this.page.locator('[data-testid^="table-"]');
    return await tables.count();
  }

  // --- Заказ ---
  async createNewOrder(guests: number = 2) {
    // Кликаем "Новый заказ"
    await this.page.getByTestId('new-order-btn').click();

    // Ждём модалку выбора количества гостей
    const guestModal = this.page.getByTestId('guest-count-modal');
    if (await guestModal.isVisible({ timeout: 2000 }).catch(() => false)) {
      // Вводим количество гостей
      for (const digit of String(guests)) {
        await this.page.getByTestId(`guest-key-${digit}`).click();
      }
      // Подтверждаем
      await this.page.getByTestId('guest-confirm-btn').click();
    }
  }

  async addDishToOrder(dishName: string, quantity: number = 1) {
    // Поиск блюда (если есть поле поиска)
    const searchInput = this.page.getByTestId('dish-search');
    if (await searchInput.isVisible().catch(() => false)) {
      await searchInput.fill(dishName);
      await this.page.waitForTimeout(500);
    }

    // Клик по блюду
    await this.page.locator(`text=${dishName}`).first().click();

    // Если нужно больше 1
    if (quantity > 1) {
      for (let i = 1; i < quantity; i++) {
        await this.page.getByTestId('qty-plus').click();
      }
    }
  }

  async selectCategory(categoryName: string) {
    await this.page.locator(`[data-testid="category-item"]:has-text("${categoryName}")`).click();
  }

  async getOrderTotal(): Promise<number> {
    const totalText = await this.page.getByTestId('order-total').textContent();
    return parseFloat(totalText?.replace(/[^\d.]/g, '') || '0');
  }

  async getOrderItemsCount(): Promise<number> {
    const items = await this.page.locator('[data-testid="order-item"]').count();
    return items;
  }

  // --- Модификаторы ---
  async selectModifier(modifierName: string, optionName: string) {
    await this.page.getByTestId(`modifier-${modifierName}`).click();
    await this.page.getByTestId(`modifier-option-${optionName}`).click();
    await this.page.getByTestId('confirm-modifiers').click();
  }

  // --- Отправка на кухню ---
  async sendToKitchen() {
    await this.page.getByTestId('send-kitchen-btn').click();
    await this.page.waitForResponse(resp =>
      resp.url().includes('/api/') && resp.status() === 200
    , { timeout: 10000 }).catch(() => null);
  }

  // --- Оплата ---
  async openPaymentModal() {
    await this.page.getByTestId('pay-btn').click();
    await this.page.getByTestId('payment-modal').waitFor({ timeout: 5000 });
  }

  async payWithCash(amount?: number) {
    await this.openPaymentModal();
    await this.page.getByTestId('payment-cash-btn').click();
    if (amount) {
      await this.page.getByTestId('cash-received-input').fill(String(amount));
    }
    await this.page.getByTestId('payment-submit-btn').click();
  }

  async payWithCard() {
    await this.openPaymentModal();
    await this.page.getByTestId('payment-card-btn').click();
    await this.page.getByTestId('payment-submit-btn').click();
  }

  // --- Кассовые операции ---
  async cashDeposit(amount: number, comment: string = 'Внесение') {
    await this.goToCash();
    await this.page.getByTestId('deposit-btn').click();
    await this.page.getByTestId('cash-operation-modal').waitFor({ timeout: 5000 });
    await this.page.getByTestId('cash-amount-input').fill(String(amount));
    await this.page.getByTestId('cash-comment-input').fill(comment);
    await this.page.getByTestId('cash-operation-submit').click();
  }

  async cashWithdrawal(amount: number, comment: string = 'Изъятие') {
    await this.goToCash();
    await this.page.getByTestId('withdrawal-btn').click();
    await this.page.getByTestId('cash-operation-modal').waitFor({ timeout: 5000 });
    await this.page.getByTestId('cash-amount-input').fill(String(amount));
    await this.page.getByTestId('cash-comment-input').fill(comment);
    await this.page.getByTestId('cash-operation-submit').click();
  }

  // --- Доставка ---
  async createDeliveryOrder(data: {
    phone: string;
    address: string;
    dishes: { name: string; qty: number }[];
  }) {
    await this.goToDelivery();
    await this.page.getByTestId('new-delivery-order-btn').click();
    await this.page.getByTestId('delivery-order-modal').waitFor({ timeout: 5000 });

    await this.page.getByTestId('customer-phone-input').fill(data.phone);
    await this.page.getByTestId('delivery-address-input').fill(data.address);

    for (const dish of data.dishes) {
      await this.page.getByTestId('dish-search-input').fill(dish.name);
      await this.page.locator(`text=${dish.name}`).first().click();
    }

    await this.page.getByTestId('create-delivery-order-btn').click();
  }

  // --- Клиенты ---
  async searchCustomer(query: string) {
    await this.goToCustomers();
    await this.page.getByTestId('customer-search-input').fill(query);
    await this.page.waitForTimeout(500);
  }

  async createCustomer(data: { name: string; phone: string; email?: string }) {
    await this.goToCustomers();
    await this.page.getByTestId('new-customer-btn').click();
    await this.page.getByTestId('customer-modal').waitFor({ timeout: 5000 });

    await this.page.getByTestId('customer-name-input').fill(data.name);
    await this.page.getByTestId('customer-phone-input').fill(data.phone);
    if (data.email) {
      await this.page.getByTestId('customer-email-input').fill(data.email);
    }
    await this.page.getByTestId('save-customer-btn').click();
  }

  // --- Стоп-лист ---
  async addToStopList(dishName: string) {
    await this.goToStopList();
    await this.page.getByTestId('add-to-stoplist-btn').click();
    await this.page.getByTestId('stoplist-dish-search').fill(dishName);
    await this.page.locator(`text=${dishName}`).first().click();
    await this.page.getByTestId('confirm-stoplist-btn').click();
  }

  async removeFromStopList(dishName: string) {
    await this.goToStopList();
    await this.page.locator(`[data-testid="stoplist-item"]:has-text("${dishName}") [data-testid="remove-from-stoplist"]`).click();
  }

  // --- Резервирования ---
  async createReservation(data: {
    date: string;
    time: string;
    guests: number;
    name: string;
    phone: string;
    tableId?: number;
  }) {
    await this.goToOrders();
    await this.page.getByTestId('new-reservation-btn').click();
    await this.page.getByTestId('reservation-modal').waitFor({ timeout: 5000 });

    await this.page.getByTestId('reservation-name-input').fill(data.name);
    await this.page.getByTestId('reservation-phone-input').fill(data.phone);
    await this.page.getByTestId('reservation-guests-input').fill(String(data.guests));

    await this.page.getByTestId('save-reservation-btn').click();
  }

  // --- Утилиты ---
  async waitForToast(text: string) {
    await this.page.locator(`text=${text}`).waitFor({ timeout: 5000 });
  }

  async dismissToast() {
    await this.page.getByTestId('toast-close').click();
  }

  async waitForLoading() {
    // Ждём пока исчезнет спиннер загрузки
    await this.page.locator('.loading-spinner, [data-testid="loading"]').waitFor({ state: 'hidden', timeout: 10000 }).catch(() => {});
    await this.page.waitForTimeout(500);
  }

  async takeScreenshot(name: string) {
    await this.page.screenshot({ path: `test-results/screenshots/${name}.png` });
  }
}

// ============================================
// KITCHEN PAGE OBJECT
// ============================================

export class KitchenPage {
  constructor(private page: Page) {}

  async goto() {
    await this.page.goto('/kitchen');
  }

  async linkDevice(code: string) {
    for (let i = 0; i < 6; i++) {
      await this.page.getByTestId(`code-digit-${i}`).fill(code[i]);
    }
    await this.page.getByTestId('kitchen-main').waitFor({ timeout: 10000 });
  }

  async selectStation(stationSlug: string) {
    await this.page.getByTestId(`station-${stationSlug}`).click();
  }

  async getOrdersCount(): Promise<number> {
    return await this.page.locator('[data-testid="kitchen-order"]').count();
  }

  async getOrderByNumber(orderNumber: string) {
    return this.page.getByTestId(`order-${orderNumber}`);
  }

  async markItemReady(orderNumber: string, itemName: string) {
    const order = this.getOrderByNumber(orderNumber);
    await order.locator(`[data-testid="item-${itemName}"] [data-testid="mark-ready"]`).click();
  }

  async markOrderReady(orderNumber: string) {
    await this.page.getByTestId(`order-${orderNumber}`).locator('[data-testid="order-ready-btn"]').click();
  }

  async recallItem(orderNumber: string, itemName: string) {
    const order = this.getOrderByNumber(orderNumber);
    await order.locator(`[data-testid="item-${itemName}"] [data-testid="recall-btn"]`).click();
  }
}

// ============================================
// DELIVERY PAGE OBJECT
// ============================================

export class DeliveryPage {
  constructor(private page: Page) {}

  async goto() {
    await this.page.goto('/pos');
    await this.page.getByTestId('tab-delivery').click();
    await this.page.getByTestId('delivery-tab').waitFor({ timeout: 5000 });
  }

  async switchViewMode(mode: 'table' | 'grid' | 'kanban' | 'map') {
    await this.page.getByTestId(`view-mode-${mode}`).click();
  }

  async filterByStatus(status: string) {
    await this.page.getByTestId(`status-filter-${status}`).click();
  }

  async getOrdersCount(): Promise<number> {
    return await this.page.locator('[data-testid="delivery-order-card"]').count();
  }

  async clickOrder(orderNumber: string) {
    await this.page.getByTestId(`delivery-order-${orderNumber}`).click();
  }

  async assignCourier(orderNumber: string, courierName: string) {
    await this.clickOrder(orderNumber);
    await this.page.getByTestId('assign-courier-btn').click();
    await this.page.getByTestId(`courier-${courierName}`).click();
  }

  async updateOrderStatus(orderNumber: string, status: string) {
    await this.clickOrder(orderNumber);
    await this.page.getByTestId(`status-${status}`).click();
  }

  async getOrderStatus(orderNumber: string): Promise<string> {
    const order = this.page.getByTestId(`delivery-order-${orderNumber}`);
    return await order.getAttribute('data-status') || 'unknown';
  }
}

// ============================================
// EXTENDED TEST FIXTURE
// ============================================

type TestFixtures = {
  posPage: PosPage;
  kitchenPage: KitchenPage;
  deliveryPage: DeliveryPage;
  testUser: TestUser;
};

export const test = base.extend<TestFixtures>({
  posPage: async ({ page }, use) => {
    const posPage = new PosPage(page);
    await use(posPage);
  },

  kitchenPage: async ({ page }, use) => {
    const kitchenPage = new KitchenPage(page);
    await use(kitchenPage);
  },

  deliveryPage: async ({ page }, use) => {
    const deliveryPage = new DeliveryPage(page);
    await use(deliveryPage);
  },

  testUser: async ({}, use) => {
    await use(TEST_USERS.admin);
  },
});

export { expect };
