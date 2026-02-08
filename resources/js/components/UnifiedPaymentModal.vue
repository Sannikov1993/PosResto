<template>
    <Teleport to="body">
        <Transition name="fade">
            <div v-if="modelValue"
                 :class="[
                     'fixed inset-0 bg-black/70 z-[9999]',
                     bottomSheet ? 'flex items-end' : 'flex items-center justify-center p-4',
                     bottomSheet && rightAligned ? 'justify-end' : ''
                 ]"
                 data-testid="payment-modal"
                 @click.self="close">
                <Transition :name="bottomSheet ? 'slide-bottom' : 'slide-up'">
                    <div v-if="modelValue"
                         :class="[
                             'bg-[#1a1f2e] overflow-hidden shadow-2xl rounded-2xl',
                             bottomSheet ? 'rounded-t-2xl rounded-b-none' : 'max-w-lg w-full',
                             bottomSheet && rightAligned ? 'w-[480px]' : (bottomSheet ? 'w-full' : '')
                         ]">
                        <!-- Header -->
                        <div class="flex items-center justify-between px-4 py-3 bg-[#151921]">
                            <div class="flex items-center gap-3">
                                <!-- Customer name as title when available, otherwise default title -->
                                <button
                                    v-if="customer && (mode === 'payment' || mode === 'prepayment')"
                                    @click="showBonusPanel = !showBonusPanel"
                                    class="flex items-center gap-2 text-base font-semibold text-white hover:text-accent transition-colors"
                                >
                                    <svg class="w-5 h-5 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                    </svg>
                                    <span>{{ customer.name || 'Клиент' }}</span>
                                    <svg :class="['w-4 h-4 text-gray-400 transition-transform', showBonusPanel ? 'rotate-180' : '']" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                    </svg>
                                </button>
                                <span v-else class="text-base font-semibold text-white">{{ title }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <!-- iOS-style pill toggle -->
                                <div v-if="canSplitByGuests" class="relative grid grid-cols-2 bg-[#0d1117] rounded-full p-1.5 text-sm">
                                    <!-- Sliding indicator -->
                                    <div
                                        :class="[
                                            'absolute top-1.5 bottom-1.5 w-[calc(50%-3px)] bg-accent rounded-full transition-all duration-200 ease-out',
                                            isSplitByGuests ? 'left-[calc(50%)]' : 'left-1.5'
                                        ]"
                                    ></div>
                                    <button
                                        @click="toggleSplitByGuests(false)"
                                        :class="[
                                            'relative z-10 px-5 py-2 rounded-full font-medium transition-all duration-200 text-center',
                                            !isSplitByGuests ? 'text-white' : 'text-gray-500 hover:text-gray-300'
                                        ]"
                                    >
                                        Все
                                    </button>
                                    <button
                                        @click="toggleSplitByGuests(true)"
                                        :class="[
                                            'relative z-10 px-5 py-2 rounded-full font-medium transition-all duration-200 text-center',
                                            isSplitByGuests ? 'text-white' : 'text-gray-500 hover:text-gray-300'
                                        ]"
                                    >
                                        Гости
                                    </button>
                                </div>
                                <button @click="close" class="p-1.5 hover:bg-[#252a3a] rounded-lg text-gray-400 hover:text-white transition-all">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <!-- Bonus panel (expandable) -->
                        <Transition name="slide-down">
                            <div v-if="showBonusPanel && customer" class="bg-[#1a1f2e] border-b border-gray-700/50 px-4 py-3">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-full bg-gradient-to-br from-yellow-400 to-orange-500 flex items-center justify-center">
                                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                        </div>
                                        <div>
                                            <p class="text-white font-medium">{{ customerBonusBalance || 0 }} бонусов</p>
                                            <p class="text-gray-500 text-xs">Доступно для списания</p>
                                        </div>
                                    </div>
                                    <div v-if="customerBonusBalance > 0" class="flex items-center gap-2">
                                        <input
                                            ref="bonusInputRef"
                                            :value="bonusToUse || ''"
                                            @input="onBonusInput"
                                            @focus="isBonusInputFocused = true"
                                            @blur="isBonusInputFocused = false"
                                            type="text"
                                            inputmode="numeric"
                                            pattern="[0-9]*"
                                            placeholder="0"
                                            :class="[
                                                'w-24 bg-[#252a3a] border-2 rounded-lg px-3 py-2 text-white text-sm text-center outline-none transition-all',
                                                isBonusInputFocused ? 'border-yellow-500 ring-1 ring-yellow-500/50' : 'border-transparent'
                                            ]"
                                        />
                                        <button
                                            @mousedown.prevent
                                            @click="bonusToUse = maxBonusToUse"
                                            class="px-3 py-2 bg-yellow-500/20 hover:bg-yellow-500/30 text-yellow-400 rounded-lg text-sm font-medium transition-colors"
                                        >
                                            Все
                                        </button>
                                    </div>
                                    <span v-else class="text-gray-500 text-sm">Нет бонусов</span>
                                </div>
                                <div v-if="bonusToUse > 0" class="mt-2 text-sm text-green-400">
                                    Будет списано: {{ bonusToUse }} бонусов (-{{ formatPrice(bonusToUse) }})
                                </div>
                            </div>
                        </Transition>

                        <!-- Guest list when split mode -->
                        <div v-if="isSplitByGuests && guests.length > 0" class="px-4 py-2 space-y-1.5 max-h-48 overflow-y-auto">
                            <div
                                v-for="guest in availableGuests"
                                :key="guest.number"
                                @click="toggleGuestSelection(guest.number)"
                                :class="[
                                    'flex items-center gap-3 p-3 rounded-xl cursor-pointer transition-all border',
                                    selectedGuestNumbers.includes(guest.number)
                                        ? 'bg-accent/10 border-accent'
                                        : 'bg-[#252a3a] border-transparent hover:bg-[#2d3348]'
                                ]"
                            >
                                <!-- Checkbox -->
                                <div :class="[
                                    'w-5 h-5 rounded-md flex items-center justify-center transition-all',
                                    selectedGuestNumbers.includes(guest.number)
                                        ? 'bg-accent'
                                        : 'border-2 border-gray-600'
                                ]">
                                    <svg v-if="selectedGuestNumbers.includes(guest.number)" class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                                    </svg>
                                </div>
                                <!-- Guest avatar -->
                                <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-blue-400 to-blue-600 flex items-center justify-center text-white text-sm font-bold">
                                    {{ guest.number }}
                                </div>
                                <!-- Guest info -->
                                <div class="flex-1">
                                    <span class="text-white text-sm font-medium">Гость {{ guest.number }}</span>
                                </div>
                                <!-- Amount -->
                                <div class="text-right">
                                    <span v-if="guest.discount > 0" class="text-green-400 text-xs mr-1">-%</span>
                                    <span :class="[
                                        'text-sm font-bold',
                                        selectedGuestNumbers.includes(guest.number) ? 'text-accent' : 'text-gray-400'
                                    ]">
                                        {{ formatPrice(guest.total || 0) }}
                                    </span>
                                </div>
                            </div>

                            <!-- Paid guests -->
                            <div
                                v-for="guestNum in paidGuests"
                                :key="'paid-' + guestNum"
                                class="flex items-center gap-3 p-3 rounded-xl bg-green-500/10 border border-green-500/30 opacity-60"
                            >
                                <svg class="w-5 h-5 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                <div class="w-8 h-8 rounded-lg bg-green-500/30 flex items-center justify-center text-green-400 text-sm font-bold">
                                    {{ guestNum }}
                                </div>
                                <span class="text-green-400 text-sm">Гость {{ guestNum }} — оплачено</span>
                            </div>

                            <!-- Selected total -->
                            <div v-if="selectedGuestNumbers.length > 0" class="flex justify-between items-center pt-2 border-t border-gray-700/50">
                                <span class="text-gray-400 text-sm">К оплате:</span>
                                <span class="text-xl font-bold text-white">{{ formatPrice(selectedGuestsTotal) }}</span>
                            </div>
                        </div>

                        <!-- Payment method buttons -->
                        <div class="px-4 py-3">
                            <div class="flex gap-2">
                                <!-- Mixed payment toggle -->
                                <button
                                    @click="toggleMixedMode"
                                    data-testid="payment-mixed-btn"
                                    :class="[
                                        'w-14 py-2 rounded-xl text-sm font-medium transition-all flex flex-col items-center justify-center gap-0.5',
                                        isMixedMode
                                            ? 'bg-purple-500 text-white'
                                            : 'bg-[#252a3a] text-gray-400 hover:bg-[#2d3348] hover:text-white'
                                    ]"
                                    title="Смешанная оплата"
                                >
                                    <!-- Card / Coin icon -->
                                    <svg class="w-7 h-5" fill="none" stroke="currentColor" viewBox="0 0 28 20">
                                        <!-- Card -->
                                        <rect x="1" y="4" width="10" height="7" rx="1" stroke-width="1.5"/>
                                        <line x1="1" y1="7" x2="11" y2="7" stroke-width="1.5"/>
                                        <!-- Slash -->
                                        <line x1="13" y1="15" x2="16" y2="3" stroke-width="1.5" stroke-linecap="round"/>
                                        <!-- Coin -->
                                        <circle cx="22" cy="10" r="5" stroke-width="1.5"/>
                                        <text x="22" y="13" text-anchor="middle" font-size="7" fill="currentColor" stroke="none">₽</text>
                                    </svg>
                                    <!-- Small arrow -->
                                    <svg :class="['w-3 h-3 transition-transform', isMixedMode ? 'rotate-180' : '']" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                    </svg>
                                </button>

                                <!-- Cash button / input -->
                                <button
                                    @click="isMixedMode ? (activeMixedField = 'cash') : setMethod('cash')"
                                    data-testid="payment-cash-btn"
                                    :class="[
                                        'flex-1 py-2.5 rounded-xl text-sm font-medium transition-all flex items-center',
                                        isMixedMode ? 'justify-between px-3' : 'justify-center gap-2',
                                        !isMixedMode && method === 'cash'
                                            ? 'bg-green-500 text-white'
                                            : 'bg-[#252a3a] text-gray-400 hover:bg-[#2d3348] hover:text-white'
                                    ]"
                                >
                                    <!-- Cash/banknotes icon -->
                                    <div v-if="isMixedMode" :class="['p-2 rounded-lg transition-all', activeMixedField === 'cash' ? 'bg-green-500' : 'bg-[#1a1f2e]']">
                                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                                        </svg>
                                    </div>
                                    <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                                    </svg>
                                    <span v-if="!isMixedMode">Наличные</span>
                                    <span v-else class="font-bold text-base flex items-center" :class="activeMixedField === 'cash' ? 'text-white' : 'text-gray-500'">
                                        {{ mixedCash || '0' }}<span v-if="activeMixedField === 'cash'" class="animate-blink ml-0.5">|</span> ₽
                                    </span>
                                </button>

                                <!-- Card button / input -->
                                <button
                                    @click="isMixedMode ? (activeMixedField = 'card') : setMethod('card')"
                                    data-testid="payment-card-btn"
                                    :class="[
                                        'flex-1 py-2.5 rounded-xl text-sm font-medium transition-all flex items-center',
                                        isMixedMode ? 'justify-between px-3' : 'justify-center gap-2',
                                        !isMixedMode && method === 'card'
                                            ? 'bg-accent text-white'
                                            : 'bg-[#252a3a] text-gray-400 hover:bg-[#2d3348] hover:text-white'
                                    ]"
                                >
                                    <!-- Credit card icon -->
                                    <div v-if="isMixedMode" :class="['p-2 rounded-lg transition-all', activeMixedField === 'card' ? 'bg-accent' : 'bg-[#1a1f2e]']">
                                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                                        </svg>
                                    </div>
                                    <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                                    </svg>
                                    <span v-if="!isMixedMode">Картой</span>
                                    <span v-else class="font-bold text-base flex items-center" :class="activeMixedField === 'card' ? 'text-white' : 'text-gray-500'">
                                        {{ mixedCard || '0' }}<span v-if="activeMixedField === 'card'" class="animate-blink ml-0.5">|</span> ₽
                                    </span>
                                </button>

                                <button
                                    v-if="mode !== 'deposit'"
                                    @mousedown.prevent
                                    @click="fillFullAmount"
                                    data-testid="payment-fill-amount-btn"
                                    class="flex items-center bg-orange-500 hover:bg-orange-600 rounded-xl px-4 transition-all active:scale-95"
                                >
                                    <span class="text-white text-sm font-medium">Чек</span>
                                    <span class="text-white text-sm font-bold ml-2">{{ formatPrice(checkButtonAmount) }}</span>
                                </button>
                            </div>
                        </div>

                        <!-- Amount display -->
                        <div v-if="!isMixedMode" class="px-4 py-2">
                            <div class="bg-[#151921] rounded-xl px-4 py-3 flex items-center justify-between">
                                <span class="text-gray-400 text-sm">{{ method === 'cash' ? 'Наличными' : 'Картой' }}</span>
                                <span class="text-3xl font-bold text-white">{{ formatAmountDisplay(amount) }}</span>
                            </div>
                        </div>

                        <!-- Bills and Numpad -->
                        <div class="px-4 py-3 flex gap-4">
                            <!-- Left side: Bills + Summary -->
                            <div class="flex-1">
                                <!-- Quick amount buttons -->
                                <div class="grid grid-cols-3 gap-2">
                                    <button
                                        v-for="bill in [100, 200, 500, 1000, 2000, 5000]"
                                        :key="bill"
                                        @mousedown.prevent
                                        @click="addAmount(bill)"
                                        class="h-14 bg-[#252a3a] hover:bg-[#2d3348] rounded-xl flex items-center justify-center text-base font-bold text-white transition-all active:scale-95"
                                    >
                                        +{{ bill }}
                                    </button>
                                </div>

                                <!-- Summary -->
                                <div class="mt-4 bg-[#151921] rounded-xl p-3 space-y-1 text-sm">
                                    <!-- For deposit mode - simple summary -->
                                    <template v-if="mode === 'deposit'">
                                        <div class="flex justify-between">
                                            <span class="text-gray-500">Сумма депозита</span>
                                            <span class="text-yellow-400 font-semibold">{{ formatPrice(parseInt(amount) || 0) }}</span>
                                        </div>
                                    </template>
                                    <!-- For payment/prepayment modes - full summary -->
                                    <template v-else>
                                        <!-- In split by guests mode - show selected guests info -->
                                        <template v-if="isSplitByGuests">
                                            <div class="flex justify-between">
                                                <span class="text-gray-500">Выбрано гостей</span>
                                                <span class="text-white font-semibold">{{ selectedGuestNumbers.length }} из {{ availableGuests.length }}</span>
                                            </div>
                                            <div v-if="selectedGuestsSubtotal > 0" class="flex justify-between">
                                                <span class="text-gray-500">Сумма</span>
                                                <span class="text-white font-semibold">{{ formatPrice(selectedGuestsSubtotal) }}</span>
                                            </div>
                                            <div v-if="selectedGuestsLoyaltyDiscount > 0" class="flex justify-between">
                                                <span class="text-gray-500">{{ loyaltyLevelName ? `Скидка "${loyaltyLevelName}"` : 'Скидка уровня' }}</span>
                                                <span class="text-purple-400 font-semibold">-{{ formatPrice(selectedGuestsLoyaltyDiscount) }}</span>
                                            </div>
                                            <div v-if="selectedGuestsManualDiscount > 0" class="flex justify-between">
                                                <span class="text-gray-500">Скидка</span>
                                                <span class="text-green-400 font-semibold">-{{ formatPrice(selectedGuestsManualDiscount) }}</span>
                                            </div>
                                            <!-- Show deposit info if available -->
                                            <div v-if="paidAmount > 0" class="flex justify-between">
                                                <span class="text-gray-500">Депозит брони</span>
                                                <span :class="depositToApply > 0 ? 'text-green-400' : 'text-gray-400'">
                                                    {{ depositToApply > 0 ? '-' : '' }}{{ formatPrice(paidAmount) }}
                                                </span>
                                            </div>
                                            <!-- Bonus deduction -->
                                            <div v-if="bonusToUse > 0" class="flex justify-between">
                                                <span class="text-gray-500">Бонусы</span>
                                                <span class="text-yellow-400">-{{ formatPrice(bonusToUse) }}</span>
                                            </div>
                                            <!-- Refund from deposit when deposit > remaining -->
                                            <div v-if="depositRefund > 0" class="flex justify-between">
                                                <span class="text-orange-400">Возврат депозита</span>
                                                <span class="text-orange-400 font-semibold">{{ formatPrice(depositRefund) }}</span>
                                            </div>
                                            <div v-if="paidAmount > 0 && !isLastPayment && selectedGuestNumbers.length > 0" class="text-xs text-gray-500 mt-1">
                                                Депозит применится при оплате всех гостей
                                            </div>
                                            <div class="border-t border-[#252a3a] my-2"></div>
                                            <div class="flex justify-between">
                                                <span class="text-yellow-400 font-semibold">К оплате</span>
                                                <span class="text-yellow-400 font-semibold">{{ formatPrice(effectiveTotal) }}</span>
                                            </div>
                                            <div v-if="parseInt(amount) > 0" class="flex justify-between mt-1">
                                                <span class="text-green-400">Внесено</span>
                                                <span class="text-white">{{ formatPrice(parseInt(amount) || 0) }}</span>
                                            </div>
                                            <!-- Change or remaining -->
                                            <div v-if="change > 0 && method === 'cash'" class="flex justify-between">
                                                <span class="text-orange-400 font-semibold">Сдача</span>
                                                <span class="text-orange-400 font-semibold">{{ formatPrice(change) }}</span>
                                            </div>
                                            <div v-else-if="remaining > 0 && selectedGuestNumbers.length > 0" class="flex justify-between">
                                                <span class="text-white font-semibold">Осталось</span>
                                                <span class="text-white font-semibold">{{ formatPrice(remaining) }}</span>
                                            </div>
                                            <div v-else-if="selectedGuestNumbers.length > 0 && enteredAmount >= effectiveTotal" class="flex justify-between">
                                                <span class="text-green-400 font-semibold">Оплачено полностью</span>
                                                <span class="text-green-400 font-semibold">✓</span>
                                            </div>
                                        </template>

                                        <!-- Regular mode - full summary -->
                                        <template v-else>
                                            <div class="flex justify-between">
                                                <span class="text-gray-500">Сумма заказа</span>
                                                <span class="text-white font-semibold">{{ formatPrice(subtotal || (total - deliveryFee + loyaltyDiscount + discount)) }}</span>
                                            </div>
                                            <div v-if="deliveryFee > 0" class="flex justify-between">
                                                <span class="text-gray-500">Доставка</span>
                                                <span class="text-white">{{ formatPrice(deliveryFee) }}</span>
                                            </div>
                                            <div v-if="loyaltyDiscount > 0" class="flex justify-between">
                                                <span class="text-gray-500">{{ loyaltyLevelName ? `Скидка "${loyaltyLevelName}"` : 'Скидка уровня' }}</span>
                                                <span class="text-purple-400">-{{ formatPrice(loyaltyDiscount) }}</span>
                                            </div>
                                            <div v-if="discount > 0" class="flex justify-between">
                                                <span class="text-gray-500">Скидка</span>
                                                <span class="text-green-400">-{{ formatPrice(discount) }}</span>
                                            </div>
                                            <div v-if="paidAmount > 0" class="flex justify-between">
                                                <span class="text-gray-500">Депозит брони</span>
                                                <span class="text-green-400">-{{ formatPrice(paidAmount) }}</span>
                                            </div>
                                            <div v-if="bonusToUse > 0" class="flex justify-between">
                                                <span class="text-gray-500">Бонусы</span>
                                                <span class="text-yellow-400">-{{ formatPrice(bonusToUse) }}</span>
                                            </div>
                                            <div class="border-t border-[#252a3a] my-2"></div>

                                            <!-- Случай: нужна доплата -->
                                            <template v-if="!fullyPaidByDeposit">
                                                <div class="flex justify-between">
                                                    <span class="text-yellow-400 font-semibold">К оплате</span>
                                                    <span class="text-yellow-400 font-semibold">{{ formatPrice(effectiveTotal) }}</span>
                                                </div>
                                                <div v-if="parseInt(amount) > 0" class="flex justify-between mt-1">
                                                    <span class="text-green-400">Внесено</span>
                                                    <span class="text-white">{{ formatPrice(parseInt(amount) || 0) }}</span>
                                                </div>
                                                <!-- Change or remaining -->
                                                <div v-if="change > 0 && method === 'cash'" class="flex justify-between">
                                                    <span class="text-orange-400 font-semibold">Сдача</span>
                                                    <span class="text-orange-400 font-semibold">{{ formatPrice(change) }}</span>
                                                </div>
                                                <div v-else-if="remaining > 0" class="flex justify-between">
                                                    <span class="text-white font-semibold">Осталось</span>
                                                    <span class="text-white font-semibold">{{ formatPrice(remaining) }}</span>
                                                </div>
                                                <div v-else class="flex justify-between">
                                                    <span class="text-green-400 font-semibold">Оплачено полностью</span>
                                                    <span class="text-green-400 font-semibold">✓</span>
                                                </div>
                                            </template>

                                            <!-- Случай: возврат из депозита -->
                                            <template v-else-if="refundAmount > 0">
                                                <div class="flex justify-between">
                                                    <span class="text-cyan-400 font-semibold">К возврату гостю</span>
                                                    <span class="text-cyan-400 font-semibold">{{ formatPrice(refundAmount) }}</span>
                                                </div>
                                            </template>

                                            <!-- Случай: депозит = сумма заказа -->
                                            <template v-else>
                                                <div class="flex justify-between">
                                                    <span class="text-green-400 font-semibold">Оплачено депозитом</span>
                                                    <span class="text-green-400 font-semibold">✓</span>
                                                </div>
                                            </template>
                                        </template>
                                    </template>
                                </div>
                            </div>

                            <!-- Numpad -->
                            <div class="w-40 grid grid-cols-3 gap-2 content-start">
                                <button
                                    v-for="key in ['1','2','3','4','5','6','7','8','9']"
                                    :key="key"
                                    @mousedown.prevent
                                    @click="appendDigit(key)"
                                    class="h-14 bg-[#252a3a] hover:bg-[#2d3348] rounded-xl text-white text-xl font-medium transition-all active:scale-95"
                                >
                                    {{ key }}
                                </button>
                                <button
                                    v-if="!roundAmounts"
                                    @mousedown.prevent
                                    @click="appendDigit('.')"
                                    :disabled="amount.includes('.')"
                                    :class="[
                                        'h-14 rounded-xl text-xl font-medium transition-all active:scale-95',
                                        amount.includes('.')
                                            ? 'bg-[#1a1f2e] text-gray-600 cursor-not-allowed'
                                            : 'bg-[#252a3a] hover:bg-[#2d3348] text-white'
                                    ]"
                                >
                                    .
                                </button>
                                <!-- Empty placeholder when rounding is enabled -->
                                <div v-else class="h-14"></div>
                                <button
                                    @mousedown.prevent
                                    @click="appendDigit('0')"
                                    class="h-14 bg-[#252a3a] hover:bg-[#2d3348] rounded-xl text-white text-xl font-medium transition-all active:scale-95"
                                >
                                    0
                                </button>
                                <button
                                    @mousedown.prevent
                                    @click="backspace"
                                    class="h-14 bg-[#252a3a] hover:bg-[#2d3348] rounded-xl text-gray-400 text-xl font-medium transition-all active:scale-95"
                                >
                                    ⌫
                                </button>
                                <button
                                    @mousedown.prevent
                                    @click="clearAmount"
                                    class="col-span-3 h-10 bg-[#252a3a] hover:bg-red-500/30 rounded-xl text-red-400 text-sm font-medium transition-all active:scale-95"
                                >
                                    Очистить
                                </button>
                            </div>
                        </div>

                        <!-- Footer actions -->
                        <div class="px-4 py-3 flex gap-3 border-t border-[#252a3a]">
                            <button
                                @click="close"
                                data-testid="payment-cancel-btn"
                                class="px-6 py-3 bg-[#252a3a] hover:bg-[#2d3348] rounded-xl text-gray-300 font-medium transition-all"
                            >
                                Отмена
                            </button>
                            <button
                                @click="confirm"
                                data-testid="payment-submit-btn"
                                :disabled="!canConfirm"
                                :class="[
                                    'flex-1 py-3 rounded-xl font-semibold transition-all',
                                    canConfirm
                                        ? 'bg-accent hover:bg-blue-500 text-white'
                                        : 'bg-[#252a3a] text-gray-500 cursor-not-allowed'
                                ]"
                            >
                                <span v-if="!canConfirm && mode === 'prepayment'">
                                    Внесите минимум {{ formatPrice(effectiveMinAmount) }}
                                </span>
                                <span v-else-if="!canConfirm">
                                    Введите сумму
                                </span>
                                <span v-else>
                                    {{ confirmButtonText }}
                                </span>
                            </button>
                        </div>
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>

    <!-- Success Animation Modal -->
    <Teleport to="body">
        <Transition name="success-fade">
            <div v-if="showSuccess"
                 class="fixed inset-0 bg-black/90 z-[10001] flex items-center justify-center">
                <div class="success-animation-container text-center">
                    <!-- Processing state -->
                    <template v-if="successState === 'processing'">
                        <div class="w-24 h-24 border-4 border-accent border-t-transparent rounded-full animate-spin"></div>
                        <div class="text-white text-xl font-bold mt-6">Обработка оплаты...</div>
                    </template>

                    <!-- Success state -->
                    <template v-else-if="successState === 'success'">
                        <svg class="checkmark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 52 52">
                            <circle class="checkmark-circle" cx="26" cy="26" r="25" fill="none"/>
                            <path class="checkmark-check" fill="none" d="M14.1 27.2l7.1 7.2 16.7-16.8"/>
                        </svg>
                        <div class="success-text">
                            <div class="text-white text-xl font-bold mt-6">Оплата успешна</div>
                            <div class="text-green-400 text-3xl font-bold mt-2">{{ formatPrice(successAmount) }}</div>
                        </div>
                    </template>

                    <!-- Closing state -->
                    <template v-else-if="successState === 'closing'">
                        <svg class="checkmark-static" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 52 52">
                            <circle cx="26" cy="26" r="25" fill="none" stroke="#22c55e" stroke-width="2"/>
                            <path fill="none" stroke="#22c55e" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" d="M14.1 27.2l7.1 7.2 16.7-16.8"/>
                        </svg>
                        <div class="text-white text-lg font-medium mt-6">Закрываем заказ...</div>
                    </template>

                    <!-- Error state -->
                    <template v-else-if="successState === 'error'">
                        <div class="w-24 h-24 rounded-full bg-red-500/20 flex items-center justify-center">
                            <svg class="w-12 h-12 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </div>
                        <div class="text-red-400 text-xl font-bold mt-6">{{ errorMessage || 'Ошибка оплаты' }}</div>
                        <button @click="closeErrorOverlay" class="mt-4 px-6 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition-colors">
                            Закрыть
                        </button>
                    </template>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';
