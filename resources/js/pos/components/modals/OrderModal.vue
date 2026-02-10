<template>
    <Teleport to="body">
        <div v-if="show" class="fixed inset-0 bg-black/70 flex items-center justify-center z-[9999] p-4" role="dialog" aria-modal="true" aria-labelledby="order-modal-title" data-testid="order-modal">
            <div class="bg-dark-800 rounded-2xl w-full max-w-7xl max-h-[95vh] flex flex-col border border-gray-700 shadow-2xl">
                <!-- Header -->
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-700">
                    <div class="flex items-center gap-6">
                        <h2 id="order-modal-title" class="text-xl font-bold text-white">{{ currentOrder ? 'Заказ #' + currentOrder.id : 'Новый заказ' }}</h2>
                        <div v-if="table" class="flex items-center gap-2 text-sm">
                            <span class="px-3 py-1.5 bg-accent/20 text-accent rounded-lg font-medium">
                                Стол {{ table.number }} • {{ table.seats }} мест
                            </span>
                        </div>
                    </div>
                    <button @click="close" class="w-10 h-10 flex items-center justify-center text-gray-400 hover:text-white hover:bg-dark-700 rounded-lg text-xl">✕</button>
                </div>

                <!-- Info Bar -->
                <div class="px-6 py-3 border-b border-gray-700 bg-dark-900/50">
                    <div class="flex items-center justify-between">
                        <div class="flex gap-4 items-center">
                            <div class="flex items-center gap-2">
                                <span class="text-gray-400 text-sm">Гостей:</span>
                                <div class="flex items-center gap-1">
                                    <button @click="guestsCount = Math.max(1, guestsCount - 1)" class="w-7 h-7 bg-dark-800 rounded-lg text-white hover:bg-dark-700">-</button>
                                    <span class="w-8 text-center text-white font-bold">{{ guestsCount }}</span>
                                    <button @click="guestsCount = Math.min(table?.seats || 20, guestsCount + 1)" class="w-7 h-7 bg-dark-800 rounded-lg text-white hover:bg-dark-700">+</button>
                                </div>
                            </div>
                            <!-- Price List Selector -->
                            <div v-if="posStore.availablePriceLists.length" class="flex items-center gap-2">
                                <span class="text-gray-400 text-sm">Прайс:</span>
                                <select
                                    v-model="orderPriceListId"
                                    @change="onPriceListChange"
                                    class="bg-dark-800 border border-gray-700 rounded-lg text-white text-sm px-3 py-1.5 focus:outline-none focus:border-accent cursor-pointer"
                                >
                                    <option :value="null">Базовые цены</option>
                                    <option v-for="pl in posStore.availablePriceLists" :key="pl.id" :value="pl.id">
                                        {{ pl.name }}
                                    </option>
                                </select>
                            </div>
                        </div>
                        <div class="flex items-center gap-4 text-sm">
                            <span class="text-gray-400">Время: <span class="text-white">{{ currentTime }}</span></span>
                        </div>
                    </div>
                </div>

                <!-- Main Content -->
                <div class="flex-1 flex overflow-hidden">
                    <!-- Left: Menu -->
                    <div class="w-[60%] flex flex-col min-w-0 border-r border-gray-700">
                        <!-- Search -->
                        <div class="px-4 py-3 border-b border-gray-700 flex gap-3">
                            <div class="relative flex-1">
                                <input v-model="menuSearch" type="text" placeholder="Поиск блюда..."
                                       class="w-full pl-10 pr-10 py-2.5 bg-dark-900 border border-gray-700 rounded-xl text-white text-sm focus:outline-none focus:border-accent">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500">🔍</span>
                                <button v-if="menuSearch" @click="menuSearch = ''" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-white">✕</button>
                            </div>
                        </div>

                        <!-- Categories -->
                        <div class="px-4 py-2 border-b border-gray-700">
                            <div class="flex items-center gap-2">
                                <button @click="scrollCategories(-1)" class="w-8 h-8 flex-shrink-0 bg-dark-900 rounded-lg text-gray-400 hover:text-white hover:bg-dark-800 flex items-center justify-center">◀</button>
                                <div ref="categoriesContainer" class="flex-1 flex gap-2 overflow-x-auto scroll-smooth" style="scrollbar-width: none;">
                                    <button v-for="cat in categories" :key="cat.id"
                                            @click="selectedCategory = cat.id; menuSearch = ''"
                                            :data-testid="`category-${cat.id}`"
                                            :class="['px-4 py-2 rounded-lg text-sm font-medium whitespace-nowrap flex items-center gap-1.5 transition-all',
                                                     selectedCategory === cat.id && !menuSearch
                                                     ? 'bg-accent text-white shadow-lg shadow-accent/20'
                                                     : 'bg-dark-900 text-gray-400 hover:bg-dark-800']">
                                        <span>{{ cat.icon || '🍽️' }}</span>
                                        <span>{{ cat.name }}</span>
                                    </button>
                                </div>
                                <button @click="scrollCategories(1)" class="w-8 h-8 flex-shrink-0 bg-dark-900 rounded-lg text-gray-400 hover:text-white hover:bg-dark-800 flex items-center justify-center">▶</button>
                            </div>
                        </div>

                        <!-- Search Results Info -->
                        <div v-if="menuSearch" class="px-4 py-2 bg-dark-900/50 text-sm text-gray-400 border-b border-gray-700">
                            Найдено: <span class="text-white font-medium">{{ filteredDishes.length }}</span> блюд
                        </div>

                        <!-- Dishes Grid -->
                        <div class="flex-1 p-4 overflow-y-auto">
                            <div v-if="dishesLoading" class="flex items-center justify-center h-full">
                                <div class="animate-spin w-8 h-8 border-4 border-accent border-t-transparent rounded-full"></div>
                            </div>
                            <div v-else-if="!filteredDishes.length" class="flex flex-col items-center justify-center h-full text-gray-500">
                                <span class="text-5xl mb-4">🍽️</span>
                                <p class="text-lg">{{ !orderPriceListId ? 'Прайс-лист не выбран' : 'Блюда не найдены' }}</p>
                                <p v-if="!orderPriceListId" class="text-sm mt-2">Выберите прайс-лист или настройте его в бэк-офисе</p>
                            </div>
                            <div v-else class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3">
                                <div v-for="dish in filteredDishes" :key="dish.id"
                                     @click="!dish.is_stopped && addToCart(dish)"
                                     :data-testid="`dish-${dish.id}`"
                                     :class="['bg-dark-900 rounded-xl overflow-hidden border border-gray-700 transition-all group',
                                              dish.is_stopped ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer hover:border-accent hover:shadow-lg hover:shadow-accent/10']">
                                    <div class="relative h-28 bg-dark-950 overflow-hidden">
                                        <img v-if="dish.image" :src="dish.image" :alt="dish.name"
                                             class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                                        <div v-else class="w-full h-full flex items-center justify-center bg-gradient-to-br from-dark-800 to-dark-900">
                                            <span class="text-4xl opacity-50">{{ dish.emoji || '🍽️' }}</span>
                                        </div>
                                        <div v-if="dish.is_stopped" class="absolute inset-0 bg-black/70 flex items-center justify-center">
                                            <span class="bg-red-600 text-white text-sm font-bold px-4 py-1.5 rounded-lg transform -rotate-12">СТОП</span>
                                        </div>
                                        <div class="absolute top-2 left-2 flex flex-col gap-1">
                                            <span v-if="dish.is_popular" class="bg-orange-500 text-white text-xs px-2 py-0.5 rounded font-medium">🔥 Хит</span>
                                            <span v-if="dish.is_new" class="bg-green-500 text-white text-xs px-2 py-0.5 rounded font-medium">🆕</span>
                                        </div>
                                    </div>
                                    <div class="p-3">
                                        <p class="text-white font-medium text-sm mb-1 line-clamp-2 min-h-[2.5rem]">{{ dish.name }}</p>
                                        <div class="flex items-center gap-3 text-xs text-gray-500 mb-2">
                                            <span v-if="dish.weight">⚖️ {{ dish.weight }}г</span>
                                            <span v-if="dish.cook_time">⏱️ {{ dish.cook_time }} мин</span>
                                        </div>
                                        <div class="flex items-center justify-between">
                                            <p :class="['font-bold text-lg', dish.is_stopped ? 'text-gray-500 line-through' : 'text-accent']">
                                                <span v-if="dish.product_type === 'parent'">от </span>{{ dish.product_type === 'parent' ? (dish.min_price || getMinVariantPrice(dish)) : (dish.resolved_price ?? dish.price) }} ₽
                                            </p>
                                            <button v-if="!dish.is_stopped"
                                                    @click.stop="addToCart(dish)"
                                                    class="w-10 h-10 bg-accent hover:bg-orange-500 rounded-xl flex items-center justify-center text-white text-xl font-bold shadow-lg shadow-accent/30 transition-all hover:scale-105">
                                                +
                                            </button>
                                        </div>
                                        <!-- Variant indicator -->
                                        <div v-if="dish.product_type === 'parent' && dish.variants?.length" class="mt-2 flex flex-wrap gap-1">
                                            <span v-for="v in dish.variants.slice(0, 3)" :key="v.id"
                                                  class="px-2 py-0.5 bg-purple-600/30 text-purple-300 text-xs rounded">
                                                {{ v.variant_name }}
                                            </span>
                                            <span v-if="dish.variants.length > 3" class="px-2 py-0.5 bg-purple-600/20 text-purple-400 text-xs rounded">
                                                +{{ dish.variants.length - 3 }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Cart -->
                    <div class="w-[40%] flex flex-col bg-dark-900/50 flex-shrink-0">
                        <!-- Cart Header -->
                        <div class="px-4 py-3 border-b border-gray-700 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <h3 class="text-sm font-medium text-gray-400">Позиции заказа</h3>
                                <span v-if="cart.length" class="px-2 py-0.5 bg-accent/20 text-accent text-xs rounded-full font-medium">{{ cart.length }}</span>
                            </div>
                            <button v-if="cart.length" @click="clearCart" class="text-gray-500 hover:text-red-400 text-xs flex items-center gap-1">
                                <span>🗑️</span> Очистить
                            </button>
                        </div>

                        <!-- Cart Items -->
                        <div class="flex-1 overflow-y-auto p-3 space-y-3">
                            <div v-if="!cart.length" class="flex flex-col items-center justify-center h-full text-gray-500">
                                <span class="text-5xl mb-3 opacity-50">🛒</span>
                                <p class="text-sm">Корзина пуста</p>
                                <p class="text-xs mt-1">Выберите блюда из меню слева</p>
                            </div>

                            <div v-for="(item, index) in cart" :key="index"
                                 class="bg-dark-800 rounded-xl p-3 border border-gray-700 hover:border-gray-600 transition-all">
                                <div class="flex items-start gap-3">
                                    <div class="w-10 h-10 bg-dark-900 rounded-lg flex items-center justify-center flex-shrink-0">
                                        <span class="text-xl">{{ item.emoji || '🍽️' }}</span>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-white text-sm font-medium">{{ item.name }}</p>
                                        <div v-if="item.modifiers && item.modifiers.length" class="mt-1 flex flex-wrap gap-1">
                                            <span v-for="mod in item.modifiers" :key="mod.id"
                                                  class="px-2 py-0.5 bg-purple-600/30 text-purple-300 text-xs rounded">
                                                {{ mod.name }} <span v-if="mod.price">+{{ mod.price }}₽</span>
                                            </span>
                                        </div>
                                        <p v-if="item.notes" class="text-gray-500 text-xs mt-1">💬 {{ item.notes }}</p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-white font-medium">{{ itemTotal(item) }} ₽</p>
                                    </div>
                                </div>
                                <div class="flex items-center justify-between mt-3 pt-3 border-t border-gray-700/50">
                                    <div class="flex items-center gap-2">
                                        <button @click="updateQuantity(index, -1)" class="w-8 h-8 rounded-lg bg-dark-900 text-white hover:bg-dark-700 flex items-center justify-center">−</button>
                                        <span class="w-8 text-center text-white font-bold">{{ item.quantity }}</span>
                                        <button @click="updateQuantity(index, 1)" class="w-8 h-8 rounded-lg bg-dark-900 text-white hover:bg-dark-700 flex items-center justify-center">+</button>
                                    </div>
                                    <button @click="removeFromCart(index)" class="w-8 h-8 rounded-lg bg-dark-900 text-gray-400 hover:text-red-400 hover:bg-dark-700 flex items-center justify-center text-sm">🗑️</button>
                                </div>
                            </div>
                        </div>

                        <!-- Comment -->
                        <div v-if="cart.length" class="px-4 py-2 border-t border-gray-700">
                            <input v-model="orderComment" type="text" placeholder="💬 Комментарий к заказу..."
                                   class="w-full px-3 py-2 bg-dark-800 border border-gray-700 rounded-lg text-white text-sm focus:outline-none focus:border-accent">
                        </div>

                        <!-- Total & Actions -->
                        <div class="p-4 border-t border-gray-700 space-y-3 bg-dark-950/50">
                            <div class="space-y-2">
                                <div class="flex justify-between items-center text-sm">
                                    <span class="text-gray-500">Подытог:</span>
                                    <span class="text-gray-400">{{ formatMoney(cartSubtotal) }} ₽</span>
                                </div>
                                <div class="flex justify-between items-center pt-2 border-t border-gray-700">
                                    <span class="text-white font-medium">ИТОГО:</span>
                                    <span class="text-2xl font-bold text-accent" data-testid="order-total">{{ formatMoney(cartTotal) }} ₽</span>
                                </div>
                            </div>

                            <button @click="submitOrder"
                                    :disabled="!cart.length || submitting"
                                    data-testid="submit-order-btn"
                                    :class="['w-full py-4 rounded-xl font-bold text-lg transition-all flex items-center justify-center gap-2',
                                             cart.length && !submitting ? 'bg-accent hover:bg-orange-500 text-white shadow-lg shadow-accent/30' : 'bg-gray-700 text-gray-500 cursor-not-allowed']">
                                <span v-if="submitting" class="animate-spin">⏳</span>
                                <span v-else>✓</span>
                                {{ currentOrder ? 'Сохранить' : 'Создать заказ' }} — {{ formatMoney(cartTotal) }} ₽
                            </button>

                            <div class="flex gap-2">
                                <button v-if="currentOrder"
                                        @click="$emit('openPayment', currentOrder)"
                                        data-testid="goto-payment-btn"
                                        class="flex-1 py-3 rounded-xl font-medium bg-green-600 hover:bg-green-700 text-white text-sm flex items-center justify-center gap-2">
                                    💳 К оплате
                                </button>
                                <button @click="close" class="flex-1 py-3 rounded-xl font-medium bg-dark-800 hover:bg-dark-700 text-gray-400 text-sm">
                                    Закрыть
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Remove Item Modal -->
        <div v-if="showRemoveItemModal" class="fixed inset-0 bg-black/80 flex items-center justify-center z-[10000] p-4">
            <div class="bg-dark-900 rounded-xl w-full max-w-md border border-dark-700">
                <!-- Header -->
                <div class="p-4 border-b border-dark-700 flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-white">Удаление позиции</h3>
                    <button v-if="removeItemMode && !canRemoveItems" @click="removeItemMode = null" class="text-gray-400 hover:text-white">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                    </button>
                </div>

                <div class="p-4 space-y-4">
                    <!-- Item info -->
                    <div v-if="removeItemIndex !== null && cart[removeItemIndex]" class="bg-dark-800 rounded-lg p-3">
                        <p class="text-white font-medium">{{ cart[removeItemIndex].name }}</p>
                        <p class="text-gray-400 text-sm">{{ cart[removeItemIndex].quantity }} × {{ cart[removeItemIndex].price }} ₽</p>
                    </div>

                    <div class="bg-yellow-500/10 border border-yellow-500/30 rounded-lg p-3">
                        <p class="text-yellow-400 text-sm">Заказ уже отправлен на кухню. Удаление требует подтверждения.</p>
                    </div>

                    <!-- Выбор режима (для не-менеджеров) -->
                    <div v-if="removeItemMode === null" class="space-y-3">
                        <button
                            @click="selectRemoveMode('pin')"
                            class="w-full p-4 bg-dark-800 hover:bg-dark-700 rounded-lg text-left transition-colors border border-dark-600 hover:border-accent"
                        >
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-lg bg-accent/20 flex items-center justify-center">
                                    <svg class="w-5 h-5 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                    </svg>
                                </div>
                                <div>
                                    <div class="text-white font-medium">Ввести PIN менеджера</div>
                                    <div class="text-gray-500 text-sm">Удаление сразу</div>
                                </div>
                            </div>
                        </button>
                        <button
                            @click="selectRemoveMode('request')"
                            class="w-full p-4 bg-dark-800 hover:bg-dark-700 rounded-lg text-left transition-colors border border-dark-600 hover:border-orange-500"
                        >
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-lg bg-orange-500/20 flex items-center justify-center">
                                    <svg class="w-5 h-5 text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                </div>
                                <div>
                                    <div class="text-white font-medium">Отправить заявку</div>
                                    <div class="text-gray-500 text-sm">После одобрения менеджером</div>
                                </div>
                            </div>
                        </button>
                    </div>

                    <!-- Режим PIN -->
                    <template v-if="removeItemMode === 'pin'">
                        <div>
                            <label class="block text-sm text-gray-400 mb-2">PIN менеджера</label>
                            <input
                                v-model="removeManagerPin"
                                type="password"
                                maxlength="4"
                                placeholder="****"
                                class="w-full px-4 py-3 bg-dark-800 border border-dark-600 rounded-lg text-white text-center text-2xl tracking-widest focus:border-accent focus:outline-none"
                                :class="removePinError ? 'border-red-500' : ''"
                            />
                            <p v-if="removePinError" class="text-red-400 text-sm mt-1">{{ removePinError }}</p>
                        </div>
                        <div>
                            <label class="block text-sm text-gray-400 mb-2">Причина</label>
                            <textarea v-model="removeReason" rows="2" placeholder="Укажите причину..." class="w-full px-3 py-2 bg-dark-800 border border-dark-600 rounded-lg text-white resize-none focus:border-accent focus:outline-none"></textarea>
                        </div>
                    </template>

                    <!-- Режим заявки -->
                    <template v-if="removeItemMode === 'request'">
                        <div>
                            <label class="block text-sm text-gray-400 mb-2">Причина удаления <span class="text-red-400">*</span></label>
                            <textarea v-model="removeReason" rows="3" placeholder="Обязательно укажите причину..." class="w-full px-3 py-2 bg-dark-800 border border-dark-600 rounded-lg text-white resize-none focus:border-accent focus:outline-none" :class="removePinError ? 'border-red-500' : ''"></textarea>
                            <p v-if="removePinError" class="text-red-400 text-sm mt-1">{{ removePinError }}</p>
                        </div>
                    </template>

                    <!-- Режим direct (менеджер) -->
                    <template v-if="removeItemMode === 'direct'">
                        <div>
                            <label class="block text-sm text-gray-400 mb-2">Причина</label>
                            <textarea v-model="removeReason" rows="2" placeholder="Укажите причину..." class="w-full px-3 py-2 bg-dark-800 border border-dark-600 rounded-lg text-white resize-none focus:border-accent focus:outline-none"></textarea>
                        </div>
                    </template>
                </div>

                <!-- Footer -->
                <div class="p-4 border-t border-dark-700 flex gap-3">
                    <button
                        @click="closeRemoveItemModal"
                        class="flex-1 py-2.5 bg-dark-800 hover:bg-dark-700 rounded-lg text-gray-300 font-medium transition-colors"
                    >
                        Отмена
                    </button>
                    <button
                        v-if="removeItemMode"
                        @click="confirmRemoveItem"
                        :disabled="removeLoading || (removeItemMode === 'pin' && removeManagerPin.length < 4) || (removeItemMode === 'request' && !removeReason.trim())"
                        class="flex-1 py-2.5 bg-red-600 hover:bg-red-700 disabled:bg-red-600/50 disabled:cursor-not-allowed rounded-lg text-white font-medium transition-colors"
                    >
                        <span v-if="removeLoading">Обработка...</span>
                        <span v-else-if="removeItemMode === 'request'">Отправить заявку</span>
                        <span v-else>Удалить</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Variant Selector Modal -->
        <div v-if="showVariantSelector" class="fixed inset-0 bg-black/80 flex items-center justify-center z-[10001] p-4">
            <div class="bg-dark-900 rounded-2xl w-full max-w-md border border-gray-700 shadow-2xl overflow-hidden">
                <!-- Header -->
                <div class="px-6 py-4 border-b border-gray-700 bg-gradient-to-r from-purple-600 to-violet-600">
                    <h3 class="text-lg font-bold text-white">{{ selectedParentDish?.name }}</h3>
                    <p class="text-purple-200 text-sm">Выберите вариант</p>
                </div>

                <!-- Variants List -->
                <div class="p-4 space-y-2 max-h-[50vh] overflow-y-auto">
                    <button
                        v-for="variant in selectedParentDish?.variants"
                        :key="variant.id"
                        @click="selectVariant(variant)"
                        :disabled="variant.is_stopped"
                        :class="[
                            'w-full p-4 rounded-xl border-2 text-left transition-all',
                            variant.is_stopped
                                ? 'border-gray-700 bg-dark-800/50 opacity-50 cursor-not-allowed'
                                : 'border-gray-700 bg-dark-800 hover:border-purple-500 hover:bg-purple-900/20'
                        ]"
                    >
                        <div class="flex items-center justify-between">
                            <div class="text-white font-medium text-lg">{{ variant.variant_name }}</div>
                            <div class="text-right">
                                <div :class="['text-xl font-bold', variant.is_stopped ? 'text-gray-500 line-through' : 'text-accent']">
                                    {{ variant.resolved_price ?? variant.price }} ₽
                                </div>
                                <div v-if="variant.is_stopped" class="text-xs text-red-400">СТОП</div>
                            </div>
                        </div>
                    </button>
                </div>

                <!-- Footer -->
                <div class="p-4 border-t border-gray-700 bg-dark-950">
                    <button
                        @click="closeVariantSelector"
                        class="w-full py-3 bg-dark-800 hover:bg-dark-700 rounded-xl text-gray-400 font-medium transition-colors"
                    >
                        Отмена
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted, PropType } from 'vue';
import { usePosStore } from '../../stores/pos';
import { useAuthStore } from '../../stores/auth';
import api from '../../api';
import { createLogger } from '../../../shared/services/logger.js';

