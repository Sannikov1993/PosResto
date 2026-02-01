<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Стол {{ $table->number }} — MenuLab</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        dark: { 800: '#1a1f2e', 900: '#141824', 950: '#0f1219' },
                        accent: '#f97316'
                    }
                }
            }
        }
    </script>

    <style>
        * { font-family: 'Inter', -apple-system, system-ui, sans-serif; }
        html, body { height: 100%; overflow: hidden; background: #0f1219; color: #e2e8f0; }

        .btn { transition: all 0.15s ease; }
        .btn:active { transform: scale(0.97); }

        .scroll-y { overflow-y: auto; scrollbar-width: thin; scrollbar-color: #334155 transparent; }
        .scroll-y::-webkit-scrollbar { width: 6px; }
        .scroll-y::-webkit-scrollbar-thumb { background: #334155; border-radius: 3px; }

        .category-active {
            background: rgba(249, 115, 22, 0.2) !important;
            border-left-color: #f97316 !important;
            color: #f97316 !important;
        }

        .product-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 40px rgba(249, 115, 22, 0.15);
        }

        .cart-item {
            animation: slideIn 0.2s ease;
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateX(20px); }
            to { opacity: 1; transform: translateX(0); }
        }

        #sendButton:not(:disabled) {
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { box-shadow: 0 0 0 0 rgba(249, 115, 22, 0.4); }
            50% { box-shadow: 0 0 0 10px rgba(249, 115, 22, 0); }
        }

        .hide-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        .hide-scrollbar::-webkit-scrollbar { display: none; }
    </style>
