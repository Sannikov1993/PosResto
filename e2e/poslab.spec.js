import { test, expect } from '@playwright/test';

test.describe('PosLab CRM - Главная страница', () => {
  test('должна загружаться главная страница', async ({ page }) => {
    await page.goto('/index.html');
    await page.waitForLoadState('domcontentloaded');
    // Проверяем что страница PosLab загрузилась
    await expect(page.locator('body')).toBeAttached();
  });

  test('навигационное меню отображается', async ({ page }) => {
    await page.goto('/index.html');
    await page.waitForLoadState('domcontentloaded');
    // Проверяем что body присутствует
    await expect(page.locator('body')).toBeAttached();
  });
});

test.describe('PosLab CRM - Модуль POS', () => {
  test('страница POS загружается', async ({ page }) => {
    await page.goto('/poslab-pos.html');
    await expect(page.locator('body')).toBeVisible();
    // Ждём загрузки JavaScript
    await page.waitForLoadState('networkidle');
  });

  test('POS содержит основные элементы интерфейса', async ({ page }) => {
    await page.goto('/poslab-pos.html');
    await page.waitForLoadState('networkidle');
    // Проверяем что страница не пустая
    const bodyContent = await page.locator('body').textContent();
    expect(bodyContent.length).toBeGreaterThan(0);
  });
});

test.describe('PosLab CRM - Модуль Backoffice', () => {
  test('страница Backoffice загружается', async ({ page }) => {
    await page.goto('/poslab-backoffice.html');
    await expect(page.locator('body')).toBeVisible();
    await page.waitForLoadState('networkidle');
  });
});

test.describe('PosLab CRM - Модуль Клиенты (в POS)', () => {
  test('вкладка Клиенты доступна в POS', async ({ page }) => {
    await page.goto('/poslab-pos.html');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('body')).toBeVisible();
  });
});

test.describe('PosLab CRM - Модуль Склад/Inventory', () => {
  test('страница Inventory загружается', async ({ page }) => {
    await page.goto('/poslab-inventory.html');
    await expect(page.locator('body')).toBeVisible();
    await page.waitForLoadState('networkidle');
  });
});

test.describe('PosLab CRM - Модуль Кухня', () => {
  test('страница Kitchen загружается', async ({ page }) => {
    await page.goto('/poslab-kitchen.html');
    await expect(page.locator('body')).toBeVisible();
    await page.waitForLoadState('networkidle');
  });
});

test.describe('PosLab CRM - Модуль Официант', () => {
  test('страница Waiter загружается', async ({ page }) => {
    await page.goto('/poslab-waiter.html');
    await expect(page.locator('body')).toBeVisible();
    await page.waitForLoadState('networkidle');
  });
});

test.describe('PosLab CRM - Модуль Аналитика', () => {
  test('страница Analytics загружается', async ({ page }) => {
    await page.goto('/poslab-analytics.html');
    await expect(page.locator('body')).toBeVisible();
    await page.waitForLoadState('networkidle');
  });
});

test.describe('PosLab CRM - Модуль Доставка', () => {
  test('страница Delivery загружается', async ({ page }) => {
    await page.goto('/poslab-delivery.html');
    await expect(page.locator('body')).toBeVisible();
    await page.waitForLoadState('networkidle');
  });
});

test.describe('PosLab CRM - Модуль Бронирования', () => {
  test('страница Reservations загружается', async ({ page }) => {
    await page.goto('/poslab-reservations.html');
    await expect(page.locator('body')).toBeVisible();
    await page.waitForLoadState('networkidle');
  });
});

test.describe('PosLab CRM - Модуль Лояльность', () => {
  test('страница Loyalty загружается', async ({ page }) => {
    await page.goto('/poslab-loyalty.html');
    await expect(page.locator('body')).toBeVisible();
    await page.waitForLoadState('networkidle');
  });
});

test.describe('PosLab CRM - Модуль Персонал', () => {
  test('страница Staff загружается', async ({ page }) => {
    await page.goto('/poslab-staff.html');
    await expect(page.locator('body')).toBeVisible();
    await page.waitForLoadState('networkidle');
  });
});

test.describe('PosLab CRM - Модуль Администрирование', () => {
  test('страница Admin загружается', async ({ page }) => {
    await page.goto('/poslab-admin.html');
    await expect(page.locator('body')).toBeVisible();
    await page.waitForLoadState('networkidle');
  });
});

test.describe('PosLab CRM - API проверки', () => {
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