const log = createLogger('POS:Order');

const authStore = useAuthStore();

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    table: { type: Object as PropType<Record<string, any>>, default: null },
    order: { type: Object as PropType<Record<string, any>>, default: null }
});

const emit = defineEmits(['update:modelValue', 'submit', 'openPayment']);

// Computed show for v-if
const show = computed(() => props.modelValue);

const posStore = usePosStore();

// State
const guestsCount = ref(2);
const cart = ref<any[]>([]);
const orderComment = ref('');
const menuSearch = ref('');
const selectedCategory = ref<any>(null);
const submitting = ref(false);
const dishesLoading = ref(false);
const dishes = ref<any[]>([]);
const categories = ref<any[]>([]);
const categoriesContainer = ref<any>(null);

// Price list
const orderPriceListId = ref<any>(null);

// Remove item modal
const showRemoveItemModal = ref(false);
const removeItemIndex = ref<any>(null);
const removeItemMode = ref<any>(null); // null = выбор, 'pin' = ввод PIN, 'request' = заявка
const removeManagerPin = ref('');
const removeReason = ref('');
const removeLoading = ref(false);
const removePinError = ref('');

// Variant selector
const showVariantSelector = ref(false);
const selectedParentDish = ref<any>(null);

// Computed - может ли текущий пользователь удалять позиции без подтверждения
const canRemoveItems = computed(() => authStore.canCancelOrders);