import { formatAmount, setRoundAmounts, getRoundAmounts, roundAmountsSetting } from '../utils/formatAmount';

const props = defineProps({
    modelValue: Boolean,
    total: { type: Number, default: 0 },
    subtotal: { type: Number, default: 0 },
    discount: { type: Number, default: 0 },
    loyaltyDiscount: { type: Number, default: 0 },
    loyaltyLevelName: { type: String, default: '' },
    deliveryFee: { type: Number, default: 0 },
    paidAmount: { type: Number, default: 0 },
    mode: { type: String, default: 'payment' },
    minAmount: { type: Number, default: null },
    initialMethod: { type: String, default: 'cash' },
    initialAmount: { type: [Number, String], default: '' },
    bottomSheet: { type: Boolean, default: false },
    rightAligned: { type: Boolean, default: false },
    guests: { type: Array, default: () => [] },
    paidGuests: { type: Array, default: () => [] },
    customer: { type: Object, default: null },
    bonusSettings: { type: Object, default: null },
    roundAmounts: { type: Boolean, default: false },
    initialBonusToSpend: { type: Number, default: 0 } // Бонусы из DiscountModal
});

const emit = defineEmits(['update:modelValue', 'confirm']);

const amount = ref('');
const method = ref('cash');
const isMixedMode = ref(false);
const mixedCash = ref('');
const mixedCard = ref('');
const activeMixedField = ref('cash');
const showSuccess = ref(false);
const successState = ref('processing'); // 'processing' | 'success' | 'closing' | 'error'
const successAmount = ref(0);
const errorMessage = ref('');

