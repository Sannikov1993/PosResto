<template>
    <Teleport to="body">
        <Transition name="modal">
            <div v-if="show" class="fixed inset-0 bg-black/90 flex items-center justify-center z-50" data-testid="new-delivery-modal">
                <div class="bg-dark-900 w-full h-full flex overflow-hidden" data-testid="new-delivery-content">
                    <!-- Left Panel - Compact Order Form -->
                    <div class="w-[520px] flex flex-col border-r border-dark-800 bg-dark-950 relative">
                        <!-- Header: Date/Time + Order Type -->
                        <div class="flex items-center justify-between px-4 py-2 bg-dark-900">
                            <div class="flex items-center gap-3">
                                <!-- Close button -->
                                <button
                                    @click="close"
                                    class="w-8 h-8 flex items-center justify-center rounded-lg bg-dark-800 hover:bg-red-600/20 text-gray-400 hover:text-red-400 transition-all"
                                    title="Закрыть (Esc)"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                                <!-- Date display -->
                                <button
                                    @click="showCalendar = !showCalendar"
                                    class="flex items-center gap-2 text-sm font-medium text-accent hover:text-blue-400 transition-colors"
                                >
                                    <span>{{ displayDate }}</span>
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </button>
                                <!-- Time display -->
                                <button
                                    @click="showTimePicker = !showTimePicker"
                                    :class="[
                                        'text-sm font-medium transition-colors',
                                        order.is_asap ? 'text-green-400 hover:text-green-300' : 'text-blue-400 hover:text-blue-300'
                                    ]"
                                >
                                    {{ order.is_asap ? 'Ближайшее' : order.scheduled_time }}
                                </button>
                            </div>
                            <!-- Order Type Switcher -->
                            <div class="flex items-center bg-dark-800 rounded-lg p-0.5">
                                <button
                                    @click="order.type = 'delivery'"
                                    :class="[
                                        'px-3 py-1.5 rounded-md text-xs font-medium transition-all',
                                        order.type === 'delivery'
                                            ? 'bg-accent text-white'
                                            : 'text-gray-400 hover:text-white'
                                    ]"
                                >
                                    Доставка
                                </button>
                                <button
                                    @click="order.type = 'pickup'"
                                    :class="[
                                        'px-3 py-1.5 rounded-md text-xs font-medium transition-all',
                                        order.type === 'pickup'
                                            ? 'bg-green-600 text-white'
                                            : 'text-gray-400 hover:text-white'
                                    ]"
                                >
                                    Самовывоз
                                </button>
                            </div>
                        </div>

                        <!-- Compact Form Fields -->
                        <div class="px-4 py-3 space-y-2 bg-dark-900/50">
                            <!-- Row 1: Phone + Name -->
                            <div class="flex gap-2 relative">
                                <div class="flex flex-col">
                                    <div class="relative">
                                        <input
                                            :value="order.phone"
                                            type="tel"
                                            inputmode="numeric"
                                            placeholder="+7 (___) ___-__-__"
                                            data-testid="delivery-phone-input"
                                            @input="onPhoneInput"
                                            @keypress="onlyDigits"
                                            @focus="order.phone?.length >= 3 && foundCustomers.length > 0 && (showCustomerDropdown = true)"
                                            @blur="hideCustomerDropdown"
                                            :class="[
                                                'w-44 bg-dark-800 rounded-lg px-3 pr-8 py-2 text-white text-sm placeholder-gray-500 focus:ring-1 focus:outline-none transition-colors',
                                                order.phone && !isPhoneValid ? 'border border-red-500 focus:ring-red-500' : 'border border-transparent focus:ring-accent',
                                                order.phone && isPhoneValid ? 'border-green-500' : ''
                                            ]"
                                        />
                                        <!-- Status icon -->
                                        <div class="absolute right-2 top-1/2 -translate-y-1/2">
                                            <svg v-if="searchingCustomer" class="w-4 h-4 animate-spin text-accent" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                            <svg v-else-if="order.phone && isPhoneValid" class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                            </svg>
                                            <svg v-else-if="order.phone && !isPhoneValid" class="w-4 h-4 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                                            </svg>
                                        </div>
                                    </div>
                                    <!-- Hint text -->
                                    <p v-if="order.phone && !isPhoneValid" class="text-red-400 text-xs mt-1">
                                        Ещё {{ phoneDigitsRemaining }} {{ phoneDigitsRemaining === 1 ? 'цифра' : phoneDigitsRemaining < 5 ? 'цифры' : 'цифр' }}
                                    </p>
                                </div>
                                <div class="flex-1 relative">
                                    <!-- Если клиент выбран - компактное отображение -->
                                    <div v-if="selectedCustomerData" class="flex items-center gap-2 bg-dark-800 rounded-lg px-3 py-2">
                                        <button
                                            ref="customerNameRef"
                                            @click="openCustomerCard"
                                            class="flex items-center gap-2 group flex-1"
                                        >
                                            <div class="w-7 h-7 rounded-full bg-gradient-to-br from-accent to-purple-500 flex items-center justify-center flex-shrink-0">
                                                <span class="text-white text-xs font-semibold">{{ (selectedCustomerData.name || 'К')[0].toUpperCase() }}</span>
                                            </div>
                                            <span class="text-white text-sm font-medium transition-colors group-hover:text-gray-300">{{ selectedCustomerData.name }}</span>
                                            <span v-if="selectedCustomerData.bonus_balance > 0" class="text-amber-400 text-xs ml-1">{{ selectedCustomerData.bonus_balance }} ★</span>
                                            <svg class="w-4 h-4 text-gray-500 transition-all group-hover:translate-x-1 group-hover:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                            </svg>
                                        </button>
                                        <button
                                            @click="openCustomerList"
                                            class="w-6 h-6 flex items-center justify-center rounded-full hover:bg-white/10 text-gray-400 hover:text-white transition-colors"
                                            title="Выбрать другого клиента"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                                            </svg>
                                        </button>
                                    </div>
                                    <!-- Если клиент не выбран - поле ввода -->
                                    <template v-else>
                                        <input
                                            v-model="order.customer_name"
                                            @blur="formatCustomerName"
                                            type="text"
                                            placeholder="Введите ФИО"
                                            data-testid="delivery-name-input"
                                            class="w-full bg-dark-800 border-0 rounded-lg px-3 py-2 text-white text-sm placeholder-gray-500 focus:ring-1 focus:ring-accent focus:outline-none"
                                        />
                                        <button
                                            @click="openCustomerList"
                                            class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-500 hover:text-white"
                                            title="Выбрать из списка"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                                            </svg>
                                        </button>
                                    </template>
                                </div>

                                <!-- Customer search dropdown -->
                                <Transition name="dropdown">
                                    <div
                                        v-if="showCustomerDropdown && foundCustomers.length > 0"
                                        class="absolute top-full left-0 right-0 mt-1 bg-dark-800 border border-dark-600 rounded-lg shadow-xl z-50 max-h-48 overflow-y-auto"
                                    >
                                        <button
                                            v-for="customer in foundCustomers"
                                            :key="customer.id"
                                            @mousedown.prevent="selectCustomer(customer)"
                                            class="w-full flex items-center gap-3 px-3 py-2 hover:bg-dark-700 transition-colors text-left"
                                        >
                                            <div class="w-8 h-8 bg-accent/20 rounded-full flex items-center justify-center flex-shrink-0">
                                                <span class="text-accent text-sm font-medium">{{ (customer.name || 'К')[0].toUpperCase() }}</span>
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <p class="text-white text-sm font-medium truncate">{{ customer.name || 'Без имени' }}</p>
                                                <p class="text-gray-400 text-xs">{{ formatPhoneNumber(customer.phone) }}</p>
                                            </div>
                                            <div v-if="customer.orders_count" class="text-xs text-gray-500">
                                                {{ customer.orders_count }} заказов
                                            </div>
                                        </button>
                                    </div>
                                </Transition>
                            </div>

                            <!-- Row 2: Comment -->
                            <input
                                v-model="order.comment"
                                type="text"
                                placeholder="Комментарий"
                                class="w-full bg-dark-800 border-0 rounded-lg px-3 py-2 text-white text-sm placeholder-gray-500 focus:ring-1 focus:ring-accent focus:outline-none"
                            />

                            <!-- Row 3: Address (only for delivery) -->
                            <button
                                v-if="order.type === 'delivery'"
                                @click="showAddressModal = true"
                                class="w-full flex items-center gap-2 bg-dark-800 rounded-lg px-3 py-2 text-left hover:bg-dark-700 transition-colors"
                            >
                                <svg class="w-4 h-4 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                <span :class="order.address ? 'text-white' : 'text-accent'" class="text-sm truncate">
                                    {{ order.address || 'Выберите адрес' }}
                                </span>
                            </button>

                            <!-- Row 5: Payment -->
                            <div class="flex items-center gap-2">
                                <span class="text-sm text-gray-400">Оплата</span>
                                <div class="relative">
                                    <button
                                        @click="showPaymentDropdown = !showPaymentDropdown"
                                        class="text-sm text-accent hover:text-blue-400 transition-colors flex items-center gap-1"
                                    >
                                        {{ paymentMethodLabel }}
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                        </svg>
                                    </button>
                                    <!-- Payment dropdown -->
                                    <Transition name="dropdown">
                                        <div
                                            v-if="showPaymentDropdown"
                                            class="absolute top-full left-0 mt-1 bg-dark-800 border border-dark-600 rounded-lg shadow-xl z-20 min-w-[120px]"
                                        >
                                            <button
                                                @click="order.payment_method = 'cash'; showPaymentDropdown = false"
                                                class="w-full flex items-center gap-2 px-3 py-2 text-sm text-left hover:bg-dark-700 transition-colors"
                                                :class="order.payment_method === 'cash' ? 'bg-accent/20 text-white' : 'text-gray-300'"
                                            >
                                                <span>💵</span> Наличными
                                            </button>
                                            <button
                                                @click="order.payment_method = 'card'; showPaymentDropdown = false"
                                                class="w-full flex items-center gap-2 px-3 py-2 text-sm text-left hover:bg-dark-700 transition-colors"
                                                :class="order.payment_method === 'card' ? 'bg-accent/20 text-white' : 'text-gray-300'"
                                            >
                                                <span>💳</span> Картой
                                            </button>
                                        </div>
                                    </Transition>
                                    <!-- Backdrop -->
                                    <div v-if="showPaymentDropdown" class="fixed inset-0 z-10" @click="showPaymentDropdown = false"></div>
                                </div>
                                <template v-if="order.payment_method === 'cash'">
                                    <span class="text-sm text-gray-400">, сдача с</span>
                                    <input
                                        v-model="order.change_from"
                                        type="number"
                                        placeholder=""
                                        class="w-24 bg-dark-800 border border-dark-600 rounded px-2 py-1 text-white text-sm focus:border-accent focus:outline-none"
                                    />
                                </template>
                            </div>
                        </div>

                        <!-- Cart Items Area -->
                        <div class="flex-1 overflow-y-auto px-4 py-3">
                            <!-- Delivery Zone Info -->
                            <Transition name="slide-fade">
                                <div v-if="deliveryInfo && order.address && order.type === 'delivery'" class="mb-3 text-center">
                                    <div class="flex items-center justify-center gap-4 text-xs text-gray-400">
                                        <span>Зона: {{ deliveryInfo.zone_name || 'Стандартная' }}</span>
                                        <span>•</span>
                                        <span :class="deliveryInfo.delivery_fee > 0 ? '' : 'text-green-400'">
                                            Доставка: {{ deliveryInfo.delivery_fee > 0 ? deliveryInfo.delivery_fee + ' ₽' : 'Бесплатно' }}
                                        </span>
                                        <span>•</span>
                                        <span>~{{ deliveryInfo.estimated_time || 45 }} мин</span>
                                    </div>
                                    <!-- Подсказка о бесплатной доставке -->
                                    <div v-if="deliveryInfo.free_delivery_from && deliveryInfo.delivery_fee > 0 && subtotal < deliveryInfo.free_delivery_from"
                                         class="text-xs text-blue-400 mt-1">
                                        Ещё {{ Math.ceil(deliveryInfo.free_delivery_from - subtotal) }} ₽ до бесплатной доставки
                                    </div>
                                </div>
                            </Transition>

                            <!-- Empty Cart -->
                            <div v-if="order.items.length === 0" class="flex flex-col items-center justify-center h-full text-gray-500">
                                <svg class="w-16 h-16 mb-3 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                                </svg>
                                <p class="text-sm">Выберите блюда из меню →</p>
                            </div>

                            <!-- Cart Items List -->
                            <div v-else>
                                <TransitionGroup name="cart-item">
                                    <div
                                        v-for="(item, index) in order.items"
                                        :key="item.id + '-' + index"
                                        class="border-b border-white/5"
                                        @mouseenter="hoveredItemIndex = index"
                                        @mouseleave="hoveredItemIndex = -1"
                                    >
                                        <!-- Item row -->
                                        <div class="px-3 py-2 hover:bg-gray-800/20 transition-colors">
                                            <!-- First row: name and price -->
                                            <div class="flex items-center gap-2">
                                                <!-- Status dot -->
                                                <span class="w-2 h-2 rounded-full flex-shrink-0 bg-blue-500"></span>
                                                <span class="text-gray-200 text-base flex-1 truncate">{{ item.name }}</span>
                                                <span class="text-gray-500 text-sm">{{ formatPrice(getItemTotalPrice(item)) }} ₽</span>
                                                <span class="text-gray-500 text-sm">×</span>
                                                <span class="text-gray-400 text-sm">{{ item.quantity }} шт</span>
                                                <span class="text-gray-300 text-[14px] font-semibold w-20 text-right">{{ formatPrice(getItemTotalPrice(item) * item.quantity) }} ₽</span>
                                            </div>

                                            <!-- Modifiers display as sub-items -->
                                            <div v-if="item.modifiers?.length" class="mt-0.5">
                                                <div
                                                    v-for="mod in item.modifiers"
                                                    :key="mod.id"
                                                    class="flex items-center gap-2 text-[12px] text-gray-500 pl-4"
                                                >
                                                    <span class="flex-1 truncate">+ {{ mod.name }}</span>
                                                    <span class="text-gray-600">× 1</span>
                                                    <span class="w-14 text-right text-gray-600">{{ mod.price > 0 ? formatPrice(mod.price) : '0.00' }}</span>
                                                </div>
                                            </div>

                                            <!-- Comment -->
                                            <div v-if="item.note" class="text-yellow-500 text-xs mt-0.5 italic">
                                                💬 {{ item.note }}
                                            </div>

                                            <!-- Action buttons (on hover) -->
                                            <div v-if="hoveredItemIndex === index"
                                                 class="flex items-center gap-2 mt-1 h-9 transition-all">
                                                <button @click.stop="decrementItem(index)"
                                                        class="w-7 h-7 bg-gray-700/50 text-gray-300 rounded text-base hover:bg-gray-600 flex items-center justify-center">−</button>
                                                <span class="text-gray-300 text-base w-5 text-center">{{ item.quantity }}</span>
                                                <button @click.stop="item.quantity++"
                                                        class="w-7 h-7 bg-gray-700/50 text-gray-300 rounded text-base hover:bg-gray-600 flex items-center justify-center">+</button>

                                                <div class="flex-1"></div>

                                                <!-- Modifier button (only if dish has modifiers) -->
                                                <button v-if="itemHasModifiers(item)"
                                                        @click.stop="openItemModifiers(index)"
                                                        :class="item.modifiers?.length ? 'text-green-400' : 'text-gray-400 hover:text-green-400'"
                                                        class="w-8 h-8 rounded flex items-center justify-center"
                                                        title="Модификаторы">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                                                    </svg>
                                                </button>

                                                <button @click.stop="openItemComment(index)"
                                                        :class="item.note ? 'text-yellow-500' : 'text-gray-400 hover:text-yellow-500'"
                                                        class="w-8 h-8 rounded flex items-center justify-center"
                                                        title="Комментарий">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                                                    </svg>
                                                </button>

                                                <button @click.stop="removeItem(index)"
                                                        class="w-8 h-8 text-gray-400 hover:text-red-500 rounded flex items-center justify-center"
                                                        title="Удалить">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                    </svg>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </TransitionGroup>
                            </div>

                            <!-- Item Comment Modal -->
                            <Transition name="modal">
                                <div v-if="showItemCommentModal" class="fixed inset-0 bg-black/70 flex items-center justify-center z-[9999]">
                                    <div class="bg-dark-900 rounded-xl w-full max-w-sm mx-4" @click.stop>
                                        <div class="flex items-center justify-between px-4 py-3 border-b border-dark-700">
                                            <h3 class="font-semibold text-white">Комментарий к блюду</h3>
                                            <button @click="showItemCommentModal = false" class="text-gray-400 hover:text-white">✕</button>
                                        </div>
                                        <div class="p-4">
                                            <input
                                                v-model="itemCommentText"
                                                type="text"
                                                placeholder="Например: без лука, поострее..."
                                                class="w-full bg-dark-800 border border-dark-600 rounded-lg px-3 py-2 text-white text-sm focus:border-accent focus:outline-none"
                                                @keyup.enter="saveItemComment"
                                            />
                                            <!-- Quick comments -->
                                            <div class="flex flex-wrap gap-2 mt-3">
                                                <button
                                                    v-for="quick in ['Без лука', 'Поострее', 'Без соуса', 'С собой']"
                                                    :key="quick"
                                                    @click="itemCommentText = quick"
                                                    class="px-2 py-1 bg-dark-800 hover:bg-dark-700 rounded text-xs text-gray-400 hover:text-white transition-colors"
                                                >
                                                    {{ quick }}
                                                </button>
                                            </div>
                                        </div>
                                        <div class="px-4 py-3 border-t border-dark-700 flex gap-2">
                                            <button
                                                v-if="order.items[editingItemIndex]?.note"
                                                @click="clearItemComment"
                                                class="px-4 py-2 bg-red-600/20 hover:bg-red-600/40 rounded-lg text-red-400 text-sm font-medium transition-colors"
                                            >
                                                Удалить
                                            </button>
                                            <button
                                                @click="saveItemComment"
                                                class="flex-1 py-2 bg-accent hover:bg-blue-600 rounded-lg text-white font-medium transition-colors"
                                            >
                                                Сохранить
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </Transition>

                            <!-- Customer Select Panel (covers entire left block) -->
                            <!-- Enterprise: используем composable для данных клиента -->
                            <CustomerSelectModal
                                v-model="showCustomerList"
                                variant="panel"
                                :selected="orderCustomer.customerData.value"
                                @select="onCustomerSelected"
                            />

                            <!-- Unified Prepayment Modal -->
                            <!-- Enterprise: используем composables для скидок и клиента -->
                            <UnifiedPaymentModal
                                v-model="showPrepaymentModal"
                                :total="total"
                                :subtotal="subtotal"
                                :deliveryFee="deliveryFee"
                                :loyaltyDiscount="orderDiscounts.loyaltyDiscount.value"
                                :loyaltyLevelName="orderDiscounts.loyaltyLevelName.value"
                                :discount="discountAmount"
                                mode="prepayment"
                                :initialMethod="order.prepayment_method || 'cash'"
                                :initialAmount="order.prepayment > 0 ? order.prepayment : ''"
                                :initialBonusToSpend="orderDiscounts.bonusToSpend.value"
                                :customer="orderCustomer.customerData.value"
                                :bonusSettings="bonusSettings"
                                :roundAmounts="posStore.roundAmounts"
                                @confirm="handlePrepaymentConfirm"
                            />
                        </div>

                        <!-- Footer Actions -->
                        <div class="px-4 py-3 bg-dark-900 space-y-2">
                            <!-- Row 1: Delete + Prepayment + Total -->
                            <div class="flex items-center gap-2">
                                <button
                                    @click="clearCart"
                                    :disabled="order.items.length === 0"
                                    class="w-10 h-10 flex items-center justify-center rounded-lg bg-dark-800 hover:bg-red-600/20 text-gray-400 hover:text-red-400 transition-all disabled:opacity-30"
                                    title="Очистить корзину"
                                >
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                                <div class="flex-1 flex gap-1">
                                    <button
                                        @click="openPrepaymentModal"
                                        :class="[
                                            'flex-1 h-10 rounded-lg text-sm font-medium transition-all',
                                            order.prepayment > 0
                                                ? 'bg-green-600/20 text-green-400 hover:bg-green-600/30'
                                                : 'bg-dark-800 hover:bg-dark-700 text-gray-300'
                                        ]"
                                    >
                                        {{ order.prepayment > 0 ? `Предоплата: ${formatPrice(order.prepayment)} ₽` : 'Аванс/предоплата' }}
                                    </button>
                                    <button
                                        v-if="order.prepayment > 0"
                                        @click="clearPrepayment"
                                        class="w-10 h-10 flex items-center justify-center rounded-lg bg-red-600/20 hover:bg-red-600/40 text-red-400 transition-all"
                                        title="Убрать предоплату"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>
                                <div class="text-right">
                                    <span class="text-lg font-bold text-white">{{ formatPrice(total) }} ₽</span>
                                </div>
                            </div>

                            <!-- Applied discounts info (над кнопками) -->
                            <template v-if="appliedDiscountsList.length > 0">
                                <div v-for="(discount, idx) in appliedDiscountsList" :key="idx"
                                     class="flex items-center justify-between text-sm px-1">
                                    <span class="text-green-400 flex items-center gap-1">
                                        <span class="text-xs">{{ getDiscountIcon(discount.type || discount.sourceType) }}</span>
                                        {{ discount.name }}
                                    </span>
                                    <span class="text-green-400 font-medium">-{{ formatDiscountAmount(discount) }}</span>
                                </div>
                            </template>

                            <!-- Bonus used info -->
                            <div v-if="bonusToSpend > 0" class="flex items-center justify-between text-sm px-1">
                                <span class="text-yellow-400">
                                    ★ Списание бонусов
                                </span>
                                <span class="text-yellow-400 font-medium">-{{ formatPrice(bonusToSpend) }} ₽</span>
                            </div>

                            <!-- Row 2: Submit buttons -->
                            <div class="flex gap-2">
                                <button
                                    @click="createOrder('kitchen')"
                                    :disabled="!canCreate || creating"
                                    data-testid="delivery-submit-btn"
                                    class="flex-1 h-12 bg-orange-600 hover:bg-orange-500 disabled:bg-dark-700 disabled:text-gray-500 rounded-lg text-white font-semibold transition-all"
                                >
                                    {{ creating ? 'Создание...' : 'Готовить' }}
                                </button>
                                <button
                                    @click="showDiscountModal = true"
                                    :class="[
                                        'relative h-12 px-4 rounded-lg font-medium transition-all',
                                        totalDiscountAmount > 0
                                            ? 'bg-green-600 hover:bg-green-500 text-white'
                                            : 'bg-dark-800 hover:bg-dark-700 text-gray-300'
                                    ]"
                                >
                                    <span v-if="totalDiscountAmount > 0">
                                        -{{ formatPrice(totalDiscountAmount) }} ₽
                                    </span>
                                    <span v-else>% Скидки</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Right Panel - Menu with Vertical Categories -->
                    <div class="flex-1 flex overflow-hidden">
                        <!-- Categories Sidebar (vertical colored strips) -->
                        <div class="w-20 bg-dark-950 border-r border-dark-800 overflow-y-auto py-2">
                            <button
                                @click="selectedCategory = null"
                                :class="[
                                    'w-full px-1 py-3 text-xs font-medium transition-all relative',
                                    selectedCategory === null
                                        ? 'bg-dark-800 text-white'
                                        : 'text-gray-400 hover:text-white hover:bg-dark-800/50'
                                ]"
                            >
                                <div
                                    v-if="selectedCategory === null"
                                    class="absolute left-0 top-0 bottom-0 w-1 bg-accent"
                                ></div>
                                <span class="block truncate px-1">Все</span>
                            </button>
                            <button
                                v-for="(category, idx) in categories"
                                :key="category.id"
                                @click="selectedCategory = category.id"
                                :class="[
                                    'w-full px-1 py-3 text-xs font-medium transition-all relative',
                                    selectedCategory === category.id
                                        ? 'bg-dark-800 text-white'
                                        : 'text-gray-400 hover:text-white hover:bg-dark-800/50'
                                ]"
                            >
                                <div
                                    class="absolute left-0 top-0 bottom-0 w-1"
                                    :style="{ backgroundColor: getCategoryColor(idx) }"
                                ></div>
                                <span class="block truncate px-1">{{ category.name }}</span>
                            </button>
                        </div>

                        <!-- Dishes Grid -->
                        <div class="flex-1 flex flex-col bg-dark-900" :style="(showAddressModal || dishGridLocked) ? 'pointer-events: none;' : ''">
                            <!-- Search -->
                            <div class="px-4 py-3 border-b border-dark-700">
                                <div class="relative">
                                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                    </svg>
                                    <input
                                        v-model="dishSearch"
                                        type="text"
                                        placeholder="Поиск блюда..."
                                        class="w-full bg-dark-800 border-0 rounded-lg pl-9 pr-4 py-2 text-white text-sm placeholder-gray-500 focus:ring-2 focus:ring-accent focus:outline-none"
                                    />
                                </div>
                            </div>

                            <!-- Dishes List -->
                            <div class="flex-1 overflow-y-auto p-2">
                                <div class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5 gap-2">
                                    <div
                                        v-for="dish in filteredDishes"
                                        :key="dish.id"
                                        :class="[
                                            'group bg-dark-800 rounded-lg overflow-hidden transition-all relative',
                                            dish.is_stopped ? 'opacity-50 cursor-not-allowed' : '',
                                            // Scale + shadow hover for simple dishes
                                            !dish.is_stopped && dish.product_type !== 'parent' ? 'hover:scale-[1.03] hover:shadow-xl hover:shadow-black/50 hover:z-10' : '',
                                        ]"
                                    >
                                        <!-- Category color strip -->
                                        <div
                                            class="absolute top-0 left-0 right-0 h-1"
                                            :style="{ backgroundColor: getDishCategoryColor(dish) }"
                                        ></div>
                                        <!-- Image - clickable for simple dishes -->
                                        <div
                                            @click="!dish.is_stopped && dish.product_type !== 'parent' && quickAddDish(dish)"
                                            :class="[
                                                'aspect-[4/3] bg-dark-700 relative overflow-hidden',
                                                !dish.is_stopped && dish.product_type !== 'parent' ? 'cursor-pointer' : ''
                                            ]"
                                        >
                                            <img
                                                v-if="dish.image"
                                                :src="dish.image"
                                                :alt="dish.name"
                                                class="w-full h-full object-cover transition-transform"
                                            />
                                            <div v-else class="w-full h-full flex items-center justify-center text-3xl text-gray-700">
                                                🍽️
                                            </div>
                                            <!-- Stop overlay -->
                                            <div v-if="dish.is_stopped" class="absolute inset-0 bg-black/70 flex items-center justify-center">
                                                <span class="bg-red-600 text-white text-sm font-bold px-4 py-1.5 rounded-lg transform -rotate-12">СТОП</span>
                                            </div>
                                            <!-- Quantity badge -->
                                            <div
                                                v-if="getTotalDishQuantity(dish) > 0 && !dish.is_stopped"
                                                class="absolute top-2 right-2 w-6 h-6 bg-green-500 rounded-full text-white text-xs font-bold flex items-center justify-center shadow-lg"
                                            >
                                                {{ getTotalDishQuantity(dish) }}
                                            </div>
                                            <!-- Info button -->
                                            <button
                                                @click.stop="openDishDetail(dish)"
                                                class="absolute top-2 left-2 w-6 h-6 bg-black/50 hover:bg-black/70 backdrop-blur-sm rounded-full text-white text-xs font-bold flex items-center justify-center transition-all opacity-0 group-hover:opacity-100"
                                                title="Подробнее"
                                            >
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                            </button>
                                        </div>
                                        <!-- Info -->
                                        <div class="p-2">
                                            <h3 class="text-xs font-medium text-white mb-1 line-clamp-1 leading-tight">{{ dish.name }}</h3>

                                            <!-- For parent dishes: size buttons (Glass style) -->
                                            <div v-if="dish.product_type === 'parent' && dish.variants?.length" class="flex gap-1 mt-1.5">
                                                <button
                                                    v-for="variant in dish.variants"
                                                    :key="variant.id"
                                                    @click.stop="!dish.is_stopped && variant.is_available !== false && quickAddVariant(dish, variant)"
                                                    :disabled="dish.is_stopped || variant.is_available === false"
                                                    :class="[
                                                        'flex-1 py-2 px-2 rounded-md text-center transition-all min-w-0 border',
                                                        dish.is_stopped || variant.is_available === false
                                                            ? 'bg-[#1a1f2e] border-gray-800 text-gray-600 cursor-not-allowed'
                                                            : 'bg-white/5 backdrop-blur-sm border-white/10 hover:bg-white/10 hover:border-white/20 text-white cursor-pointer active:scale-[0.97]'
                                                    ]"
                                                >
                                                    <div class="text-xs text-gray-400 truncate">{{ variant.variant_name }}</div>
                                                    <div class="text-sm font-semibold text-white">{{ formatPrice(variant.price) }} ₽</div>
                                                </button>
                                            </div>

                                            <!-- For simple dishes: price -->
                                            <div
                                                v-else
                                                @click="!dish.is_stopped && quickAddDish(dish)"
                                                class="cursor-pointer"
                                            >
                                                <span :class="['text-sm font-bold', dish.is_stopped ? 'text-gray-500 line-through' : 'text-white']">
                                                    {{ formatPrice(dish.price) }} <span class="text-xs text-gray-500">₽</span>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Empty -->
                                <div v-if="filteredDishes.length === 0" class="flex flex-col items-center justify-center h-64 text-gray-500">
                                    <svg class="w-12 h-12 mb-3 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                    </svg>
                                    <p class="text-sm">Блюда не найдены</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Address Modal -->
                    <Transition name="modal">
                        <div v-if="showAddressModal" class="fixed inset-0 bg-black/70 flex items-center justify-center z-60" @click.self="closeAddressModalSafely()" @mousedown.stop>
                            <div class="bg-dark-900 rounded-xl w-full max-w-xl mx-4" @click.stop>
                                <div class="flex items-center justify-between px-4 py-3">
                                    <h3 class="font-semibold text-white">Адрес доставки</h3>
                                    <button @click="closeAddressModalSafely()" class="text-gray-400 hover:text-white">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>
                                <div class="p-4 pt-0 space-y-3">
                                    <div>
                                        <label class="block text-xs text-gray-400 mb-1">Улица, дом</label>
                                        <input
                                            v-model="order.address"
                                            type="text"
                                            placeholder="ул. Ленина, д. 15"
                                            data-testid="delivery-address-input"
                                            class="w-full bg-dark-800 border-0 rounded-lg px-3 py-2 text-white text-sm focus:ring-1 focus:ring-accent focus:outline-none"
                                        />
                                    </div>
                                    <div class="grid grid-cols-3 gap-2">
                                        <div>
                                            <label class="block text-xs text-gray-400 mb-1">Подъезд</label>
                                            <input
                                                v-model="order.entrance"
                                                type="text"
                                                placeholder="—"
                                                class="w-full bg-dark-800 border-0 rounded-lg px-3 py-2 text-white text-sm text-center focus:ring-1 focus:ring-accent focus:outline-none"
                                            />
                                        </div>
                                        <div>
                                            <label class="block text-xs text-gray-400 mb-1">Этаж</label>
                                            <input
                                                v-model="order.floor"
                                                type="text"
                                                placeholder="—"
                                                class="w-full bg-dark-800 border-0 rounded-lg px-3 py-2 text-white text-sm text-center focus:ring-1 focus:ring-accent focus:outline-none"
                                            />
                                        </div>
                                        <div>
                                            <label class="block text-xs text-gray-400 mb-1">Кв./офис</label>
                                            <input
                                                v-model="order.apartment"
                                                type="text"
                                                placeholder="—"
                                                class="w-full bg-dark-800 border-0 rounded-lg px-3 py-2 text-white text-sm text-center focus:ring-1 focus:ring-accent focus:outline-none"
                                            />
                                        </div>
                                    </div>

                                    <!-- Mini Map -->
                                    <div class="relative">
                                        <div ref="miniMapContainer" class="h-56 rounded-lg overflow-hidden bg-dark-800 relative">
                                            <!-- Map placeholder -->
                                            <div v-if="!miniMapReady" class="absolute inset-0 flex items-center justify-center text-gray-500 text-sm">
                                                <template v-if="order.address && deliveryInfo?.coordinates">
                                                    <svg class="w-5 h-5 animate-spin mr-2" fill="none" viewBox="0 0 24 24">
                                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                    </svg>
                                                    Загрузка карты...
                                                </template>
                                                <template v-else-if="order.address && !deliveryInfo?.coordinates">
                                                    <svg class="w-5 h-5 animate-spin mr-2" fill="none" viewBox="0 0 24 24">
                                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                    </svg>
                                                    Определение координат...
                                                </template>
                                                <template v-else>
                                                    <svg class="w-6 h-6 mr-2 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                                    </svg>
                                                    Введите адрес для отображения на карте
                                                </template>
                                            </div>
                                        </div>
                                        <!-- Delivery info overlay -->
                                        <div v-if="deliveryInfo && miniMapReady" class="absolute bottom-2 left-2 right-2 bg-dark-900/90 backdrop-blur rounded-lg px-3 py-2 flex items-center justify-between text-xs">
                                            <span class="text-gray-400">{{ deliveryInfo.zone_name || 'Стандартная' }}</span>
                                            <span :class="deliveryInfo.delivery_fee > 0 ? 'text-white' : 'text-green-400'">
                                                {{ deliveryInfo.delivery_fee > 0 ? deliveryInfo.delivery_fee + ' ₽' : 'Бесплатно' }}
                                            </span>
                                            <span class="text-gray-400">~{{ deliveryInfo.estimated_time || 45 }} мин</span>
                                        </div>
                                    </div>

                                    <!-- Saved addresses from customer profile -->
                                    <div v-if="customerSavedAddresses.length > 0">
                                        <label class="block text-xs text-gray-400 mb-2">Сохранённые адреса</label>
                                        <div class="space-y-1 max-h-40 overflow-y-auto">
                                            <button
                                                v-for="addr in customerSavedAddresses"
                                                :key="addr.id"
                                                @click="selectSavedAddress(addr)"
                                                class="w-full text-left px-3 py-2 bg-dark-800 hover:bg-dark-700 rounded-lg transition-colors group"
                                            >
                                                <div class="flex items-center justify-between">
                                                    <span class="text-sm text-white">{{ addr.street }}</span>
                                                    <span v-if="addr.is_default" class="text-xs text-green-400">✓</span>
                                                </div>
                                                <div v-if="addr.apartment || addr.entrance || addr.floor" class="text-xs text-gray-500 mt-0.5">
                                                    <span v-if="addr.apartment">кв. {{ addr.apartment }}</span>
                                                    <span v-if="addr.entrance">, подъезд {{ addr.entrance }}</span>
                                                    <span v-if="addr.floor">, этаж {{ addr.floor }}</span>
                                                </div>
                                            </button>
                                        </div>
                                    </div>
                                    <!-- Recent addresses (from local storage) if no saved addresses -->
                                    <div v-else-if="recentAddresses.length > 0">
                                        <label class="block text-xs text-gray-400 mb-2">Недавние адреса</label>
                                        <div class="space-y-1 max-h-32 overflow-y-auto">
                                            <button
                                                v-for="addr in recentAddresses.slice(0, 5)"
                                                :key="typeof addr === 'string' ? addr : addr?.street"
                                                @click="selectRecentAddress(addr)"
                                                class="w-full text-left px-3 py-2 bg-dark-800 hover:bg-dark-700 rounded-lg text-sm text-gray-300 truncate transition-colors"
                                            >
                                                {{ typeof addr === 'string' ? addr : addr?.street }}
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="px-4 py-3">
                                    <button
                                        @click="closeAddressModalSafely()"
                                        class="w-full py-2 bg-accent hover:bg-blue-600 rounded-lg text-white font-medium transition-colors"
                                    >
                                        Сохранить
                                    </button>
                                </div>
                            </div>
                        </div>
                    </Transition>

                    <!-- Calendar Popup -->
                    <Transition name="dropdown">
                        <div
                            v-if="showCalendar"
                            class="fixed top-16 left-20 bg-dark-800 rounded-xl p-4 shadow-2xl z-60 border border-dark-700"
                            @click.stop
                        >
                            <div class="fixed inset-0 z-[-1]" @click="showCalendar = false"></div>
                            <!-- Calendar Header -->
                            <div class="flex items-center justify-between mb-4">
                                <button @click="calendarPrevMonth" class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-dark-700 text-gray-400 hover:text-white">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                                    </svg>
                                </button>
                                <span class="text-white font-semibold text-sm">{{ calendarMonthYear }}</span>
                                <button @click="calendarNextMonth" class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-dark-700 text-gray-400 hover:text-white">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                    </svg>
                                </button>
                            </div>
                            <!-- Weekdays -->
                            <div class="grid grid-cols-7 gap-1 mb-2">
                                <div v-for="day in ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс']" :key="day" class="text-center text-xs text-gray-500 py-1">
                                    {{ day }}
                                </div>
                            </div>
                            <!-- Days -->
                            <div class="grid grid-cols-7 gap-1">
                                <button
                                    v-for="day in calendarDays"
                                    :key="day.date"
                                    @click="selectDate(day)"
                                    :disabled="day.disabled"
                                    :class="[
                                        'h-8 w-8 rounded-lg text-xs font-medium transition-colors',
                                        day.isToday && !day.isSelected ? 'ring-1 ring-accent' : '',
                                        day.isSelected ? 'bg-accent text-white' : '',
                                        day.isCurrentMonth && !day.disabled && !day.isSelected ? 'text-gray-300 hover:bg-dark-700' : '',
                                        !day.isCurrentMonth ? 'text-gray-700' : '',
                                        day.disabled ? 'text-gray-700 cursor-not-allowed' : ''
                                    ]"
                                >
                                    {{ day.day }}
                                </button>
                            </div>
                            <!-- Quick dates -->
                            <div class="flex gap-2 mt-3 pt-3 border-t border-dark-700">
                                <button @click="selectQuickDate('today')" class="flex-1 py-1.5 text-xs rounded-lg bg-dark-700 text-gray-300 hover:bg-gray-600 transition-colors">
                                    Сегодня
                                </button>
                                <button @click="selectQuickDate('tomorrow')" class="flex-1 py-1.5 text-xs rounded-lg bg-dark-700 text-gray-300 hover:bg-gray-600 transition-colors">
                                    Завтра
                                </button>
                            </div>
                        </div>
                    </Transition>

                    <!-- Time Picker Popup -->
                    <Transition name="dropdown">
                        <div
                            v-if="showTimePicker"
                            class="fixed top-0 left-0 w-[520px] h-full bg-dark-900 p-4 shadow-2xl z-60 border-r border-dark-700 flex flex-col"
                            @click.stop
                        >
                            <div class="fixed inset-0 z-[-1]" @click="showTimePicker = false"></div>

                            <!-- Header -->
                            <div class="flex items-center gap-3 mb-4 pb-4 border-b border-dark-700">
                                <button
                                    @click="showTimePicker = false"
                                    class="w-8 h-8 flex items-center justify-center rounded-lg bg-dark-800 hover:bg-dark-700 text-gray-400 hover:text-white transition-all"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                                    </svg>
                                </button>
                                <span class="text-lg font-semibold text-white">Время доставки</span>
                            </div>

                            <!-- ASAP Button (только для сегодняшней даты) -->
                            <button
                                v-if="isScheduledDateToday"
                                @click="setAsap"
                                :class="[
                                    'w-full py-4 rounded-xl text-base font-semibold mb-4 transition-all',
                                    order.is_asap
                                        ? 'bg-green-600 text-white shadow-lg shadow-green-600/30'
                                        : 'bg-dark-800 text-gray-400 hover:bg-green-600/20 hover:text-green-400'
                                ]"
                            >
                                ⚡ Ближайшее время
                            </button>
                            <!-- Подсказка если дата не сегодня -->
                            <div v-else class="mb-4 px-4 py-3 bg-amber-500/10 border border-amber-500/30 rounded-xl text-amber-400 text-sm text-center">
                                Выберите время доставки
                            </div>

                            <!-- Time slots grid -->
                            <div class="grid grid-cols-6 gap-2">
                                <button
                                    v-for="slot in availableTimeSlots"
                                    :key="slot.time"
                                    @click="selectTime(slot.time)"
                                    :disabled="slot.disabled"
                                    :class="[
                                        'h-11 text-sm rounded-xl font-medium transition-all',
                                        slot.disabled
                                            ? 'bg-dark-800/50 text-gray-700 cursor-not-allowed'
                                            : order.scheduled_time === slot.time && !order.is_asap
                                                ? 'bg-accent text-white shadow-lg shadow-accent/30 scale-105'
                                                : 'bg-dark-800 text-gray-300 hover:bg-accent hover:text-white hover:scale-105'
                                    ]"
                                >
                                    {{ slot.time }}
                                </button>
                            </div>

                            <!-- Numpad Section -->
                            <div class="mt-4 pt-4 border-t border-dark-700">
                                <div class="text-xs text-gray-500 mb-3">Ручной ввод времени</div>

                                <!-- Display -->
                                <div class="flex items-center justify-center py-3 bg-dark-800 rounded-xl mb-3">
                                    <span class="text-4xl font-bold font-mono" :class="timeInput.length >= 4 ? 'text-accent' : 'text-white'">
                                        {{ formatTimeDisplay }}
                                    </span>
                                </div>

                                <!-- Numpad grid - vertical 3x4 -->
                                <div class="grid grid-cols-3 gap-2 mb-3">
                                    <button
                                        v-for="n in [1,2,3,4,5,6,7,8,9]"
                                        :key="n"
                                        @click="addTimeDigit(n)"
                                        class="h-12 rounded-xl bg-dark-800 text-white font-semibold text-xl hover:bg-dark-700 active:bg-accent transition-all"
                                    >
                                        {{ n }}
                                    </button>
                                    <button
                                        @click="clearTimeInput"
                                        class="h-12 rounded-xl bg-red-600/20 text-red-400 font-semibold text-lg hover:bg-red-600/40 transition-all"
                                    >
                                        C
                                    </button>
                                    <button
                                        @click="addTimeDigit(0)"
                                        class="h-12 rounded-xl bg-dark-800 text-white font-semibold text-xl hover:bg-dark-700 active:bg-accent transition-all"
                                    >
                                        0
                                    </button>
                                    <button
                                        @click="backspaceTimeInput"
                                        class="h-12 rounded-xl bg-dark-800 text-gray-400 hover:bg-dark-700 hover:text-white transition-all flex items-center justify-center"
                                    >
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2M3 12l6.414 6.414a2 2 0 001.414.586H19a2 2 0 002-2V7a2 2 0 00-2-2h-8.172a2 2 0 00-1.414.586L3 12z" />
                                        </svg>
                                    </button>
                                </div>

                                <!-- Confirm button -->
                                <button
                                    @click="confirmTimeInput"
                                    :disabled="!isTimeInputValid"
                                    class="w-full py-3 rounded-xl font-semibold text-lg transition-all"
                                    :class="isTimeInputValid ? 'bg-accent text-white hover:bg-blue-600' : 'bg-dark-800 text-gray-600 cursor-not-allowed'"
                                >
                                    {{ timeInput.length >= 4 && !isTimeInputValid ? 'Время недоступно' : 'Подтвердить' }}
                                </button>
                            </div>
                        </div>
                    </Transition>

                    <!-- Discount Modal moved outside -->
                </div>
            </div>
        </Transition>
    </Teleport>

    <!-- Customer Info Card -->
    <!-- Enterprise: используем composable для данных клиента -->
    <CustomerInfoCard
        :show="showCustomerCard"
        :customer="orderCustomer.customerData.value"
        :anchor-el="customerNameRef"
        @close="showCustomerCard = false"
        @update="handleCustomerUpdate"
    />

    <!-- Discount Modal (shared component) -->
    <!-- Enterprise: используем composables для передачи данных -->
    <DiscountModal
        v-model="showDiscountModal"
        :subtotal="subtotal"
        :currentAppliedDiscounts="orderDiscounts.appliedDiscounts.value"
        :customerId="orderCustomer.customerId.value"
        :customerName="order.customer_name"
        :customerLoyaltyLevel="orderCustomer.customerLoyaltyLevel.value"
        :customerBonusBalance="orderCustomer.customerBonusBalance.value"
        :currentBonusToSpend="orderDiscounts.bonusToSpend.value"
        :bonusSettings="bonusSettings"
        :orderType="order.type"
        :items="discountItems"
        @apply="handleDiscountApply"
    />

    <!-- Dish Customizer Side Panel -->
    <Teleport to="body">
        <!-- Backdrop with fade -->
        <Transition name="fade">
            <div v-if="showModifierSelector"
                 class="fixed inset-0 bg-black/50 z-[70]"
                 @click="closeModifierSelector">
            </div>
        </Transition>

        <!-- Side Panel with slide -->
        <Transition name="slide-panel">
            <div v-if="showModifierSelector"
                 class="fixed top-0 right-0 h-full w-[420px] max-w-[90vw] bg-[#1a1f2e] shadow-2xl flex flex-col z-[71] border-l border-gray-800">

                <!-- Header -->
                <div class="flex items-center justify-between px-4 py-3 border-b border-gray-800">
                    <div class="flex items-center gap-3">
                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                        </svg>
                        <span class="font-semibold text-white">
                            {{ editingCartItemIndex >= 0 ? 'Модификаторы' : 'Настроить' }}
                        </span>
                    </div>
                    <button @click="closeModifierSelector"
                            class="w-9 h-9 flex items-center justify-center rounded-lg hover:bg-white/10 text-gray-400 hover:text-white transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Dish info compact -->
                <div class="flex items-center gap-3 px-4 py-3 border-b border-gray-800">
                    <div class="w-12 h-12 rounded-lg bg-[#252a3a] overflow-hidden flex-shrink-0">
                        <img v-if="modifierDish?.image" :src="modifierDish.image" :alt="modifierDish?.name" class="w-full h-full object-cover"/>
                        <div v-else class="w-full h-full flex items-center justify-center text-xl text-gray-600">🍽️</div>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="font-medium text-white truncate">
                            {{ editingCartItemIndex >= 0 ? order.items[editingCartItemIndex]?.name : modifierDish?.name }}
                        </div>
                        <div v-if="editingCartItemIndex < 0" class="text-sm text-gray-500">
                            {{ formatPrice(selectedVariantForModifiers?.price || modifierDish?.price || 0) }} ₽
                        </div>
                    </div>
                </div>

                <!-- Content -->
                <div class="flex-1 overflow-y-auto">

                    <!-- Variants selection -->
                    <div v-if="editingCartItemIndex < 0 && modifierDish?.product_type === 'parent' && modifierDish?.variants?.length"
                         class="px-4 py-3 border-b border-gray-800">
                        <div class="text-xs text-gray-500 uppercase tracking-wide mb-2">Размер</div>
                        <div class="flex gap-2">
                            <button
                                v-for="variant in modifierDish.variants"
                                :key="variant.id"
                                @click="variant.is_available !== false && (selectedVariantForModifiers = variant)"
                                :disabled="variant.is_available === false"
                                :class="[
                                    'flex-1 px-3 py-2 rounded-lg text-sm transition-all',
                                    variant.is_available === false
                                        ? 'bg-[#252a3a]/50 text-gray-600 cursor-not-allowed'
                                        : selectedVariantForModifiers?.id === variant.id
                                            ? 'bg-blue-500 text-white'
                                            : 'bg-[#252a3a] text-white hover:bg-[#2d3348]'
                                ]"
                            >
                                <div class="font-medium">{{ variant.variant_name }}</div>
                                <div class="text-xs mt-0.5" :class="selectedVariantForModifiers?.id === variant.id ? 'text-blue-200' : 'text-gray-400'">
                                    {{ formatPrice(variant.price) }} ₽
                                </div>
                            </button>
                        </div>
                    </div>

                    <!-- Modifiers list -->
                    <div v-if="modifierDish?.modifiers?.length">
                        <div v-for="modifier in modifierDish.modifiers" :key="modifier.id" class="border-b border-gray-800 last:border-b-0">
                            <!-- Modifier header -->
                            <div class="flex items-center justify-between px-4 py-2 bg-[#151923]">
                                <span class="text-xs text-gray-500 uppercase tracking-wide">{{ modifier.name }}</span>
                                <span v-if="modifier.is_required" class="text-[10px] text-orange-400">обязательно</span>
                                <span v-else-if="modifier.type === 'multiple'" class="text-[10px] text-gray-600">
                                    {{ modifier.max_selections ? `макс. ${modifier.max_selections}` : '' }}
                                </span>
                            </div>

                            <!-- Options -->
                            <div class="px-2 py-1">
                                <button
                                    v-for="option in modifier.options"
                                    :key="option.id"
                                    @click="toggleModifierOption(modifier, option.id)"
                                    class="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg transition-colors hover:bg-[#252a3a]"
                                >
                                    <!-- Checkbox/Radio -->
                                    <div :class="[
                                        'w-5 h-5 flex items-center justify-center flex-shrink-0 transition-colors',
                                        modifier.type === 'single' ? 'rounded-full' : 'rounded',
                                        isModifierSelected(modifier.id, option.id)
                                            ? 'bg-blue-500 border-blue-500'
                                            : 'border-2 border-gray-600'
                                    ]">
                                        <svg v-if="isModifierSelected(modifier.id, option.id)"
                                             class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                                        </svg>
                                    </div>
                                    <!-- Name -->
                                    <span class="flex-1 text-left text-white text-sm">{{ option.name }}</span>
                                    <!-- Price -->
                                    <span v-if="option.price > 0" class="text-sm text-gray-400">+{{ formatPrice(option.price) }} ₽</span>
                                    <span v-else class="text-sm text-gray-600">0 ₽</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Empty state -->
                    <div v-if="!modifierDish?.modifiers?.length && !(editingCartItemIndex < 0 && modifierDish?.product_type === 'parent')"
                         class="flex flex-col items-center justify-center py-12 text-gray-500">
                        <svg class="w-10 h-10 mb-2 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                        </svg>
                        <span class="text-sm">Нет доступных опций</span>
                    </div>
                </div>

                <!-- Footer -->
                <div class="px-4 py-3 border-t border-gray-800 bg-[#151923]">
                    <div v-if="editingCartItemIndex < 0" class="flex items-center justify-between mb-3">
                        <span class="text-sm text-gray-400">Итого</span>
                        <span class="text-lg font-semibold text-white">{{ formatPrice(customizerTotalPrice) }} ₽</span>
                    </div>
                    <button
                        @click="confirmModifiers"
                        class="w-full py-2.5 bg-blue-500 hover:bg-blue-600 rounded-lg text-white font-medium transition-colors"
                    >
                        {{ editingCartItemIndex >= 0 ? 'Сохранить' : 'Добавить' }}
                    </button>
                </div>
            </div>
        </Transition>
    </Teleport>

    <!-- Dish Detail Panel -->
    <Teleport to="body">
        <!-- Backdrop -->
        <Transition name="fade">
            <div
                v-if="showDetailPanel"
                class="fixed inset-0 bg-black/60 z-[70]"
                @click="closeDishDetail"
            ></div>
        </Transition>

        <!-- Panel -->
        <Transition name="slide-panel">
            <div
                v-if="showDetailPanel"
                class="fixed top-0 right-0 h-full w-[420px] bg-[#1a1f2e] shadow-2xl z-[71] flex flex-col"
            >
                <!-- Header -->
                <div class="flex items-center justify-between px-4 py-3 border-b border-gray-800 bg-[#151923]">
                    <h3 class="text-base font-semibold text-white">Информация о товаре</h3>
                    <button
                        @click="closeDishDetail"
                        class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-gray-700 text-gray-400 hover:text-white transition-colors"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Content -->
                <div v-if="detailDish" class="flex-1 overflow-y-auto">
                    <!-- Image -->
                    <div class="aspect-video bg-dark-800 relative">
                        <img
                            v-if="detailDish.image"
                            :src="detailDish.image"
                            :alt="detailDish.name"
                            class="w-full h-full object-cover"
                        />
                        <div v-else class="w-full h-full flex items-center justify-center text-6xl text-gray-700">
                            🍽️
                        </div>
                        <!-- Tags -->
                        <div v-if="detailDish.is_popular || detailDish.is_new || detailDish.is_spicy || detailDish.is_vegetarian" class="absolute bottom-3 left-3 flex gap-1.5">
                            <span v-if="detailDish.is_popular" class="px-2 py-0.5 bg-red-500 text-white text-xs font-medium rounded">Хит</span>
                            <span v-if="detailDish.is_new" class="px-2 py-0.5 bg-green-500 text-white text-xs font-medium rounded">Новинка</span>
                            <span v-if="detailDish.is_spicy" class="px-2 py-0.5 bg-orange-500 text-white text-xs font-medium rounded">🌶️ Острое</span>
                            <span v-if="detailDish.is_vegetarian" class="px-2 py-0.5 bg-emerald-500 text-white text-xs font-medium rounded">🌱 Вегетарианское</span>
                        </div>
                    </div>

                    <!-- Info -->
                    <div class="p-4">
                        <!-- Name & Category -->
                        <h2 class="text-xl font-bold text-white mb-1">{{ detailDish.name }}</h2>
                        <p v-if="detailDish.category?.name" class="text-sm text-gray-500 mb-3">{{ detailDish.category.name }}</p>

                        <!-- Price -->
                        <div class="flex items-baseline gap-2 mb-4">
                            <span v-if="detailDish.product_type === 'parent'" class="text-2xl font-bold text-white">
                                от {{ formatPrice(detailDish.variants?.[0]?.price || 0) }} ₽
                            </span>
                            <span v-else class="text-2xl font-bold text-white">{{ formatPrice(detailDish.price) }} ₽</span>
                            <span v-if="detailDish.old_price" class="text-lg text-gray-500 line-through">{{ formatPrice(detailDish.old_price) }} ₽</span>
                        </div>

                        <!-- Description -->
                        <div v-if="detailDish.description" class="mb-4">
                            <h4 class="text-xs text-gray-500 uppercase tracking-wide mb-1">Описание</h4>
                            <p class="text-sm text-gray-300 leading-relaxed">{{ detailDish.description }}</p>
                        </div>

                        <!-- Variants -->
                        <div v-if="detailDish.product_type === 'parent' && detailDish.variants?.length" class="mb-4">
                            <h4 class="text-xs text-gray-500 uppercase tracking-wide mb-2">Варианты</h4>
                            <div class="space-y-1.5">
                                <div
                                    v-for="variant in detailDish.variants"
                                    :key="variant.id"
                                    class="flex items-center justify-between px-3 py-2 bg-[#252a3a] rounded-lg"
                                >
                                    <span class="text-sm text-white">{{ variant.variant_name }}</span>
                                    <span class="text-sm font-semibold text-white">{{ formatPrice(variant.price) }} ₽</span>
                                </div>
                            </div>
                        </div>

                        <!-- Weight & Cooking time -->
                        <div class="grid grid-cols-2 gap-3 mb-4">
                            <div v-if="detailDish.weight" class="px-3 py-2.5 bg-[#252a3a] rounded-lg">
                                <div class="text-[10px] text-gray-500 uppercase tracking-wide">Вес</div>
                                <div class="text-sm font-semibold text-white">{{ detailDish.weight }} г</div>
                            </div>
                            <div v-if="detailDish.cooking_time" class="px-3 py-2.5 bg-[#252a3a] rounded-lg">
                                <div class="text-[10px] text-gray-500 uppercase tracking-wide">Время готовки</div>
                                <div class="text-sm font-semibold text-white">{{ detailDish.cooking_time }} мин</div>
                            </div>
                        </div>

                        <!-- Nutrition (КБЖУ) -->
                        <div v-if="detailDish.calories || detailDish.proteins || detailDish.fats || detailDish.carbs" class="mb-4">
                            <h4 class="text-xs text-gray-500 uppercase tracking-wide mb-2">Пищевая ценность</h4>
                            <div class="grid grid-cols-4 gap-2">
                                <div class="px-2 py-2 bg-[#252a3a] rounded-lg text-center">
                                    <div class="text-lg font-bold text-white">{{ detailDish.calories || 0 }}</div>
                                    <div class="text-[10px] text-gray-500">ккал</div>
                                </div>
                                <div class="px-2 py-2 bg-[#252a3a] rounded-lg text-center">
                                    <div class="text-lg font-bold text-blue-400">{{ detailDish.proteins || 0 }}</div>
                                    <div class="text-[10px] text-gray-500">белки</div>
                                </div>
                                <div class="px-2 py-2 bg-[#252a3a] rounded-lg text-center">
                                    <div class="text-lg font-bold text-yellow-400">{{ detailDish.fats || 0 }}</div>
                                    <div class="text-[10px] text-gray-500">жиры</div>
                                </div>
                                <div class="px-2 py-2 bg-[#252a3a] rounded-lg text-center">
                                    <div class="text-lg font-bold text-green-400">{{ detailDish.carbs || 0 }}</div>
                                    <div class="text-[10px] text-gray-500">углеводы</div>
                                </div>
                            </div>
                        </div>

                        <!-- SKU -->
                        <div v-if="detailDish.sku" class="text-xs text-gray-600">
                            Артикул: {{ detailDish.sku }}
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="px-4 py-3 border-t border-gray-800 bg-[#151923]">
                    <button
                        @click="closeDishDetail"
                        class="w-full py-2.5 bg-gray-700 hover:bg-gray-600 rounded-lg text-white font-medium transition-colors"
                    >
                        Закрыть
                    </button>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup>