// Заказ отправлен на кухню? (не новый)
const isOrderSentToKitchen = computed(() => {
    return currentOrder.value && currentOrder.value.status !== 'new';
});

// Current order (for editing)
const currentOrder = computed(() => props.order);

// Current time
const currentTime = computed(() => {
    return new Date().toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' });
});

// Filtered dishes
const filteredDishes = computed(() => {
    let result = dishes.value;

    if (menuSearch.value) {
        const search = menuSearch.value.toLowerCase();
        result = result.filter((d: any) => d.name.toLowerCase().includes(search));
    } else if (selectedCategory.value) {
        result = result.filter((d: any) => d.category_id === selectedCategory.value);
    }

    return result;
});

// Cart calculations
const cartSubtotal = computed(() => {
    return cart.value.reduce((sum: any, item: any) => sum + itemTotal(item), 0);
});

const cartTotal = computed(() => {
    return cartSubtotal.value;
});

// Methods
const formatMoney = (amount: any) => {
    return new Intl.NumberFormat('ru-RU').format(amount || 0);
};

const itemTotal = (item: any) => {
    const modifiersPrice = (item.modifiers || []).reduce((sum: any, m: any) => sum + (m.price || 0), 0);
    return (item.price + modifiersPrice) * item.quantity;
};

const addToCart = (dish: any) => {
    // For parent products, show variant selector
    if (dish.product_type === 'parent' && dish.variants?.length) {
        selectedParentDish.value = dish;
        showVariantSelector.value = true;
        return;
    }

    // For simple products or variants, add directly
    addItemToCart(dish);
};