// Split payment by guests
const isSplitByGuests = ref(false);
const selectedGuestNumbers = ref([]);

// Bonus points
const showBonusPanel = ref(false);
const bonusToUse = ref(0);
const isBonusInputFocused = ref(false);
const bonusInputRef = ref(null);

// Bonus input handler
const onBonusInput = (e) => {
    const value = e.target.value.replace(/\D/g, '');
    const numValue = parseInt(value) || 0;
    bonusToUse.value = Math.min(numValue, maxBonusToUse.value);
};

// Keyboard handler
const handleKeydown = (e) => {
    if (!props.modelValue) return;
    if (showSuccess.value) return; // Block input during success animation

    // Let native input handle when bonus input is focused
    if (isBonusInputFocused.value) {
        // Only handle special keys via numpad
        if (e.key === 'Delete' || (e.key === 'Backspace' && e.ctrlKey)) {
            e.preventDefault();
            bonusToUse.value = 0;
        }
        return;
    }

    // Number keys (0-9)
    if (/^[0-9]$/.test(e.key)) {
        e.preventDefault();
        appendDigit(e.key);
        return;
    }

    // Decimal point (. or ,) - skip if rounding is enabled
    if (e.key === '.' || e.key === ',') {
        e.preventDefault();
        if (!props.roundAmounts) {
            appendDigit('.');
        }
        return;
    }

    // Backspace - delete last digit
    if (e.key === 'Backspace') {
        e.preventDefault();
        backspace();
        return;
    }

    // Delete or Escape - clear field
    if (e.key === 'Delete') {
        e.preventDefault();
        if (isMixedMode.value) {
            if (activeMixedField.value === 'cash') {
                mixedCash.value = '';
            } else {
                mixedCard.value = '';
            }
        } else {
            amount.value = '';
        }
        return;
    }

    // Enter - confirm
    if (e.key === 'Enter') {
        e.preventDefault();
        if (canConfirm.value) {
            confirm();
        }
        return;
    }

    // Tab - switch between cash/card in mixed mode
    if (e.key === 'Tab' && isMixedMode.value) {
        e.preventDefault();
        activeMixedField.value = activeMixedField.value === 'cash' ? 'card' : 'cash';
        return;
    }
};

