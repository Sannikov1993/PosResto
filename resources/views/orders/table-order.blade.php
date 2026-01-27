{{-- resources/views/orders/table-order.blade.php --}}
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Стол {{ $table->number }} - PosLab</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/axios/dist/axios.min.js"></script>
    <style>
        body { background: #0d1117; font-family: 'Inter', system-ui, sans-serif; }

        /* Скроллбар */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #374151; border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: #4b5563; }

        /* Анимация добавления в корзину */
        .cart-item-enter { animation: slideIn 0.2s ease; }
        @keyframes slideIn {
            from { opacity: 0; transform: translateX(-10px); }
            to { opacity: 1; transform: translateX(0); }
        }

        /* Пульсация кнопки оплаты */
        .pulse-orange { animation: pulseOrange 2s infinite; }
        @keyframes pulseOrange {
            0%, 100% { box-shadow: 0 0 0 0 rgba(249, 115, 22, 0.4); }
            50% { box-shadow: 0 0 0 8px rgba(249, 115, 22, 0); }
        }

        /* Hover эффект */
        .product-row:hover .add-btn { opacity: 1; }
    </style>
</head>
<body class="h-screen overflow-hidden">
    <div id="app" class="flex h-full">

        {{-- ========== КОМПАКТНАЯ БОКОВАЯ ПАНЕЛЬ ========== --}}
        <aside class="w-16 bg-black flex flex-col items-center py-3 gap-2 border-r border-gray-800">
            {{-- Логотип --}}
            <div class="w-10 h-10 bg-gradient-to-br from-orange-500 to-red-500 rounded-xl flex items-center justify-center text-white font-bold mb-4">
                P
            </div>

            {{-- Навигация --}}
            <a href="/pos-vue"
               class="w-10 h-10 bg-orange-500/20 text-orange-500 rounded-xl flex items-center justify-center"
               title="Касса">&#x1F4B3;</a>
            <a href="/pos-vue#hall"
               class="w-10 h-10 text-gray-500 rounded-xl flex items-center justify-center hover:bg-white/5 hover:text-white"
               title="Зал">&#x1F4CB;</a>
            <a href="/pos-vue#catalog"
               class="w-10 h-10 text-gray-500 rounded-xl flex items-center justify-center hover:bg-white/5 hover:text-white"
               title="Каталог">&#x1F4E6;</a>
            <a href="/pos-vue#kitchen"
               class="w-10 h-10 text-gray-500 rounded-xl flex items-center justify-center hover:bg-white/5 hover:text-white relative"
               title="Кухня">
                &#x1F468;&#x200D;&#x1F373;
                <span id="kitchenBadge" class="absolute -top-1 -right-1 w-4 h-4 bg-red-500 rounded-full text-white text-xs flex items-center justify-center hidden">0</span>
            </a>
            <a href="/pos-vue#delivery"
               class="w-10 h-10 text-gray-500 rounded-xl flex items-center justify-center hover:bg-white/5 hover:text-white"
               title="Доставка">&#x1F69A;</a>
            <a href="/pos-vue#customers"
               class="w-10 h-10 text-gray-500 rounded-xl flex items-center justify-center hover:bg-white/5 hover:text-white"
               title="Клиенты">&#x1F465;</a>

            <div class="flex-1"></div>

            {{-- Настройки и профиль --}}
            <a href="/pos-vue#settings"
               class="w-10 h-10 text-gray-500 rounded-xl flex items-center justify-center hover:bg-white/5 hover:text-white"
               title="Настройки">&#x2699;&#xFE0F;</a>
            <div class="w-10 h-10 bg-gray-800 rounded-full flex items-center justify-center text-white text-xs font-medium cursor-pointer hover:bg-gray-700"
                 title="Официант">
                &#x1F464;
            </div>
        </aside>

        {{-- ========== ОСНОВНАЯ ОБЛАСТЬ ========== --}}
        <div class="flex-1 flex flex-col">

            {{-- Верхняя панель --}}
            <header class="h-14 flex items-center justify-between px-4 border-b border-gray-800 bg-gray-900/50">
                <div class="flex items-center gap-3">
                    {{-- Кнопка назад + табы заказов --}}
                    <a href="/pos-vue#hall" class="text-gray-400 hover:text-white p-2">
                        &#x2190; <span class="text-sm">Зал</span>
                    </a>

                    <div class="flex items-center gap-2 bg-gray-800 rounded-xl p-1">
                        <button class="flex items-center gap-2 px-3 py-1.5 bg-orange-500 text-white rounded-lg text-sm font-medium">
                            <span>&#x1F37D;</span> Стол {{ $table->number }}
                        </button>
                        <button id="addOrderTab" class="w-8 h-8 bg-gray-700 text-gray-500 rounded-lg hover:text-white hover:bg-gray-600">+</button>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    {{-- Поиск --}}
                    <div class="relative">
                        <input type="text"
                               id="searchInput"
                               placeholder="Поиск блюда..."
                               class="w-64 bg-gray-800 border-none rounded-lg px-4 py-2 text-white text-sm placeholder-gray-500 focus:ring-2 focus:ring-orange-500 focus:outline-none">
                        <span class="absolute right-3 top-2.5 text-gray-500">&#x1F50D;</span>
                    </div>

                    {{-- Клавиатура для быстрого ввода кода --}}
                    <button id="numpadBtn" class="px-3 py-2 bg-gray-800 text-gray-300 rounded-lg text-sm hover:bg-gray-700">
                        &#x2328;&#xFE0F; 123
                    </button>

                    {{-- Время --}}
                    <div class="flex items-center gap-2 text-gray-400 text-sm">
                        <span id="currentTime" class="text-gray-500">--:--</span>
                    </div>
                </div>
            </header>

            {{-- Контент: Заказ + Меню --}}
            <div class="flex-1 flex overflow-hidden">

                {{-- ========== ЛЕВАЯ ПАНЕЛЬ: ЗАКАЗ ========== --}}
                <section class="w-80 flex flex-col bg-gray-900/50 border-r border-gray-800">
                    {{-- Хедер заказа --}}
                    <div class="px-4 py-2 text-gray-500 text-xs uppercase tracking-wider border-b border-gray-800 flex items-center justify-between">
                        <span>Заказ <span id="orderNumber" class="text-white">#{{ $activeOrder?->daily_number ?? 'новый' }}</span></span>
                        <span>Гость <span id="guestNumber" class="text-white">1</span></span>
                    </div>

                    {{-- Таблица позиций --}}
                    <div class="flex-1 overflow-y-auto">
                        <table class="w-full">
                            <thead class="text-gray-500 text-xs sticky top-0 bg-gray-900/95 backdrop-blur">
                                <tr>
                                    <th class="text-left py-2 px-3 font-medium">Позиция</th>
                                    <th class="text-center py-2 px-1 w-10 font-medium">Кол</th>
                                    <th class="text-right py-2 px-3 w-20 font-medium">Сумма</th>
                                </tr>
                            </thead>
                            <tbody id="cartItems" class="text-sm">
                                {{-- Позиции заполняются через JS --}}
                            </tbody>
                        </table>

                        {{-- Пустая корзина --}}
                        <div id="emptyCart" class="p-8 text-center">
                            <div class="text-4xl mb-2">&#x1F6D2;</div>
                            <p class="text-gray-500">Корзина пуста</p>
                            <p class="text-gray-600 text-sm">Выберите блюда из меню справа</p>
                        </div>
                    </div>

                    {{-- Итого и кнопки действий --}}
                    <div class="p-3 border-t border-gray-800 space-y-3 bg-gray-900/80">
                        {{-- Клиент (если выбран) --}}
                        <div id="customerInfo" class="hidden bg-gray-800 rounded-lg p-2 flex items-center gap-2">
                            <div class="w-8 h-8 bg-orange-500 rounded-full flex items-center justify-center text-white text-xs font-bold" id="customerAvatar">--</div>
                            <div class="flex-1 min-w-0">
                                <p class="text-white text-sm truncate" id="customerName">-</p>
                                <p class="text-gray-500 text-xs" id="customerPhone">-</p>
                            </div>
                            <button onclick="removeCustomer()" class="text-gray-500 hover:text-red-500">&#x2715;</button>
                        </div>

                        {{-- Сумма --}}
                        <div class="flex justify-between items-center">
                            <span class="text-gray-400">Итого:</span>
                            <span id="totalAmount" class="text-white text-xl font-bold">0 &#x20BD;</span>
                        </div>

                        {{-- Скидка (если есть) --}}
                        <div id="discountRow" class="hidden flex justify-between items-center text-green-500 text-sm">
                            <span id="discountLabel">Скидка:</span>
                            <span id="discountAmount">-0 &#x20BD;</span>
                        </div>

                        {{-- Кнопки действий --}}
                        <div class="grid grid-cols-4 gap-2">
                            <button onclick="showMoreActions()" class="py-2.5 bg-gray-800 text-gray-400 rounded-lg text-xs hover:bg-gray-700 hover:text-white" title="Ещё">&#x22EF;</button>
                            <button onclick="showCustomerSearch()" class="py-2.5 bg-gray-800 text-gray-400 rounded-lg text-xs hover:bg-gray-700 hover:text-white" title="Клиент">&#x1F464;</button>
                            <button onclick="printPrecheck()" class="py-2.5 bg-gray-800 text-gray-400 rounded-lg text-xs hover:bg-gray-700 hover:text-white" title="Пречек">&#x1F9FE;</button>
                            <button onclick="sendToKitchen()" class="py-2.5 bg-gray-800 text-gray-400 rounded-lg text-xs hover:bg-gray-700 hover:text-white" title="Готовить">&#x21BB;</button>
                        </div>

                        {{-- Кнопки оплаты --}}
                        <div class="grid grid-cols-3 gap-2">
                            <button onclick="payByCash()" class="py-3 bg-green-600 text-white rounded-lg text-sm font-medium hover:bg-green-700 transition-colors">
                                &#x1F4B5; Нал
                            </button>
                            <button onclick="payByCard()" class="py-3 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors">
                                &#x1F4B3; Карта
                            </button>
                            <button onclick="showPaymentModal()" id="payBtn" class="py-3 bg-orange-500 text-white rounded-lg text-sm font-bold hover:bg-orange-600 transition-colors disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                                Оплата
                            </button>
                        </div>
                    </div>
                </section>

                {{-- ========== ПРАВАЯ ПАНЕЛЬ: МЕНЮ ========== --}}
                <section class="flex-1 flex flex-col">
                    {{-- Быстрые фильтры категорий --}}
                    <div class="px-4 py-2 flex gap-2 border-b border-gray-800 overflow-x-auto bg-gray-900/30">
                        <button onclick="filterCategory(null)" class="category-filter active flex-shrink-0 px-3 py-1.5 bg-orange-500 text-white rounded-full text-xs font-medium" data-category="">
                            Все
                        </button>
                        <button onclick="filterCategory('popular')" class="category-filter flex-shrink-0 px-3 py-1.5 bg-gray-800 text-gray-300 rounded-full text-xs hover:bg-gray-700" data-category="popular">
                            &#x1F525; Хиты
                        </button>
                        @foreach($categories as $category)
                        <button onclick="filterCategory({{ $category->id }})" class="category-filter flex-shrink-0 px-3 py-1.5 bg-gray-800 text-gray-300 rounded-full text-xs hover:bg-gray-700" data-category="{{ $category->id }}">
                            {{ $category->icon ?? '&#x1F4E6;' }} {{ $category->name }}
                        </button>
                        @endforeach
                    </div>

                    {{-- Список товаров --}}
                    <div class="flex-1 overflow-y-auto p-2" id="productsContainer">
                        <div id="productsList" class="grid grid-cols-1 gap-1">
                            {{-- Товары заполняются через JS --}}
                        </div>

                        {{-- Загрузка --}}
                        <div id="productsLoading" class="p-8 text-center">
                            <div class="animate-spin text-3xl mb-2">&#x23F3;</div>
                            <p class="text-gray-500">Загрузка меню...</p>
                        </div>
                    </div>
                </section>
            </div>
        </div>

        {{-- ========== МОДАЛКА: ДОБАВЛЕНИЕ ТОВАРА С МОДИФИКАТОРАМИ ========== --}}
        <div id="productModal" class="fixed inset-0 bg-black/70 z-50 hidden flex items-center justify-center p-4">
            <div class="bg-gray-900 rounded-2xl w-full max-w-md max-h-[90vh] overflow-hidden flex flex-col">
                {{-- Хедер --}}
                <div class="p-4 border-b border-gray-800 flex items-center justify-between">
                    <h3 id="modalProductName" class="text-white font-semibold text-lg">Название товара</h3>
                    <button onclick="closeProductModal()" class="text-gray-500 hover:text-white text-xl">&#x2715;</button>
                </div>

                {{-- Контент --}}
                <div class="flex-1 overflow-y-auto p-4 space-y-4">
                    {{-- Информация о товаре --}}
                    <div class="text-center">
                        <div id="modalProductIcon" class="text-6xl mb-2">&#x1F355;</div>
                        <p id="modalProductWeight" class="text-gray-500 text-sm">450 г</p>
                        <p id="modalProductPrice" class="text-orange-500 text-2xl font-bold mt-2">500 &#x20BD;</p>
                    </div>

                    {{-- Модификаторы --}}
                    <div id="modifiersSection" class="space-y-3">
                        {{-- Заполняется через JS --}}
                    </div>

                    {{-- Комментарий --}}
                    <div>
                        <label class="text-gray-400 text-sm mb-1 block">Комментарий к блюду</label>
                        <input type="text" id="modalComment" placeholder="Без лука, острое..." class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-white text-sm placeholder-gray-500">
                    </div>

                    {{-- Количество --}}
                    <div class="flex items-center justify-center gap-4">
                        <button onclick="changeModalQuantity(-1)" class="w-12 h-12 bg-gray-800 text-white rounded-xl text-xl hover:bg-gray-700">&#x2212;</button>
                        <span id="modalQuantity" class="text-white text-2xl font-bold w-12 text-center">1</span>
                        <button onclick="changeModalQuantity(1)" class="w-12 h-12 bg-gray-800 text-white rounded-xl text-xl hover:bg-gray-700">+</button>
                    </div>
                </div>

                {{-- Футер --}}
                <div class="p-4 border-t border-gray-800">
                    <button onclick="addToCartFromModal()" class="w-full py-4 bg-orange-500 text-white rounded-xl font-bold text-lg hover:bg-orange-600 transition-colors">
                        Добавить &#x2014; <span id="modalTotalPrice">500 &#x20BD;</span>
                    </button>
                </div>
            </div>
        </div>

        {{-- ========== МОДАЛКА: ПОИСК КЛИЕНТА ========== --}}
        <div id="customerModal" class="fixed inset-0 bg-black/70 z-50 hidden flex items-center justify-center p-4">
            <div class="bg-gray-900 rounded-2xl w-full max-w-md overflow-hidden">
                <div class="p-4 border-b border-gray-800 flex items-center justify-between">
                    <h3 class="text-white font-semibold">Выбор клиента</h3>
                    <button onclick="closeCustomerModal()" class="text-gray-500 hover:text-white text-xl">&#x2715;</button>
                </div>

                <div class="p-4">
                    <input type="text" id="customerSearch" placeholder="Поиск по телефону или имени..."
                           class="w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-3 text-white placeholder-gray-500 mb-4"
                           oninput="searchCustomers(this.value)">

                    <div id="customerResults" class="space-y-2 max-h-64 overflow-y-auto">
                        {{-- Результаты поиска --}}
                    </div>

                    <button onclick="showNewCustomerForm()" class="w-full mt-4 py-3 border-2 border-dashed border-gray-700 rounded-xl text-orange-500 hover:border-orange-500 transition-colors">
                        + Новый клиент
                    </button>
                </div>
            </div>
        </div>

        {{-- ========== МОДАЛКА: ЦИФРОВАЯ КЛАВИАТУРА ========== --}}
        <div id="numpadModal" class="fixed inset-0 bg-black/70 z-50 hidden flex items-center justify-center p-4">
            <div class="bg-gray-900 rounded-2xl w-full max-w-xs overflow-hidden">
                <div class="p-4 border-b border-gray-800 flex items-center justify-between">
                    <h3 class="text-white font-semibold">Код товара</h3>
                    <button onclick="closeNumpad()" class="text-gray-500 hover:text-white text-xl">&#x2715;</button>
                </div>

                <div class="p-4">
                    <input type="text" id="numpadInput" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-3 text-white text-2xl text-center font-mono mb-4" readonly>

                    <div class="grid grid-cols-3 gap-2">
                        <button onclick="numpadPress(1)" class="py-4 bg-gray-800 text-white rounded-xl text-xl hover:bg-gray-700">1</button>
                        <button onclick="numpadPress(2)" class="py-4 bg-gray-800 text-white rounded-xl text-xl hover:bg-gray-700">2</button>
                        <button onclick="numpadPress(3)" class="py-4 bg-gray-800 text-white rounded-xl text-xl hover:bg-gray-700">3</button>
                        <button onclick="numpadPress(4)" class="py-4 bg-gray-800 text-white rounded-xl text-xl hover:bg-gray-700">4</button>
                        <button onclick="numpadPress(5)" class="py-4 bg-gray-800 text-white rounded-xl text-xl hover:bg-gray-700">5</button>
                        <button onclick="numpadPress(6)" class="py-4 bg-gray-800 text-white rounded-xl text-xl hover:bg-gray-700">6</button>
                        <button onclick="numpadPress(7)" class="py-4 bg-gray-800 text-white rounded-xl text-xl hover:bg-gray-700">7</button>
                        <button onclick="numpadPress(8)" class="py-4 bg-gray-800 text-white rounded-xl text-xl hover:bg-gray-700">8</button>
                        <button onclick="numpadPress(9)" class="py-4 bg-gray-800 text-white rounded-xl text-xl hover:bg-gray-700">9</button>
                        <button onclick="numpadClear()" class="py-4 bg-red-600 text-white rounded-xl text-xl hover:bg-red-700">C</button>
                        <button onclick="numpadPress(0)" class="py-4 bg-gray-800 text-white rounded-xl text-xl hover:bg-gray-700">0</button>
                        <button onclick="numpadSubmit()" class="py-4 bg-green-600 text-white rounded-xl text-xl hover:bg-green-700">&#x2713;</button>
                    </div>
                </div>
            </div>
        </div>

        {{-- ========== TOAST УВЕДОМЛЕНИЯ ========== --}}
        <div id="toast" class="fixed top-4 right-4 bg-green-500 text-white px-6 py-3 rounded-xl shadow-lg z-50 hidden transition-all">
            <span id="toastMessage">Сообщение</span>
        </div>
    </div>

    <script>
        // ==================== СОСТОЯНИЕ ====================
        const state = {
            tableId: {{ $table->id }},
            tableNumber: {{ $table->number }},
            orderId: {{ $activeOrder?->id ?? 'null' }},
            cart: [],
            products: [],
            categories: @json($categories),
            customer: null,
            activeCategory: null,
            modalProduct: null,
            modalQuantity: 1,
            modalModifiers: [],
        };

        // Axios настройка
        axios.defaults.headers.common['X-CSRF-TOKEN'] = document.querySelector('meta[name="csrf-token"]').content;

        // ==================== ИНИЦИАЛИЗАЦИЯ ====================
        document.addEventListener('DOMContentLoaded', () => {
            loadProducts();
            updateTime();
            setInterval(updateTime, 1000);

            // Загрузить существующий заказ если есть
            @if($activeOrder)
                loadExistingOrder(@json($activeOrder->items));
            @endif

            // Поиск с debounce
            document.getElementById('searchInput').addEventListener('input', debounce(searchProducts, 300));

            // Горячие клавиши
            document.addEventListener('keydown', handleHotkeys);
        });

        // ==================== ЗАГРУЗКА СУЩЕСТВУЮЩЕГО ЗАКАЗА ====================
        function loadExistingOrder(items) {
            if (!items || items.length === 0) return;

            items.forEach(item => {
                state.cart.push({
                    id: item.id,
                    productId: item.dish_id,
                    name: item.name,
                    price: parseFloat(item.price),
                    quantity: item.quantity,
                    modifiers: [],
                    comment: item.notes || '',
                    icon: item.dish?.icon || ''
                });
            });

            renderCart();
            updateTotals();
        }

        // ==================== ЗАГРУЗКА ТОВАРОВ ====================
        async function loadProducts(categoryId = null) {
            const loading = document.getElementById('productsLoading');
            const container = document.getElementById('productsList');

            loading.classList.remove('hidden');
            container.innerHTML = '';

            try {
                let url = '/api/dishes?available=1';
                if (categoryId && categoryId !== 'popular') {
                    url += `&category_id=${categoryId}`;
                }
                if (categoryId === 'popular') {
                    url += '&popular=1';
                }

                const response = await axios.get(url);
                state.products = response.data.data || response.data || [];

                renderProducts();
            } catch (error) {
                console.error('Ошибка загрузки товаров:', error);
                container.innerHTML = '<div class="p-8 text-center text-red-500">Ошибка загрузки меню</div>';
            } finally {
                loading.classList.add('hidden');
            }
        }

        function renderProducts() {
            const container = document.getElementById('productsList');

            if (state.products.length === 0) {
                container.innerHTML = '<div class="p-8 text-center text-gray-500">Товары не найдены</div>';
                return;
            }

            container.innerHTML = state.products.map(product => `
                <div class="product-row flex items-center justify-between px-4 py-3 hover:bg-white/5 rounded-lg cursor-pointer group transition-colors ${product.in_stop ? 'opacity-50' : ''}"
                     onclick="${product.in_stop ? '' : `openProductModal(${product.id})`}">
                    <div class="flex items-center gap-3">
                        <span class="text-lg ${product.in_stop ? 'grayscale' : ''}">${product.icon || '&#x1F37D;'}</span>
                        <div>
                            <p class="text-white text-sm group-hover:text-orange-500 transition-colors ${product.in_stop ? 'line-through text-gray-500' : ''}">
                                ${product.name}
                            </p>
                            <p class="text-gray-500 text-xs">
                                ${product.in_stop ? '<span class="text-red-500">СТОП</span>' : (product.weight ? product.weight + ' г' : '')}
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="${product.in_stop ? 'text-gray-500 line-through' : 'text-orange-500'} font-semibold">${product.price} &#x20BD;</span>
                        ${!product.in_stop ? `
                            <button onclick="event.stopPropagation(); quickAddToCart(${product.id})"
                                    class="add-btn w-8 h-8 bg-orange-500 text-white rounded-lg opacity-0 group-hover:opacity-100 transition-opacity hover:bg-orange-600">+</button>
                        ` : ''}
                    </div>
                </div>
            `).join('');
        }

        // ==================== ФИЛЬТРАЦИЯ ====================
        function filterCategory(categoryId) {
            // Обновить активную кнопку
            document.querySelectorAll('.category-filter').forEach(btn => {
                btn.classList.remove('bg-orange-500', 'text-white');
                btn.classList.add('bg-gray-800', 'text-gray-300');
            });

            const activeBtn = document.querySelector(`.category-filter[data-category="${categoryId || ''}"]`);
            if (activeBtn) {
                activeBtn.classList.remove('bg-gray-800', 'text-gray-300');
                activeBtn.classList.add('bg-orange-500', 'text-white');
            }

            state.activeCategory = categoryId;
            loadProducts(categoryId);
        }

        function searchProducts(event) {
            const query = event.target.value.toLowerCase().trim();

            if (!query) {
                renderProducts();
                return;
            }

            const filtered = state.products.filter(p =>
                p.name.toLowerCase().includes(query) ||
                (p.sku && p.sku.toLowerCase().includes(query))
            );

            const container = document.getElementById('productsList');
            if (filtered.length === 0) {
                container.innerHTML = '<div class="p-8 text-center text-gray-500">Ничего не найдено</div>';
                return;
            }

            container.innerHTML = filtered.map(product => `
                <div class="product-row flex items-center justify-between px-4 py-3 hover:bg-white/5 rounded-lg cursor-pointer group transition-colors"
                     onclick="openProductModal(${product.id})">
                    <div class="flex items-center gap-3">
                        <span class="text-lg">${product.icon || '&#x1F37D;'}</span>
                        <div>
                            <p class="text-white text-sm group-hover:text-orange-500">${product.name}</p>
                            <p class="text-gray-500 text-xs">${product.weight ? product.weight + ' г' : ''}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="text-orange-500 font-semibold">${product.price} &#x20BD;</span>
                        <button onclick="event.stopPropagation(); quickAddToCart(${product.id})"
                                class="add-btn w-8 h-8 bg-orange-500 text-white rounded-lg opacity-0 group-hover:opacity-100 transition-opacity">+</button>
                    </div>
                </div>
            `).join('');
        }

        // ==================== КОРЗИНА ====================
        function quickAddToCart(productId) {
            const product = state.products.find(p => p.id === productId);
            if (!product || product.in_stop) return;

            addToCart({
                productId: product.id,
                name: product.name,
                price: parseFloat(product.price),
                quantity: 1,
                modifiers: [],
                comment: '',
                icon: product.icon || ''
            });

            showToast('Добавлено: ' + product.name, 'success');
        }

        function addToCart(item) {
            // Проверить есть ли уже такой товар без модификаторов
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

        function updateCartItem(itemId, quantity) {
            const item = state.cart.find(i => i.id === itemId);
            if (item) {
                item.quantity = Math.max(0, quantity);
                if (item.quantity === 0) {
                    removeFromCart(itemId);
                } else {
                    renderCart();
                    updateTotals();
                }
            }
        }

        function removeFromCart(itemId) {
            state.cart = state.cart.filter(i => i.id !== itemId);
            renderCart();
            updateTotals();
        }

        function renderCart() {
            const tbody = document.getElementById('cartItems');
            const emptyCart = document.getElementById('emptyCart');

            if (state.cart.length === 0) {
                tbody.innerHTML = '';
                emptyCart.classList.remove('hidden');
                return;
            }

            emptyCart.classList.add('hidden');

            tbody.innerHTML = state.cart.map(item => `
                <tr class="border-b border-gray-800/50 hover:bg-white/5 cursor-pointer cart-item-enter" onclick="editCartItem(${item.id})">
                    <td class="py-3 px-3">
                        <p class="text-white">${item.name}</p>
                        ${item.modifiers.length > 0 ? `<p class="text-orange-400 text-xs">${item.modifiers.map(m => '+ ' + m.name).join(', ')}</p>` : ''}
                        ${item.comment ? `<p class="text-gray-500 text-xs">&#x1F4AC; ${item.comment}</p>` : ''}
                    </td>
                    <td class="text-center text-gray-400">
                        <div class="flex items-center justify-center gap-1">
                            <button onclick="event.stopPropagation(); updateCartItem(${item.id}, ${item.quantity - 1})" class="w-6 h-6 bg-gray-800 rounded hover:bg-gray-700 text-xs">&#x2212;</button>
                            <span class="w-6 text-center">${item.quantity}</span>
                            <button onclick="event.stopPropagation(); updateCartItem(${item.id}, ${item.quantity + 1})" class="w-6 h-6 bg-gray-800 rounded hover:bg-gray-700 text-xs">+</button>
                        </div>
                    </td>
                    <td class="text-right px-3 text-white font-medium">${(item.price * item.quantity).toLocaleString('ru-RU')} &#x20BD;</td>
                </tr>
            `).join('');
        }

        function updateTotals() {
            const subtotal = state.cart.reduce((sum, item) => {
                const modifiersPrice = item.modifiers.reduce((m, mod) => m + (mod.price || 0), 0);
                return sum + ((item.price + modifiersPrice) * item.quantity);
            }, 0);

            document.getElementById('totalAmount').textContent = subtotal.toLocaleString('ru-RU') + ' \u20BD';

            // Активировать кнопку оплаты
            const payBtn = document.getElementById('payBtn');
            payBtn.disabled = state.cart.length === 0;
            if (state.cart.length > 0) {
                payBtn.classList.add('pulse-orange');
            } else {
                payBtn.classList.remove('pulse-orange');
            }
        }

        // ==================== МОДАЛКА ТОВАРА ====================
        function openProductModal(productId) {
            const product = state.products.find(p => p.id === productId);
            if (!product) return;

            state.modalProduct = product;
            state.modalQuantity = 1;
            state.modalModifiers = [];

            document.getElementById('modalProductName').textContent = product.name;
            document.getElementById('modalProductIcon').textContent = product.icon || '\u{1F37D}';
            document.getElementById('modalProductWeight').textContent = product.weight ? product.weight + ' г' : '';
            document.getElementById('modalProductPrice').textContent = product.price + ' \u20BD';
            document.getElementById('modalQuantity').textContent = '1';
            document.getElementById('modalTotalPrice').textContent = product.price + ' \u20BD';
            document.getElementById('modalComment').value = '';
            document.getElementById('modifiersSection').innerHTML = '';

            document.getElementById('productModal').classList.remove('hidden');
        }

        function changeModalQuantity(delta) {
            state.modalQuantity = Math.max(1, state.modalQuantity + delta);
            document.getElementById('modalQuantity').textContent = state.modalQuantity;
            updateModalTotal();
        }

        function updateModalTotal() {
            const basePrice = parseFloat(state.modalProduct.price);
            const modifiersPrice = state.modalModifiers.reduce((sum, m) => sum + m.price, 0);
            const total = (basePrice + modifiersPrice) * state.modalQuantity;

            document.getElementById('modalTotalPrice').textContent = total.toLocaleString('ru-RU') + ' \u20BD';
        }

        function addToCartFromModal() {
            addToCart({
                productId: state.modalProduct.id,
                name: state.modalProduct.name,
                price: parseFloat(state.modalProduct.price) + state.modalModifiers.reduce((sum, m) => sum + m.price, 0),
                quantity: state.modalQuantity,
                modifiers: state.modalModifiers,
                comment: document.getElementById('modalComment').value,
                icon: state.modalProduct.icon || ''
            });

            closeProductModal();
            showToast('Добавлено: ' + state.modalProduct.name, 'success');
        }

        function closeProductModal() {
            document.getElementById('productModal').classList.add('hidden');
            state.modalProduct = null;
        }

        // ==================== КЛИЕНТ ====================
        function showCustomerSearch() {
            document.getElementById('customerModal').classList.remove('hidden');
            document.getElementById('customerSearch').focus();
        }

        function closeCustomerModal() {
            document.getElementById('customerModal').classList.add('hidden');
        }

        async function searchCustomers(query) {
            if (query.length < 2) {
                document.getElementById('customerResults').innerHTML = '';
                return;
            }

            try {
                const response = await axios.get(`/api/customers?search=${encodeURIComponent(query)}`);
                const customers = response.data.data || response.data || [];

                document.getElementById('customerResults').innerHTML = customers.map(c => `
                    <div onclick='selectCustomer(${JSON.stringify(c).replace(/'/g, "\\'")})'
                         class="p-3 bg-gray-800 rounded-lg cursor-pointer hover:bg-gray-700 flex items-center gap-3">
                        <div class="w-10 h-10 bg-orange-500 rounded-full flex items-center justify-center text-white font-bold">
                            ${(c.name || 'Г').substring(0, 2).toUpperCase()}
                        </div>
                        <div class="flex-1">
                            <p class="text-white">${c.name || 'Гость'}</p>
                            <p class="text-gray-500 text-sm">${c.phone || ''}</p>
                        </div>
                        ${c.is_vip ? '<span class="bg-yellow-500 text-black text-xs px-2 py-1 rounded-full font-bold">VIP</span>' : ''}
                    </div>
                `).join('') || '<p class="text-gray-500 text-center p-4">Клиенты не найдены</p>';
            } catch (error) {
                console.error('Ошибка поиска клиентов:', error);
            }
        }

        function selectCustomer(customer) {
            state.customer = customer;

            document.getElementById('customerInfo').classList.remove('hidden');
            document.getElementById('customerAvatar').textContent = (customer.name || 'Г').substring(0, 2).toUpperCase();
            document.getElementById('customerName').textContent = customer.name || 'Гость';
            document.getElementById('customerPhone').textContent = customer.phone || '';

            closeCustomerModal();
        }

        function removeCustomer() {
            state.customer = null;
            document.getElementById('customerInfo').classList.add('hidden');
        }

        // ==================== ОТПРАВКА ЗАКАЗА ====================
        async function sendToKitchen() {
            if (state.cart.length === 0) {
                showToast('Корзина пуста', 'error');
                return;
            }

            try {
                const orderData = {
                    type: 'dine_in',
                    table_id: state.tableId,
                    customer_id: state.customer?.id,
                    items: state.cart.map(item => ({
                        dish_id: item.productId,
                        quantity: item.quantity,
                        modifiers: item.modifiers.map(m => m.id),
                        notes: item.comment
                    }))
                };

                let response;
                if (state.orderId) {
                    // Добавить позиции к существующему заказу
                    for (const item of orderData.items) {
                        await axios.post(`/api/orders/${state.orderId}/items`, item);
                    }
                    response = { data: { success: true, data: { id: state.orderId } } };
                } else {
                    response = await axios.post('/api/orders', orderData);
                }

                if (response.data.success) {
                    state.orderId = response.data.data.id;
                    document.getElementById('orderNumber').textContent = '#' + state.orderId;
                    showToast('Заказ отправлен на кухню!', 'success');
                } else {
                    showToast(response.data.message || 'Ошибка', 'error');
                }
            } catch (error) {
                console.error('Ошибка:', error);
                showToast('Ошибка соединения', 'error');
            }
        }

        // ==================== ОПЛАТА ====================
        async function payByCash() {
            await processPayment('cash');
        }

        async function payByCard() {
            await processPayment('card');
        }

        async function processPayment(method) {
            if (state.cart.length === 0) {
                showToast('Корзина пуста', 'error');
                return;
            }

            // Сначала создаём/обновляем заказ
            if (!state.orderId) {
                await sendToKitchen();
            }

            if (!state.orderId) {
                showToast('Сначала создайте заказ', 'error');
                return;
            }

            try {
                const response = await axios.post(`/api/orders/${state.orderId}/pay`, {
                    method: method
                });

                if (response.data.success) {
                    showToast('Оплата принята!', 'success');
                    // Перенаправляем в зал
                    setTimeout(() => {
                        window.location.href = '/pos-vue#hall';
                    }, 1500);
                } else {
                    showToast(response.data.message || 'Ошибка оплаты', 'error');
                }
            } catch (error) {
                console.error('Ошибка оплаты:', error);
                showToast('Ошибка оплаты', 'error');
            }
        }

        function showPaymentModal() {
            // Можно добавить модалку выбора способа оплаты
            payByCard();
        }

        function printPrecheck() {
            showToast('Печать пречека...', 'info');
        }

        function showMoreActions() {
            showToast('Дополнительные действия', 'info');
        }

        function showNewCustomerForm() {
            showToast('Форма нового клиента', 'info');
        }

        function editCartItem(itemId) {
            // Можно открыть модалку редактирования
            console.log('Edit item:', itemId);
        }

        // ==================== NUMPAD ====================
        document.getElementById('numpadBtn').addEventListener('click', () => {
            document.getElementById('numpadModal').classList.remove('hidden');
            document.getElementById('numpadInput').value = '';
        });

        function closeNumpad() {
            document.getElementById('numpadModal').classList.add('hidden');
        }

        function numpadPress(num) {
            document.getElementById('numpadInput').value += num;
        }

        function numpadClear() {
            document.getElementById('numpadInput').value = '';
        }

        function numpadSubmit() {
            const code = document.getElementById('numpadInput').value;
            if (code) {
                // Найти товар по коду
                const product = state.products.find(p => p.sku === code || p.id.toString() === code);
                if (product) {
                    quickAddToCart(product.id);
                    closeNumpad();
                } else {
                    showToast('Товар не найден', 'error');
                }
            }
        }

        // ==================== УТИЛИТЫ ====================
        function updateTime() {
            const now = new Date();
            document.getElementById('currentTime').textContent =
                now.toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' });
        }

        function debounce(func, wait) {
            let timeout;
            return function(...args) {
                clearTimeout(timeout);
                timeout = setTimeout(() => func.apply(this, args), wait);
            };
        }

        function handleHotkeys(e) {
            // Escape - закрыть модалки
            if (e.key === 'Escape') {
                closeProductModal();
                closeCustomerModal();
                closeNumpad();
            }

            // F2 - поиск
            if (e.key === 'F2') {
                e.preventDefault();
                document.getElementById('searchInput').focus();
            }

            // F4 - клиент
            if (e.key === 'F4') {
                e.preventDefault();
                showCustomerSearch();
            }

            // F9 - отправить на кухню
            if (e.key === 'F9') {
                e.preventDefault();
                sendToKitchen();
            }
        }

        function showToast(message, type = 'info') {
            const toast = document.getElementById('toast');
            const toastMessage = document.getElementById('toastMessage');

            const colors = {
                success: 'bg-green-500',
                error: 'bg-red-500',
                info: 'bg-blue-500'
            };

            toast.className = `fixed top-4 right-4 ${colors[type]} text-white px-6 py-3 rounded-xl shadow-lg z-50 transition-all`;
            toastMessage.textContent = message;
            toast.classList.remove('hidden');

            setTimeout(() => {
                toast.classList.add('hidden');
            }, 3000);
        }
    </script>
</body>
</html>