const addItemToCart = (dish: any, variantInfo: any = null) => {
    const itemName = variantInfo
        ? `${selectedParentDish.value?.name || dish.name} ${variantInfo.variant_name}`
        : dish.name;

    const dishId = variantInfo ? variantInfo.id : dish.id;
    const price = variantInfo
        ? (variantInfo.resolved_price ?? variantInfo.price)
        : (dish.resolved_price ?? dish.price);

    const existing = cart.value.find((item: any) => item.dish_id === dishId && !item.modifiers?.length);
    if (existing) {
        existing.quantity++;
    } else {
        cart.value.push({
            dish_id: dishId,
            name: itemName,
            price: price,
            quantity: 1,
            emoji: dish.emoji,
            category_id: dish.category_id,
            modifiers: [] as any[],
            notes: '',
            variant_name: variantInfo?.variant_name || null,
            parent_id: variantInfo ? dish.id : null
        });
    }
};

const getMinVariantPrice = (dish: any) => {
    if (!dish.variants?.length) return dish.resolved_price ?? dish.price ?? 0;
    return Math.min(...dish.variants.map((v: any) => v.resolved_price ?? v.price ?? 0));
};

const selectVariant = (variant: any) => {
    if (variant.is_stopped) return;
    addItemToCart(selectedParentDish.value, variant);
    closeVariantSelector();
};

