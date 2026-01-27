{{-- Модалка нового заказа --}}

<div id="newOrderModal" class="fixed inset-0 bg-black/80 flex items-center justify-center z-50 hidden">
    <div class="bg-gray-900 rounded-2xl w-full max-w-6xl max-h-[95vh] flex flex-col border border-gray-700 shadow-2xl">

        {{-- Заголовок --}}
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-700">
            <h2 class="text-xl font-bold text-white">Новый заказ на доставку</h2>
            <button onclick="closeNewOrderModal()" class="w-10 h-10 flex items-center justify-center text-gray-400 hover:text-white hover:bg-gray-800 rounded-lg btn text-xl">✕</button>
        </div>

        {{-- Тип заказа --}}
        <div class="px-6 py-3 border-b border-gray-700 flex items-center gap-4">
            <span class="text-gray-400 text-sm">Тип:</span>
            <div class="flex gap-2">
                <button onclick="setOrderType('delivery')"
                        class="order-type-btn px-4 py-2 rounded-lg text-sm font-medium btn bg-accent text-white"
                        data-type="delivery">
                    🛵 Доставка
                </button>
                <button onclick="setOrderType('pickup')"
                        class="order-type-btn px-4 py-2 rounded-lg text-sm font-medium btn bg-gray-800 text-gray-300"
                        data-type="pickup">
                    🏃 Самовывоз
                </button>
            </div>
        </div>

        {{-- Основной контент --}}
        <div class="flex-1 flex overflow-hidden">

            {{-- Левая часть: Меню --}}
            <div class="w-[60%] flex flex-col border-r border-gray-700">
                {{-- Категории --}}
                <div class="px-4 py-3 border-b border-gray-700 flex gap-2 overflow-x-auto">
                    <button onclick="loadProducts(); filterCategory(null, this)"
                            class="category-btn px-4 py-2 rounded-lg text-sm font-medium btn whitespace-nowrap bg-accent text-white">
                        Все
                    </button>
                    @foreach($categories as $category)
                        <button onclick="filterCategory({{ $category->id }}, this)"
                                class="category-btn px-4 py-2 rounded-lg text-sm font-medium btn whitespace-nowrap bg-gray-800 text-gray-300 hover:bg-gray-700">
                            {{ $category->icon ?? '🍽' }} {{ $category->name }}
                        </button>
                    @endforeach
                </div>

                {{-- Сетка товаров --}}
                <div class="flex-1 p-4 overflow-y-auto">
                    <div id="productsGrid" class="grid grid-cols-3 gap-3">
                        {{-- Товары загружаются через JS --}}
                    </div>
                </div>
            </div>

            {{-- Правая часть: Форма и корзина --}}
            <div class="w-[40%] flex flex-col bg-gray-800/30">
                <form id="newOrderForm" class="flex-1 flex flex-col overflow-hidden">

                    {{-- Данные клиента --}}
                    <div class="p-4 border-b border-gray-700">
                        <h3 class="text-gray-400 text-sm mb-3">👤 Данные клиента</h3>
                        <div class="grid grid-cols-2 gap-3">
                            <input type="text" name="customer_name" placeholder="Имя *"
                                   class="px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-white text-sm focus:border-accent focus:outline-none" required>
                            <input type="tel" name="customer_phone" placeholder="Телефон *"
                                   class="px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-white text-sm focus:border-accent focus:outline-none" required>
                        </div>
                    </div>

                    {{-- Адрес доставки --}}
                    <div id="addressSection" class="p-4 border-b border-gray-700">
                        <h3 class="text-gray-400 text-sm mb-3">📍 Адрес доставки</h3>
                        <div class="space-y-3">
                            <div class="grid grid-cols-2 gap-3">
                                <input type="text" name="address_street" placeholder="Улица *"
                                       class="px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-white text-sm focus:border-accent focus:outline-none">
                                <input type="text" name="address_house" placeholder="Дом *"
                                       class="px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-white text-sm focus:border-accent focus:outline-none">
                            </div>
                            <div class="grid grid-cols-4 gap-2">
                                <input type="text" name="address_apartment" placeholder="Кв."
                                       class="px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-white text-sm focus:border-accent focus:outline-none">
                                <input type="text" name="address_entrance" placeholder="Подъезд"
                                       class="px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-white text-sm focus:border-accent focus:outline-none">
                                <input type="text" name="address_floor" placeholder="Этаж"
                                       class="px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-white text-sm focus:border-accent focus:outline-none">
                                <input type="text" name="address_intercom" placeholder="Домофон"
                                       class="px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-white text-sm focus:border-accent focus:outline-none">
                            </div>
                            <input type="text" name="address_comment" placeholder="Комментарий к адресу..."
                                   class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-white text-sm focus:border-accent focus:outline-none">
                        </div>
                    </div>

                    {{-- Корзина --}}
                    <div class="flex-1 flex flex-col min-h-0">
                        <div class="px-4 py-3 border-b border-gray-700 flex items-center justify-between">
                            <h3 class="text-gray-400 text-sm">🛒 Корзина</h3>
                            <button type="button" onclick="clearCart()" class="text-gray-500 hover:text-red-400 text-xs">🗑️ Очистить</button>
                        </div>

                        <div class="flex-1 overflow-y-auto p-4">
                            <div id="emptyDeliveryCart" class="flex flex-col items-center justify-center h-full text-gray-500">
                                <span class="text-4xl mb-2">🛒</span>
                                <p class="text-sm">Корзина пуста</p>
                            </div>
                            <div id="deliveryCartItems" class="space-y-2 hidden">
                                {{-- Позиции добавляются через JS --}}
                            </div>
                        </div>
                    </div>

                    {{-- Оплата --}}
                    <div class="p-4 border-t border-gray-700">
                        <h3 class="text-gray-400 text-sm mb-3">💳 Способ оплаты</h3>
                        <div class="flex gap-2 mb-3">
                            <button type="button" onclick="setPaymentMethod('cash')"
                                    class="payment-btn flex-1 px-3 py-2 rounded-lg text-sm font-medium btn bg-green-600 text-white"
                                    data-method="cash">
                                💵 Наличные
                            </button>
                            <button type="button" onclick="setPaymentMethod('card')"
                                    class="payment-btn flex-1 px-3 py-2 rounded-lg text-sm font-medium btn bg-gray-800 text-gray-300"
                                    data-method="card">
                                💳 Картой
                            </button>
                            <button type="button" onclick="setPaymentMethod('online')"
                                    class="payment-btn flex-1 px-3 py-2 rounded-lg text-sm font-medium btn bg-gray-800 text-gray-300"
                                    data-method="online">
                                📱 Онлайн
                            </button>
                        </div>
                        <div id="changeSection">
                            <input type="number" name="change_from" placeholder="Сдача с какой суммы..."
                                   class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-white text-sm focus:border-accent focus:outline-none">
                        </div>
                    </div>

                    {{-- Итого --}}
                    <div class="p-4 border-t border-gray-700 bg-gray-900/50">
                        <div class="space-y-2 mb-4">
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-400">Товары:</span>
                                <span id="subtotalAmount" class="text-white">0 ₽</span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-400">Доставка:</span>
                                <span id="deliveryCostAmount" class="text-white">0 ₽</span>
                            </div>
                            <div class="flex justify-between text-lg font-bold pt-2 border-t border-gray-700">
                                <span class="text-white">Итого:</span>
                                <span id="totalAmount" class="text-accent">0 ₽</span>
                            </div>
                        </div>

                        <button type="button" onclick="submitOrder()" id="submitOrderBtn"
                                class="w-full py-3 bg-accent hover:bg-orange-600 text-white rounded-xl font-bold btn disabled:opacity-50 disabled:cursor-not-allowed"
                                disabled>
                            Создать заказ — <span id="submitBtnAmount">0 ₽</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
