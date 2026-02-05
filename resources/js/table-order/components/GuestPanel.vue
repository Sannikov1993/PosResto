<template>
    <div class="w-[440px] bg-[#151921] flex flex-col border-r border-gray-800/50 relative">
        <!-- Reservation info panel -->
        <div v-if="reservation" class="bg-[#1a1f2e] border-b border-gray-700/50 flex-shrink-0">
            <!-- Header: Date/Time/Guests -->
            <div class="px-4 py-2.5 bg-[#151921]">
                <div class="flex items-center gap-2">
                    <!-- Date -->
                    <div class="flex-1 flex items-center justify-center gap-1.5 px-3 py-2 bg-[#252a3a] rounded-lg text-sm">
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        <span class="text-white font-medium">{{ dateBadgeText }}</span>
                    </div>
                    <!-- Time -->
                    <div class="flex-1 flex items-center justify-center gap-1.5 px-3 py-2 bg-[#252a3a] rounded-lg text-sm">
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span class="text-white font-medium">{{ formatTime(reservation.time_from) }}–{{ formatTime(reservation.time_to) }}</span>
                    </div>
                    <!-- Guests -->
                    <div class="flex-1 flex items-center justify-center gap-1.5 px-3 py-2 bg-[#252a3a] rounded-lg text-sm">
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        <span class="text-white font-medium">{{ reservation.guests_count || 2 }}</span>
                    </div>
                </div>
            </div>

            <!-- Divider -->
            <div class="h-px bg-gray-700/50"></div>

            <!-- Guest info - Read-only mode when seated -->
            <div v-if="reservation.status === 'seated'" class="px-4 py-3 space-y-2 bg-[#151921]">
                <!-- Row 1: Phone + Avatar + Name -->
                <div class="flex gap-2 relative">
                    <!-- Phone (read-only, no validation indicators) -->
                    <div class="flex flex-col">
                        <div class="w-44 bg-[#1e2330] rounded-lg px-3 py-2.5 text-white text-sm">
                            {{ inlineForm.guest_phone || 'Нет телефона' }}
                        </div>
                    </div>

                    <!-- Avatar + Name container (like in ReservationModal) -->
                    <div class="flex-1 flex items-center gap-2 bg-[#1e2330] rounded-lg px-3 py-2">
                        <button
                            ref="seatedCustomerRef"
                            @click="openReservationCustomerCard($event)"
                            class="flex items-center gap-2 group"
                        >
                            <div class="w-7 h-7 rounded-full bg-gradient-to-br from-accent to-purple-500 flex items-center justify-center flex-shrink-0">
                                <span class="text-white text-xs font-semibold">{{ (inlineForm.guest_name || 'Г')[0].toUpperCase() }}</span>
                            </div>
                            <span class="text-white text-sm font-medium transition-colors group-hover:text-gray-300">{{ inlineForm.guest_name || 'Гость' }}</span>
                            <span v-if="customerBonusBalance > 0" class="text-amber-400 text-xs ml-1">{{ customerBonusBalance }} ★</span>
                            <svg class="w-4 h-4 text-gray-500 transition-all group-hover:translate-x-1 group-hover:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </button>
                        <!-- No menu button when seated - guest cannot be changed -->
                    </div>
                </div>

                <!-- Row 2: Comment (read-only) -->
                <div class="w-full bg-[#1e2330] rounded-lg px-3 py-2.5 text-sm text-gray-500">
                    {{ inlineForm.notes || 'Комментарий' }}
                </div>
            </div>

            <!-- Guest info fields - Edit mode when not seated -->
            <div v-else class="px-4 py-3 space-y-2 bg-[#151921]">
                <!-- Row 1: Phone + Name -->
                <div class="flex gap-2 relative">
                    <div class="flex flex-col">
                        <div class="relative">
                            <input
                                :value="inlineForm.guest_phone"
                                type="tel"
                                inputmode="numeric"
                                placeholder="+7 (___) __-__-__"
                                @input="onPhoneInput"
                                @keypress="onlyDigits"
                                @change="saveInlineChanges"
                                :class="[
                                    'w-44 bg-[#1e2330] rounded-lg px-3 pr-8 py-2.5 text-white text-sm placeholder-gray-500 focus:ring-1 focus:outline-none transition-colors',
                                    inlineForm.guest_phone && !isPhoneValid ? 'border border-red-500 focus:ring-red-500' : 'border border-transparent focus:ring-accent',
                                    inlineForm.guest_phone && isPhoneValid ? 'border-green-500' : ''
                                ]"
                            />
                            <!-- Status icon -->
                            <div class="absolute right-2 top-1/2 -translate-y-1/2">
                                <svg v-if="inlineForm.guest_phone && isPhoneValid" class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <svg v-else-if="inlineForm.guest_phone && !isPhoneValid" class="w-4 h-4 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                                </svg>
                            </div>
                        </div>
                        <!-- Hint text -->
                        <p v-if="inlineForm.guest_phone && !isPhoneValid" class="text-red-400 text-xs mt-1">
                            Ещё {{ phoneDigitsRemaining }} {{ phoneDigitsRemaining === 1 ? 'цифра' : phoneDigitsRemaining < 5 ? 'цифры' : 'цифр' }}
                        </p>
                    </div>
                    <div class="flex-1 relative">
                        <input
                            ref="reservationNameRef"
                            v-model="inlineForm.guest_name"
                            type="text"
                            placeholder="Введите ФИО"
                            @blur="formatGuestName"
                            @change="saveInlineChanges"
                            :class="[
                                'w-full bg-[#1e2330] border-0 rounded-lg py-2.5 text-white text-sm placeholder-gray-500 focus:ring-1 focus:ring-accent focus:outline-none',
                                inlineForm.guest_phone ? 'pl-3 pr-16' : 'px-3 pr-8'
                            ]"
                        />
                        <!-- View customer card button (when has phone) -->
                        <button
                            v-if="inlineForm.guest_phone"
                            @click="openReservationCustomerCard($event)"
                            class="absolute right-8 top-1/2 -translate-y-1/2 text-accent hover:text-accent/80 transition-colors"
                            title="Просмотреть карточку клиента"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                        </button>
                        <!-- Open customer list button -->
                        <button
                            @click="openCustomerListOverlay"
                            class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-500 hover:text-white"
                            title="Выбрать из списка"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Row 2: Comment -->
                <input
                    v-model="inlineForm.notes"
                    type="text"
                    placeholder="Комментарий"
                    @change="saveInlineChanges"
                    class="w-full bg-[#1e2330] border-0 rounded-lg px-3 py-2.5 text-white text-sm placeholder-gray-500 focus:ring-1 focus:ring-accent focus:outline-none"
                />

                <!-- Deposit -->
                <div v-if="reservation.deposit" class="flex items-center gap-2 text-sm">
                    <div :class="[
                        'flex items-center gap-1.5 px-2.5 py-1 rounded-lg',
                        reservation.deposit_paid ? 'bg-green-500/20 text-green-400' : 'bg-yellow-500/20 text-yellow-400'
                    ]">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                        <span>Депозит: {{ formatPrice(reservation.deposit) }}</span>
                        <span v-if="reservation.deposit_paid" class="text-[10px]">(внесён)</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Customer info panel (when no reservation but has customer) -->
        <div v-if="customer && !reservation" class="bg-[#1a1f2e] border-b border-gray-700/50 flex-shrink-0">
            <div class="px-4 py-3 space-y-2 bg-[#151921]">
                <!-- Row 1: Phone + Avatar + Name -->
                <div class="flex gap-2">
                    <!-- Phone (read-only display) -->
                    <div class="w-44 bg-[#1e2330] rounded-lg px-3 py-2.5 text-white text-sm">
                        {{ formatPhoneDisplay(customer.phone) || 'Нет телефона' }}
                    </div>

                    <!-- Avatar + Name container -->
                    <div class="flex-1 flex items-center gap-2 bg-[#1e2330] rounded-lg px-3 py-2">
                        <button
                            ref="customerNameRef"
                            @click="openCustomerCard"
                            class="flex items-center gap-2 group flex-1 min-w-0"
                        >
                            <div class="w-7 h-7 rounded-full bg-gradient-to-br from-accent to-purple-500 flex items-center justify-center flex-shrink-0">
                                <span class="text-white text-xs font-semibold">{{ (customer.name || 'К')[0].toUpperCase() }}</span>
                            </div>
                            <span class="text-white text-sm font-medium transition-colors group-hover:text-gray-300 truncate">{{ customer.name || 'Гость' }}</span>
                            <span v-if="customer.bonus_balance > 0" class="text-amber-400 text-xs ml-1 flex-shrink-0">{{ customer.bonus_balance }} ★</span>
                            <svg class="w-4 h-4 text-gray-500 transition-all group-hover:translate-x-1 group-hover:text-white flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </button>
                        <!-- Change customer button -->
                        <button
                            @click="openCustomerListOverlay"
                            class="p-1.5 text-gray-500 hover:text-white hover:bg-[#252a3a] rounded-lg transition-colors flex-shrink-0"
                            title="Сменить клиента"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Guests list -->
        <div class="flex-1 overflow-y-auto">
            <GuestSection
                v-for="guest in guests"
                :key="guest.number"
                :guest="guest"
                :isSelected="selectedGuest === guest.number"
                :guestsCount="guests.length"
                :guestColors="guestColors"
                :selectMode="selectMode"
                :selectModeGuest="selectModeGuest"
                :selectedItems="selectedItems"
                :roundAmounts="roundAmounts"
                :categories="categories"
                @select="$emit('selectGuest', guest.number)"
                @toggleCollapse="$emit('toggleGuestCollapse', guest)"
                @updateItemQuantity="$emit('updateItemQuantity', $event.item, $event.delta)"
                @removeItem="$emit('removeItem', $event)"
                @sendItemToKitchen="$emit('sendItemToKitchen', $event)"
                @openCommentModal="$emit('openCommentModal', $event)"
                @openMoveModal="$emit('openMoveModal', $event.item, $event.guest)"
                @markItemServed="$emit('markItemServed', $event)"
                @startSelectMode="$emit('startSelectMode', guest.number)"
                @cancelSelectMode="$emit('cancelSelectMode')"
                @toggleItemSelection="$emit('toggleItemSelection', $event)"
                @selectAllGuestItems="$emit('selectAllGuestItems', guest)"
                @deselectAllItems="$emit('deselectAllItems')"
                @openBulkMoveModal="$emit('openBulkMoveModal')"
                @openModifiersModal="$emit('openModifiersModal', $event)"
            />

            <!-- Add guest button -->
            <button @click="$emit('addGuest')"
                    class="w-full px-3 py-2.5 text-gray-500 hover:text-gray-300 hover:bg-gray-800/30 text-sm flex items-center justify-center gap-1 transition-all">
                <span>+ Гость</span>
            </button>
        </div>

        <!-- Order Total -->
        <div class="px-3 py-3 border-t border-gray-800/50 bg-[#1a1f2e]">
            <!-- Скидки из applied_discounts (каждая отдельной строкой) -->
            <template v-if="appliedDiscountsList.length > 0">
                <div v-for="(discount, idx) in appliedDiscountsList" :key="idx"
                     class="flex items-center justify-between text-sm mb-1">
                    <span class="text-gray-500 truncate mr-2 flex items-center gap-1" :title="discount.name">
                        <span class="text-xs">{{ getDiscountIcon(discount.type || discount.sourceType) }}</span>
                        {{ discount.name }}
                    </span>
                    <span class="text-green-400 whitespace-nowrap">-{{ formatDiscountAmount(discount) }}</span>
                </div>
            </template>
            <!-- Фоллбэк: старый формат (для обратной совместимости) -->
            <template v-else>
                <!-- Скидка уровня лояльности -->
                <div v-if="loyaltyDiscount > 0" class="flex items-center justify-between text-sm mb-1">
                    <span class="text-gray-500 flex items-center gap-1">
                        <span class="text-xs">★</span>
                        {{ loyaltyLevelName || 'Скидка уровня' }}
                    </span>
                    <span class="text-green-400">-{{ formatPrice(loyaltyDiscount) }}</span>
                </div>
                <!-- Ручная/промо скидка -->
                <div v-if="discount > 0" class="flex items-center justify-between text-sm mb-1">
                    <span class="text-gray-500 truncate mr-2" :title="discountReason">
                        {{ discountReason || 'Скидка' }}
                    </span>
                    <span class="text-green-400 whitespace-nowrap">-{{ formatPrice(discount) }}</span>
                </div>
            </template>
            <!-- Бонусы к списанию (Enterprise: pending_bonus_spend с сервера) -->
            <div v-if="pendingBonusSpend > 0" class="flex items-center justify-between text-sm mb-1">
                <span class="text-amber-400 flex items-center gap-1">
                    <span class="text-xs">★</span>
                    Списание бонусов
                </span>
                <span class="text-amber-400">-{{ formatPrice(pendingBonusSpend) }}</span>
            </div>
            <div class="flex items-center justify-between" data-testid="order-total">
                <span class="text-gray-400 text-sm">Итого заказ</span>
                <span class="text-white font-bold text-xl">{{ formatPrice(orderTotal) }}</span>
            </div>
            <div v-if="unpaidTotal < orderTotal && unpaidTotal > 0" class="flex items-center justify-between mt-1">
                <span class="text-gray-500 text-xs">К оплате</span>
                <span class="text-orange-400 font-bold text-lg">{{ formatPrice(unpaidTotal) }}</span>
            </div>
        </div>

        <!-- Action buttons -->
        <div class="p-2 border-t border-gray-800/50 space-y-1.5 bg-[#151921]">
            <button v-if="pendingItems > 0" @click="$emit('sendAllToKitchen')"
                    data-testid="submit-order-btn"
                    class="w-full h-10 bg-[#1e2a38] hover:bg-[#263545] text-white rounded-lg text-sm font-medium transition-colors flex items-center justify-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4c-2.5 0-4.5 1.5-5 3.5C5 8 4 9.5 4 11c0 2 1.5 3.5 3 4v4h10v-4c1.5-.5 3-2 3-4 0-1.5-1-3-3-3.5-.5-2-2.5-3.5-5-3.5z"/>
                </svg>
                <span>Готовить</span>
                <span class="bg-accent text-white text-xs font-bold px-1.5 py-0.5 rounded">{{ pendingItems }}</span>
            </button>

            <button v-if="readyItems > 0" @click="$emit('serveAllReady')"
                    class="w-full py-2.5 bg-gradient-to-r from-green-500/10 to-green-400/5 border border-green-500/30 text-green-400 rounded-lg text-sm font-medium hover:from-green-500/20 hover:to-green-400/10 hover:border-green-400/50 transition-all duration-200 flex items-center justify-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                Подать ({{ readyItems }})
            </button>

            <!-- Row 1: Delete + Split + Discount -->
            <div class="flex gap-1.5">
                <button @click="$emit('deleteOrder')"
                        data-testid="delete-order-btn"
                        class="w-10 h-10 flex items-center justify-center bg-[#252a3a] hover:bg-red-500/20 text-gray-400 hover:text-red-400 rounded-lg transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                </button>
                <button @click="openCustomerListOverlay"
                        :disabled="reservation?.status === 'seated'"
                        :class="[
                            'flex-1 h-10 rounded-lg text-xs transition-colors flex items-center justify-center gap-1',
                            reservation?.status === 'seated'
                                ? 'bg-[#252a3a] text-gray-600 cursor-not-allowed'
                                : 'bg-[#252a3a] hover:bg-[#2d3348] text-gray-400'
                        ]">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                    Клиент
                </button>
                <button @click="$emit('showDiscount')"
                        data-testid="discount-btn"
                        :class="[
                            'flex-1 h-10 rounded-lg text-xs transition-colors flex items-center justify-center gap-1',
                            totalDiscountWithBonus > 0 ? 'bg-green-600/20 text-green-400 hover:bg-green-600/30' : 'bg-[#252a3a] hover:bg-[#2d3348] text-gray-400'
                        ]">
                    <span>% Скидки</span>
                    <span v-if="totalDiscountWithBonus > 0" class="font-medium">-{{ formatPrice(totalDiscountWithBonus) }}</span>
                </button>
            </div>

            <!-- Row 2: Precheck + Payment -->
            <div class="flex gap-1.5">
                <div class="flex-1 relative">
                    <button @click="handlePrecheckClick"
                            class="w-full h-10 bg-[#1e2a38] hover:bg-[#263545] text-white rounded-lg text-sm font-medium transition-colors flex items-center justify-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        <span>Счёт</span>
                    </button>

                    <!-- Precheck type popup -->
                    <Transition name="popup">
                        <div v-if="showPrecheckMenu" class="absolute bottom-full left-0 right-0 mb-2 bg-[#252a3a] rounded-xl overflow-hidden shadow-xl border border-gray-700/50 z-10">
                            <button @click="selectPrecheckType('all')"
                                    class="w-full px-4 py-3 text-left text-sm text-white hover:bg-[#2d3348] transition-colors flex items-center gap-3">
                                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                <div>
                                    <div class="font-medium">Общий счёт</div>
                                    <div class="text-xs text-gray-500">Один чек на всех гостей</div>
                                </div>
                            </button>
                            <div class="h-px bg-gray-700/50"></div>
                            <button @click="selectPrecheckType('split')"
                                    class="w-full px-4 py-3 text-left text-sm text-white hover:bg-[#2d3348] transition-colors flex items-center gap-3">
                                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                                <div>
                                    <div class="font-medium">По гостям</div>
                                    <div class="text-xs text-gray-500">Отдельный чек каждому гостю</div>
                                </div>
                            </button>
                        </div>
                    </Transition>

                    <!-- Backdrop for popup -->
                    <div v-if="showPrecheckMenu" class="fixed inset-0 z-0" @click="showPrecheckMenu = false"></div>
                </div>
                <button @click="$emit('showPaymentModal')"
                        data-testid="goto-payment-btn"
                        :class="[
                            'flex-1 h-10 flex items-center justify-center gap-2 rounded-lg text-sm font-medium transition-colors',
                            unpaidTotal > 0
                                ? 'bg-orange-500 hover:bg-orange-600 text-white'
                                : 'bg-[#252a3a] hover:bg-[#2d3348] text-gray-400'
                        ]">
                    <span>Оплата</span>
                    <span v-if="unpaidTotal > 0" class="font-bold">{{ formatPrice(unpaidTotal) }}</span>
                </button>
            </div>
        </div>

        <!-- Customer Select Panel (covers entire left block) -->
        <CustomerSelectModal
            v-model="showCustomerOverlay"
            variant="panel"
            :selected="selectedCustomerForCard"
            @select="onCustomerSelected"
        />

        <!-- Customer Info Card -->
        <Teleport to="body">
            <CustomerInfoCard
                :show="showCustomerCard"
                :customer="selectedCustomerForCard"
                :anchor-el="customerCardAnchor"
                @close="showCustomerCard = false"
                @update="handleCustomerUpdate"
            />
        </Teleport>
    </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import GuestSection from './GuestSection.vue';