const closeVariantSelector = () => {
    showVariantSelector.value = false;
    selectedParentDish.value = null;
};

const updateQuantity = (index: any, delta: any) => {
    const item = cart.value[index];
    if (item) {
        if (delta < 0 && item.quantity <= 1) {
            // Удаление последней единицы = удаление позиции
            tryRemoveItem(index);
            return;
        }
        item.quantity += delta;
    }
};

const tryRemoveItem = (index: any) => {
    // Если заказ не отправлен на кухню - удаляем сразу
    if (!isOrderSentToKitchen.value) {
        cart.value.splice(index, 1);
        return;
    }

    // Если пользователь менеджер - сразу показываем форму удаления
    removeItemIndex.value = index;
    removeItemMode.value = canRemoveItems.value ? 'direct' : null;
    removeManagerPin.value = '';
    removeReason.value = '';
    removePinError.value = '';
    showRemoveItemModal.value = true;
};

const removeFromCart = (index: any) => {
    tryRemoveItem(index);
};

const closeRemoveItemModal = () => {
    showRemoveItemModal.value = false;
    removeItemIndex.value = null;
    removeItemMode.value = null;
    removeManagerPin.value = '';
    removeReason.value = '';
    removePinError.value = '';
};

const selectRemoveMode = (mode: any) => {
    removeItemMode.value = mode;
    removePinError.value = '';
};

