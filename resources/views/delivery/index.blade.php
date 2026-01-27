<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Доставка — PosLab</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        dark: {
                            700: '#374151',
                            800: '#1f2937',
                            900: '#111827',
                            950: '#0a0a0f',
                        },
                        accent: '#f97316',
                    }
                }
            }
        }
    </script>
    <style>
        body { background: #0a0a0f; font-family: 'Inter', system-ui, sans-serif; }

        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #374151; border-radius: 3px; }

        .status-new { background: #3b82f6; }
        .status-cooking { background: #f59e0b; }
        .status-ready { background: #10b981; }
        .status-delivering { background: #8b5cf6; }
        .status-completed { background: #6b7280; }
        .status-cancelled { background: #ef4444; }

        .order-card { transition: all 0.15s ease; }
        .order-card:hover { background: rgba(255,255,255,0.05); }
        .order-card.active { background: rgba(249, 115, 22, 0.1); border-color: rgba(249, 115, 22, 0.3); }

        .btn { transition: all 0.15s ease; cursor: pointer; }
        .btn:active { transform: scale(0.98); }
    </style>
</head>
<body class="h-screen overflow-hidden text-white">
    <div class="flex h-full">

        {{-- ========== БОКОВАЯ ПАНЕЛЬ ========== --}}
        <div class="w-16 bg-gray-900 flex flex-col items-center py-4 border-r border-gray-800">
            <a href="/" class="w-10 h-10 bg-accent rounded-xl flex items-center justify-center text-white font-bold mb-6">P</a>

            <nav class="flex-1 flex flex-col gap-2">
                <a href="/pos-vue" class="w-10 h-10 rounded-xl flex items-center justify-center text-gray-400 hover:text-white hover:bg-gray-800" title="POS">
                    🏠
                </a>
                <a href="/delivery" class="w-10 h-10 rounded-xl flex items-center justify-center bg-accent text-white" title="Доставка">
                    🛵
                </a>
                <a href="/kitchen-vue" class="w-10 h-10 rounded-xl flex items-center justify-center text-gray-400 hover:text-white hover:bg-gray-800" title="Кухня">
                    👨‍🍳
                </a>
                <a href="/backoffice-vue" class="w-10 h-10 rounded-xl flex items-center justify-center text-gray-400 hover:text-white hover:bg-gray-800" title="Бэк-офис">
                    📊
                </a>
            </nav>

            <div class="flex flex-col gap-2">
                <button class="w-10 h-10 rounded-xl flex items-center justify-center text-gray-400 hover:text-white hover:bg-gray-800" title="Настройки">
                    ⚙️
                </button>
            </div>
        </div>

        {{-- ========== ОСНОВНАЯ ОБЛАСТЬ ========== --}}
        <div class="flex-1 flex flex-col">

            {{-- Верхняя панель --}}
            <header class="h-14 bg-gray-900 flex items-center px-4 gap-3 border-b border-gray-800">
                <button onclick="openNewOrderModal()" class="flex items-center gap-2 px-4 py-2 bg-accent text-white rounded-lg text-sm font-medium hover:bg-orange-600 btn">
                    <span>+</span> Новый заказ
                </button>

                {{-- Табы статусов --}}
                <div class="flex items-center gap-1 bg-gray-800 rounded-lg p-1">
                    <a href="{{ route('delivery.index') }}"
                       class="px-4 py-1.5 {{ !request('status') ? 'bg-gray-700 text-white' : 'text-gray-400 hover:text-white' }} rounded-md text-sm font-medium">
                        Все
                    </a>
                    <a href="{{ route('delivery.index', ['status' => 'new']) }}"
                       class="px-4 py-1.5 {{ request('status') === 'new' ? 'bg-gray-700 text-white' : 'text-gray-400 hover:text-white' }} rounded-md text-sm flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full status-new"></span> Новые
                        @if(($statusCounts['new'] ?? 0) > 0)
                            <span class="bg-blue-500 text-white text-xs px-1.5 rounded">{{ $statusCounts['new'] }}</span>
                        @endif
                    </a>
                    <a href="{{ route('delivery.index', ['status' => 'cooking']) }}"
                       class="px-4 py-1.5 {{ request('status') === 'cooking' ? 'bg-gray-700 text-white' : 'text-gray-400 hover:text-white' }} rounded-md text-sm flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full status-cooking"></span> Готовятся
                        @if(($statusCounts['cooking'] ?? 0) > 0)
                            <span class="bg-yellow-500 text-black text-xs px-1.5 rounded">{{ $statusCounts['cooking'] }}</span>
                        @endif
                    </a>
                    <a href="{{ route('delivery.index', ['status' => 'ready']) }}"
                       class="px-4 py-1.5 {{ request('status') === 'ready' ? 'bg-gray-700 text-white' : 'text-gray-400 hover:text-white' }} rounded-md text-sm flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full status-ready"></span> Готовы
                        @if(($statusCounts['ready'] ?? 0) > 0)
                            <span class="bg-green-500 text-white text-xs px-1.5 rounded">{{ $statusCounts['ready'] }}</span>
                        @endif
                    </a>
                    <a href="{{ route('delivery.index', ['status' => 'delivering']) }}"
                       class="px-4 py-1.5 {{ request('status') === 'delivering' ? 'bg-gray-700 text-white' : 'text-gray-400 hover:text-white' }} rounded-md text-sm flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full status-delivering"></span> В пути
                        @if(($statusCounts['delivering'] ?? 0) > 0)
                            <span class="bg-purple-500 text-white text-xs px-1.5 rounded">{{ $statusCounts['delivering'] }}</span>
                        @endif
                    </a>
                </div>

                <div class="flex-1"></div>

                <div class="flex items-center gap-2">
                    <div class="relative">
                        <input type="text"
                               id="searchInput"
                               placeholder="Поиск по номеру, телефону..."
                               class="w-64 bg-gray-800 border border-gray-700 rounded-lg px-4 py-2 text-white text-sm placeholder-gray-500 focus:border-accent focus:outline-none">
                        <span class="absolute right-3 top-2.5 text-gray-500">🔍</span>
                    </div>
                </div>
            </header>

            {{-- Контент --}}
            <div class="flex-1 flex overflow-hidden">

                {{-- Список заказов --}}
                <div class="w-96 flex flex-col border-r border-gray-800 bg-gray-900/50">
                    <div class="p-3 border-b border-gray-800 flex items-center justify-between">
                        <span class="text-gray-400 text-sm">Заказы на доставку</span>
                        <span class="text-white text-sm font-medium">{{ $orders->count() }} заказов</span>
                    </div>

                    <div class="flex-1 overflow-y-auto p-2 space-y-2" id="ordersList">
                        @forelse($orders as $order)
                            <div class="order-card p-3 bg-gray-800/50 rounded-xl border border-gray-700 cursor-pointer transition-all {{ $selectedOrder?->id === $order->id ? 'active' : '' }}"
                                 onclick="selectOrder({{ $order->id }})"
                                 data-order-id="{{ $order->id }}">
                                <div class="flex items-start justify-between mb-2">
                                    <div>
                                        <span class="text-white font-semibold">{{ $order->order_number }}</span>
                                        <span class="ml-2 px-2 py-0.5 rounded text-xs font-medium status-{{ $order->status }} {{ in_array($order->status, ['cooking']) ? 'text-black' : 'text-white' }}">
                                            {{ $order->status_label }}
                                        </span>
                                        @if($order->type === 'pickup')
                                            <span class="ml-1 px-2 py-0.5 rounded text-xs font-medium bg-cyan-500 text-white">Самовывоз</span>
                                        @endif
                                    </div>
                                    <span class="text-accent font-bold">{{ number_format($order->total, 0, '', ' ') }} ₽</span>
                                </div>
                                <p class="text-gray-400 text-sm mb-1">👤 {{ $order->customer_name }}</p>
                                @if($order->type === 'delivery')
                                    <p class="text-gray-500 text-xs mb-2">📍 {{ $order->full_address }}</p>
                                @else
                                    <p class="text-gray-500 text-xs mb-2">📱 {{ $order->customer_phone }}</p>
                                @endif
                                <div class="flex items-center justify-between">
                                    <span class="text-gray-500 text-xs">
                                        🕐 {{ $order->created_at->format('H:i') }}
                                        @if($order->deliver_at)
                                            • К {{ $order->deliver_at->format('H:i') }}
                                        @endif
                                    </span>
                                    @if($order->time_remaining !== null)
                                        <span class="{{ $order->time_remaining < 15 ? 'text-red-500' : 'text-gray-500' }} text-xs font-medium">
                                            ⏱ {{ $order->time_remaining }} мин
                                        </span>
                                    @endif
                                    @if($order->courier)
                                        <span class="text-purple-500 text-xs">🛵 {{ $order->courier->name }}</span>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div class="p-8 text-center">
                                <div class="text-4xl mb-2">📭</div>
                                <p class="text-gray-500">Нет заказов</p>
                            </div>
                        @endforelse
                    </div>
                </div>

                {{-- Детали заказа --}}
                <div class="flex-1 flex flex-col bg-gray-900/30" id="orderDetails">
                    @if($selectedOrder)
                        @include('delivery.partials.order-details', ['order' => $selectedOrder, 'couriers' => $couriers])
                    @else
                        <div class="flex-1 flex items-center justify-center">
                            <div class="text-center">
                                <div class="text-6xl mb-4">📦</div>
                                <p class="text-gray-500 text-lg">Выберите заказ из списка</p>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- ========== МОДАЛКА: НОВЫЙ ЗАКАЗ ========== --}}
    @include('delivery.partials.new-order-modal', ['categories' => $categories])

    <script>
        // ==================== СОСТОЯНИЕ ====================
        const state = {
            selectedOrderId: {{ $selectedOrder?->id ?? 'null' }},
            cart: [],
            products: [],
            orderType: 'delivery',
            deliveryTime: 'asap',
            paymentMethod: 'cash',
        };

        // ==================== ВЫБОР ЗАКАЗА ====================
        function selectOrder(orderId) {
            document.querySelectorAll('.order-card').forEach(card => {
                card.classList.remove('active');
                if (card.dataset.orderId == orderId) {
                    card.classList.add('active');
                }
            });

            state.selectedOrderId = orderId;
            window.location.href = `/delivery?order_id=${orderId}`;
        }

        // ==================== ИЗМЕНЕНИЕ СТАТУСА ====================
        async function updateOrderStatus(orderId, status) {
            try {
                const response = await fetch(`/delivery/orders/${orderId}/status`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ status })
                });

                const result = await response.json();

                if (result.success) {
                    showNotification('Статус обновлён', 'success');
                    location.reload();
                } else {
                    showNotification(result.message, 'error');
                }
            } catch (error) {
                console.error('Ошибка:', error);
                showNotification('Ошибка обновления статуса', 'error');
            }
        }

        // ==================== НАЗНАЧЕНИЕ КУРЬЕРА ====================
        async function assignCourier(orderId, courierId) {
            try {
                const response = await fetch(`/delivery/orders/${orderId}/courier`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ courier_id: courierId })
                });

                const result = await response.json();

                if (result.success) {
                    showNotification('Курьер назначен', 'success');
                    location.reload();
                } else {
                    showNotification(result.message, 'error');
                }
            } catch (error) {
                console.error('Ошибка:', error);
                showNotification('Ошибка назначения курьера', 'error');
            }
        }

        // ==================== МОДАЛКА НОВОГО ЗАКАЗА ====================
        function openNewOrderModal() {
            document.getElementById('newOrderModal').classList.remove('hidden');
            loadProducts();
        }

        function closeNewOrderModal() {
            document.getElementById('newOrderModal').classList.add('hidden');
            clearCart();
        }

        // ==================== ЗАГРУЗКА ТОВАРОВ ====================
        async function loadProducts(categoryId = null, search = null) {
            try {
                let url = '/delivery/products?';
                if (categoryId) url += `category_id=${categoryId}&`;
                if (search) url += `search=${encodeURIComponent(search)}&`;

                const response = await fetch(url);
                state.products = await response.json();
                renderProducts();
            } catch (error) {
                console.error('Ошибка загрузки товаров:', error);
            }
        }

        function renderProducts() {
            const container = document.getElementById('productsGrid');
            if (!container) return;

            container.innerHTML = state.products.map(product => `
                <div class="bg-gray-800 rounded-xl p-4 hover:bg-gray-700 cursor-pointer group border border-transparent hover:border-accent transition-all"
                     onclick="addToCart(${product.id})">
                    <div class="flex justify-center mb-3">
                        <span class="text-4xl group-hover:scale-110 transition-transform">${product.category?.icon || '🍽'}</span>
                    </div>
                    <h4 class="text-white font-medium text-center mb-1 text-sm">${product.name}</h4>
                    <div class="flex items-center justify-between mt-2">
                        <span class="text-accent font-bold">${product.price} ₽</span>
                        <button class="w-8 h-8 bg-accent text-white rounded-lg text-lg hover:bg-orange-600 btn">+</button>
                    </div>
                </div>
            `).join('');
        }

        // ==================== КОРЗИНА ====================
        function addToCart(productId) {
            const product = state.products.find(p => p.id === productId);
            if (!product) return;

            const existing = state.cart.find(i => i.product_id === productId);
            if (existing) {
                existing.quantity++;
            } else {
                state.cart.push({
                    product_id: product.id,
                    name: product.name,
                    price: product.price,
                    quantity: 1,
                    icon: product.category?.icon || '🍽'
                });
            }

            renderCart();
            updateTotals();
        }

        function updateCartQuantity(productId, delta) {
            const item = state.cart.find(i => i.product_id === productId);
            if (item) {
                item.quantity += delta;
                if (item.quantity <= 0) {
                    state.cart = state.cart.filter(i => i.product_id !== productId);
                }
                renderCart();
                updateTotals();
            }
        }

        function renderCart() {
            const container = document.getElementById('deliveryCartItems');
            const empty = document.getElementById('emptyDeliveryCart');
            if (!container) return;

            if (state.cart.length === 0) {
                container.classList.add('hidden');
                if (empty) empty.classList.remove('hidden');
                return;
            }

            if (empty) empty.classList.add('hidden');
            container.classList.remove('hidden');

            container.innerHTML = state.cart.map(item => `
                <div class="flex items-center gap-3 p-3 bg-gray-800 rounded-lg">
                    <span class="text-xl">${item.icon}</span>
                    <div class="flex-1">
                        <p class="text-white text-sm">${item.name}</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button onclick="updateCartQuantity(${item.product_id}, -1)" class="w-6 h-6 bg-gray-700 text-white rounded text-sm hover:bg-gray-600 btn">−</button>
                        <span class="text-white text-sm w-4 text-center">${item.quantity}</span>
                        <button onclick="updateCartQuantity(${item.product_id}, 1)" class="w-6 h-6 bg-gray-700 text-white rounded text-sm hover:bg-gray-600 btn">+</button>
                    </div>
                    <span class="text-accent font-medium">${item.price * item.quantity} ₽</span>
                </div>
            `).join('');
        }

        function updateTotals() {
            const subtotal = state.cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
            const deliveryCost = state.orderType === 'delivery' ? 150 : 0;
            const total = subtotal + deliveryCost;

            const subtotalEl = document.getElementById('subtotalAmount');
            const deliveryEl = document.getElementById('deliveryCostAmount');
            const totalEl = document.getElementById('totalAmount');
            const btnEl = document.getElementById('submitBtnAmount');

            if (subtotalEl) subtotalEl.textContent = subtotal + ' ₽';
            if (deliveryEl) deliveryEl.textContent = state.orderType === 'delivery' ? deliveryCost + ' ₽' : 'бесплатно';
            if (totalEl) totalEl.textContent = total + ' ₽';
            if (btnEl) btnEl.textContent = total + ' ₽';

            const submitBtn = document.getElementById('submitOrderBtn');
            if (submitBtn) submitBtn.disabled = state.cart.length === 0;
        }

        function clearCart() {
            state.cart = [];
            renderCart();
            updateTotals();
        }

        // ==================== ОФОРМЛЕНИЕ ЗАКАЗА ====================
        async function submitOrder() {
            const form = document.getElementById('newOrderForm');
            const formData = new FormData(form);

            const orderData = {
                type: state.orderType,
                customer_name: formData.get('customer_name'),
                customer_phone: formData.get('customer_phone'),
                address_street: formData.get('address_street'),
                address_house: formData.get('address_house'),
                address_apartment: formData.get('address_apartment'),
                address_entrance: formData.get('address_entrance'),
                address_floor: formData.get('address_floor'),
                address_intercom: formData.get('address_intercom'),
                address_comment: formData.get('address_comment'),
                deliver_at: state.deliveryTime === 'asap' ? null : formData.get('deliver_at'),
                payment_method: state.paymentMethod,
                change_from: formData.get('change_from'),
                items: state.cart.map(item => ({
                    product_id: item.product_id,
                    quantity: item.quantity,
                    modifiers: [],
                    comment: null
                }))
            };

            try {
                const response = await fetch('/delivery/orders', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify(orderData)
                });

                const result = await response.json();

                if (result.success) {
                    showNotification(`Заказ ${result.order_number} создан!`, 'success');
                    closeNewOrderModal();
                    location.reload();
                } else {
                    showNotification(result.message, 'error');
                }
            } catch (error) {
                console.error('Ошибка:', error);
                showNotification('Ошибка создания заказа', 'error');
            }
        }

        // ==================== ТИП ЗАКАЗА ====================
        function setOrderType(type) {
            state.orderType = type;

            document.querySelectorAll('.order-type-btn').forEach(btn => {
                btn.classList.remove('bg-accent', 'text-white');
                btn.classList.add('bg-gray-800', 'text-gray-300');
            });
            const activeBtn = document.querySelector(`.order-type-btn[data-type="${type}"]`);
            if (activeBtn) {
                activeBtn.classList.remove('bg-gray-800', 'text-gray-300');
                activeBtn.classList.add('bg-accent', 'text-white');
            }

            const addressSection = document.getElementById('addressSection');
            if (addressSection) {
                addressSection.style.display = type === 'pickup' ? 'none' : 'block';
            }

            updateTotals();
        }

        // ==================== СПОСОБ ОПЛАТЫ ====================
        function setPaymentMethod(method) {
            state.paymentMethod = method;

            document.querySelectorAll('.payment-btn').forEach(btn => {
                btn.classList.remove('bg-green-600', 'text-white');
                btn.classList.add('bg-gray-800', 'text-gray-300');
            });
            const activeBtn = document.querySelector(`.payment-btn[data-method="${method}"]`);
            if (activeBtn) {
                activeBtn.classList.remove('bg-gray-800', 'text-gray-300');
                activeBtn.classList.add('bg-green-600', 'text-white');
            }

            const changeSection = document.getElementById('changeSection');
            if (changeSection) {
                changeSection.style.display = method === 'cash' ? 'block' : 'none';
            }
        }

        // ==================== ФИЛЬТР КАТЕГОРИЙ ====================
        function filterCategory(categoryId, btn) {
            document.querySelectorAll('.category-btn').forEach(b => {
                b.classList.remove('bg-accent', 'text-white');
                b.classList.add('bg-gray-800', 'text-gray-300');
            });
            btn.classList.remove('bg-gray-800', 'text-gray-300');
            btn.classList.add('bg-accent', 'text-white');

            loadProducts(categoryId);
        }

        // ==================== УВЕДОМЛЕНИЯ ====================
        function showNotification(message, type = 'info') {
            const colors = {
                success: 'bg-green-500',
                error: 'bg-red-500',
                info: 'bg-blue-500'
            };

            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 ${colors[type]} text-white px-6 py-3 rounded-xl shadow-lg z-50`;
            notification.textContent = message;
            document.body.appendChild(notification);

            setTimeout(() => notification.remove(), 3000);
        }

        // ==================== ИНИЦИАЛИЗАЦИЯ ====================
        document.addEventListener('DOMContentLoaded', () => {
            const searchInput = document.getElementById('searchInput');
            if (searchInput) {
                searchInput.addEventListener('input', debounce((e) => {
                    const query = e.target.value.toLowerCase();
                    document.querySelectorAll('.order-card').forEach(card => {
                        const text = card.textContent.toLowerCase();
                        card.style.display = text.includes(query) ? '' : 'none';
                    });
                }, 300));
            }
        });

        function debounce(func, wait) {
            let timeout;
            return function(...args) {
                clearTimeout(timeout);
                timeout = setTimeout(() => func.apply(this, args), wait);
            };
        }
    </script>
</body>
</html>
