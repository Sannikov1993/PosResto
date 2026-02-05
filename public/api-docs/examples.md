# MenuLab API - Примеры кода

## Аутентификация

### cURL с API Key

```bash
curl -X GET "https://api.menulab.ru/api/v1/menu/dishes" \
  -H "X-API-Key: ml_your_api_key" \
  -H "X-API-Secret: your_api_secret" \
  -H "Content-Type: application/json"
```

### cURL с Bearer Token

```bash
curl -X GET "https://api.menulab.ru/api/v1/menu/dishes" \
  -H "Authorization: Bearer your_access_token" \
  -H "Content-Type: application/json"
```

---

## PHP (Laravel/Guzzle)

### Инициализация клиента

```php
<?php

use GuzzleHttp\Client;

class MenuLabApi
{
    private Client $client;
    private string $apiKey;
    private string $apiSecret;

    public function __construct(string $apiKey, string $apiSecret)
    {
        $this->apiKey = $apiKey;
        $this->apiSecret = $apiSecret;
        $this->client = new Client([
            'base_uri' => 'https://api.menulab.ru/api/v1/',
            'timeout' => 30,
        ]);
    }

    private function headers(): array
    {
        return [
            'X-API-Key' => $this->apiKey,
            'X-API-Secret' => $this->apiSecret,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
    }

    public function get(string $endpoint, array $query = []): array
    {
        $response = $this->client->get($endpoint, [
            'headers' => $this->headers(),
            'query' => $query,
        ]);

        return json_decode($response->getBody(), true);
    }

    public function post(string $endpoint, array $data = [], ?string $idempotencyKey = null): array
    {
        $headers = $this->headers();
        if ($idempotencyKey) {
            $headers['X-Idempotency-Key'] = $idempotencyKey;
        }

        $response = $this->client->post($endpoint, [
            'headers' => $headers,
            'json' => $data,
        ]);

        return json_decode($response->getBody(), true);
    }
}
```

### Получение меню

```php
$api = new MenuLabApi('ml_xxx', 'secret_xxx');

// Получить все блюда
$dishes = $api->get('menu/dishes');

// Получить блюда категории
$dishes = $api->get('menu/dishes', ['category_id' => 1]);

// Получить полное меню с категориями
$menu = $api->get('menu/full');
```

### Создание заказа

```php
$api = new MenuLabApi('ml_xxx', 'secret_xxx');

$order = $api->post('orders', [
    'type' => 'delivery',
    'customer_id' => 123,
    'delivery_address' => 'ул. Пушкина, д. 10, кв. 5',
    'items' => [
        [
            'dish_id' => 45,
            'quantity' => 2,
            'modifiers' => [
                ['modifier_id' => 12, 'quantity' => 1]
            ],
            'comment' => 'Без лука'
        ],
        [
            'dish_id' => 67,
            'quantity' => 1
        ]
    ],
    'comment' => 'Позвонить за 5 минут'
], idempotencyKey: 'order-' . uniqid());

echo "Заказ #{$order['data']['order_number']} создан!";
```

---

## JavaScript (Fetch API)

### Базовый клиент

```javascript
class MenuLabApi {
  constructor(apiKey, apiSecret) {
    this.baseUrl = 'https://api.menulab.ru/api/v1';
    this.apiKey = apiKey;
    this.apiSecret = apiSecret;
  }

  async request(method, endpoint, data = null, idempotencyKey = null) {
    const headers = {
      'X-API-Key': this.apiKey,
      'X-API-Secret': this.apiSecret,
      'Content-Type': 'application/json',
    };

    if (idempotencyKey) {
      headers['X-Idempotency-Key'] = idempotencyKey;
    }

    const config = {
      method,
      headers,
    };

    if (data && ['POST', 'PATCH', 'PUT'].includes(method)) {
      config.body = JSON.stringify(data);
    }

    const response = await fetch(`${this.baseUrl}/${endpoint}`, config);
    const json = await response.json();

    if (!response.ok) {
      throw new Error(json.error?.message || 'API Error');
    }

    return json;
  }

  get(endpoint, params = {}) {
    const query = new URLSearchParams(params).toString();
    const url = query ? `${endpoint}?${query}` : endpoint;
    return this.request('GET', url);
  }

  post(endpoint, data, idempotencyKey = null) {
    return this.request('POST', endpoint, data, idempotencyKey);
  }

  patch(endpoint, data) {
    return this.request('PATCH', endpoint, data);
  }
}
```

### Получение меню

```javascript
const api = new MenuLabApi('ml_xxx', 'secret_xxx');

// Получить все блюда
const dishes = await api.get('menu/dishes');
console.log(dishes.data);

// С фильтрацией
const available = await api.get('menu/dishes', {
  is_available: true,
  category_id: 5
});
```

### Создание заказа на доставку

```javascript
const api = new MenuLabApi('ml_xxx', 'secret_xxx');

const order = await api.post('orders', {
  type: 'delivery',
  customer_id: 123,
  delivery_address: 'ул. Ленина, д. 15',
  items: [
    { dish_id: 45, quantity: 2 },
    { dish_id: 67, quantity: 1, comment: 'Без соуса' }
  ]
}, `order-${Date.now()}`);

console.log(`Заказ #${order.data.order_number} создан`);
```

---

## Python (requests)

### Базовый клиент

```python
import requests
import uuid