import CustomerInfoCard from '../../components/CustomerInfoCard.vue';
import CustomerSelectModal from '../../shared/components/modals/CustomerSelectModal.vue';
import { useCustomers } from '../../pos/composables/useCustomers';
import { useCurrentCustomer } from '../../pos/composables/useCurrentCustomer';

// Получаем метод поиска клиентов
const { searchCustomers } = useCustomers();

// Единый источник данных о текущем клиенте (Enterprise pattern)
const {
    bonusBalance: currentCustomerBonusBalance,
    setCustomer: setCurrentCustomer,
    setFromOrder,
    setFromReservation,
    updateCustomer: updateCurrentCustomer,
    clear: clearCurrentCustomer,
} = useCurrentCustomer();

// Helper для локальной даты (не UTC!)
const getLocalDateString = (date = new Date()) => {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
};

const props = defineProps({
    guests: Array,
    selectedGuest: Number,
    pendingItems: Number,
    readyItems: Number,
    reservation: Object,
    customer: Object,
    currentOrder: Object,
    guestColors: Array,
    selectMode: Boolean,
    selectModeGuest: Number,
    selectedItems: Array,
    table: Object,
    discount: { type: Number, default: 0 },
    discountReason: { type: String, default: '' },
    loyaltyDiscount: { type: Number, default: 0 },
    loyaltyLevelName: { type: String, default: '' },
    orderTotal: { type: Number, default: 0 },
    unpaidTotal: { type: Number, default: 0 },
    roundAmounts: { type: Boolean, default: false },
    categories: { type: Array, default: () => [] },
    pendingBonusSpend: { type: Number, default: 0 }
});