const confirmRemoveItem = async () => {
    const index = removeItemIndex.value;
    if (index === null) return;

    const item = cart.value[index];
    if (!item) return;

    // Режим заявки - отправляем на одобрение
    if (removeItemMode.value === 'request') {
        if (!removeReason.value.trim()) {
            removePinError.value = 'Укажите причину удаления';
            return;
        }

        removeLoading.value = true;
        try {
            // Используем API для отмены позиции
            if (item.id && currentOrder.value?.id) {
                await api.orders.requestCancellation(
                    currentOrder.value.id,
                    `Удаление позиции "${item.name}": ${removeReason.value}`,
                    authStore.user?.id
                );
            }

            window.$toast?.('Заявка на удаление отправлена', 'success');
            closeRemoveItemModal();
        } catch (error: any) {
            log.error('Failed to send remove request:', error);
            window.$toast?.('Ошибка: ' + (error.response?.data?.message || error.message), 'error');
        } finally {
            removeLoading.value = false;
        }
        return;
    }

    // Режим PIN - проверяем PIN менеджера
    if (removeItemMode.value === 'pin') {
        if (removeManagerPin.value.length < 4) {
            removePinError.value = 'Введите PIN менеджера';
            return;
        }

        removeLoading.value = true;
        try {
            const authResult = await api.auth.loginWithPin(removeManagerPin.value);
            const managerRoles = ['super_admin', 'owner', 'admin', 'manager'];
            const userRole = (authResult.data as any)?.user?.role;
            if (!authResult.success || !managerRoles.includes(userRole)) {
                removePinError.value = 'Неверный PIN или недостаточно прав';
                removeLoading.value = false;
                return;
            }
        } catch (error: any) {
            removePinError.value = 'Неверный PIN';
            removeLoading.value = false;
            return;
        }
    }

    // Режим direct (менеджер) или после успешной проверки PIN - удаляем
    removeLoading.value = true;
    try {
        cart.value.splice(index, 1);
        window.$toast?.('Позиция удалена', 'success');
        closeRemoveItemModal();
    } finally {
        removeLoading.value = false;
    }
};