// Watch for initial values
watch(() => props.modelValue, (val) => {
    if (val) {
        method.value = props.initialMethod || 'cash';
        amount.value = props.initialAmount ? String(props.initialAmount) : '';
        isMixedMode.value = false;
        mixedCash.value = '';
        mixedCard.value = '';
        activeMixedField.value = 'cash';
        showSuccess.value = false;
        successState.value = 'processing';
        successAmount.value = 0;
        errorMessage.value = '';
        isSplitByGuests.value = false;
        selectedGuestNumbers.value = [];
        // Initialize bonus from DiscountModal if provided
        const initialBonus = props.initialBonusToSpend || 0;
        bonusToUse.value = Math.min(initialBonus, maxBonusToUse.value);
        showBonusPanel.value = bonusToUse.value > 0; // Показываем панель если бонусы уже выбраны
        // Add keyboard listener when modal opens
        window.addEventListener('keydown', handleKeydown);
    } else {
        // Remove keyboard listener when modal closes
        window.removeEventListener('keydown', handleKeydown);
        // Hide success overlay when modal closes
        showSuccess.value = false;
        successState.value = 'processing';
    }
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeydown);
});

const toggleMixedMode = () => {
    isMixedMode.value = !isMixedMode.value;
    if (isMixedMode.value) {
        mixedCash.value = '';
        mixedCard.value = '';
        activeMixedField.value = 'cash';
    }
};