const emit = defineEmits([
    'selectGuest',
    'addGuest',
    'toggleGuestCollapse',
    'updateItemQuantity',
    'removeItem',
    'sendItemToKitchen',
    'openCommentModal',
    'openMoveModal',
    'markItemServed',
    'startSelectMode',
    'cancelSelectMode',
    'toggleItemSelection',
    'selectAllGuestItems',
    'deselectAllItems',
    'openBulkMoveModal',
    'sendAllToKitchen',
    'serveAllReady',
    'showSplitPayment',
    'showPaymentModal',
    'showDiscount',
    'deleteOrder',
    'saveReservation',
    'unlinkReservation',
    'printPrecheck',
    'attachCustomer',
    'detachCustomer',
    'openModifiersModal'
]);

// Applied discounts list from current order (каждая скидка отдельной строкой)
const appliedDiscountsList = computed(() => {
    const result = [];

    // Получаем applied_discounts из текущего заказа
    const discounts = props.currentOrder?.applied_discounts;

    // Проверяем, есть ли скидка уровня лояльности в applied_discounts
    const hasLoyaltyInDiscounts = discounts?.some(d => d.type === 'level' || d.sourceType === 'level');

    // Если есть скидка лояльности из props и её нет в applied_discounts - добавляем
    if (props.loyaltyDiscount > 0 && !hasLoyaltyInDiscounts) {
        result.push({
            name: props.loyaltyLevelName || 'Скидка уровня',
            type: 'level',
            sourceType: 'level',
            amount: props.loyaltyDiscount
        });
    }

    // Добавляем скидки из applied_discounts (фильтруем записи с нулевой суммой)
    if (discounts && Array.isArray(discounts)) {
        const validDiscounts = discounts.filter(d => d.amount > 0);
        result.push(...validDiscounts);
    }

    return result;
});