const clearCart = () => {
    cart.value = [];
};

const scrollCategories = (direction: any) => {
    if (categoriesContainer.value) {
        categoriesContainer.value.scrollBy({ left: direction * 200, behavior: 'smooth' });
    }
};

const onPriceListChange = async () => {
    posStore.selectedPriceListId = orderPriceListId.value;
    cart.value = [];
    await loadMenu();
};

const loadMenu = async () => {
    dishesLoading.value = true;
    try {
        const priceListId = orderPriceListId.value;
        const [cats, dishesList] = await Promise.all([
            api.menu.getCategories(),
            api.menu.getDishes(null, priceListId)
        ]);
        categories.value = cats || [];
        dishes.value = dishesList || [];
        if (categories.value.length && !selectedCategory.value) {
            selectedCategory.value = categories.value[0].id;
        }
    } catch (error: any) {
        log.error('Error loading menu:', error);
    } finally {
        dishesLoading.value = false;
    }
};

const submitOrder = async () => {
    if (!cart.value.length || submitting.value) return;

    submitting.value = true;
    try {
        const orderData = {
            table_id: props.table?.id,
            type: 'dine_in',
            guests_count: guestsCount.value,
            comment: orderComment.value,
            price_list_id: orderPriceListId.value || null,
            items: cart.value.map((item: any) => ({
                dish_id: item.dish_id,
                quantity: item.quantity,
                modifiers: item.modifiers?.map((m: any) => m.id) || [],
                notes: item.notes
            }))
        };

        let result;
        if (currentOrder.value?.id) {
            result = await api.orders.update(currentOrder.value.id, orderData);
        } else {
            result = await api.orders.create(orderData);
        }

        posStore.loadActiveOrders();
        posStore.loadTables();
        emit('submit', result);
        close();
    } catch (error: any) {
        log.error('Error submitting order:', error);
    } finally {
        submitting.value = false;
    }
};