// Auto-fill card amount when cash is entered
watch(mixedCash, (newVal) => {
    if (!isMixedMode.value) return;
    // Use checkButtonAmount which includes bonus deduction
    const toPay = checkButtonAmount.value;
    const cashAmt = parseInt(newVal) || 0;
    const remaining = Math.max(0, toPay - cashAmt);
    mixedCard.value = remaining > 0 ? String(remaining) : '0';
});

// Update amount when bonus changes
watch(bonusToUse, () => {
    // Update amount to effectiveTotal when bonus changes
    if (isSplitByGuests.value && selectedGuestNumbers.value.length > 0) {
        nextTick(() => {
            amount.value = String(effectiveTotal.value);
        });
    } else if (!isSplitByGuests.value && !isMixedMode.value && enteredAmount.value > 0) {
        // In regular mode, update amount if already entered
        nextTick(() => {
            amount.value = String(effectiveTotal.value);
        });
    }
    // Update mixed mode amounts too
    if (isMixedMode.value) {
        nextTick(() => {
            const toPay = checkButtonAmount.value;
            if (activeMixedField.value === 'cash') {
                mixedCash.value = String(toPay);
                mixedCard.value = '0';
            } else {
                mixedCard.value = String(toPay);
                mixedCash.value = '0';
            }
        });
    }
});

const fillRemainingToActive = () => {
    // Use checkButtonAmount which includes bonus deduction
    const toPay = checkButtonAmount.value;
    const cashAmt = parseInt(mixedCash.value) || 0;
    const cardAmt = parseInt(mixedCard.value) || 0;
    const remaining = toPay - cashAmt - cardAmt;

    if (remaining > 0) {
        if (activeMixedField.value === 'cash') {
            mixedCash.value = String(cashAmt + remaining);
        } else {
            mixedCard.value = String(cardAmt + remaining);
        }
    }
};

const swapMixedAmounts = () => {
    const temp = mixedCash.value;
    mixedCash.value = mixedCard.value;
    mixedCard.value = temp;
};

const mixedTotal = computed(() => {
    return (parseInt(mixedCash.value) || 0) + (parseInt(mixedCard.value) || 0);
});

// Guests that are not yet paid
const availableGuests = computed(() => {
    if (!props.guests.length) return [];
    return props.guests.filter(g => !props.paidGuests.includes(g.number));
});

// Has multiple guests to show split option
const canSplitByGuests = computed(() => {
    return props.mode === 'payment' && props.guests.length > 1;
});

// Total for selected guests (with discount)
const selectedGuestsTotal = computed(() => {
    if (!isSplitByGuests.value || !selectedGuestNumbers.value.length) return 0;
    return props.guests
        .filter(g => selectedGuestNumbers.value.includes(g.number))
        .reduce((sum, g) => sum + (g.total || 0), 0);
});

// Subtotal for selected guests (without discount)
const selectedGuestsSubtotal = computed(() => {
    if (!isSplitByGuests.value || !selectedGuestNumbers.value.length) return 0;
    return props.guests
        .filter(g => selectedGuestNumbers.value.includes(g.number))
        .reduce((sum, g) => sum + (g.subtotal || g.total || 0), 0);
});

// Discount for selected guests (total)
const selectedGuestsDiscount = computed(() => {
    if (!isSplitByGuests.value || !selectedGuestNumbers.value.length) return 0;
    return props.guests
        .filter(g => selectedGuestNumbers.value.includes(g.number))
        .reduce((sum, g) => sum + (g.discount || 0), 0);
});

