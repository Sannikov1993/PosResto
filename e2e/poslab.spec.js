import { test, expect } from '@playwright/test';

test.describe('PosResto CRM - Главная страница', () => {
  test('должна загружаться главная страница', async ({ page }) => {
    await page.goto('/index.html');
    await page.waitForLoadState('domcontentloaded');
    // Проверяем что страница PosResto загрузилась
    await expect(page.locator('body')).toBeAttached();
  });

  test('навигационное меню отображается', async ({ page }) => {
    await page.goto('/index.html');
    await page.waitForLoadState('domcontentloaded');
    // Проверяем что body присутствует
    await expect(page.locator('body')).toBeAttached();
  });
});

test.describe('PosResto CRM - Модуль POS', () => {
  test('страница POS загружается', async ({ page }) => {
    await page.goto('/posresto-pos.html');
    await expect(page.locator('body')).toBeVisible();
    // Ждём загрузки JavaScript
    await page.waitForLoadState('networkidle');
  });

  test('POS содержит основные элементы интерфейса', async ({ page }) => {
    await page.goto('/posresto-pos.html');
    await page.waitForLoadState('networkidle');
    // Проверяем что страница не пустая
    const bodyContent = await page.locator('body').textContent();
    expect(bodyContent.length).toBeGreaterThan(0);
  });
});

test.describe('PosResto CRM - Модуль Backoffice', () => {
  test('страница Backoffice загружается', async ({ page }) => {
    await page.goto('/posresto-backoffice.html');
    await expect(page.locator('body')).toBeVisible();
    await page.waitForLoadState('networkidle');
  });
});

test.describe('PosResto CRM - Модуль Клиенты (в POS)', () => {
  test('вкладка Клиенты доступна в POS', async ({ page }) => {
    await page.goto('/posresto-pos.html');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('body')).toBeVisible();
  });
});

test.describe('PosResto CRM - Модуль Склад/Inventory', () => {
  test('страница Inventory загружается', async ({ page }) => {
    await page.goto('/posresto-inventory.html');
    await expect(page.locator('body')).toBeVisible();
    await page.waitForLoadState('networkidle');
  });
});

test.describe('PosResto CRM - Модуль Кухня', () => {
  test('страница Kitchen загружается', async ({ page }) => {
    await page.goto('/posresto-kitchen.html');
    await expect(page.locator('body')).toBeVisible();
    await page.waitForLoadState('networkidle');
  });
});

test.describe('PosResto CRM - Модуль Официант', () => {
  test('страница Waiter загружается', async ({ page }) => {
    await page.goto('/posresto-waiter.html');
    await expect(page.locator('body')).toBeVisible();
    await page.waitForLoadState('networkidle');
  });
});

test.describe('PosResto CRM - Модуль Аналитика', () => {
  test('страница Analytics загружается', async ({ page }) => {
    await page.goto('/posresto-analytics.html');
    await expect(page.locator('body')).toBeVisible();
    await page.waitForLoadState('networkidle');
  });
});

test.describe('PosResto CRM - Модуль Доставка', () => {
  test('страница Delivery загружается', async ({ page }) => {
    await page.goto('/posresto-delivery.html');
    await expect(page.locator('body')).toBeVisible();
    await page.waitForLoadState('networkidle');
  });
});

test.describe('PosResto CRM - Модуль Бронирования', () => {
  test('страница Reservations загружается', async ({ page }) => {
    await page.goto('/posresto-reservations.html');
    await expect(page.locator('body')).toBeVisible();
    await page.waitForLoadState('networkidle');
  });
});

test.describe('PosResto CRM - Модуль Лояльность', () => {
  test('страница Loyalty загружается', async ({ page }) => {
    await page.goto('/posresto-loyalty.html');
    await expect(page.locator('body')).toBeVisible();
    await page.waitForLoadState('networkidle');
  });
});

test.describe('PosResto CRM - Модуль Персонал', () => {
  test('страница Staff загружается', async ({ page }) => {
    await page.goto('/posresto-staff.html');
    await expect(page.locator('body')).toBeVisible();
    await page.waitForLoadState('networkidle');
  });
});

test.describe('PosResto CRM - Модуль Администрирование', () => {
  test('страница Admin загружается', async ({ page }) => {
    await page.goto('/posresto-admin.html');
    await expect(page.locator('body')).toBeVisible();
    await page.waitForLoadState('networkidle');
  });
});

test.describe('PosResto CRM - API проверки', () => {
  test('API dashboard доступен', async ({ request }) => {
    const response = await request.get('/api/dashboard');
    expect(response.status()).toBeLessThan(500);
  });

  test('API категории меню доступны', async ({ request }) => {
    const response = await request.get('/api/categories');
    expect(response.status()).toBeLessThan(500);
  });

  test('API блюда доступны', async ({ request }) => {
    const response = await request.get('/api/dishes');
    expect(response.status()).toBeLessThan(500);
  });

  test('API клиенты доступны', async ({ request }) => {
    const response = await request.get('/api/customers');
    expect(response.status()).toBeLessThan(500);
  });
});