// Общая сумма скидок включая бонусы к списанию (для кнопки "% Скидки")
const totalDiscountWithBonus = computed(() => {
    return props.discount + props.loyaltyDiscount + props.pendingBonusSpend;
});

// Helper: get discount icon by type
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
        'quick': '💰',
        'custom': '✏️',
        'percent': '💰',
        'fixed': '💵',
        'rounding': '🔄'
    };
    return icons[type] || '💰';
};

// Форматирование суммы скидки (с учётом копеек для округления)
const formatDiscountAmount = (discount) => {
    const amount = discount.amount || 0;
    // Для округления показываем с копейками (0,50 ₽)
    if (discount.type === 'rounding' || discount.sourceType === 'rounding') {
        return new Intl.NumberFormat('ru-RU', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(amount) + ' ₽';
    }
    // Для остальных - обычный формат
    return formatPrice(amount);
};

// Inline form for auto-save on change
const inlineForm = ref({
    guest_name: '',
    guest_phone: '',
    notes: ''
});

// Saving state
const savingInline = ref(false);

// Precheck menu
const showPrecheckMenu = ref(false);

const handlePrecheckClick = () => {
    // Если только 1 гость - печатаем сразу общий счёт
    if (props.guests.length <= 1) {
        emit('printPrecheck', 'all');
        return;
    }
    // Показываем меню для выбора: общий счёт или по гостям
    showPrecheckMenu.value = true;
};

const selectPrecheckType = (type) => {
    showPrecheckMenu.value = false;
    emit('printPrecheck', type);
};

// Customer list overlay (using CustomerSelectModal)
const showCustomerOverlay = ref(false);

// Customer card
const showCustomerCard = ref(false);
const customerNameRef = ref(null);
const reservationNameRef = ref(null);
const seatedCustomerRef = ref(null);
const selectedCustomerForCard = ref(null);
const customerCardAnchor = ref(null);

// Phone formatting helper (defined first to be used in initInlineForm)
const formatPhoneDisplay = (phone) => {
    if (!phone) return '';
    const digits = phone.replace(/\D/g, '');
    if (digits.length < 11) return phone;
    return `+${digits[0]} (${digits.slice(1, 4)}) ${digits.slice(4, 7)}-${digits.slice(7, 9)}-${digits.slice(9, 11)}`;
};

// Initialize inline form
const initInlineForm = () => {
    inlineForm.value = {
        guest_name: props.reservation?.guest_name || '',
        guest_phone: formatPhoneDisplay(props.reservation?.guest_phone) || '',
        notes: props.reservation?.notes || ''
    };
};

// Watch reservation changes
watch(() => props.reservation, () => {
    initInlineForm();
    // Сбрасываем выбранного клиента при смене бронирования
    selectedCustomerForCard.value = null;
    // Синхронизируем с единым источником данных о клиенте
    // Вызываем setFromReservation если есть customer или customer_id
    if (props.reservation?.customer || props.reservation?.customer_id) {
        setFromReservation(props.reservation);
    }
}, { immediate: true });

// Синхронизация клиента заказа с единым источником (Enterprise pattern)
watch(() => props.customer, (newCustomer) => {
    if (newCustomer) {
        setCurrentCustomer(newCustomer);
    } else if (!props.reservation?.customer) {
        clearCurrentCustomer();
    }
}, { immediate: true });

// Бонусы клиента из единого источника (Enterprise pattern)
const customerBonusBalance = currentCustomerBonusBalance;

// Computed: Table name from reservation or prop
const tableName = computed(() => {
    // First check table prop
    if (props.table?.name) return props.table.name;
    if (props.table?.number) return props.table.number;
    // Then check reservation
    if (props.reservation?.table?.name) return props.reservation.table.name;
    if (props.reservation?.table?.number) return props.reservation.table.number;
    if (props.reservation?.table_number) return props.reservation.table_number;
    return 'Стол';
});

// Computed: Проверка валидности телефона
const isPhoneValid = computed(() => {
    const digits = (inlineForm.value.guest_phone || '').replace(/\D/g, '');
    return digits.length >= 11;
});

// Сколько цифр осталось ввести
const phoneDigitsRemaining = computed(() => {
    const digits = (inlineForm.value.guest_phone || '').replace(/\D/g, '');
    return Math.max(0, 11 - digits.length);
});

// Computed: Date badge text
const dateBadgeText = computed(() => {
    const rawDate = props.reservation?.date;
    if (!rawDate) return 'Сегодня';
    // Нормализуем дату (убираем время если есть)
    const date = rawDate.substring(0, 10);
    const today = getLocalDateString();
    if (date === today) return 'Сегодня';
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    if (date === getLocalDateString(tomorrow)) return 'Завтра';
    const d = new Date(date);
    const months = ['янв', 'фев', 'мар', 'апр', 'май', 'июн', 'июл', 'авг', 'сен', 'окт', 'ноя', 'дек'];
    return `${d.getDate()} ${months[d.getMonth()]}`;
});

// Блокировка ввода букв - только цифры
const onlyDigits = (e) => {
    const char = String.fromCharCode(e.which || e.keyCode);
    if (!/[\d]/.test(char)) {
        e.preventDefault();
    }
};

// Phone input formatting
const onPhoneInput = (e) => {
    let value = e.target.value.replace(/\D/g, '');
    if (value.length > 0 && value[0] !== '7') {
        if (value[0] === '8') {
            value = '7' + value.slice(1);
        } else {
            value = '7' + value;
        }
    }
    let formatted = '';
    if (value.length > 0) {
        formatted = '+' + value[0];
        if (value.length > 1) formatted += ' (' + value.slice(1, 4);
        if (value.length > 4) formatted += ') ' + value.slice(4, 7);
        if (value.length > 7) formatted += '-' + value.slice(7, 9);
        if (value.length > 9) formatted += '-' + value.slice(9, 11);
    }
    inlineForm.value.guest_phone = formatted;
};

// Форматирование имени гостя (первая буква каждого слова заглавная)
const formatGuestName = () => {
    if (inlineForm.value.guest_name) {
        const words = inlineForm.value.guest_name.trim().replace(/\s+/g, ' ').split(' ');
        inlineForm.value.guest_name = words.map(word => {
            if (!word) return '';
            return word.charAt(0).toUpperCase() + word.slice(1).toLowerCase();
        }).join(' ');
    }
};

// Save inline changes
const saveInlineChanges = async () => {
    if (!props.reservation?.id) return;

    // Проверка что телефон полный перед сохранением
    if (inlineForm.value.guest_phone && !isPhoneValid.value) {
        window.$toast?.('Введите полный номер телефона', 'error');
        return;
    }

    savingInline.value = true;
    try {
        emit('saveReservation', {
            guest_name: inlineForm.value.guest_name,
            guest_phone: inlineForm.value.guest_phone.replace(/\D/g, ''),
            notes: inlineForm.value.notes
        });
        // Small delay to show the checkmark
        await new Promise(resolve => setTimeout(resolve, 300));
    } finally {
        savingInline.value = false;
    }
};

// Customer list overlay (using CustomerSelectModal)
const openCustomerListOverlay = () => {
    showCustomerOverlay.value = true;
};

const onCustomerSelected = (customer) => {
    // Сохраняем выбранного клиента для карточки
    selectedCustomerForCard.value = customer;

    // Если есть бронь - заполняем форму
    if (props.reservation) {
        inlineForm.value.guest_name = customer.name || '';
        inlineForm.value.guest_phone = formatPhoneDisplay(customer.phone) || '';
        // Modal closes itself
        saveInlineChanges();
    } else {
        // Если нет брони - привязываем клиента к заказу
        emit('attachCustomer', customer);
    }
};

// Открыть карточку клиента при клике на имя (без бронирования)
const openCustomerCard = (e) => {
    if (props.customer) {
        selectedCustomerForCard.value = props.customer;
        customerCardAnchor.value = e.currentTarget;
        showCustomerCard.value = true;
    }
};

// Открыть карточку клиента для бронирования
const openReservationCustomerCard = async (e) => {
    // Устанавливаем якорь для позиционирования карточки
    if (e?.currentTarget) {
        customerCardAnchor.value = e.currentTarget;
    } else if (seatedCustomerRef.value) {
        customerCardAnchor.value = seatedCustomerRef.value;
    } else if (reservationNameRef.value) {
        customerCardAnchor.value = reservationNameRef.value;
    }

    // Если клиент уже привязан к бронированию - используем его напрямую
    if (props.reservation?.customer?.id) {
        selectedCustomerForCard.value = props.reservation.customer;
        showCustomerCard.value = true;
        return;
    }

    // Получаем телефон:
    // - Для seated режима: из props.reservation (данные зафиксированы)
    // - Для не-seated режима: из inlineForm (актуальные данные формы)
    const isSeated = props.reservation?.status === 'seated';
    const phoneSource = isSeated
        ? (props.reservation?.guest_phone || '')
        : (inlineForm.value.guest_phone || '');
    const cleanPhone = phoneSource.replace(/\D/g, '');

    // Сбрасываем кэш
    selectedCustomerForCard.value = null;

    // Получаем имя с учётом режима
    const nameSource = isSeated
        ? (props.reservation?.guest_name || 'Гость')
        : (inlineForm.value.guest_name || props.reservation?.guest_name || 'Гость');

    // Ищем клиента по телефону
    if (!cleanPhone || cleanPhone.length < 10) {
        // Нет телефона - показываем временную карточку
        selectedCustomerForCard.value = {
            id: null,
            name: nameSource,
            phone: cleanPhone || null,
            is_new: true
        };
        showCustomerCard.value = true;
        return;
    }

    try {
        // Используем searchCustomers из composable (с авторизацией)
        const customers = await searchCustomers(cleanPhone);

        if (customers.length > 0) {
            selectedCustomerForCard.value = customers[0];
            showCustomerCard.value = true;
        } else {
            // Клиент не найден - создаём временный объект для отображения
            selectedCustomerForCard.value = {
                id: null,
                name: nameSource,
                phone: cleanPhone,
                is_new: true
            };
            showCustomerCard.value = true;
        }
    } catch (err) {
        console.error('Failed to find customer:', err);
    }
};

// Обновление клиента после редактирования в карточке
const handleCustomerUpdate = (updatedCustomer) => {
    selectedCustomerForCard.value = updatedCustomer;
    // Обновляем единый источник данных о клиенте (Enterprise pattern)
    updateCurrentCustomer(updatedCustomer);
    // Если клиент привязан к заказу, нужно обновить и его
    if (props.customer && props.customer.id === updatedCustomer.id) {
        emit('attachCustomer', updatedCustomer);
    }
};



// Статус бронирования
const reservationStatusText = computed(() => {
    const statusMap = {
        pending: 'Ожидает подтверждения',
        confirmed: 'Подтверждено',
        seated: 'Гости за столом',
        completed: 'Завершено',
        cancelled: 'Отменено',
        no_show: 'Не пришли'
    };
    return statusMap[props.reservation?.status] || 'Подтверждено';
});

const reservationStatusClass = computed(() => {
    const classMap = {
        pending: 'bg-yellow-500/20 text-yellow-400',
        confirmed: 'bg-green-500/20 text-green-400',
        seated: 'bg-blue-500/20 text-blue-400',
        completed: 'bg-gray-500/20 text-gray-400',
        cancelled: 'bg-red-500/20 text-red-400',
        no_show: 'bg-red-500/20 text-red-400'
    };
    return classMap[props.reservation?.status] || 'bg-green-500/20 text-green-400';
});

// Цвет аватара
const avatarColors = [
    'bg-gradient-to-br from-blue-400 to-blue-600',
    'bg-gradient-to-br from-teal-400 to-teal-600',
    'bg-gradient-to-br from-purple-400 to-purple-600',
    'bg-gradient-to-br from-pink-400 to-pink-600',
    'bg-gradient-to-br from-orange-400 to-orange-600',
];

const avatarColor = computed(() => {
    if (!props.reservation?.guest_name) return avatarColors[0];
    let hash = 0;
    for (let i = 0; i < props.reservation.guest_name.length; i++) {
        hash = props.reservation.guest_name.charCodeAt(i) + ((hash << 5) - hash);
    }
    return avatarColors[Math.abs(hash) % avatarColors.length];
});

// Получить инициалы
const getInitials = (name) => {
    if (!name || !name.trim()) return '??';
    const parts = name.trim().split(/\s+/);
    if (parts.length >= 2) {
        return (parts[0][0] + parts[1][0]).toUpperCase();
    }
    return name.substring(0, 2).toUpperCase();
};

// Форматирование телефона
const formatPhone = (phone) => {
    if (!phone) return '';
    const cleaned = phone.replace(/\D/g, '');
    if (cleaned.length === 11) {
        return `+${cleaned[0]} ${cleaned.slice(1, 4)} ${cleaned.slice(4, 7)}-${cleaned.slice(7, 9)}-${cleaned.slice(9)}`;
    }
    return phone;
};

// Форматирование времени
const formatTime = (time) => {
    if (!time) return '';
    return time.substring(0, 5);
};

// Форматирование цены с учётом округления
const formatPrice = (price) => {
    let num = parseFloat(price) || 0;
    // Округляем в пользу клиента (вниз) если включена настройка
    if (props.roundAmounts) {
        num = Math.floor(num);
    }
    return new Intl.NumberFormat('ru-RU').format(num) + ' ₽';
};
</script>

<style scoped>
.slide-up-enter-active,
.slide-up-leave-active {
    transition: transform 0.25s ease-out, opacity 0.2s ease;
}
.slide-up-enter-from,
.slide-up-leave-to {
    transform: translateY(20px);
    opacity: 0;
}

.popup-enter-active,
.popup-leave-active {
    transition: all 0.15s ease;
}
.popup-enter-from,
.popup-leave-to {
    opacity: 0;
    transform: translateY(8px);
}
</style>