// Пропорциональная скидка уровня для выбранных гостей
const selectedGuestsLoyaltyDiscount = computed(() => {
    if (!isSplitByGuests.value || !selectedGuestNumbers.value.length) return 0;
    if (!props.loyaltyDiscount || props.loyaltyDiscount <= 0) return 0;

    // Рассчитываем пропорцию от общей суммы заказа
    const totalSubtotal = props.subtotal || props.total || 0;
    if (totalSubtotal <= 0) return 0;

    const ratio = selectedGuestsSubtotal.value / totalSubtotal;
    return Math.round(props.loyaltyDiscount * ratio);
});

// Пропорциональная ручная скидка для выбранных гостей
const selectedGuestsManualDiscount = computed(() => {
    if (!isSplitByGuests.value || !selectedGuestNumbers.value.length) return 0;
    if (!props.discount || props.discount <= 0) return 0;

    // Рассчитываем пропорцию от общей суммы заказа
    const totalSubtotal = props.subtotal || props.total || 0;
    if (totalSubtotal <= 0) return 0;

    const ratio = selectedGuestsSubtotal.value / totalSubtotal;
    return Math.round(props.discount * ratio);
});

// Check if all remaining unpaid guests are selected (final payment)
const isLastPayment = computed(() => {
    if (!isSplitByGuests.value || !selectedGuestNumbers.value.length) return false;
    // Get unpaid guest numbers
    const unpaidGuestNumbers = availableGuests.value.map(g => g.number);
    // Check if all unpaid guests are selected
    return unpaidGuestNumbers.length > 0 &&
        unpaidGuestNumbers.every(num => selectedGuestNumbers.value.includes(num));
});

// Deposit to apply (only on final payment in split mode)
const depositToApply = computed(() => {
    if (isSplitByGuests.value && isLastPayment.value && props.paidAmount > 0) {
        // Apply deposit up to the guests total (can't apply more than owed)
        return Math.min(props.paidAmount, selectedGuestsTotal.value);
    }
    return 0;
});

// Refund from deposit (when deposit > remaining guests total)
const depositRefund = computed(() => {
    if (isSplitByGuests.value && isLastPayment.value && props.paidAmount > 0) {
        const excess = props.paidAmount - selectedGuestsTotal.value;
        return excess > 0 ? excess : 0;
    }
    return 0;
});

// Amount for "Чек" button - shows selected guests total in split mode (with bonus deduction)
const checkButtonAmount = computed(() => {
    let total;
    if (isSplitByGuests.value) {
        total = Math.max(0, selectedGuestsTotal.value - depositToApply.value - (bonusToUse.value || 0));
    } else {
        total = Math.max(0, props.total - props.paidAmount - (bonusToUse.value || 0));
    }
    // Apply rounding in favor of client (down) if enabled
    return props.roundAmounts ? Math.floor(total) : total;
});

// Customer bonus balance
const customerBonusBalance = computed(() => {
    if (!props.customer) return 0;
    return props.customer.bonus_balance ?? 0;
});

// Max bonus points that can be used (min of customer balance, order total, and spend_rate limit)
// Always returns integer - can't spend half a bonus point
const maxBonusToUse = computed(() => {
    // Check if bonus system is enabled
    if (props.bonusSettings && !props.bonusSettings.is_enabled) return 0;
    if (customerBonusBalance.value <= 0) return 0;

    const orderAmount = isSplitByGuests.value
        ? Math.max(0, selectedGuestsTotal.value - depositToApply.value)
        : Math.max(0, props.total - props.paidAmount);

    // Apply spend_rate limit (max % of order that can be paid with bonus)
    let maxBySettings = orderAmount;
    if (props.bonusSettings?.spend_rate) {
        maxBySettings = orderAmount * (props.bonusSettings.spend_rate / 100);
    }

    // Check minimum order amount for spending
    if (props.bonusSettings?.min_spend_amount && orderAmount < props.bonusSettings.min_spend_amount) {
        return 0;
    }

    // Round down - can't spend fractional bonus points
    return Math.floor(Math.min(customerBonusBalance.value, maxBySettings));
});

// Effective total for all calculations - respects split by guests mode and bonus
const effectiveTotal = computed(() => {
    let total;
    if (isSplitByGuests.value) {
        // Apply deposit only on final payment (all remaining guests selected)
        total = Math.max(0, selectedGuestsTotal.value - depositToApply.value);
    } else {
        total = Math.max(0, props.total - props.paidAmount);
    }
    // Subtract bonus points
    total = Math.max(0, total - (bonusToUse.value || 0));
    // Apply rounding in favor of client (down) if enabled
    return props.roundAmounts ? Math.floor(total) : total;
});

// Toggle guest selection
const toggleGuestSelection = (guestNumber) => {
    const idx = selectedGuestNumbers.value.indexOf(guestNumber);
    if (idx >= 0) {
        selectedGuestNumbers.value.splice(idx, 1);
    } else {
        selectedGuestNumbers.value.push(guestNumber);
    }
    // Update amount to effective total (with deposit applied on final payment)
    if (selectedGuestNumbers.value.length > 0) {
        // Use nextTick to ensure computed properties are updated
        nextTick(() => {
            amount.value = String(effectiveTotal.value);
        });
    } else {
        amount.value = '';
    }
};

// Toggle split by guests mode
const toggleSplitByGuests = (enabled) => {
    isSplitByGuests.value = enabled;
    if (enabled) {
        isMixedMode.value = false;
        selectedGuestNumbers.value = [];
        amount.value = '';
    } else {
        selectedGuestNumbers.value = [];
        amount.value = '';
    }
};

const title = computed(() => {
    if (props.mode === 'prepayment') return 'Предоплата';
    if (props.mode === 'deposit') return 'Депозит';
    return 'Оплата заказа';
});

const effectiveMinAmount = computed(() => {
    if (props.minAmount !== null) return props.minAmount;
    // Предоплата и депозит — частичные, минимум 1₽
    return 1;
});

const enteredAmount = computed(() => {
    if (isMixedMode.value) {
        return (parseInt(mixedCash.value) || 0) + (parseInt(mixedCard.value) || 0);
    }
    return parseFloat(amount.value) || 0;
});

const amountToPay = computed(() => {
    if (isSplitByGuests.value) {
        return selectedGuestsTotal.value;
    }
    return props.total - props.paidAmount;
});

const refundAmount = computed(() => {
    if (isSplitByGuests.value) return 0; // No refund in split mode
    return Math.max(0, props.paidAmount - props.total);
});

const fullyPaidByDeposit = computed(() => {
    if (isSplitByGuests.value) return false; // Not applicable in split mode
    return props.paidAmount >= props.total && props.total > 0;
});

const remaining = computed(() => {
    return Math.max(0, effectiveTotal.value - enteredAmount.value);
});

const change = computed(() => {
    return Math.max(0, enteredAmount.value - effectiveTotal.value);
});