const close = () => {
    emit('update:modelValue', false);
};

// Initialize cart from existing order
const initFromOrder = () => {
    if (props.order) {
        cart.value = (props.order.items || []).map((item: any) => ({
            id: item.id,
            dish_id: item.dish_id,
            name: item.name || item.dish?.name,
            price: item.price,
            quantity: item.quantity,
            emoji: item.dish?.emoji,
            category_id: item.dish?.category_id,
            modifiers: item.modifiers || [],
            notes: item.notes || ''
        }));
        guestsCount.value = props.order.guests_count || 2;
        orderComment.value = props.order.comment || '';
    } else if (props.table?._guestsCount) {
        guestsCount.value = props.table._guestsCount;
    }
};

// Watchers
watch(() => props.modelValue, (val) => {
    if (val) {
        orderPriceListId.value = props.order?.price_list_id ?? posStore.selectedPriceListId ?? null;
        loadMenu();
        initFromOrder();
    } else {
        cart.value = [];
        orderComment.value = '';
        menuSearch.value = '';
    }
});

watch(() => props.order, () => {
    initFromOrder();
});

// Lifecycle
onMounted(() => {
    if (props.modelValue) {
        orderPriceListId.value = props.order?.price_list_id ?? posStore.selectedPriceListId ?? null;
        loadMenu();
        initFromOrder();
    }
});
</script>

<style scoped>
.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>