import { ref, reactive, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';
import api from '../../api';
import { usePosStore } from '../../stores/pos';
import { formatAmount } from '@/utils/formatAmount.js';
import UnifiedPaymentModal from '../../../components/UnifiedPaymentModal.vue';
import CustomerInfoCard from '../../../components/CustomerInfoCard.vue';
import DiscountModal from '../../../shared/components/modals/DiscountModal.vue';
import CustomerSelectModal from '../../../shared/components/modals/CustomerSelectModal.vue';
import { useCustomers } from '../../composables/useCustomers';
import { useOrderDiscounts } from '../../composables/useOrderDiscounts';
import { useOrderCustomer } from '../../composables/useOrderCustomer';

// Подключаем store для доступа к стоп-листу
const posStore = usePosStore();

const props = defineProps({
    show: { type: Boolean, default: false }
});

const emit = defineEmits(['close', 'created']);

// Data
const dishes = ref([]);
const categories = ref([]);
const couriers = ref([]);
const dishSearch = ref('');
const selectedCategory = ref(null);

// UI State
const showCalendar = ref(false);
const showTimePicker = ref(false);
const showAddressModal = ref(false);
const dishGridLocked = ref(false);

// Безопасное закрытие модалки адреса — блокируем pointer-events на dish grid
const closeAddressModalSafely = () => {
    dishGridLocked.value = true;
    showAddressModal.value = false;
    setTimeout(() => { dishGridLocked.value = false; }, 400);
};
const selectRecentAddress = (addr) => {
    order.address = (typeof addr === 'string' ? addr : addr?.street) || '';
    closeAddressModalSafely();
};
const showCourierList = ref(false);
const showCustomerList = ref(false);
const showDiscountModal = ref(false);
const showPrepaymentModal = ref(false);
const showPaymentDropdown = ref(false);
const showItemCommentModal = ref(false);
const editingItemIndex = ref(-1);
const itemCommentText = ref('');
const hoveredItemIndex = ref(-1);
const foundCustomers = ref([]);
const showCustomerDropdown = ref(false);
const hideCustomerDropdown = () => window.setTimeout(() => showCustomerDropdown.value = false, 200);
const searchingCustomer = ref(false);

const calendarDate = ref(new Date());
const timeInput = ref('');
const recentAddresses = ref([]);
const selectedCourier = ref(null);
const creating = ref(false);
const deliveryInfo = ref(null);

// Mini map
const miniMapContainer = ref(null);
const miniMapReady = ref(false);
const ymapsLoading = ref(false);
let miniMap = null;
let miniMapPlacemark = null;
let ymapsLoaded = false;

// ============================================================
// Enterprise: Composables для единой логики скидок и клиентов
// ============================================================

// Инициализируем composable для скидок
const orderDiscounts = useOrderDiscounts({
    onReset: () => {
        // При сбросе скидок удаляем подарочные позиции
        order.items = order.items.filter(item => !item.is_gift);
        order.bonus_used = 0;
        order.promo_code = '';
    }
});

// Инициализируем composable для клиента с привязкой к скидкам
const orderCustomer = useOrderCustomer({
    discounts: orderDiscounts,  // Автосброс скидок при смене клиента
    onCustomerChange: (customer, { isChange }) => {
        if (isChange) {
            // Пересчитать скидки для нового клиента
            calculateDiscountFromAPI();
        }
    }
});

// Алиасы для обратной совместимости (переход на composables)
const promoDiscount = orderDiscounts.promoDiscount;
const manualDiscount = orderDiscounts.manualDiscountPercent;
const selectedPromotion = orderDiscounts.selectedPromotion;
const promotionDiscount = orderDiscounts.promotionDiscount;
const appliedDiscountsData = orderDiscounts.appliedDiscounts;
const loyaltyDiscount = orderDiscounts.loyaltyDiscount;
const loyaltyLevelName = orderDiscounts.loyaltyLevelName;
const bonusToSpend = orderDiscounts.bonusToSpend;

// Алиасы для клиента
const selectedCustomerId = orderCustomer.customerId;
const selectedCustomerData = orderCustomer.customerData;

// Promotions (акции из бэк-офиса)
const activePromotions = ref([]);
const loadingPromotions = ref(false);
const calculatingDiscount = ref(false);

// Customer Info Card
const showCustomerCard = ref(false);
const customerNameRef = ref(null);

// Bonus settings (настройки бонусной системы)
const bonusSettings = ref(null);

// Working hours
const WORKING_HOURS = { start: 10, end: 23 };
const MIN_PREP_TIME = 30;

// Date helper
const formatDateForInput = (date) => {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
};

// Order form
const order = reactive({
    type: 'delivery',
    phone: '',
    customer_name: '',
    address: '',
    entrance: '',
    floor: '',
    apartment: '',
    is_asap: true,
    scheduled_date: formatDateForInput(new Date()),
    scheduled_time: '',
    items: [],
    promo_code: '',
    payment_method: 'card',
    change_from: null,
    comment: '',
    prepayment: 0,
    prepayment_method: null,
    bonus_used: 0
});

// Category colors
const categoryColors = [
    '#FF6B6B', '#4ECDC4', '#45B7D1', '#96CEB4', '#FFEAA7',
    '#DDA0DD', '#98D8C8', '#F7DC6F', '#BB8FCE', '#85C1E9',
    '#F8B500', '#00CED1', '#FF69B4', '#32CD32', '#FFD700'
];

const getCategoryColor = (index) => categoryColors[index % categoryColors.length];

const getDishCategoryColor = (dish) => {
    const idx = categories.value.findIndex(c => c.id === dish.category_id);
    return idx >= 0 ? getCategoryColor(idx) : '#666';
};

// Computed
const currentTime = computed(() => {
    const now = new Date();
    return now.toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' });
});

const displayDate = computed(() => {
    const today = new Date();
    const tomorrow = new Date(today);
    tomorrow.setDate(tomorrow.getDate() + 1);

    if (order.scheduled_date === formatDateForInput(today)) return 'Сегодня';
    if (order.scheduled_date === formatDateForInput(tomorrow)) return 'Завтра';

    const date = new Date(order.scheduled_date);
    return date.toLocaleDateString('ru-RU', { day: 'numeric', month: 'short' });
});

// Проверка: выбрана ли сегодняшняя дата (для "Ближайшее время")
const isScheduledDateToday = computed(() => {
    const today = new Date();
    return order.scheduled_date === formatDateForInput(today);
});

const paymentMethodLabel = computed(() => {
    const labels = { cash: 'наличными', card: 'картой' };
    return labels[order.payment_method] || 'наличными';
});

const filteredDishes = computed(() => {
    let result = dishes.value;
    if (selectedCategory.value) {
        result = result.filter(d => d.category_id === selectedCategory.value);
    }
    if (dishSearch.value) {
        const q = dishSearch.value.toLowerCase();
        result = result.filter(d => d.name.toLowerCase().includes(q));
    }
    // Добавляем флаг is_stopped для блюд из стоп-листа
    return result.map(dish => ({
        ...dish,
        is_stopped: posStore.stopListDishIds.has(dish.id)
    }));
});

const subtotal = computed(() => {
    return order.items.reduce((sum, item) => sum + (item.price * item.quantity), 0);
});

// Items для DiscountModal (формат для API)
const discountItems = computed(() => {
    return order.items.map(item => ({
        dish_id: item.dish_id,
        category_id: item.category_id,
        price: item.price,
        quantity: item.quantity
    }));
});

const deliveryFee = computed(() => {
    if (order.type !== 'delivery') return 0;
    return deliveryInfo.value?.delivery_fee || 0;
});

const discountAmount = computed(() => {
    const manual = manualDiscount.value > 0 ? Math.round(subtotal.value * manualDiscount.value / 100) : 0;
    // Скидка акции применяется к товарам (кроме free_delivery, которая отнимается от доставки)
    const promoFromPromotion = selectedPromotion.value?.type === 'free_delivery' ? 0 : promotionDiscount.value;
    return promoDiscount.value + manual + promoFromPromotion;
});

// Скидка на доставку (например, от акции "бесплатная доставка")
const deliveryDiscountAmount = computed(() => {
    if (selectedPromotion.value?.type === 'free_delivery') {
        return promotionDiscount.value;
    }
    return 0;
});

// Общая сумма всех скидок (для кнопки) - включая бонусы
const totalDiscountAmount = computed(() => {
    const bonus = bonusToSpend.value || 0;
    return discountAmount.value + loyaltyDiscount.value + bonus;
});

// Количество товаров в корзине
const itemsCount = computed(() => {
    return order.items.reduce((sum, item) => sum + (item.quantity || 1), 0);
});

// Текст для количества товаров (склонение)
const itemsCountText = computed(() => {
    const count = itemsCount.value;
    const lastDigit = count % 10;
    const lastTwoDigits = count % 100;

    if (lastTwoDigits >= 11 && lastTwoDigits <= 14) return 'товаров';
    if (lastDigit === 1) return 'товар';
    if (lastDigit >= 2 && lastDigit <= 4) return 'товара';
    return 'товаров';
});

// Список скидок для отображения в футере (как в зале)
const appliedDiscountsList = computed(() => {
    const result = [];

    // Если есть applied_discounts - используем их
    if (appliedDiscountsData.value && appliedDiscountsData.value.length > 0) {
        const validDiscounts = appliedDiscountsData.value.filter(d => d.amount > 0);
        result.push(...validDiscounts);
        return result;
    }

    // Фоллбэк: собираем из отдельных переменных
    if (loyaltyDiscount.value > 0) {
        result.push({
            name: loyaltyLevelName.value || 'Скидка уровня',
            type: 'level',
            sourceType: 'level',
            amount: loyaltyDiscount.value
        });
    }

    if (promotionDiscount.value > 0 && selectedPromotion.value) {
        result.push({
            name: selectedPromotion.value.name || 'Акция',
            type: 'promotion',
            sourceType: 'promotion',
            amount: promotionDiscount.value
        });
    }

    return result;
});

// Иконка для типа скидки
const getDiscountIcon = (type) => {
    const icons = {
        'level': '★',
        'promo_code': '🏷️',
        'promotion': '🎁',
        'certificate': '🎫',
        'bonus': '💎',
        'bonus_multiply': '✨',
        'bonus_add': '💎',
        'gift': '🎁',
        'birthday': '🎂',
        'rounding': '🔄',
        'quick': '%',
        'custom': '%',
        'percent': '%',
        'fixed': '₽'
    };
    return icons[type] || '🏷️';
};

// Форматирование суммы скидки
const formatDiscountAmount = (discount) => {
    const amount = discount.amount || 0;

    if (discount.type === 'rounding' || discount.sourceType === 'rounding') {
        return new Intl.NumberFormat('ru-RU', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(amount) + ' ₽';
    }

    return formatPrice(amount) + ' ₽';
};

const total = computed(() => {
    const delivery = Math.max(0, deliveryFee.value - deliveryDiscountAmount.value);
    const bonus = bonusToSpend.value || 0; // Enterprise: бонусы к списанию
    return Math.max(0, subtotal.value - discountAmount.value - loyaltyDiscount.value - bonus + delivery);
});

// Total price in customizer panel (base price + modifiers)
const customizerTotalPrice = computed(() => {
    // Base price from variant or dish
    let price = parseFloat(selectedVariantForModifiers.value?.price || modifierDish.value?.price || 0);

    // Add modifier prices
    modifierDish.value?.modifiers?.forEach(mod => {
        const sel = selectedModifiers.value[mod.id];
        if (mod.type === 'single' && sel) {
            const opt = mod.options?.find(o => o.id === sel);
            if (opt) price += parseFloat(opt.price) || 0;
        } else if (Array.isArray(sel)) {
            sel.forEach(optId => {
                const opt = mod.options?.find(o => o.id === optId);
                if (opt) price += parseFloat(opt.price) || 0;
            });
        }
    });

    return price;
});

// Проверка валидности телефона
const isPhoneValid = computed(() => {
    const digits = (order.phone || '').replace(/\D/g, '');
    return digits.length >= 11;
});

// Сколько цифр осталось ввести
const phoneDigitsRemaining = computed(() => {
    const digits = (order.phone || '').replace(/\D/g, '');
    return Math.max(0, 11 - digits.length);
});

const canCreate = computed(() => {
    // Базовые проверки
    const baseValid = order.phone?.trim() &&
           isPhoneValid.value &&
           order.customer_name?.trim() &&
           order.items.length > 0 &&
           (order.type === 'pickup' || order.address?.trim());

    if (!baseValid) return false;

    // Если дата не сегодня - обязательно должно быть выбрано время (не ASAP)
    if (!isScheduledDateToday.value && order.is_asap) {
        return false;
    }

    return true;
});

const formatTimeDisplay = computed(() => {
    const raw = timeInput.value.replace(':', '');
    const h1 = raw[0] || '_';
    const h2 = raw[1] || '_';
    const m1 = raw[2] || '_';
    const m2 = raw[3] || '_';
    return `${h1}${h2}:${m1}${m2}`;
});

const isTimeInputValid = computed(() => {
    const raw = timeInput.value.replace(':', '');
    if (raw.length < 4) return false;

    const hours = parseInt(raw.slice(0, 2));
    const minutes = parseInt(raw.slice(2, 4));

    // Check valid time format
    if (hours < 0 || hours > 23 || minutes < 0 || minutes > 59) return false;

    // Check working hours
    if (hours < WORKING_HOURS.start || hours >= WORKING_HOURS.end) return false;

    // Check if today - time must be in future
    const now = new Date();
    const isToday = order.scheduled_date === formatDateForInput(now);

    if (isToday) {
        const currentMinutes = now.getHours() * 60 + now.getMinutes();
        const inputMinutes = hours * 60 + minutes;
        if (inputMinutes < currentMinutes + MIN_PREP_TIME) return false;
    }

    return true;
});

// Calendar
const calendarMonthYear = computed(() => {
    const months = ['Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь',
                    'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь'];
    return `${months[calendarDate.value.getMonth()]} ${calendarDate.value.getFullYear()}`;
});

const calendarDays = computed(() => {
    const year = calendarDate.value.getFullYear();
    const month = calendarDate.value.getMonth();
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    const firstDay = new Date(year, month, 1);
    const lastDay = new Date(year, month + 1, 0);

    let startDay = firstDay.getDay() - 1;
    if (startDay < 0) startDay = 6;

    const days = [];

    // Previous month
    const prevMonthLastDay = new Date(year, month, 0).getDate();
    for (let i = startDay - 1; i >= 0; i--) {
        days.push({
            day: prevMonthLastDay - i,
            date: '',
            isCurrentMonth: false,
            isToday: false,
            isSelected: false,
            disabled: true
        });
    }

    // Current month
    for (let i = 1; i <= lastDay.getDate(); i++) {
        const date = new Date(year, month, i);
        const dateStr = formatDateForInput(date);
        const isPast = date < today;
        days.push({
            day: i,
            date: dateStr,
            isCurrentMonth: true,
            isToday: date.getTime() === today.getTime(),
            isSelected: order.scheduled_date === dateStr,
            disabled: isPast
        });
    }

    // Next month
    const remaining = 42 - days.length;
    for (let i = 1; i <= remaining; i++) {
        days.push({
            day: i,
            date: '',
            isCurrentMonth: false,
            isToday: false,
            isSelected: false,
            disabled: true
        });
    }

    return days;
});

const availableTimeSlots = computed(() => {
    const slots = [];
    const now = new Date();
    const currentMinutes = now.getHours() * 60 + now.getMinutes();
    const isToday = order.scheduled_date === formatDateForInput(now);

    for (let h = WORKING_HOURS.start; h < WORKING_HOURS.end; h++) {
        for (let m = 0; m < 60; m += 30) {
            const time = `${h.toString().padStart(2, '0')}:${m.toString().padStart(2, '0')}`;
            const slotMinutes = h * 60 + m;
            const disabled = isToday && slotMinutes < currentMinutes + MIN_PREP_TIME;
            slots.push({ time, disabled });
        }
    }
    return slots;
});

// Editing cart item index (for modifier editing)
const editingCartItemIndex = ref(-1);

// Dish detail panel
const showDetailPanel = ref(false);
const detailDish = ref(null);

const openDishDetail = (dish) => {
    detailDish.value = dish;
    showDetailPanel.value = true;
};

const closeDishDetail = () => {
    showDetailPanel.value = false;
    setTimeout(() => {
        detailDish.value = null;
    }, 300);
};

// Methods
const close = () => emit('close');

const formatPrice = (price) => formatAmount(price).toLocaleString('ru-RU');

// Get total price of an item including modifiers
const getItemTotalPrice = (item) => {
    let price = parseFloat(item.price) || 0;
    if (item.modifiers?.length) {
        item.modifiers.forEach(mod => {
            price += parseFloat(mod.price) || 0;
        });
    }
    return price;
};

// Check if cart item has available modifiers
const itemHasModifiers = (item) => {
    // If item already has modifiers, show the button
    if (item.modifiers?.length) return true;

    // Find the parent dish to check if it has modifiers
    const dishId = item.parent_id || item.id;
    const dish = dishes.value.find(d => d.id === dishId);
    if (dish?.modifiers?.length) return true;

    // Also check if it's a variant and parent has modifiers
    if (!dish) {
        const parent = dishes.value.find(d =>
            d.product_type === 'parent' &&
            d.variants?.some(v => v.id === item.id)
        );
        if (parent?.modifiers?.length) return true;
    }

    return false;
};

const cyclePaymentMethod = () => {
    const methods = ['cash', 'card'];
    const idx = methods.indexOf(order.payment_method);
    order.payment_method = methods[(idx + 1) % methods.length];
};

const calendarPrevMonth = () => {
    const newDate = new Date(calendarDate.value);
    newDate.setMonth(newDate.getMonth() - 1);
    calendarDate.value = newDate;
};

const calendarNextMonth = () => {
    const newDate = new Date(calendarDate.value);
    newDate.setMonth(newDate.getMonth() + 1);
    calendarDate.value = newDate;
};

const selectDate = (day) => {
    if (day.disabled || !day.isCurrentMonth) return;
    order.scheduled_date = day.date;
    showCalendar.value = false;
};

const selectQuickDate = (type) => {
    const date = new Date();
    if (type === 'tomorrow') date.setDate(date.getDate() + 1);
    order.scheduled_date = formatDateForInput(date);
    showCalendar.value = false;
};

const setAsap = () => {
    order.is_asap = true;
    order.scheduled_time = '';
    timeInput.value = '';
    showTimePicker.value = false;
};

const selectTime = (time) => {
    order.scheduled_time = time;
    order.is_asap = false;
    timeInput.value = time;
    showTimePicker.value = false;
};

// Numpad methods
const addTimeDigit = (digit) => {
    const raw = timeInput.value.replace(':', '');
    if (raw.length >= 4) return;
    timeInput.value = raw + digit;
};

const clearTimeInput = () => {
    timeInput.value = '';
};

const backspaceTimeInput = () => {
    const raw = timeInput.value.replace(':', '');
    timeInput.value = raw.slice(0, -1);
};

const confirmTimeInput = () => {
    if (!isTimeInputValid.value) return;

    const raw = timeInput.value.replace(':', '');
    const formattedTime = `${raw.slice(0, 2)}:${raw.slice(2, 4)}`;
    order.scheduled_time = formattedTime;
    order.is_asap = false;
    showTimePicker.value = false;
};

const selectCourier = (courier) => {
    selectedCourier.value = courier;
    showCourierList.value = false;
};

const getItemQuantity = (dishId) => {
    const item = order.items.find(i => i.id === dishId);
    return item ? item.quantity : 0;
};

// Get total quantity for a dish including all its variants
const getTotalDishQuantity = (dish) => {
    if (dish.product_type === 'parent' && dish.variants?.length) {
        // Sum quantities of all variants
        return dish.variants.reduce((sum, v) => {
            const item = order.items.find(i => i.id === v.id);
            return sum + (item ? item.quantity : 0);
        }, 0);
    }
    // For simple dishes
    const item = order.items.find(i => i.id === dish.id);
    return item ? item.quantity : 0;
};

// Quick add dish - immediately adds to cart
const quickAddDish = (dish) => {
    // Check stop list
    if (dish.is_stopped || posStore.stopListDishIds.has(dish.id)) {
        window.$toast?.('Блюдо недоступно (в стоп-листе)', 'error');
        return;
    }

    // For simple dishes, add directly
    addItemToCart(dish);
};

// Quick add variant - adds specific variant to cart
const quickAddVariant = (dish, variant) => {
    // Check stop list
    if (dish.is_stopped || posStore.stopListDishIds.has(variant.id)) {
        window.$toast?.('Блюдо недоступно (в стоп-листе)', 'error');
        return;
    }

    addItemToCart(dish, variant);
};

// Open customizer panel (for variants and modifiers)
const openDishCustomizer = (dish) => {
    if (dish.is_stopped) return;

    modifierDish.value = dish;
    selectedVariantForModifiers.value = null;
    selectedModifiers.value = {};

    // If parent with variants, select first available
    if (dish.product_type === 'parent' && dish.variants?.length) {
        const firstAvailable = dish.variants.find(v => v.is_available !== false);
        if (firstAvailable) {
            selectedVariantForModifiers.value = firstAvailable;
        }
    }

    // Set default modifiers
    dish.modifiers?.forEach(mod => {
        if (mod.type === 'single') {
            const defaultOpt = mod.options?.find(o => o.is_default);
            if (defaultOpt) {
                selectedModifiers.value[mod.id] = defaultOpt.id;
            }
        } else {
            selectedModifiers.value[mod.id] = [];
        }
    });

    showModifierSelector.value = true;
};

// Add variant directly from the card (used for quick-add buttons)
const addVariantDirect = (dish, variant) => {
    // Check stop list
    if (dish.is_stopped || posStore.stopListDishIds.has(variant.id)) {
        window.$toast?.('Блюдо недоступно (в стоп-листе)', 'error');
        return;
    }

    // If dish has modifiers, open modifier selector
    if (dish.modifiers?.length) {
        selectedVariantForModifiers.value = variant;
        openModifierSelector(dish, variant);
        return;
    }

    // Otherwise add directly to cart
    addItemToCart(dish, variant);
};

const addDish = (dish) => {
    // Проверка на стоп-лист
    if (dish.is_stopped || posStore.stopListDishIds.has(dish.id)) {
        window.$toast?.('Блюдо недоступно (в стоп-листе)', 'error');
        return;
    }

    // Для parent товаров показываем селектор вариантов
    if (dish.product_type === 'parent' && dish.variants?.length) {
        selectedParentDish.value = dish;
        showVariantSelector.value = true;
        return;
    }

    // Для товаров с модификаторами показываем модалку
    if (dish.modifiers?.length) {
        openModifierSelector(dish);
        return;
    }

    // Для простых товаров добавляем напрямую
    addItemToCart(dish);
};

const addItemToCart = (dish, variant = null, modifiers = []) => {
    const itemId = variant ? variant.id : dish.id;
    const itemName = variant ? `${dish.name} ${variant.variant_name}` : dish.name;
    const itemPrice = variant ? variant.price : dish.price;

    // Если нет модификаторов, пробуем найти существующий такой же товар
    if (!modifiers.length) {
        const existing = order.items.find(i => i.id === itemId && !i.note && !i.modifiers?.length);
        if (existing) {
            existing.quantity++;
            // Пересчитать скидки
            calculateDiscountFromAPI();
            return;
        }
    }

    order.items.push({
        id: itemId,
        dish_id: variant ? variant.id : dish.id,
        category_id: dish.category_id,
        name: itemName,
        price: itemPrice,
        quantity: 1,
        emoji: dish.emoji || null,
        modifiers: modifiers,
        note: null,
        parent_id: variant ? dish.id : null
    });

    // Пересчитать скидки после добавления товара
    calculateDiscountFromAPI();
};

const getMinVariantPrice = (dish) => {
    if (!dish.variants?.length) return dish.price || 0;
    return Math.min(...dish.variants.map(v => parseFloat(v.price) || 0));
};

// Variant selector
const showVariantSelector = ref(false);
const selectedParentDish = ref(null);

const selectVariant = (variant) => {
    if (!variant.is_available) return;
    const parent = selectedParentDish.value;

    // Проверяем есть ли модификаторы у родителя
    if (parent.modifiers?.length) {
        showVariantSelector.value = false;
        selectedVariantForModifiers.value = variant;
        openModifierSelector(parent, variant);
    } else {
        addItemToCart(parent, variant);
        closeVariantSelector();
    }
};

const closeVariantSelector = () => {
    showVariantSelector.value = false;
    selectedParentDish.value = null;
};

// Modifier selector
const showModifierSelector = ref(false);
const modifierDish = ref(null);
const selectedVariantForModifiers = ref(null);
const selectedModifiers = ref({});

const openModifierSelector = (dish, variant = null) => {
    modifierDish.value = dish;
    selectedVariantForModifiers.value = variant;
    selectedModifiers.value = {};

    // Устанавливаем значения по умолчанию
    dish.modifiers?.forEach(mod => {
        if (mod.type === 'single') {
            const defaultOpt = mod.options?.find(o => o.is_default);
            if (defaultOpt) {
                selectedModifiers.value[mod.id] = defaultOpt.id;
            }
        } else {
            selectedModifiers.value[mod.id] = [];
        }
    });

    showModifierSelector.value = true;
};

const toggleModifierOption = (modifier, optionId) => {
    if (modifier.type === 'single') {
        // For single type: if clicking on already selected option and not required - deselect
        if (selectedModifiers.value[modifier.id] === optionId && !modifier.is_required) {
            selectedModifiers.value[modifier.id] = null;
        } else {
            selectedModifiers.value[modifier.id] = optionId;
        }
    } else {
        const current = selectedModifiers.value[modifier.id] || [];
        const index = current.indexOf(optionId);
        if (index >= 0) {
            current.splice(index, 1);
        } else {
            if (!modifier.max_selections || current.length < modifier.max_selections) {
                current.push(optionId);
            }
        }
        selectedModifiers.value[modifier.id] = current;
    }
};

const isModifierSelected = (modifierId, optionId) => {
    const sel = selectedModifiers.value[modifierId];
    if (Array.isArray(sel)) {
        return sel.includes(optionId);
    }
    return sel === optionId;
};

const confirmModifiers = () => {
    const dish = modifierDish.value;
    const variant = selectedVariantForModifiers.value;

    // Собираем выбранные модификаторы
    const mods = [];
    dish.modifiers?.forEach(mod => {
        const sel = selectedModifiers.value[mod.id];
        if (mod.type === 'single' && sel) {
            const opt = mod.options?.find(o => o.id === sel);
            if (opt) {
                mods.push({ id: opt.id, name: opt.name, price: parseFloat(opt.price) || 0 });
            }
        } else if (Array.isArray(sel)) {
            sel.forEach(optId => {
                const opt = mod.options?.find(o => o.id === optId);
                if (opt) {
                    mods.push({ id: opt.id, name: opt.name, price: parseFloat(opt.price) || 0 });
                }
            });
        }
    });

    // If editing existing cart item, update it
    if (editingCartItemIndex.value >= 0) {
        const item = order.items[editingCartItemIndex.value];
        if (item) {
            item.modifiers = mods;
        }
        closeModifierSelector();
        return;
    }

    // Otherwise add new item
    addItemToCart(dish, variant, mods);
    closeModifierSelector();
};

const closeModifierSelector = () => {
    showModifierSelector.value = false;
    modifierDish.value = null;
    selectedVariantForModifiers.value = null;
    selectedModifiers.value = {};
    editingCartItemIndex.value = -1;
};

// Open modifier panel for existing cart item
const openItemModifiers = (index) => {
    const item = order.items[index];
    if (!item) return;

    // Find the original dish to get modifiers list
    // If item has parent_id (it's a variant), use that; otherwise use item.id
    const dishId = item.parent_id || item.id;
    let parentDish = dishes.value.find(d => d.id === dishId);

    // If still not found, try to find parent dish that has this item as variant
    if (!parentDish) {
        parentDish = dishes.value.find(d =>
            d.product_type === 'parent' &&
            d.variants?.some(v => v.id === item.id)
        );
    }

    if (!parentDish || !parentDish.modifiers?.length) {
        window.$toast?.('У этого блюда нет модификаторов', 'info');
        return;
    }

    // Set up for editing
    editingCartItemIndex.value = index;
    modifierDish.value = parentDish;
    selectedVariantForModifiers.value = null;

    // Pre-select existing modifiers
    selectedModifiers.value = {};
    parentDish.modifiers.forEach(mod => {
        if (mod.type === 'single') {
            // Find if any option is selected
            const selected = item.modifiers?.find(m =>
                mod.options?.some(o => o.id === m.id)
            );
            if (selected) {
                selectedModifiers.value[mod.id] = selected.id;
            }
        } else {
            // Multiple selection
            selectedModifiers.value[mod.id] = item.modifiers
                ?.filter(m => mod.options?.some(o => o.id === m.id))
                .map(m => m.id) || [];
        }
    });

    showModifierSelector.value = true;
};

const decrementItem = (index) => {
    if (order.items[index].quantity > 1) {
        order.items[index].quantity--;
    } else {
        order.items.splice(index, 1);
    }
};

const removeItem = (index) => {
    order.items.splice(index, 1);
};

const clearCart = () => {
    order.items = [];
    // Enterprise: сброс скидок при очистке корзины
    orderDiscounts.resetAllDiscounts(false);
    order.bonus_used = 0;
    order.promo_code = '';
};

// Item comment methods
const openItemComment = (index) => {
    editingItemIndex.value = index;
    itemCommentText.value = order.items[index]?.note || '';
    showItemCommentModal.value = true;
};

const saveItemComment = () => {
    if (editingItemIndex.value >= 0 && editingItemIndex.value < order.items.length) {
        order.items[editingItemIndex.value].note = itemCommentText.value.trim() || null;
    }
    showItemCommentModal.value = false;
    editingItemIndex.value = -1;
    itemCommentText.value = '';
};

const clearItemComment = () => {
    if (editingItemIndex.value >= 0 && editingItemIndex.value < order.items.length) {
        order.items[editingItemIndex.value].note = null;
    }
    showItemCommentModal.value = false;
    editingItemIndex.value = -1;
    itemCommentText.value = '';
};

// Форматирование имени клиента (первая буква каждого слова заглавная)
const formatCustomerName = () => {
    if (!order.customer_name) return;
    const words = order.customer_name.trim().replace(/\s+/g, ' ').split(' ');
    order.customer_name = words.map(word => {
        if (!word) return '';
        return word.charAt(0).toUpperCase() + word.slice(1).toLowerCase();
    }).join(' ');
};

// Phone mask functions
const formatPhoneNumber = (value) => {
    if (!value) return '';

    // Remove all non-digits
    let digits = value.replace(/\D/g, '');

    // Handle 8 at start - convert to 7
    if (digits.startsWith('8')) {
        digits = '7' + digits.slice(1);
    }

    // Add 7 if not present
    if (digits.length > 0 && !digits.startsWith('7')) {
        digits = '7' + digits;
    }

    // Limit to 11 digits
    digits = digits.slice(0, 11);

    // Format: +7 (999) 999-99-99
    let formatted = '';
    if (digits.length > 0) {
        formatted = '+' + digits[0];
    }
    if (digits.length > 1) {
        formatted += ' (' + digits.slice(1, 4);
    }
    if (digits.length >= 4) {
        formatted += ') ' + digits.slice(4, 7);
    }
    if (digits.length >= 7) {
        formatted += '-' + digits.slice(7, 9);
    }
    if (digits.length >= 9) {
        formatted += '-' + digits.slice(9, 11);
    }

    return formatted;
};

// Блокировка ввода букв - только цифры
const onlyDigits = (e) => {
    const char = String.fromCharCode(e.which || e.keyCode);
    if (!/[\d]/.test(char)) {
        e.preventDefault();
    }
};

const onPhoneInput = (event) => {
    const input = event.target;
    const inputValue = input.value;
    const cursorPos = input.selectionStart;
    const oldValue = order.phone || '';

    // Get digits from old and new values
    const oldDigits = oldValue.replace(/\D/g, '');
    const newDigits = inputValue.replace(/\D/g, '');

    // Check if user is deleting
    const isDeleting = newDigits.length < oldDigits.length;

    // Format the new value
    order.phone = formatPhoneNumber(inputValue);

    // Calculate new cursor position
    nextTick(() => {
        let newPos;

        if (isDeleting) {
            // When deleting, count digits before cursor in new value
            const digitsBeforeCursor = inputValue.slice(0, cursorPos).replace(/\D/g, '').length;
            // Find position in formatted string where this many digits are before
            let digitCount = 0;
            newPos = 0;
            for (let i = 0; i < order.phone.length; i++) {
                if (/\d/.test(order.phone[i])) {
                    digitCount++;
                }
                if (digitCount >= digitsBeforeCursor) {
                    newPos = i + 1;
                    break;
                }
                newPos = i + 1;
            }
        } else {
            // When typing, put cursor after the last digit entered
            const digitsBeforeCursor = inputValue.slice(0, cursorPos).replace(/\D/g, '').length;
            let digitCount = 0;
            newPos = order.phone.length;
            for (let i = 0; i < order.phone.length; i++) {
                if (/\d/.test(order.phone[i])) {
                    digitCount++;
                    if (digitCount === digitsBeforeCursor) {
                        newPos = i + 1;
                        break;
                    }
                }
            }
        }

        // Don't let cursor go before +7 (
        if (newPos < 4 && order.phone.length >= 4) newPos = 4;
        if (newPos > order.phone.length) newPos = order.phone.length;

        input.setSelectionRange(newPos, newPos);
    });

    // Trigger customer search with raw digits
    searchCustomer();
};

const getPhoneDigits = () => {
    return (order.phone || '').replace(/\D/g, '');
};

let searchTimeout = null;
const searchCustomer = () => {
    // Debounce search
    if (searchTimeout) clearTimeout(searchTimeout);

    const digits = getPhoneDigits();
    // Need at least 4 digits (7 + 3 more) to search
    if (digits.length < 4) {
        foundCustomers.value = [];
        showCustomerDropdown.value = false;
        // Если телефон почти пустой — сбрасываем выбранного клиента
        if (digits.length <= 1 && selectedCustomerId.value) {
            clearSelectedCustomer();
        }
        return;
    }

    searchTimeout = setTimeout(async () => {
        searchingCustomer.value = true;
        try {
            const response = await api.customers.search(digits);
            foundCustomers.value = Array.isArray(response) ? response : (response?.data || []);
            showCustomerDropdown.value = foundCustomers.value.length > 0;
        } catch (e) {
            console.error('Customer search failed:', e);
            foundCustomers.value = [];
            showCustomerDropdown.value = false;
        } finally {
            searchingCustomer.value = false;
        }
    }, 300);
};

const selectCustomer = (customer) => {
    // Enterprise: используем composable для управления клиентом
    // Composable автоматически сбросит скидки при смене клиента
    orderCustomer.selectCustomer(customer);

    // Заполняем форму заказа данными клиента
    order.phone = formatPhoneNumber(customer.phone || '');
    order.customer_name = customer.name || '';

    // Извлекаем строку адреса из объекта default_address или используем строковое поле
    const defaultAddr = customer.default_address;
    if (defaultAddr && typeof defaultAddr === 'object') {
        order.address = defaultAddr.street || defaultAddr.address || defaultAddr.full_address || '';
        order.apartment = defaultAddr.apartment || '';
        order.entrance = defaultAddr.entrance || '';
        order.floor = defaultAddr.floor || '';
    } else {
        order.address = defaultAddr || customer.address || '';
    }

    showCustomerDropdown.value = false;
    foundCustomers.value = [];

    // Устанавливаем скидку лояльности если есть уровень
    applyLoyaltyDiscount(customer);

    // Load customer's recent addresses
    if (customer.id) {
        loadCustomerAddresses(customer.id);
    }
};

const loadCustomerAddresses = async (customerId) => {
    try {
        const response = await api.customers.getAddresses(customerId);
        const addresses = Array.isArray(response) ? response : (response?.data || []);
        if (addresses.length > 0) {
            // Store full address objects for detailed selection
            customerSavedAddresses.value = addresses;
            // Also update string-based recentAddresses for backward compatibility
            recentAddresses.value = addresses.map(a => {
                if (typeof a === 'string') return a;
                return a.street || a.address || a.full_address || '';
            }).filter(Boolean);
        }
    } catch (e) {
        console.error('Failed to load customer addresses:', e);
    }
};

// Customer's saved addresses (full objects)
const customerSavedAddresses = ref([]);

// Select saved address with all details
const selectSavedAddress = (addr) => {
    order.address = addr.street || addr.address || addr.full_address || '';
    order.apartment = addr.apartment || '';
    order.entrance = addr.entrance || '';
    order.floor = addr.floor || '';
    closeAddressModalSafely();
};

// Customer list methods (using CustomerSelectModal)
const openCustomerList = () => {
    showCustomerList.value = true;
};

const onCustomerSelected = (customer) => {
    // Enterprise: используем composable для управления клиентом
    // Composable автоматически сбросит скидки при смене клиента
    orderCustomer.selectCustomer(customer);

    // Заполняем форму заказа данными клиента
    order.phone = formatPhoneNumber(customer.phone || '');
    order.customer_name = customer.name || '';

    // Извлекаем строку адреса из объекта default_address
    const defaultAddr = customer.default_address;
    if (defaultAddr && typeof defaultAddr === 'object') {
        order.address = defaultAddr.street || defaultAddr.address || defaultAddr.full_address || '';
        order.apartment = defaultAddr.apartment || '';
        order.entrance = defaultAddr.entrance || '';
        order.floor = defaultAddr.floor || '';
    } else {
        order.address = defaultAddr || customer.address || '';
    }

    // Устанавливаем скидку лояльности если есть уровень
    applyLoyaltyDiscount(customer);

    if (customer.id) {
        loadCustomerAddresses(customer.id);
    }
};

// Функция для применения скидки лояльности
const applyLoyaltyDiscount = (customer) => {
    const level = customer?.loyalty_level || customer?.loyaltyLevel;
    if (level && level.discount_percent > 0) {
        loyaltyLevelName.value = level.name || '';
        // Пересчитать все скидки через API
        calculateDiscountFromAPI();
    } else {
        loyaltyDiscount.value = 0;
        loyaltyLevelName.value = '';
    }
};

// Customer Info Card методы
const openCustomerCard = (e) => {
    if (selectedCustomerData.value) {
        customerNameRef.value = e.currentTarget;
        showCustomerCard.value = true;
    }
};

// Enterprise: сброс скидок теперь через composable
// Алиас для обратной совместимости
const resetAllDiscounts = () => {
    orderDiscounts.resetAllDiscounts(false); // без toast, composable сам покажет
};

const clearSelectedCustomer = () => {
    // Enterprise: используем composable - он автоматически сбросит скидки
    orderCustomer.clearCustomer();
    order.customer_name = '';
};

const handleCustomerUpdate = (updatedCustomer) => {
    // Enterprise: используем composable
    orderCustomer.updateCustomer(updatedCustomer);
};

// Пересчёт скидки лояльности
const recalculateLoyaltyDiscount = () => {
    const customer = selectedCustomerData.value;
    const level = customer?.loyalty_level || customer?.loyaltyLevel;
    if (level && level.discount_percent > 0) {
        loyaltyDiscount.value = Math.round(subtotal.value * level.discount_percent / 100);
    } else {
        loyaltyDiscount.value = 0;
    }
};

// Prepayment methods
const openPrepaymentModal = () => {
    showPrepaymentModal.value = true;
};

const handlePrepaymentConfirm = ({ amount, method, bonusUsed }) => {
    // Сохраняем предоплату (деньгами)
    order.prepayment = amount || 0;
    order.prepayment_method = method;
    // Сохраняем списанные бонусы
    order.bonus_used = bonusUsed || 0;
    showPrepaymentModal.value = false;
};

const clearPrepayment = () => {
    order.prepayment = 0;
    order.prepayment_method = null;
    order.bonus_used = 0;
};

const remainingToPay = computed(() => {
    return Math.max(0, total.value - order.prepayment - (order.bonus_used || 0));
});

const applyPromoCode = async () => {
    if (!order.promo_code) return;
    try {
        // Interceptor бросит исключение при success: false
        const response = await api.loyalty?.validatePromoCode?.(
            order.promo_code,
            order.customer_id || null,
            subtotal.value
        );
        const discount = response?.data?.discount || response?.discount;
        if (discount) {
            promoDiscount.value = discount;
            window.$toast?.('Промокод применён', 'success');
        } else {
            window.$toast?.('Промокод недействителен', 'error');
        }
    } catch (e) {
        window.$toast?.(e.response?.data?.message || e.message || 'Промокод недействителен', 'error');
    }
};

const calculateDelivery = async () => {
    if (order.type !== 'delivery' || !order.address) {
        deliveryInfo.value = null;
        return;
    }
    try {
        // Interceptor бросит исключение при success: false
        const response = await api.delivery?.calculateDelivery?.({
            address: order.address,
            total: subtotal.value
        });
        const data = response?.data || response;
        deliveryInfo.value = {
            zone_name: data?.zone?.name || data?.zone_name || 'Стандартная',
            delivery_fee: data?.delivery_cost ?? data?.delivery_fee ?? 0,
            base_delivery_fee: data?.zone?.delivery_fee ?? data?.delivery_cost ?? 0,
            free_delivery_from: data?.free_delivery_from ?? data?.zone?.free_delivery_from ?? null,
            estimated_time: data?.delivery_time || data?.estimated_time || 45,
            distance: data?.distance,
            formatted_address: data?.formatted_address,
            coordinates: data?.coordinates || null
        };
    } catch (e) {
        deliveryInfo.value = {
            zone_name: 'Стандартная',
            delivery_fee: 0,
            base_delivery_fee: 0,
            free_delivery_from: null,
            estimated_time: 45
        };
    }
};

// Пересчёт стоимости доставки при изменении суммы (без нового API запроса)
const recalculateDeliveryFee = () => {
    if (!deliveryInfo.value) return;

    const freeFrom = deliveryInfo.value.free_delivery_from;
    const baseFee = deliveryInfo.value.base_delivery_fee || 0;

    // Если есть порог бесплатной доставки и сумма превышает его
    if (freeFrom && subtotal.value >= freeFrom) {
        deliveryInfo.value.delivery_fee = 0;
    } else {
        deliveryInfo.value.delivery_fee = baseFee;
    }
};

// Mini map functions
const loadYandexMaps = () => {
    return new Promise((resolve) => {
        if (window.ymaps) {
            ymapsLoaded = true;
            resolve();
            return;
        }
        if (ymapsLoading.value) {
            // Already loading, wait for it
            const checkInterval = setInterval(() => {
                if (window.ymaps) {
                    clearInterval(checkInterval);
                    ymapsLoaded = true;
                    resolve();
                }
            }, 100);
            return;
        }
        ymapsLoading.value = true;
        const script = document.createElement('script');
        script.src = 'https://api-maps.yandex.ru/2.1/?lang=ru_RU';
        script.onload = () => {
            ymapsLoaded = true;
            ymapsLoading.value = false;
            resolve();
        };
        script.onerror = () => {
            ymapsLoading.value = false;
            resolve(); // Resolve anyway to not block
        };
        document.head.appendChild(script);
    });
};

const updateMiniMap = async (coords) => {
    if (!coords?.lat || !coords?.lng) return;
    if (!miniMapContainer.value) return;

    // Load Yandex Maps API if not loaded
    if (!window.ymaps) {
        await loadYandexMaps();
    }
    if (!window.ymaps) return;

    const center = [coords.lat, coords.lng];

    await window.ymaps.ready();

    if (!miniMap && miniMapContainer.value) {
        // Initialize map
        miniMap = new window.ymaps.Map(miniMapContainer.value, {
            center: center,
            zoom: 16,
            controls: []
        }, {
            suppressMapOpenBlock: true
        });

        // Add placemark
        miniMapPlacemark = new window.ymaps.Placemark(center, {
            hintContent: deliveryInfo.value?.formatted_address || order.address
        }, {
            preset: 'islands#redDeliveryIcon'
        });
        miniMap.geoObjects.add(miniMapPlacemark);
        miniMapReady.value = true;
    } else if (miniMap) {
        // Update existing map
        miniMap.setCenter(center, 16);

        // Update or create placemark
        if (miniMapPlacemark) {
            miniMapPlacemark.geometry.setCoordinates(center);
            miniMapPlacemark.properties.set('hintContent', deliveryInfo.value?.formatted_address || order.address);
        } else {
            miniMapPlacemark = new window.ymaps.Placemark(center, {
                hintContent: deliveryInfo.value?.formatted_address || order.address
            }, {
                preset: 'islands#redDeliveryIcon'
            });
            miniMap.geoObjects.add(miniMapPlacemark);
        }
    }
};

const destroyMiniMap = () => {
    if (miniMap) {
        miniMap.destroy();
        miniMap = null;
        miniMapPlacemark = null;
        miniMapReady.value = false;
    }
};

// Watch for coordinates changes to update mini map
watch(() => deliveryInfo.value?.coordinates, (coords) => {
    if (coords && showAddressModal.value) {
        nextTick(() => updateMiniMap(coords));
    }
}, { deep: true });

// Initialize map when address modal opens
watch(() => showAddressModal.value, (isOpen) => {
    if (isOpen) {
        nextTick(() => {
            // If we already have coordinates, show them on map
            if (deliveryInfo.value?.coordinates) {
                updateMiniMap(deliveryInfo.value.coordinates);
            }
        });
    } else {
        // Destroy map when modal closes
        destroyMiniMap();
    }
});

const saveAddressToRecent = (address) => {
    if (!address) return;
    const filtered = recentAddresses.value.filter(a => a !== address);
    filtered.unshift(address);
    recentAddresses.value = filtered.slice(0, 20);
    localStorage.setItem('recent_delivery_addresses', JSON.stringify(recentAddresses.value));
};

const createOrder = async (action) => {
    if (!canCreate.value) return;

    creating.value = true;
    try {
        const fullAddress = order.type === 'delivery'
            ? [
                order.address,
                order.entrance ? `подъезд ${order.entrance}` : '',
                order.floor ? `этаж ${order.floor}` : '',
                order.apartment ? `кв. ${order.apartment}` : ''
              ].filter(Boolean).join(', ')
            : null;

        if (order.type === 'delivery' && order.address) {
            saveAddressToRecent(order.address);
        }

        // Для ASAP заказов при нажатии "На кухню" сразу ставим статус "preparing"
        const initialStatus = (order.is_asap && action === 'kitchen') ? 'preparing' : 'pending';

        const orderData = {
            customer_name: order.customer_name,
            phone: order.phone,
            delivery_address: fullAddress,
            notes: order.comment,
            payment_method: order.payment_method,
            is_asap: order.is_asap,
            scheduled_at: order.scheduled_date
                ? `${order.scheduled_date} ${order.scheduled_time || '00:00'}:00`
                : null,
            items: order.items.map(item => ({
                dish_id: item.id,
                quantity: item.quantity,
                modifiers: item.modifiers?.map(m => m.id) || [],
                note: item.note || null
            })),
            promo_code: order.promo_code || null,
            change_from: order.payment_method === 'cash' ? order.change_from : null,
            courier_id: selectedCourier.value?.id || null,
            delivery_status: initialStatus,
            prepayment: order.prepayment || 0,
            prepayment_method: order.prepayment > 0 ? order.prepayment_method : null,
            // Скидки (единый формат с залом)
            promotion_id: selectedPromotion.value?.id || null,
            discount_amount: discountAmount.value || 0,
            manual_discount_percent: manualDiscount.value || 0,
            applied_discounts: appliedDiscountsData.value || [],
            // Бонусы
            bonus_used: order.bonus_used || 0,
            customer_id: selectedCustomerId.value || null
        };

        let createdOrder = null;

        if (order.type === 'pickup') {
            // Самовывоз — через тот же endpoint доставки, но без адреса
            orderData.type = 'pickup';
            orderData.delivery_address = null;
            const response = await api.orders.createDelivery(orderData);
            createdOrder = response?.data || response;
        } else {
            const response = await api.orders.createDelivery(orderData);
            createdOrder = response?.data || response;
        }

        // Проводим предоплату в кассу с номером заказа
        if (order.prepayment > 0) {
            try {
                const orderId = createdOrder?.id || null;
                const orderNumber = createdOrder?.order_number || createdOrder?.daily_number || null;
                await api.cashOperations.orderPrepayment(
                    order.prepayment,
                    order.prepayment_method,
                    order.customer_name || null,
                    order.type,
                    orderId,
                    orderNumber
                );
                window.$toast?.(`Предоплата ${order.prepayment} ₽ внесена в кассу`, 'success');
            } catch (depositError) {
                console.error('Failed to create prepayment transaction:', depositError);
                const depositMessage = depositError.response?.data?.message || 'Ошибка внесения предоплаты в кассу';
                window.$toast?.(depositMessage, 'error');
            }
        }

        // Auto-save address to customer profile
        if (order.type === 'delivery' && selectedCustomerId.value && order.address) {
            try {
                await api.customers.saveDeliveryAddress(selectedCustomerId.value, {
                    street: order.address,
                    apartment: order.apartment || null,
                    entrance: order.entrance || null,
                    floor: order.floor || null
                });
            } catch (addrError) {
                console.error('Failed to save address to customer:', addrError);
                // Not critical - don't show error to user
            }
        }

        window.$toast?.('Заказ создан', 'success');
        emit('created');
        close();
    } catch (error) {
        console.error('Failed to create order:', error);
        const message = error.response?.data?.message || 'Ошибка создания заказа';
        window.$toast?.(message, 'error');
    } finally {
        creating.value = false;
    }
};

// Keyboard shortcuts
const handleKeydown = (e) => {
    if (!props.show) return;

    // Numpad keyboard input when time picker is open
    if (showTimePicker.value) {
        // Numbers 0-9
        if (/^[0-9]$/.test(e.key)) {
            e.preventDefault();
            addTimeDigit(parseInt(e.key));
            return;
        }
        // Backspace
        if (e.key === 'Backspace') {
            e.preventDefault();
            backspaceTimeInput();
            return;
        }
        // Delete / C - clear
        if (e.key === 'Delete' || e.key.toLowerCase() === 'c') {
            e.preventDefault();
            clearTimeInput();
            return;
        }
        // Enter - confirm
        if (e.key === 'Enter') {
            e.preventDefault();
            if (isTimeInputValid.value) {
                confirmTimeInput();
            }
            return;
        }
    }

    if (e.key === 'Escape') {
        if (showAddressModal.value) showAddressModal.value = false;
        else if (showDiscountModal.value) showDiscountModal.value = false;
        else if (showCalendar.value) showCalendar.value = false;
        else if (showTimePicker.value) showTimePicker.value = false;
        else close();
    }

    if (e.key === 'F4') {
        e.preventDefault();
        order.payment_method = 'cash';
        if (canCreate.value) createOrder('kitchen');
    }

    if (e.key === 'F6') {
        e.preventDefault();
        order.payment_method = 'card';
        if (canCreate.value) createOrder('kitchen');
    }

    if (e.key === 'F10') {
        e.preventDefault();
        showDiscountModal.value = true;
    }
};

// Data loading
const loadDishes = async () => {
    try {
        const response = await api.menu.getDishes(null, posStore.selectedPriceListId);
        dishes.value = Array.isArray(response) ? response : (response.data || []);

        const cats = {};
        dishes.value.forEach(d => {
            if (d.category && !cats[d.category.id]) {
                cats[d.category.id] = d.category;
            }
        });
        categories.value = Object.values(cats);
    } catch (error) {
        console.error('Failed to load dishes:', error);
    }
};

const loadCouriers = async () => {
    try {
        const response = await api.couriers?.getAll?.();
        couriers.value = Array.isArray(response) ? response : (response?.data || []);
    } catch (e) {
        couriers.value = [];
    }
};

const loadRecentAddresses = () => {
    const stored = localStorage.getItem('recent_delivery_addresses');
    if (stored) {
        try {
            recentAddresses.value = JSON.parse(stored);
        } catch (e) {
            recentAddresses.value = [];
        }
    }
};

// Load active promotions from backend
const loadActivePromotions = async () => {
    loadingPromotions.value = true;
    try {
        const response = await api.loyalty?.getActivePromotions?.();
        const data = response?.data || response || [];
        // Фильтруем акции по типу заказа
        activePromotions.value = data.filter(promo => {
            // Если order_types не задан - акция доступна для всех типов
            if (!promo.order_types || promo.order_types.length === 0) return true;
            // Проверяем соответствие типу заказа
            return promo.order_types.includes(order.type);
        });
    } catch (e) {
        console.error('Failed to load promotions:', e);
        activePromotions.value = [];
    } finally {
        loadingPromotions.value = false;
    }
};

// Apply selected promotion
const applyPromotion = async (promo) => {
    if (selectedPromotion.value?.id === promo.id) {
        // Отменить выбор если кликнули повторно
        selectedPromotion.value = null;
        promotionDiscount.value = 0;
        appliedDiscountsData.value = [];
        return;
    }

    selectedPromotion.value = promo;
    await calculateDiscountFromAPI();
};

// Обработчик события @apply от DiscountModal (unified)
// Enterprise: используем composable для единой логики
const handleDiscountApply = (discountData) => {
    console.log('[NewDeliveryOrderModal] Discount applied:', discountData);

    // Enterprise: используем composable для обработки данных скидок
    orderDiscounts.applyDiscountData(discountData);

    // Синхронизируем с формой заказа
    order.promo_code = orderDiscounts.promoCode.value || '';
    order.bonus_used = orderDiscounts.bonusToSpend.value || 0;
};

// Calculate discount via API (единый источник истины)
const calculateDiscountFromAPI = async () => {
    if (calculatingDiscount.value) return;

    calculatingDiscount.value = true;
    try {
        // Подготовка items для API
        const items = order.items.map(item => ({
            dish_id: item.dish_id,
            category_id: item.category_id,
            quantity: item.quantity,
            price: item.price,
        }));

        const params = {
            order_total: subtotal.value,
            order_subtotal: subtotal.value,
            order_type: order.type,
            customer_id: order.customer_id || null,
            promo_code: order.promo_code || null,
            items: items,
        };

        const response = await api.loyalty?.calculateDiscount?.(params);
        const data = response?.data || response;

        if (data) {
            // Обновляем скидки из API
            appliedDiscountsData.value = data.applied_discounts || [];

            // Ищем скидку по уровню лояльности
            const levelDiscount = data.discounts?.find(d => d.type === 'level');
            loyaltyDiscount.value = levelDiscount?.amount || 0;
            loyaltyLevelName.value = levelDiscount?.name?.replace('Скидка ', '') || '';

            // Автоматические акции (is_automatic = true)
            const autoPromotion = data.discounts?.find(d => d.type === 'promotion' && d.auto);
            if (autoPromotion) {
                selectedPromotion.value = {
                    id: autoPromotion.promotion_id,
                    name: autoPromotion.name,
                    type: autoPromotion.promo_type || autoPromotion.discount_type,
                    auto: true
                };
                promotionDiscount.value = autoPromotion.amount || 0;

                // Обработка free_delivery
                if (autoPromotion.promo_type === 'free_delivery' && data.free_delivery) {
                    promotionDiscount.value = deliveryInfo.value?.delivery_fee || 0;
                }
            }
            // Ищем скидку выбранной акции (если уже выбрана вручную)
            else if (selectedPromotion.value && !selectedPromotion.value.auto) {
                const promoDiscount = data.discounts?.find(
                    d => d.type === 'promotion' && d.promotion_id === selectedPromotion.value.id
                );
                promotionDiscount.value = promoDiscount?.amount || 0;

                // Если скидка 0 и это не free_delivery - показываем причину
                if (promotionDiscount.value === 0 && selectedPromotion.value.type !== 'free_delivery') {
                    if (selectedPromotion.value.min_order_amount && subtotal.value < selectedPromotion.value.min_order_amount) {
                        window.$toast?.(`Минимальная сумма заказа: ${selectedPromotion.value.min_order_amount} ₽`, 'warning');
                    }
                }

                // Обработка free_delivery
                if (selectedPromotion.value.type === 'free_delivery' && data.free_delivery) {
                    promotionDiscount.value = deliveryInfo.value?.delivery_fee || 0;
                }
            } else if (!selectedPromotion.value) {
                // Сбрасываем скидку акции если нет акций
                promotionDiscount.value = 0;
            }
        }
    } catch (e) {
        console.error('Failed to calculate discount:', e);
    } finally {
        calculatingDiscount.value = false;
    }
};

// Computed: filtered promotions by order type
const applicablePromotions = computed(() => {
    return activePromotions.value.filter(promo => {
        // Проверка минимальной суммы
        if (promo.min_order_amount && subtotal.value < promo.min_order_amount) {
            return false;
        }
        return true;
    });
});

// Watchers
// Debounce для расчёта доставки (экономия запросов к геокодеру)
let deliveryTimeout = null;
watch(() => order.address, () => {
    clearTimeout(deliveryTimeout);
    deliveryTimeout = setTimeout(calculateDelivery, 800); // 800мс после последнего ввода
});
watch(() => subtotal.value, () => {
    // Пересчитать доставку локально (без API запроса)
    if (order.type === 'delivery') recalculateDeliveryFee();

    // Если корзина пуста - сбросить все скидки
    if (subtotal.value === 0 || order.items.length === 0) {
        appliedDiscountsData.value = [];
        selectedPromotion.value = null;
        promotionDiscount.value = 0;
        loyaltyDiscount.value = 0;
        promoDiscount.value = 0;
        manualDiscount.value = 0;
        return;
    }

    // Пересчитать скидки через API (всегда, для автоматических акций)
    calculateDiscountFromAPI();
});

// При смене типа заказа перезагрузить акции и пересчитать скидки
watch(() => order.type, () => {
    loadActivePromotions();

    // Сбросить все скидки и пересчитать для нового типа заказа
    appliedDiscountsData.value = [];
    selectedPromotion.value = null;
    promotionDiscount.value = 0;

    // Пересчитать скидки если есть товары
    if (subtotal.value > 0) {
        calculateDiscountFromAPI();
    }
});

// При смене даты: сбросить "Ближайшее время" если дата не сегодня
watch(() => order.scheduled_date, (newDate) => {
    const today = new Date();
    const isToday = newDate === formatDateForInput(today);

    if (!isToday && order.is_asap) {
        // Сбрасываем ASAP и выбираем первый доступный слот
        order.is_asap = false;
        // Выбираем первый доступный слот
        const slots = availableTimeSlots.value;
        const firstAvailable = slots.find(s => !s.disabled);
        if (firstAvailable) {
            order.scheduled_time = firstAvailable.time;
        }
    }
});

watch(() => props.show, (val) => {
    if (val) {
        // Reset form
        order.type = 'delivery';
        order.phone = '';
        order.customer_name = '';
        order.address = '';
        order.entrance = '';
        order.floor = '';
        order.apartment = '';
        order.is_asap = true;
        order.scheduled_date = formatDateForInput(new Date());
        order.scheduled_time = '';
        order.items = [];
        order.promo_code = '';
        order.payment_method = 'card';
        order.change_from = null;
        order.comment = '';
        order.prepayment = 0;
        order.prepayment_method = null;
        order.bonus_used = 0;

        // Enterprise: сброс через composables
        orderDiscounts.resetAllDiscounts(false);
        orderCustomer.clearCustomer();

        selectedCourier.value = null;
        deliveryInfo.value = null;
        destroyMiniMap(); // Очищаем мини-карту
        dishSearch.value = '';
        selectedCategory.value = null;

        loadDishes();
        loadCouriers();
        loadRecentAddresses();
        loadActivePromotions();
        loadBonusSettings();
    }
});

// Load bonus settings
const loadBonusSettings = async () => {
    try {
        // Interceptor бросит исключение при success: false
        const response = await api.loyalty.getBonusSettings();
        bonusSettings.value = response?.data || response || {};
    } catch (e) {
        console.warn('Failed to load bonus settings:', e);
    }
};

onMounted(() => {
    document.addEventListener('keydown', handleKeydown);
    if (props.show) {
        loadDishes();
        loadCouriers();
        loadRecentAddresses();
        loadActivePromotions();
        loadBonusSettings();
    }
});

onUnmounted(() => {
    document.removeEventListener('keydown', handleKeydown);
    destroyMiniMap();
});
</script>

<style scoped>
.modal-enter-active { transition: all 0.3s ease; }
.modal-leave-active { transition: all 0.2s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; transform: scale(0.95); }

.dropdown-enter-active { transition: all 0.2s ease; }
.dropdown-leave-active { transition: all 0.15s ease; }
.dropdown-enter-from, .dropdown-leave-to { opacity: 0; transform: translateY(-5px); }

.slide-fade-enter-active { transition: all 0.3s ease; }
.slide-fade-leave-active { transition: all 0.2s ease; }
.slide-fade-enter-from, .slide-fade-leave-to { opacity: 0; transform: translateY(-10px); }

.cart-item-enter-active { transition: all 0.3s ease; }
.cart-item-leave-active { transition: all 0.2s ease; }
.cart-item-enter-from { opacity: 0; transform: translateX(-20px); }
.cart-item-leave-to { opacity: 0; transform: translateX(20px); }

.slide-up-enter-active { transition: all 0.3s ease-out; }
.slide-up-leave-active { transition: all 0.2s ease-in; }
.slide-up-enter-from { opacity: 0; transform: translateY(100%); }
.slide-up-leave-to { opacity: 0; transform: translateY(100%); }

/* Fade animation for backdrop */
.fade-enter-active { transition: opacity 0.2s ease-out; }
.fade-leave-active { transition: opacity 0.15s ease-in; }
.fade-enter-from,
.fade-leave-to { opacity: 0; }

/* Slide panel animation */
.slide-panel-enter-active {
    transition: transform 0.25s cubic-bezier(0.4, 0, 0.2, 1);
}
.slide-panel-leave-active {
    transition: transform 0.2s cubic-bezier(0.4, 0, 1, 1);
}
.slide-panel-enter-from,
.slide-panel-leave-to {
    transform: translateX(100%);
}

.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

::-webkit-scrollbar { width: 4px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: rgba(75, 85, 99, 0.5); border-radius: 2px; }

/* Hide number input arrows */
input[type="number"]::-webkit-outer-spin-button,
input[type="number"]::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}
input[type="number"] {
    -moz-appearance: textfield;
}
</style>