const canConfirm = computed(() => {
    if (fullyPaidByDeposit.value) return true;

    // Use effectiveTotal which includes bonus deduction
    const toPay = effectiveTotal.value;

    // In split by guests mode - must have at least one guest selected
    if (isSplitByGuests.value) {
        if (selectedGuestNumbers.value.length === 0) return false;
        // If deposit covers everything and there's a refund - allow confirm
        if (depositRefund.value > 0 && toPay === 0) return true;
        if (isMixedMode.value) {
            return mixedTotal.value >= toPay;
        }
        return enteredAmount.value >= toPay;
    }

    if (isMixedMode.value) {
        return mixedTotal.value >= toPay;
    }

    if (!amount.value || enteredAmount.value <= 0) return false;
    if (props.mode === 'deposit') {
        return enteredAmount.value >= 1;
    }
    if (props.mode === 'prepayment') {
        return enteredAmount.value >= effectiveMinAmount.value;
    }
    return enteredAmount.value >= toPay;
});

const confirmButtonText = computed(() => {
    // Split by guests with deposit refund
    if (isSplitByGuests.value && depositRefund.value > 0 && effectiveTotal.value === 0) {
        return `Возврат депозита ${formatPrice(depositRefund.value)}`;
    }
    if (isMixedMode.value) {
        return `Оплатить ${formatPrice(mixedTotal.value)}`;
    }
    if (props.mode === 'deposit') {
        return `Внести депозит ${formatPrice(enteredAmount.value)}`;
    }
    if (props.mode === 'prepayment') {
        return `Принять ${formatPrice(enteredAmount.value)}`;
    }
    if (fullyPaidByDeposit.value) {
        if (refundAmount.value > 0) {
            return `Вернуть ${formatPrice(refundAmount.value)}`;
        }
        return 'Закрыть заказ';
    }
    // Show effectiveTotal (amount after bonus deduction) for payment
    return `Оплатить ${formatPrice(effectiveTotal.value)}`;
});

const setMethod = (m) => {
    method.value = m;
    if (m === 'card' && props.mode !== 'deposit') {
        const maxAmount = Math.max(0, props.total - props.paidAmount);
        if (enteredAmount.value > maxAmount) {
            amount.value = String(maxAmount);
        }
    }
};

const appendDigit = (digit) => {
    // Bonus input mode - only integers allowed
    if (isBonusInputFocused.value) {
        // Don't allow decimal point for bonus input
        if (digit === '.') return;
        const currentValue = String(bonusToUse.value || '');
        if (currentValue.length >= 6) return;
        const newValue = currentValue + digit;
        const newAmount = parseInt(newValue) || 0;
        bonusToUse.value = Math.min(newAmount, maxBonusToUse.value);
        return;
    }

    if (isMixedMode.value) {
        // Use checkButtonAmount which includes bonus deduction
        const toPay = checkButtonAmount.value;

        if (activeMixedField.value === 'cash') {
            if (mixedCash.value.length >= 7) return;
            const newValue = mixedCash.value + digit;
            const newAmount = parseInt(newValue) || 0;
            // Limit cash to not exceed order total
            if (newAmount > toPay) {
                mixedCash.value = String(toPay);
                return;
            }
            mixedCash.value = newValue;
        } else {
            if (mixedCard.value.length >= 7) return;
            const newValue = mixedCard.value + digit;
            const newAmount = parseInt(newValue) || 0;
            const cashAmt = parseInt(mixedCash.value) || 0;
            const maxCard = toPay - cashAmt;
            if (newAmount > maxCard) {
                mixedCard.value = String(Math.max(0, maxCard));
                return;
            }
            mixedCard.value = newValue;
        }
        return;
    }

    if (amount.value.length >= 10) return;

    // Проверка на точку: не в начале, только одна, и не если округление включено
    if (digit === '.') {
        if (props.roundAmounts || amount.value === '' || amount.value.includes('.')) return;
    }

    // Ограничиваем до 2 знаков после точки
    if (amount.value.includes('.')) {
        const decimalPart = amount.value.split('.')[1] || '';
        if (decimalPart.length >= 2 && digit !== '.') return;
    }

    const newValue = amount.value + digit;
    const newAmount = parseFloat(newValue) || 0;

    if (method.value === 'card' && props.mode !== 'deposit') {
        // Use checkButtonAmount which includes bonus deduction
        const maxAmount = checkButtonAmount.value;
        if (newAmount > maxAmount) {
            amount.value = String(maxAmount);
            return;
        }
    }
    amount.value = newValue;
};

const backspace = () => {
    // Bonus input mode
    if (isBonusInputFocused.value) {
        const currentValue = String(bonusToUse.value || '');
        const newValue = currentValue.slice(0, -1);
        bonusToUse.value = parseInt(newValue) || 0;
        return;
    }

    if (isMixedMode.value) {
        if (activeMixedField.value === 'cash') {
            mixedCash.value = mixedCash.value.slice(0, -1);
        } else {
            mixedCard.value = mixedCard.value.slice(0, -1);
        }
        return;
    }
    amount.value = amount.value.slice(0, -1);
};

const clearAmount = () => {
    // Bonus input mode
    if (isBonusInputFocused.value) {
        bonusToUse.value = 0;
        return;
    }

    if (isMixedMode.value) {
        if (activeMixedField.value === 'cash') {
            mixedCash.value = '';
        } else {
            mixedCard.value = '';
        }
    } else {
        amount.value = '';
    }
};

const addAmount = (add) => {
    // Bonus input mode - add to bonus
    if (isBonusInputFocused.value) {
        const current = bonusToUse.value || 0;
        bonusToUse.value = Math.min(current + add, maxBonusToUse.value);
        return;
    }

    if (isMixedMode.value) {
        // Use checkButtonAmount which includes bonus deduction
        const toPay = checkButtonAmount.value;

        if (activeMixedField.value === 'cash') {
            const current = parseInt(mixedCash.value) || 0;
            // Limit cash to not exceed order total
            mixedCash.value = String(Math.min(current + add, toPay));
        } else {
            const current = parseInt(mixedCard.value) || 0;
            const cashAmt = parseInt(mixedCash.value) || 0;
            const maxCard = toPay - cashAmt;
            mixedCard.value = String(Math.min(current + add, maxCard));
        }
        return;
    }

    const current = enteredAmount.value;
    let newAmount = current + add;

    if (method.value === 'card' && props.mode !== 'deposit') {
        // Use checkButtonAmount which includes bonus deduction
        const maxAmount = checkButtonAmount.value;
        newAmount = Math.min(newAmount, maxAmount);
    }
    amount.value = String(newAmount);
};

const fillFullAmount = () => {
    // Use checkButtonAmount which respects split by guests mode
    const toPay = checkButtonAmount.value;
    if (isMixedMode.value) {
        if (activeMixedField.value === 'cash') {
            mixedCash.value = String(toPay);
            mixedCard.value = '0';
        } else {
            mixedCard.value = String(toPay);
            mixedCash.value = '0';
        }
    } else {
        amount.value = String(toPay);
    }
};

const close = () => {
    if (showSuccess.value) return; // Block close during success animation
    emit('update:modelValue', false);
};

// Close error overlay and reset state
const closeErrorOverlay = () => {
    showSuccess.value = false;
    successState.value = 'processing';
    errorMessage.value = '';
};