</head>
<body>
    <div id="app" class="h-full flex">

        <!-- ========== LEFT: КАТЕГОРИИ ========== -->
        <aside class="w-32 bg-gray-950 flex flex-col border-r border-gray-800 flex-shrink-0">
            <!-- Кнопка назад -->
            <a href="/pos-vue"
               class="p-4 text-gray-400 hover:text-white hover:bg-gray-800 border-b border-gray-800 flex items-center gap-2 transition-colors btn">
                <span class="text-xl">←</span>
                <span class="text-sm">Зал</span>
            </a>

            <!-- Список категорий -->
            <nav class="flex-1 scroll-y py-2" id="categoriesList">
                <!-- Заполняется через JS -->
            </nav>
        </aside>

        <!-- ========== CENTER: ТОВАРЫ ========== -->
        <main class="flex-1 flex flex-col bg-dark-900 min-w-0">
            <!-- Top Bar -->
            <header class="h-16 bg-dark-800 px-6 flex items-center justify-between border-b border-gray-700 flex-shrink-0">
                <div class="flex items-center gap-4">
                    <div class="bg-accent text-white px-4 py-2 rounded-lg font-bold">
                        Стол {{ $table->number }}
                    </div>
                    <span class="text-gray-400">{{ $table->seats }} мест • {{ $table->zone->name ?? 'Основной зал' }}</span>
                </div>

                <div class="flex items-center gap-4">
                    <div class="relative">
                        <input type="text"
                               id="searchInput"
                               placeholder="Поиск блюда..."
                               class="bg-dark-900 border border-gray-700 rounded-lg pl-10 pr-4 py-2 text-white placeholder-gray-500 w-64 focus:outline-none focus:border-accent">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500">🔍</span>
                    </div>

                    <div class="flex items-center gap-2 text-gray-300">
                        <span>Гостей:</span>
                        <button onclick="changeGuests(-1)" class="bg-dark-900 w-8 h-8 rounded-lg hover:bg-gray-700 btn">−</button>
                        <span id="guestsCount" class="font-bold text-white px-2">{{ $activeOrder->guests_count ?? 2 }}</span>
                        <button onclick="changeGuests(1)" class="bg-accent w-8 h-8 rounded-lg text-white hover:bg-orange-600 btn">+</button>
                    </div>
                </div>

                <div class="flex items-center gap-3 text-gray-400">
                    <span id="sessionTime">⏱ 00:00</span>
                </div>
            </header>

            <!-- Tabs: В зале / Бронь -->
            <div class="px-6 py-3 bg-dark-800/50 border-b border-gray-700 flex gap-2 flex-shrink-0">
                <button id="tabOrder" onclick="switchTab('order')"
                        class="bg-accent text-white px-6 py-2 rounded-lg font-medium transition-colors btn">
                    🍽 В зале
                </button>
                <button id="tabBooking" onclick="switchTab('booking')"
                        class="bg-dark-900 text-gray-300 px-6 py-2 rounded-lg hover:bg-gray-700 transition-colors btn">
                    📅 Бронь
                </button>
                <div class="flex-1"></div>
                <button onclick="filterProducts('all')" id="filterAll" class="bg-gray-700 text-white px-4 py-2 rounded-lg text-sm btn">
                    Все
                </button>
                <button onclick="filterProducts('popular')" id="filterPopular" class="bg-dark-900 text-gray-300 px-4 py-2 rounded-lg text-sm hover:bg-gray-700 btn">
                    🔥 Популярное
                </button>
                <button onclick="filterProducts('new')" id="filterNew" class="bg-dark-900 text-gray-300 px-4 py-2 rounded-lg text-sm hover:bg-gray-700 btn">
                    🆕 Новинки
                </button>
            </div>

            <!-- Products Grid -->
            <div class="flex-1 p-4 scroll-y" id="productsContainer">
                <div id="productsList" class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5 gap-3">
                    <!-- Заполняется через JS -->
                </div>

                <!-- Booking Form (скрыт по умолчанию) -->
                <div id="bookingForm" class="hidden max-w-2xl mx-auto">
                    @include('hall.partials.booking-form', ['table' => $table])
                </div>
            </div>
        </main>

        <!-- ========== RIGHT: КОРЗИНА ========== -->
        <aside class="w-96 bg-gray-950 flex flex-col border-l border-gray-800 flex-shrink-0">
            <!-- Client Section -->
            <div class="p-4 border-b border-gray-800">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-gray-400 font-medium">Клиент</span>
                    <button onclick="openClientSearch()" class="text-accent text-sm font-medium hover:text-orange-400 btn">
                        + Добавить
                    </button>
                </div>
                <div id="clientCard" class="bg-dark-800 rounded-xl p-3">
                    <p class="text-gray-500 text-sm">Клиент не выбран</p>
                </div>
            </div>

            <!-- Cart Header -->
            <div class="px-4 py-3 border-b border-gray-800 flex items-center justify-between">
                <span class="text-white font-semibold">Позиции заказа</span>
                <span id="cartCount" class="text-gray-500 text-sm">0 позиций</span>
            </div>

            <!-- Cart Items -->
            <div class="flex-1 p-4 scroll-y" id="cartItems">
                <div id="emptyCart" class="text-center text-gray-500 py-10">
                    <span class="text-5xl opacity-50">🛒</span>
                    <p class="mt-3">Корзина пуста</p>
                    <p class="text-sm mt-1">Выберите блюда из меню</p>
                </div>
            </div>

            <!-- Discount Section -->
            <div class="px-4 py-3 border-t border-gray-800">
                <div class="flex gap-2">
                    <input id="promoInput"
                           placeholder="Промокод"
                           class="flex-1 bg-dark-800 border border-gray-700 rounded-lg px-3 py-2 text-white text-sm placeholder-gray-500 focus:outline-none focus:border-accent">
                    <button onclick="applyPromo()" class="bg-gray-700 text-gray-300 px-4 py-2 rounded-lg text-sm hover:bg-gray-600 btn">
                        OK
                    </button>
                </div>
            </div>

            <!-- Total Section -->
            <div class="p-4 border-t border-gray-800 bg-dark-900">
                <div class="space-y-2 mb-4">
                    <div class="flex justify-between text-gray-400">
                        <span>Подытог:</span>
                        <span id="subtotal">0 ₽</span>
                    </div>
                    <div id="discountRow" class="flex justify-between text-green-500 hidden">
                        <span id="discountLabel">Скидка:</span>
                        <span id="discountAmount">-0 ₽</span>
                    </div>
                    <div class="flex justify-between text-white text-xl font-bold pt-2 border-t border-gray-700">
                        <span>ИТОГО:</span>
                        <span id="total" class="text-accent">0 ₽</span>
                    </div>
                </div>

                <!-- Action Buttons -->
                <button onclick="sendToKitchen()"
                        id="sendButton"
                        disabled
                        class="w-full bg-gradient-to-r from-accent to-orange-600 text-white py-4 rounded-xl font-bold text-lg mb-3 hover:from-orange-600 hover:to-orange-700 shadow-lg shadow-accent/30 disabled:opacity-50 disabled:cursor-not-allowed transition-all btn">
                    ✓ Отправить на кухню
                </button>
                <div class="grid grid-cols-3 gap-2">
                    <button onclick="printPrecheck()" class="bg-dark-800 text-gray-300 py-3 rounded-xl text-sm font-medium hover:bg-gray-700 btn">
                        🖨 Пречек
                    </button>
                    <button onclick="requestBill()" class="bg-dark-800 text-gray-300 py-3 rounded-xl text-sm font-medium hover:bg-gray-700 btn">
                        💳 Счёт
                    </button>
                    <button onclick="cancelOrder()" class="bg-dark-800 text-gray-300 py-3 rounded-xl text-sm font-medium hover:bg-red-900/50 hover:text-red-400 btn">
                        ✕ Отмена
                    </button>
                </div>
            </div>
        </aside>

    </div>

    <!-- Модалка добавления товара с модификаторами -->
    <div id="productModal" class="fixed inset-0 bg-black/70 z-[9999] hidden flex items-center justify-center p-4">
        <div class="bg-dark-800 rounded-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto border border-gray-700">
            <div id="productModalContent">
                <!-- Содержимое модалки модификаторов -->
            </div>
        </div>
    </div>

    <!-- Модалка поиска клиента -->
    <div id="clientModal" class="fixed inset-0 bg-black/70 z-[9999] hidden flex items-center justify-center p-4">
        <div class="bg-dark-800 rounded-2xl w-full max-w-md p-6 border border-gray-700">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-xl font-bold text-white">Выбор клиента</h3>
                <button onclick="closeClientModal()" class="text-gray-400 hover:text-white btn">✕</button>
            </div>
            <input type="text" id="clientSearchInput" placeholder="Поиск по имени или телефону..."
                   class="w-full bg-dark-900 border border-gray-700 rounded-lg px-4 py-3 text-white mb-4 focus:outline-none focus:border-accent">
            <div id="clientSearchResults" class="max-h-64 scroll-y space-y-2">
                <!-- Результаты поиска -->
            </div>
            <button onclick="createNewClient()" class="w-full mt-4 py-3 bg-accent text-white rounded-xl font-medium hover:bg-orange-600 btn">
                + Создать нового клиента
            </button>
        </div>
    </div>

    <!-- Toast уведомления -->
    <div id="toast" class="fixed bottom-6 left-1/2 -translate-x-1/2 px-6 py-3 rounded-xl shadow-xl z-[9999] hidden">
        <span id="toastMessage" class="text-white font-medium"></span>
    </div>

    <script>
        // ========== КОНФИГУРАЦИЯ ==========
        const API = '/api';
        const TABLE_ID = {{ $table->id }};
        const TABLE_NUMBER = '{{ $table->number }}';

        // ========== СОСТОЯНИЕ ==========
        const state = {
            tableId: TABLE_ID,
            guests: {{ $activeOrder->guests_count ?? 2 }},
            categories: [],
            products: [],
            cart: [],
            client: null,
            activeCategory: null,
            activeFilter: 'all',
            sessionStart: new Date(),
            discount: { type: null, value: 0, label: '' },
            activeOrderId: {{ $activeOrder->id ?? 'null' }}
        };

        // ========== ИНИЦИАЛИЗАЦИЯ ==========
        document.addEventListener('DOMContentLoaded', () => {
            loadCategories();
            loadProducts();
            startSessionTimer();

            // Загрузить существующий заказ если есть
            @if($activeOrder)
            loadExistingOrder(@json($activeOrder));
            @endif

            // Поиск с debounce
            document.getElementById('searchInput').addEventListener('input', debounce(searchProducts, 300));
            document.getElementById('clientSearchInput').addEventListener('input', debounce(searchClients, 300));
        });

        // ========== ЗАГРУЗКА СУЩЕСТВУЮЩЕГО ЗАКАЗА ==========
        function loadExistingOrder(order) {
            state.activeOrderId = order.id;
            state.guests = order.guests_count || 2;
            document.getElementById('guestsCount').textContent = state.guests;

            if (order.customer) {
                state.client = order.customer;
                renderClientCard();
            }

            // Загрузить позиции в корзину
            if (order.items && order.items.length > 0) {
                order.items.forEach(item => {
                    state.cart.push({
                        id: item.id,
                        productId: item.dish_id,
                        name: item.name,
                        price: parseFloat(item.price),
                        quantity: item.quantity,
                        modifiers: [],
                        comment: item.notes || '',
                        icon: '🍽'
                    });
                });
                renderCart();
                updateTotals();
            }
        }

        // ========== КАТЕГОРИИ ==========
        async function loadCategories() {
            try {
                const response = await axios.get(`${API}/categories`);
                state.categories = response.data.data || response.data || [];
                renderCategories();
                if (state.categories.length > 0) {
                    selectCategory(state.categories[0].id);
                }
            } catch (error) {
                console.error('Ошибка загрузки категорий:', error);
            }
        }

        function renderCategories() {
            const container = document.getElementById('categoriesList');
            container.innerHTML = state.categories.map(cat => `
                <button onclick="selectCategory(${cat.id})"
                        id="cat-${cat.id}"
                        class="w-full py-4 flex flex-col items-center gap-2 border-l-4 border-transparent text-gray-500 hover:bg-gray-800 transition-colors btn">
                    <span class="text-2xl">${cat.icon || '📦'}</span>
                    <span class="text-xs text-center px-1 leading-tight">${cat.name}</span>
                </button>
            `).join('');
        }

        function selectCategory(categoryId) {
            document.querySelectorAll('#categoriesList button').forEach(btn => {
                btn.classList.remove('category-active');
                btn.classList.add('text-gray-500');
            });

            const activeBtn = document.getElementById(`cat-${categoryId}`);
            if (activeBtn) {
                activeBtn.classList.add('category-active');
                activeBtn.classList.remove('text-gray-500');
            }

            state.activeCategory = categoryId;
            state.activeFilter = 'all';
            updateFilterButtons();
            loadProducts(categoryId);
        }

        // ========== ТОВАРЫ ==========
        async function loadProducts(categoryId = null) {
            try {
                let url = `${API}/dishes`;
                const params = {};
                if (categoryId) params.category_id = categoryId;

                const response = await axios.get(url, { params });
                let products = response.data.data || response.data || [];

                // Фильтруем по категории на клиенте если API не поддерживает
                if (categoryId) {
                    products = products.filter(p => p.category_id === categoryId);
                }

                state.products = products;
                renderProducts();
            } catch (error) {
                console.error('Ошибка загрузки товаров:', error);
            }
        }

        function renderProducts() {
            let products = state.products;

            // Применяем фильтр
            if (state.activeFilter === 'popular') {
                products = products.filter(p => p.is_popular || p.order_count > 10);
            } else if (state.activeFilter === 'new') {
                products = products.filter(p => p.is_new);
            }

            const container = document.getElementById('productsList');

            if (products.length === 0) {
                container.innerHTML = `
                    <div class="col-span-full text-center text-gray-500 py-20">
                        <span class="text-5xl">🍽️</span>
                        <p class="mt-4 text-lg">Блюда не найдены</p>
                    </div>
                `;
                return;
            }

            container.innerHTML = products.map(product => `
                <div onclick="${product.is_stopped ? '' : `openProductModal(${product.id})`}"
                     class="bg-dark-800 rounded-xl overflow-hidden border border-gray-700 cursor-pointer product-card transition-all ${product.is_stopped ? 'opacity-50 cursor-not-allowed' : 'hover:border-accent'}">
                    <div class="relative h-32 bg-dark-950">
                        ${product.image
                            ? `<img src="${product.image}" class="w-full h-full object-cover">`
                            : `<div class="w-full h-full flex items-center justify-center text-5xl opacity-50">${product.emoji || getCategoryEmoji(product.category_id)}</div>`
                        }
                        ${product.is_stopped ? '<div class="absolute inset-0 bg-black/70 flex items-center justify-center"><span class="bg-red-600 text-white text-sm font-bold px-4 py-1.5 rounded-lg transform -rotate-12">СТОП</span></div>' : ''}
                        <div class="absolute top-2 left-2 flex flex-col gap-1">
                            ${product.is_popular || product.order_count > 10 ? '<span class="bg-orange-500 text-white text-xs px-2 py-0.5 rounded font-medium">🔥 Хит</span>' : ''}
                            ${product.is_new ? '<span class="bg-green-500 text-white text-xs px-2 py-0.5 rounded font-medium">🆕</span>' : ''}
                        </div>
                    </div>
                    <div class="p-3">
                        <p class="text-white font-medium text-sm mb-1 line-clamp-2 min-h-[2.5rem] ${product.is_stopped ? 'line-through' : ''}">${product.name}</p>
                        <div class="flex items-center gap-2 text-xs text-gray-500 mb-2">
                            ${product.weight ? `<span>⚖️ ${product.weight}г</span>` : ''}
                            ${product.cook_time ? `<span>⏱ ${product.cook_time} мин</span>` : ''}
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-accent font-bold text-lg ${product.is_stopped ? 'line-through text-gray-500' : ''}">${product.price} ₽</span>
                            ${!product.is_stopped ? `
                                <button onclick="event.stopPropagation(); quickAdd(${product.id})"
                                        class="w-10 h-10 bg-accent hover:bg-orange-600 rounded-xl text-white text-xl font-bold shadow-lg shadow-accent/30 btn">+</button>
                            ` : ''}
                        </div>
                    </div>
                </div>
            `).join('');
        }

        function getCategoryEmoji(categoryId) {
            const cat = state.categories.find(c => c.id === categoryId);
            return cat?.icon || '🍽️';
        }

        function filterProducts(filter) {
            state.activeFilter = filter;
            updateFilterButtons();
            renderProducts();
        }

        function updateFilterButtons() {
            ['all', 'popular', 'new'].forEach(f => {
                const btn = document.getElementById(`filter${f.charAt(0).toUpperCase() + f.slice(1)}`);
                if (btn) {
                    if (state.activeFilter === f) {
                        btn.classList.add('bg-gray-700', 'text-white');
                        btn.classList.remove('bg-dark-900', 'text-gray-300');
                    } else {
                        btn.classList.remove('bg-gray-700', 'text-white');
                        btn.classList.add('bg-dark-900', 'text-gray-300');
                    }
                }
            });
        }

        function searchProducts(event) {
            const query = event.target.value.toLowerCase().trim();
            if (!query) {
                renderProducts();
                return;
            }

            const filtered = state.products.filter(p =>
                p.name.toLowerCase().includes(query) ||
                (p.description && p.description.toLowerCase().includes(query))
            );

            const container = document.getElementById('productsList');
            if (filtered.length === 0) {
                container.innerHTML = `
                    <div class="col-span-full text-center text-gray-500 py-20">
                        <p>Ничего не найдено по запросу "${query}"</p>
                    </div>
                `;
            } else {
                state.products = filtered;
                renderProducts();
            }
        }

        // ========== МОДАЛКА ТОВАРА ==========
        function openProductModal(productId) {
            const product = state.products.find(p => p.id === productId);
            if (!product || product.is_stopped) return;

            const modal = document.getElementById('productModal');
            const content = document.getElementById('productModalContent');

            content.innerHTML = `
                <div class="p-6">
                    <div class="flex items-start justify-between mb-4">
                        <div>
                            <h3 class="text-xl font-bold text-white">${product.name}</h3>
                            <p class="text-gray-400 text-sm mt-1">${product.description || ''}</p>
                        </div>
                        <button onclick="closeProductModal()" class="text-gray-400 hover:text-white btn text-xl">✕</button>
                    </div>

                    <div class="flex items-center gap-4 mb-6">
                        <span class="text-accent text-2xl font-bold">${product.price} ₽</span>
                        ${product.weight ? `<span class="text-gray-500">⚖️ ${product.weight}г</span>` : ''}
                    </div>

                    <div class="mb-4">
                        <label class="block text-gray-400 text-sm mb-2">Количество</label>
                        <div class="flex items-center gap-3">
                            <button onclick="updateModalQuantity(-1)" class="w-12 h-12 bg-dark-900 rounded-xl text-white text-xl hover:bg-gray-700 btn">−</button>
                            <span id="modalQuantity" class="text-2xl font-bold text-white w-12 text-center">1</span>
                            <button onclick="updateModalQuantity(1)" class="w-12 h-12 bg-dark-900 rounded-xl text-white text-xl hover:bg-gray-700 btn">+</button>
                        </div>
                    </div>

                    <div class="mb-6">
                        <label class="block text-gray-400 text-sm mb-2">Комментарий</label>
                        <input type="text" id="modalComment" placeholder="Без лука, острее и т.д."
                               class="w-full bg-dark-900 border border-gray-700 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-accent">
                    </div>

                    <button onclick="addFromModal(${product.id})"
                            class="w-full py-4 bg-accent hover:bg-orange-600 text-white rounded-xl font-bold text-lg btn">
                        Добавить — <span id="modalTotal">${product.price}</span> ₽
                    </button>
                </div>
            `;

            modal.classList.remove('hidden');
            modal.dataset.productId = productId;
            modal.dataset.quantity = 1;
            modal.dataset.price = product.price;
        }

        function closeProductModal() {
            document.getElementById('productModal').classList.add('hidden');
        }

        function updateModalQuantity(delta) {
            const modal = document.getElementById('productModal');
            let qty = parseInt(modal.dataset.quantity) + delta;
            if (qty < 1) qty = 1;
            modal.dataset.quantity = qty;

            document.getElementById('modalQuantity').textContent = qty;
            document.getElementById('modalTotal').textContent = parseInt(modal.dataset.price) * qty;
        }

        function addFromModal(productId) {
            const modal = document.getElementById('productModal');
            const product = state.products.find(p => p.id === productId);
            const quantity = parseInt(modal.dataset.quantity);
            const comment = document.getElementById('modalComment').value;

            addToCart({
                productId: product.id,
                name: product.name,
                price: product.price,
                quantity: quantity,
                modifiers: [],
                comment: comment,
                icon: product.emoji || getCategoryEmoji(product.category_id)
            });

            closeProductModal();
            showToast('Добавлено в корзину', 'success');
        }

        // ========== КОРЗИНА ==========
        function quickAdd(productId) {
            const product = state.products.find(p => p.id === productId);
            if (!product || product.is_stopped) return;

            addToCart({
                productId: product.id,
                name: product.name,
                price: product.price,
                quantity: 1,
                modifiers: [],
                comment: '',
                icon: product.emoji || getCategoryEmoji(product.category_id)
            });

            showToast('Добавлено в корзину', 'success');
        }

        function addToCart(item) {
            const existingIndex = state.cart.findIndex(i =>
                i.productId === item.productId &&
                i.modifiers.length === 0 &&
                item.modifiers.length === 0 &&
                i.comment === item.comment
            );

            if (existingIndex > -1) {
                state.cart[existingIndex].quantity += item.quantity;
            } else {
                state.cart.push({...item, id: Date.now()});
            }

            renderCart();
            updateTotals();
        }

        function removeFromCart(itemId) {
            state.cart = state.cart.filter(i => i.id !== itemId);
            renderCart();
            updateTotals();
        }

        function updateCartItemQuantity(itemId, delta) {
            const item = state.cart.find(i => i.id === itemId);
            if (item) {
                item.quantity += delta;
                if (item.quantity <= 0) {
                    removeFromCart(itemId);
                } else {
                    renderCart();
                    updateTotals();
                }
            }
        }

        function renderCart() {
            const container = document.getElementById('cartItems');
            const emptyCart = document.getElementById('emptyCart');

            if (state.cart.length === 0) {
                container.innerHTML = `
                    <div id="emptyCart" class="text-center text-gray-500 py-10">
                        <span class="text-5xl opacity-50">🛒</span>
                        <p class="mt-3">Корзина пуста</p>
                        <p class="text-sm mt-1">Выберите блюда из меню</p>
                    </div>
                `;
                document.getElementById('cartCount').textContent = '0 позиций';
                return;
            }

            document.getElementById('cartCount').textContent = `${state.cart.length} позиций`;

            container.innerHTML = state.cart.map(item => `
                <div class="bg-dark-800 rounded-xl p-3 mb-3 cart-item border border-gray-700">
                    <div class="flex gap-3">
                        <div class="w-12 h-12 bg-dark-900 rounded-lg flex items-center justify-center text-2xl flex-shrink-0">
                            ${item.icon}
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-start justify-between">
                                <p class="text-white font-medium text-sm">${item.name}</p>
                                <span class="text-accent font-bold ml-2">${item.price * item.quantity} ₽</span>
                            </div>
                            ${item.comment ? `<p class="text-gray-500 text-xs mt-1">💬 ${item.comment}</p>` : ''}
                            <div class="flex items-center justify-between mt-2">
                                <div class="flex items-center gap-1 bg-dark-900 rounded-lg">
                                    <button onclick="updateCartItemQuantity(${item.id}, -1)" class="w-8 h-8 text-white hover:bg-gray-700 rounded-l-lg btn">−</button>
                                    <span class="text-white px-3 font-medium">${item.quantity}</span>
                                    <button onclick="updateCartItemQuantity(${item.id}, 1)" class="w-8 h-8 text-white hover:bg-gray-700 rounded-r-lg btn">+</button>
                                </div>
                                <button onclick="removeFromCart(${item.id})" class="text-gray-500 hover:text-red-400 p-1 btn">🗑</button>
                            </div>
                        </div>
                    </div>
                </div>
            `).join('');
        }

        function updateTotals() {
            const subtotal = state.cart.reduce((sum, item) => {
                const modifiersPrice = item.modifiers.reduce((m, mod) => m + (mod.price || 0), 0);
                return sum + ((item.price + modifiersPrice) * item.quantity);
            }, 0);

            let discount = 0;
            if (state.discount.type === 'percent') {
                discount = subtotal * (state.discount.value / 100);
            } else if (state.discount.type === 'fixed') {
                discount = state.discount.value;
            }

            const total = Math.max(0, subtotal - discount);

            document.getElementById('subtotal').textContent = `${subtotal} ₽`;
            document.getElementById('total').textContent = `${total} ₽`;

            if (discount > 0) {
                document.getElementById('discountRow').classList.remove('hidden');
                document.getElementById('discountLabel').textContent = state.discount.label || 'Скидка:';
                document.getElementById('discountAmount').textContent = `-${Math.round(discount)} ₽`;
            } else {
                document.getElementById('discountRow').classList.add('hidden');
            }

            document.getElementById('sendButton').disabled = state.cart.length === 0;
        }

        // ========== ОТПРАВКА ЗАКАЗА ==========
        async function sendToKitchen() {
            if (state.cart.length === 0) return;

            const orderData = {
                table_id: state.tableId,
                type: 'dine_in',
                guests_count: state.guests,
                customer_id: state.client?.id || null,
                items: state.cart.map(item => ({
                    dish_id: item.productId,
                    quantity: item.quantity,
                    modifiers: item.modifiers.map(m => m.id),
                    notes: item.comment
                })),
                discount_type: state.discount.type,
                discount_value: state.discount.value
            };

            try {
                let response;
                if (state.activeOrderId) {
                    // Обновляем существующий заказ
                    response = await axios.put(`${API}/orders/${state.activeOrderId}`, orderData);
                } else {
                    // Создаём новый заказ
                    response = await axios.post(`${API}/orders`, orderData);
                }

                const result = response.data;

                if (result.success) {
                    showToast('Заказ отправлен на кухню!', 'success');
                    state.activeOrderId = result.data.id;
                    // Не очищаем корзину - показываем текущий заказ
                } else {
                    showToast(result.message || 'Ошибка создания заказа', 'error');
                }
            } catch (error) {
                console.error('Ошибка:', error);
                showToast(error.response?.data?.message || 'Ошибка соединения', 'error');
            }
        }

        // ========== КЛИЕНТЫ ==========
        function openClientSearch() {
            document.getElementById('clientModal').classList.remove('hidden');
            document.getElementById('clientSearchInput').focus();
        }

        function closeClientModal() {
            document.getElementById('clientModal').classList.add('hidden');
        }

        async function searchClients(event) {
            const query = event.target.value.trim();
            if (query.length < 2) {
                document.getElementById('clientSearchResults').innerHTML = '';
                return;
            }

            try {
                const response = await axios.get(`${API}/customers`, { params: { search: query } });
                const clients = response.data.data || [];

                document.getElementById('clientSearchResults').innerHTML = clients.map(c => `
                    <div onclick="selectClient(${JSON.stringify(c).replace(/"/g, '&quot;')})"
                         class="bg-dark-900 rounded-lg p-3 cursor-pointer hover:bg-gray-700 btn">
                        <p class="text-white font-medium">${c.name}</p>
                        <p class="text-gray-500 text-sm">${c.phone}</p>
                    </div>
                `).join('');
            } catch (error) {
                console.error('Ошибка поиска клиентов:', error);
            }
        }

        function selectClient(client) {
            state.client = client;
            renderClientCard();
            closeClientModal();
        }

        function renderClientCard() {
            const card = document.getElementById('clientCard');
            if (state.client) {
                card.innerHTML = `
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-white font-medium">${state.client.name}</p>
                            <p class="text-gray-500 text-sm">${state.client.phone}</p>
                            ${state.client.is_vip ? '<span class="text-yellow-400 text-xs">⭐ VIP</span>' : ''}
                        </div>
                        <button onclick="clearClient()" class="text-gray-500 hover:text-red-400 btn">✕</button>
                    </div>
                `;
            } else {
                card.innerHTML = '<p class="text-gray-500 text-sm">Клиент не выбран</p>';
            }
        }

        function clearClient() {
            state.client = null;
            renderClientCard();
        }

        // ========== ТАБЫ ==========
        function switchTab(tab) {
            const tabOrder = document.getElementById('tabOrder');
            const tabBooking = document.getElementById('tabBooking');
            const productsList = document.getElementById('productsList');
            const bookingForm = document.getElementById('bookingForm');

            if (tab === 'order') {
                tabOrder.classList.add('bg-accent', 'text-white');
                tabOrder.classList.remove('bg-dark-900', 'text-gray-300');
                tabBooking.classList.remove('bg-accent', 'text-white');
                tabBooking.classList.add('bg-dark-900', 'text-gray-300');
                productsList.classList.remove('hidden');
                bookingForm.classList.add('hidden');
            } else {
                tabBooking.classList.add('bg-accent', 'text-white');
                tabBooking.classList.remove('bg-dark-900', 'text-gray-300');
                tabOrder.classList.remove('bg-accent', 'text-white');
                tabOrder.classList.add('bg-dark-900', 'text-gray-300');
                productsList.classList.add('hidden');
                bookingForm.classList.remove('hidden');
            }
        }

        // ========== УТИЛИТЫ ==========
        function debounce(func, wait) {
            let timeout;
            return function(...args) {
                clearTimeout(timeout);
                timeout = setTimeout(() => func.apply(this, args), wait);
            };
        }

        function startSessionTimer() {
            setInterval(() => {
                const now = new Date();
                const diff = Math.floor((now - state.sessionStart) / 1000);
                const minutes = Math.floor(diff / 60).toString().padStart(2, '0');
                const seconds = (diff % 60).toString().padStart(2, '0');
                document.getElementById('sessionTime').textContent = `⏱ ${minutes}:${seconds}`;
            }, 1000);
        }

        function changeGuests(delta) {
            state.guests = Math.max(1, state.guests + delta);
            document.getElementById('guestsCount').textContent = state.guests;
        }

        function showToast(message, type = 'info') {
            const toast = document.getElementById('toast');
            const toastMessage = document.getElementById('toastMessage');

            toast.className = 'fixed bottom-6 left-1/2 -translate-x-1/2 px-6 py-3 rounded-xl shadow-xl z-[9999]';
            toast.classList.add(type === 'success' ? 'bg-green-600' : type === 'error' ? 'bg-red-600' : 'bg-gray-700');

            toastMessage.textContent = message;
            toast.classList.remove('hidden');

            setTimeout(() => {
                toast.classList.add('hidden');
            }, 3000);
        }

        function cancelOrder() {
            if (confirm('Вернуться в зал?')) {
                window.location.href = '/pos-vue';
            }
        }

        function printPrecheck() {
            showToast('Печать пречека...', 'info');
        }

        function requestBill() {
            showToast('Запрос счёта...', 'info');
        }

        function applyPromo() {
            const code = document.getElementById('promoInput').value.trim();
            if (!code) return;
            // TODO: Проверка промокода через API
            showToast('Промокод применён', 'success');
        }
    </script>
</body>
</html>