class MenuLabApi:
    def __init__(self, api_key: str, api_secret: str):
        self.base_url = 'https://api.menulab.ru/api/v1'
        self.session = requests.Session()
        self.session.headers.update({
            'X-API-Key': api_key,
            'X-API-Secret': api_secret,
            'Content-Type': 'application/json',
        })

    def get(self, endpoint: str, params: dict = None) -> dict:
        response = self.session.get(f'{self.base_url}/{endpoint}', params=params)
        response.raise_for_status()
        return response.json()

    def post(self, endpoint: str, data: dict = None, idempotency_key: str = None) -> dict:
        headers = {}
        if idempotency_key:
            headers['X-Idempotency-Key'] = idempotency_key

        response = self.session.post(
            f'{self.base_url}/{endpoint}',
            json=data,
            headers=headers
        )
        response.raise_for_status()
        return response.json()


# Использование
api = MenuLabApi('ml_xxx', 'secret_xxx')

# Получить меню
menu = api.get('menu/full')
for category in menu['data']:
    print(f"📁 {category['name']}")
    for dish in category.get('dishes', []):
        print(f"  - {dish['name']}: {dish['price']} ₽")

# Создать заказ
order = api.post('orders', {
    'type': 'pickup',
    'items': [
        {'dish_id': 1, 'quantity': 2},
        {'dish_id': 5, 'quantity': 1}
    ]
}, idempotency_key=f'order-{uuid.uuid4()}')

print(f"Заказ #{order['data']['order_number']} создан!")
```

---

## Webhook обработка

### PHP

```php
<?php

// Получаем payload
$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_MENULAB_SIGNATURE'] ?? '';
$event = $_SERVER['HTTP_X_MENULAB_EVENT'] ?? '';

// Проверяем подпись
$expectedSignature = hash_hmac('sha256', $payload, $webhookSecret);

if (!hash_equals($expectedSignature, $signature)) {
    http_response_code(401);
    exit('Invalid signature');
}

// Обрабатываем событие
$data = json_decode($payload, true);

switch ($event) {
    case 'order.created':
        handleNewOrder($data['data']);
        break;

    case 'order.completed':
        handleOrderCompleted($data['data']);
        break;

    case 'kitchen.item_ready':
        notifyWaiter($data['data']);
        break;
}

http_response_code(200);
echo json_encode(['received' => true]);
```

### Node.js (Express)

```javascript
const express = require('express');
const crypto = require('crypto');

const app = express();
app.use(express.json());

app.post('/webhooks/menulab', (req, res) => {
  const signature = req.headers['x-menulab-signature'];
  const event = req.headers['x-menulab-event'];

  // Проверка подписи
  const expectedSignature = crypto
    .createHmac('sha256', process.env.WEBHOOK_SECRET)
    .update(JSON.stringify(req.body))
    .digest('hex');

  if (signature !== expectedSignature) {
    return res.status(401).json({ error: 'Invalid signature' });
  }

  // Обработка события
  const { data } = req.body;

  switch (event) {
    case 'order.created':
      console.log(`Новый заказ #${data.order_number}`);
      // Отправить в Telegram, обновить дашборд и т.д.
      break;

    case 'order.paid':
      console.log(`Заказ #${data.order_number} оплачен`);
      break;
  }

  res.json({ received: true });
});

app.listen(3000);
```

---

## Идемпотентность

Используйте заголовок `X-Idempotency-Key` для безопасных повторных запросов:

```javascript
// Создание заказа с идемпотентностью
const idempotencyKey = `order-${customerId}-${Date.now()}`;

try {
  const order = await api.post('orders', orderData, idempotencyKey);
} catch (error) {
  // При сетевой ошибке можно безопасно повторить запрос
  // с тем же idempotencyKey - заказ не задублируется
  const order = await api.post('orders', orderData, idempotencyKey);
}
```

---

## Обработка ошибок

```javascript
try {
  const order = await api.post('orders', orderData);
} catch (error) {
  const response = error.response?.data;

  switch (response?.error?.code) {
    case 'VALIDATION_ERROR':
      console.error('Ошибка валидации:', response.error.errors);
      break;

    case 'DISH_UNAVAILABLE':
      console.error('Блюдо недоступно');
      break;

    case 'RATE_LIMIT_EXCEEDED':
      console.error('Превышен лимит запросов, повторите позже');
      break;

    default:
      console.error('Ошибка API:', response?.error?.message);
  }
}
```

---

## Rate Limiting

Отслеживайте заголовки лимитов:

```javascript
const response = await fetch(`${baseUrl}/orders`, config);

const limit = response.headers.get('X-RateLimit-Limit');
const remaining = response.headers.get('X-RateLimit-Remaining');
const reset = response.headers.get('X-RateLimit-Reset');

console.log(`Осталось ${remaining}/${limit} запросов`);
console.log(`Сброс через ${reset - Date.now() / 1000} сек`);

if (remaining < 10) {
  console.warn('Приближаемся к лимиту!');
}
```