// Show success animation with callback for redirect
const showSuccessAndClose = (paymentData, isPartialPayment = false) => {
    successState.value = 'success';

    // Shorter animation for partial payment (staying in modal), longer for full close
    const animationDuration = isPartialPayment ? 800 : 1200;

    // After success animation, transition to next state
    setTimeout(() => {
        if (isPartialPayment) {
            // For partial payment (split by guests) - stay in modal, reset selection
            showSuccess.value = false;
            successState.value = 'processing';
            // Reset selection for next payment
            selectedGuestNumbers.value = [];
            amount.value = '';
            bonusToUse.value = 0;
            // Don't close modal - just notify parent that payment was processed
            emit('confirm', { ...paymentData, _handled: true, _stayOpen: true });
        } else {
            // For full payment - emit for redirect immediately (parent will create persistent overlay)
            emit('confirm', { ...paymentData, _handled: true });
        }
    }, animationDuration);
};

// Show error in overlay
const showError = (message) => {
    successState.value = 'error';
    errorMessage.value = message;
};

const confirm = () => {
    if (!canConfirm.value) return;
    if (showSuccess.value) return; // Prevent double-click

    // Store the payment data before showing success
    let paymentData;

    // Determine the actual amount to pay
    // For deposit/prepayment mode - use entered amount, otherwise use effectiveTotal
    const actualAmountToPay = (props.mode === 'deposit' || props.mode === 'prepayment')
        ? enteredAmount.value
        : effectiveTotal.value;

    // Deposit used: in split mode use depositToApply (only on final payment), otherwise paidAmount
    const actualDepositUsed = isSplitByGuests.value ? depositToApply.value : props.paidAmount;
    // Refund amount from deposit excess
    const actualRefundAmount = isSplitByGuests.value ? depositRefund.value : refundAmount.value;

    if (isMixedMode.value) {
        const cashAmt = parseInt(mixedCash.value) || 0;
        const cardAmt = parseInt(mixedCard.value) || 0;
        const mixedChange = Math.max(0, cashAmt + cardAmt - actualAmountToPay);
        successAmount.value = actualAmountToPay;

        paymentData = {
            amount: actualAmountToPay,
            method: 'mixed',
            cashAmount: cashAmt,
            cardAmount: cardAmt,
            change: mixedChange,
            refundAmount: actualRefundAmount,
            fullyPaidByDeposit: fullyPaidByDeposit.value,
            depositUsed: actualDepositUsed,
            bonusUsed: bonusToUse.value || 0
        };
    } else {
        successAmount.value = actualAmountToPay;

        paymentData = {
            amount: actualAmountToPay,
            method: method.value,
            change: change.value,
            refundAmount: actualRefundAmount,
            fullyPaidByDeposit: fullyPaidByDeposit.value,
            depositUsed: actualDepositUsed,
            bonusUsed: bonusToUse.value || 0
        };
    }

    // Add split by guests info if enabled
    if (isSplitByGuests.value && selectedGuestNumbers.value.length > 0) {
        paymentData.splitByGuests = true;
        paymentData.guestNumbers = [...selectedGuestNumbers.value];
        paymentData.guestsTotal = selectedGuestsTotal.value;
    }

    // For deposit/prepayment mode - skip built-in animation (parent has its own)
    if (props.mode === 'deposit' || props.mode === 'prepayment') {
        emit('confirm', paymentData);
        emit('update:modelValue', false);
        return;
    }

    // Show processing overlay immediately
    showSuccess.value = true;
    successState.value = 'processing';

    // Emit confirm - parent will handle API call and call back with result
    emit('confirm', paymentData);
};

const formatPrice = (price) => {
    let num = parseFloat(price) || 0;
    // Округляем в пользу клиента (вниз) если включена настройка
    if (props.roundAmounts) {
        num = Math.floor(num);
    }
    return new Intl.NumberFormat('ru-RU').format(num) + ' ₽';
};

// Форматирование суммы для отображения в поле ввода
const formatAmountDisplay = (val) => {
    if (!val) return props.roundAmounts ? '0' : '0.00';
    const num = parseFloat(val) || 0;
    if (props.roundAmounts) {
        return String(Math.floor(num));
    }
    return num.toFixed(2);
};

// Expose methods for parent component
defineExpose({
    showSuccessAndClose,
    showError,
    closeErrorOverlay
});
</script>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.2s ease;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

.slide-up-enter-active,
.slide-up-leave-active {
    transition: all 0.3s ease;
}
.slide-up-enter-from,
.slide-up-leave-to {
    opacity: 0;
    transform: translateY(20px) scale(0.95);
}

/* Bottom sheet animation */
.slide-bottom-enter-active,
.slide-bottom-leave-active {
    transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}
.slide-bottom-enter-from,
.slide-bottom-leave-to {
    transform: translateY(100%);
}

/* Slide down animation for mixed payment */
.slide-down-enter-active,
.slide-down-leave-active {
    transition: all 0.3s ease;
}
.slide-down-enter-from,
.slide-down-leave-to {
    opacity: 0;
    max-height: 0;
    transform: translateY(-10px);
}
.slide-down-enter-to,
.slide-down-leave-from {
    opacity: 1;
    max-height: 200px;
}

/* Blinking cursor animation */
@keyframes blink {
    0%, 50% { opacity: 1; }
    51%, 100% { opacity: 0; }
}
.animate-blink {
    animation: blink 1s infinite;
}

/* Success animation transition */
.success-fade-enter-active,
.success-fade-leave-active {
    transition: opacity 0.3s ease;
}
.success-fade-enter-from,
.success-fade-leave-to {
    opacity: 0;
}

/* Success checkmark animation */
.success-animation-container {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}

.checkmark {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    display: block;
    stroke-width: 2;
    stroke: #22c55e;
    stroke-miterlimit: 10;
    animation: checkmark-scale 0.4s ease-in-out 0.4s both, checkmark-glow 1.5s ease-in-out 0.6s;
}

.checkmark-circle {
    stroke-dasharray: 166;
    stroke-dashoffset: 166;
    stroke-width: 2;
    stroke-miterlimit: 10;
    stroke: #22c55e;
    fill: none;
    animation: checkmark-stroke 0.6s cubic-bezier(0.65, 0, 0.45, 1) forwards;
}

.checkmark-check {
    transform-origin: 50% 50%;
    stroke-dasharray: 48;
    stroke-dashoffset: 48;
    stroke-width: 3;
    stroke-linecap: round;
    stroke-linejoin: round;
    animation: checkmark-stroke 0.3s cubic-bezier(0.65, 0, 0.45, 1) 0.5s forwards;
}

@keyframes checkmark-stroke {
    100% {
        stroke-dashoffset: 0;
    }
}

@keyframes checkmark-scale {
    0%, 100% {
        transform: scale(1);
    }
    50% {
        transform: scale(1.1);
    }
}

@keyframes checkmark-glow {
    0% {
        filter: drop-shadow(0 0 0 rgba(34, 197, 94, 0));
    }
    50% {
        filter: drop-shadow(0 0 20px rgba(34, 197, 94, 0.6));
    }
    100% {
        filter: drop-shadow(0 0 0 rgba(34, 197, 94, 0));
    }
}

.success-text {
    text-align: center;
    animation: success-text-appear 0.4s ease-out 0.6s both;
}

@keyframes success-text-appear {
    0% {
        opacity: 0;
        transform: translateY(15px);
    }
    100% {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Static checkmark for closing state */
.checkmark-static {
    width: 100px;
    height: 100px;
}
</style>
