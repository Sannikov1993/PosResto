<template>
    <Teleport to="body">
        <Transition name="slide">
            <div v-if="modelValue" class="fixed inset-0 z-50 flex justify-end">
                <!-- Backdrop -->
                <div class="absolute inset-0 bg-black/60 z-0" @click="close"></div>

                <!-- Sidebar Panel -->
                <div data-testid="discount-modal" class="sidebar-panel relative z-10 w-[420px] h-full bg-dark-900 border-l border-dark-700 flex flex-col shadow-2xl">
                    <!-- Header -->
                    <div class="flex items-center justify-between px-4 py-3 border-b border-dark-700" data-testid="discount-header">
                        <h3 class="font-semibold text-white">Скидки</h3>
                        <button @click="close" class="text-gray-400 hover:text-white transition-colors" data-testid="discount-close-btn">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <!-- Customer & Bonus Section -->
                    <div v-if="customerId" class="px-4 py-3 border-b border-dark-700">
                        <!-- Customer info row -->
                        <div
                            class="flex items-center justify-between cursor-pointer group"
                            @click="effectiveBonusSettings?.is_enabled && customerBonusBalance > 0 && (showBonusInput = !showBonusInput)"
                        >
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full bg-dark-800 flex items-center justify-center">
                                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                    </svg>
                                </div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <p class="text-white font-medium text-sm">{{ customerName || 'Клиент' }}</p>
                                        <!-- Chevron для раскрытия бонусов (только если есть бонусы) -->
                                        <svg
                                            v-if="effectiveBonusSettings?.is_enabled && customerBonusBalance > 0"
                                            :class="[
                                                'w-4 h-4 transition-transform duration-200',
                                                showBonusInput ? 'rotate-90 text-yellow-400' : 'text-gray-500 group-hover:text-gray-400'
                                            ]"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                        </svg>
                                    </div>
                                    <p v-if="customerLoyaltyLevel" class="text-xs" :style="{ color: customerLoyaltyLevel.color }">
                                        {{ customerLoyaltyLevel.icon }} {{ customerLoyaltyLevel.name }}
                                    </p>
                                </div>
                            </div>
                            <!-- Bonus balance (показываем если бонусная система включена) -->
                            <div v-if="effectiveBonusSettings?.is_enabled" class="text-right">
                                <p :class="customerBonusBalance > 0 ? 'text-yellow-400' : 'text-gray-500'" class="font-medium">{{ customerBonusBalance }} ★</p>
                                <p class="text-xs text-gray-500">{{ confirmedBonusToSpend > 0 ? `списать ${confirmedBonusToSpend}` : 'бонусов' }}</p>
                            </div>
                        </div>

                        <!-- Bonus spending (expandable) -->
                        <Transition name="expand">
                            <div v-if="showBonusInput && customerBonusBalance > 0 && effectiveBonusSettings?.is_enabled" class="mt-3 pt-3 border-t border-dark-700">
                                <div class="flex items-center gap-2">
                                    <div class="relative flex-1">
                                        <input
                                            v-model.number="bonusToSpend"
                                            type="number"
                                            min="0"
                                            :max="maxBonusToSpend"
                                            placeholder="Количество бонусов"
                                            class="w-full bg-dark-800 border border-dark-600 rounded-lg pl-3 pr-24 py-2.5 text-white text-sm focus:outline-none focus:border-yellow-500 transition-colors no-spinners"
                                            @input="validateBonusInput"
                                        />
                                        <div class="absolute right-2 top-1/2 -translate-y-1/2 flex items-center gap-1">
                                            <button
                                                v-if="bonusToSpend > 0"
                                                @click="bonusToSpend = 0; confirmedBonusToSpend = 0"
                                                class="p-1 text-gray-500 hover:text-red-400 transition-colors"
                                            >
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                </svg>
                                            </button>
                                            <button
                                                @click="bonusToSpend = maxBonusToSpend"
                                                class="px-2 py-1 text-xs text-yellow-400 hover:text-yellow-300 transition-colors"
                                            >
                                                Макс
                                            </button>
                                        </div>
                                    </div>
                                    <button
                                        @click="applyBonus"
                                        :disabled="bonusToSpend <= 0"
                                        class="px-4 py-2.5 bg-yellow-500 hover:bg-yellow-400 disabled:bg-dark-700 disabled:text-gray-500 text-dark-900 rounded-lg text-sm font-medium transition-colors"
                                    >
                                        Списать
                                    </button>
                                </div>
                                <div class="flex items-center justify-between mt-2 text-xs">
                                    <span class="text-gray-500">Доступно: {{ maxBonusToSpend }} из {{ customerBonusBalance }}</span>
                                    <span v-if="bonusToSpend > 0" class="text-green-400">-{{ formatPrice(bonusToSpend) }} ₽</span>
                                </div>
                            </div>
                        </Transition>
                    </div>

                    <!-- Price Summary -->
                    <div class="px-4 py-3 border-b border-dark-700 space-y-1" data-testid="price-summary">
                        <!-- Final price (large) -->
                        <div class="flex justify-between items-baseline">
                            <span class="text-gray-400 text-sm">Сумма со скидкой</span>
                            <span class="text-white text-2xl font-bold" data-testid="final-total">{{ formatPrice(finalTotal) }}</span>
                        </div>
                        <!-- Original price -->
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-500">Без скидки</span>
                            <span class="text-gray-400" data-testid="original-subtotal">{{ formatPrice(subtotal) }}</span>
                        </div>
                        <!-- Total discount -->
                        <div v-if="totalDiscountAmount > 0" class="flex justify-between text-sm">
                            <span class="text-gray-500">Итоговая скидка {{ totalDiscountPercent }}%</span>
                            <span class="text-green-400" data-testid="total-discount-amount">-{{ formatPrice(totalDiscountAmount) }}</span>
                        </div>

                        <!-- Applied discounts -->
                        <div v-if="appliedDiscounts.length > 0 || confirmedBonusToSpend > 0" class="pt-2 space-y-1">
                            <div v-for="(discount, idx) in appliedDiscounts" :key="idx"
                                 class="text-sm bg-dark-800 rounded px-2 py-1.5">
                                <div class="flex items-center justify-between">
                                    <span class="text-gray-300 flex items-center gap-1.5 truncate">
                                        <span class="text-xs">{{ getDiscountIcon(discount.type) }}</span>
                                        {{ getDiscountName(discount) }}
                                    </span>
                                    <div class="flex items-center gap-2">
                                        <span :class="['bonus_multiply', 'bonus_add'].includes(discount.type) ? 'text-yellow-400' : 'text-green-400'" class="whitespace-nowrap">
                                            <template v-if="discount.type === 'bonus_multiply'">x{{ discount.bonusMultiplier }}</template>
                                            <template v-else-if="discount.type === 'bonus_add'">+{{ discount.bonusAdd }}</template>
                                            <template v-else>-{{ formatPrice(discount.amount) }}</template>
                                        </span>
                                        <!-- Кнопка удаления только для неавтоматических скидок -->
                                        <button v-if="!discount.auto && discount.type !== 'level' && discount.sourceType !== 'level'"
                                                @click="removeAppliedDiscount(idx)"
                                                class="text-gray-500 hover:text-red-400 transition-colors">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                                <!-- Доп. информация для процентных промокодов с лимитом -->
                                <div v-if="discount.sourceType === 'promo_code' && discount.percent > 0 && discount.maxDiscount"
                                     class="text-xs text-gray-500 mt-0.5 pl-5">
                                    {{ discount.percent }}% от суммы, макс. {{ formatPrice(discount.maxDiscount) }}
                                </div>
                            </div>

                            <!-- Bonus spending row -->
                            <div v-if="confirmedBonusToSpend > 0" class="text-sm bg-dark-800 rounded px-2 py-1.5">
                                <div class="flex items-center justify-between">
                                    <span class="text-gray-300 flex items-center gap-1.5">
                                        <span class="text-xs">★</span>
                                        Списание бонусов
                                    </span>
                                    <div class="flex items-center gap-2">
                                        <span class="text-yellow-400 whitespace-nowrap">-{{ formatPrice(confirmedBonusToSpend) }}</span>
                                        <button
                                            @click="bonusToSpend = 0; confirmedBonusToSpend = 0"
                                            class="text-gray-500 hover:text-red-400 transition-colors"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Rounding (только если есть копейки для округления) -->
                        <div v-if="roundingAmount < 0" class="flex items-center justify-between text-sm bg-dark-800 rounded px-2 py-1.5">
                            <span class="text-gray-300 flex items-center gap-1.5">
                                <span class="text-xs">🔄</span>
                                Округление
                            </span>
                            <span class="text-green-400 whitespace-nowrap">-{{ formatRounding(Math.abs(roundingAmount)) }}</span>
                        </div>

                        <!-- Details toggle -->
                        <button v-if="appliedDiscounts.length > 0 || discountBreakdown.discounts?.length > 0"
                                @click="showDetails = !showDetails"
                                class="mt-2 text-xs text-accent hover:text-blue-400 transition-colors">
                            {{ showDetails ? 'Скрыть подробности' : 'Подробнее' }}
                        </button>

                        <!-- Expanded details -->
                        <Transition name="expand">
                            <div v-if="showDetails" class="pt-2 text-xs text-gray-500 space-y-1 border-t border-dark-700 mt-2">
                                <div v-if="customerId && customerName" class="flex justify-between">
                                    <span>Клиент:</span>
                                    <span class="text-gray-400">{{ customerName }}</span>
                                </div>
                                <div v-if="customerLoyaltyLevel" class="flex justify-between">
                                    <span>Уровень:</span>
                                    <span :style="{ color: customerLoyaltyLevel.color }">
                                        {{ customerLoyaltyLevel.icon }} {{ customerLoyaltyLevel.name }}
                                    </span>
                                </div>
                                <div v-if="discountBreakdown.bonus_earned > 0" class="flex justify-between">
                                    <span>Будет начислено:</span>
                                    <span class="text-yellow-400">+{{ discountBreakdown.bonus_earned }} бонусов</span>
                                </div>
                            </div>
                        </Transition>
                    </div>

                    <!-- Promo Code Input -->
                    <div class="px-4 py-3 border-b border-dark-700" data-testid="promo-code-section">
                        <div class="flex gap-2">
                            <div class="relative flex-1">
                                <input
                                    v-model="searchQuery"
                                    type="text"
                                    placeholder="Промокод или сертификат"
                                    :disabled="promoLoading"
                                    :class="[
                                        'w-full bg-dark-800 border rounded-lg pl-9 pr-9 py-2.5 text-white text-sm placeholder-gray-500 focus:outline-none transition-colors',
                                        promoStatus === 'success' ? 'border-green-500' :
                                        promoStatus === 'error' ? 'border-red-500' :
                                        'border-dark-600 focus:border-accent'
                                    ]"
                                    @keyup.enter="applySearchQuery"
                                    @input="resetPromoStatus"
                                    data-testid="promo-code-input"
                                />
                                <!-- Left icon -->
                                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                                </svg>
                                <!-- Right status icon -->
                                <div class="absolute right-3 top-1/2 -translate-y-1/2">
                                    <!-- Loading -->
                                    <svg v-if="promoLoading" class="w-4 h-4 text-accent animate-spin" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <!-- Success -->
                                    <svg v-else-if="promoStatus === 'success'" class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                    <!-- Error -->
                                    <svg v-else-if="promoStatus === 'error'" class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </div>
                            </div>
                            <button
                                @click="applySearchQuery"
                                :disabled="!searchQuery || promoLoading"
                                class="px-4 py-2.5 bg-accent hover:bg-blue-600 disabled:bg-dark-700 disabled:text-gray-500 rounded-lg text-white text-sm font-medium transition-colors"
                                data-testid="apply-promo-btn"
                            >
                                Применить
                            </button>
                        </div>
                        <p v-if="searchError" class="text-red-400 text-xs mt-1.5">{{ searchError }}</p>
                        <p v-if="promoStatus === 'success' && promoSuccessMessage" class="text-green-400 text-xs mt-1.5">{{ promoSuccessMessage }}</p>
                    </div>

                    <!-- Available Discounts List -->
                    <div class="flex-1 overflow-y-auto">
                        <!-- Loading -->
                        <div v-if="loading" class="p-4 text-center text-gray-500 text-sm">
                            Загрузка...
                        </div>

                        <div v-else class="divide-y divide-dark-800">
                            <!-- Gift Certificates -->
                            <div v-if="availableCertificates.length > 0">
                                <div class="px-4 py-2 text-xs text-gray-500 bg-dark-850 sticky top-0">Сертификаты</div>
                                <div v-for="cert in availableCertificates" :key="'cert-' + cert.id"
                                     class="flex items-center justify-between px-4 py-2.5 hover:bg-dark-800 transition-colors cursor-pointer"
                                     @click="applyCertificate(cert)">
                                    <span class="text-gray-300 text-sm">{{ cert.name || 'Сертификат ' + cert.code }}</span>
                                    <div class="flex items-center gap-3">
                                        <span class="text-gray-400 text-sm">{{ formatPrice(cert.balance || cert.amount) }}</span>
                                        <button class="w-6 h-6 flex items-center justify-center rounded bg-dark-700 hover:bg-accent text-gray-400 hover:text-white transition-colors">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Quick Percent Discounts -->
                            <div v-if="settings.preset_percentages?.length > 0" data-testid="quick-discounts-section">
                                <div class="px-4 py-2 text-xs text-gray-500 bg-dark-850 sticky top-0">Быстрая скидка</div>
                                <div class="px-4 py-3 flex flex-wrap gap-2">
                                    <button v-for="pct in settings.preset_percentages" :key="'pct-' + pct"
                                            @click="applyQuickPercent(pct)"
                                            :disabled="hasNonStackableDiscount"
                                            :class="[
                                                'px-4 py-2 rounded-lg text-sm font-medium transition-colors',
                                                isQuickPercentApplied(pct)
                                                    ? 'bg-accent text-white'
                                                    : 'bg-dark-700 text-gray-300 hover:bg-dark-600 hover:text-white',
                                                hasNonStackableDiscount ? 'opacity-50 cursor-not-allowed' : ''
                                            ]"
                                            :data-testid="`quick-discount-${pct}`">
                                        {{ pct }}%
                                    </button>
                                </div>
                            </div>

                            <!-- Custom Discount (percent or fixed) -->
                            <div v-if="settings.allow_custom_percent || settings.allow_fixed_amount" data-testid="custom-discount-section">
                                <div class="px-4 py-2 text-xs text-gray-500 bg-dark-850 sticky top-0">Произвольная скидка</div>
                                <div class="px-4 py-3">
                                    <div class="flex gap-2">
                                        <input
                                            v-model.number="customValue"
                                            type="number"
                                            min="0"
                                            :max="customDiscountType === 'percent' ? 100 : undefined"
                                            placeholder="0"
                                            :disabled="hasNonStackableDiscount"
                                            class="flex-1 bg-dark-800 border border-dark-600 rounded-lg px-3 py-2.5 text-white text-sm focus:border-accent focus:outline-none disabled:opacity-50"
                                            @keyup.enter="applyCustomDiscount"
                                            data-testid="custom-discount-input"
                                        />
                                        <!-- Type toggle -->
                                        <div class="flex rounded-lg overflow-hidden border border-dark-600" data-testid="custom-discount-type-toggle">
                                            <button v-if="settings.allow_custom_percent"
                                                    @click="customDiscountType = 'percent'"
                                                    :class="[
                                                        'px-3 py-2.5 text-sm font-medium transition-colors',
                                                        customDiscountType === 'percent'
                                                            ? 'bg-accent text-white'
                                                            : 'bg-dark-700 text-gray-400 hover:text-white'
                                                    ]"
                                                    data-testid="custom-discount-percent-btn">
                                                %
                                            </button>
                                            <button v-if="settings.allow_fixed_amount"
                                                    @click="customDiscountType = 'fixed'"
                                                    :class="[
                                                        'px-3 py-2.5 text-sm font-medium transition-colors',
                                                        customDiscountType === 'fixed'
                                                            ? 'bg-accent text-white'
                                                            : 'bg-dark-700 text-gray-400 hover:text-white'
                                                    ]"
                                                    data-testid="custom-discount-fixed-btn">
                                                ₽
                                            </button>
                                        </div>
                                        <button @click="applyCustomDiscount"
                                                :disabled="!customValue || hasNonStackableDiscount"
                                                class="px-4 py-2.5 bg-accent hover:bg-blue-600 text-white rounded-lg text-sm font-medium transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                                                data-testid="apply-custom-discount-btn">
                                            OK
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Discount Reason Selection (Dropdown) -->
                            <div v-if="discountReasons.length > 0" class="px-4 py-3" data-testid="discount-reason-section">
                                <select
                                    v-model="selectedReasonId"
                                    class="w-full bg-dark-800 border border-dark-600 rounded-lg px-3 py-2.5 text-white text-sm focus:border-accent focus:outline-none appearance-none cursor-pointer"
                                    style="background-image: url('data:image/svg+xml;charset=UTF-8,%3csvg xmlns=%27http://www.w3.org/2000/svg%27 viewBox=%270 0 24 24%27 fill=%27none%27 stroke=%27%236b7280%27 stroke-width=%272%27 stroke-linecap=%27round%27 stroke-linejoin=%27round%27%3e%3cpolyline points=%276 9 12 15 18 9%27%3e%3c/polyline%3e%3c/svg%3e'); background-repeat: no-repeat; background-position: right 0.75rem center; background-size: 1rem;"
                                    data-testid="discount-reason-select"
                                >
                                    <option value="">Причина скидки (необязательно)</option>
                                    <option v-for="reason in discountReasons" :key="reason.id" :value="reason.id">
                                        {{ reason.name }}
                                    </option>
                                </select>
                            </div>

                            <!-- Promotions (manual selection) -->
                            <div v-if="manualPromotions.length > 0">
                                <div class="px-4 py-2 text-xs text-gray-500 bg-dark-850 sticky top-0">Акции по выбору</div>
                                <div v-for="promo in filteredPromotions" :key="'promo-' + promo.id"
                                     class="flex items-center justify-between px-4 py-2.5 hover:bg-dark-800 transition-colors cursor-pointer"
                                     :class="{
                                         'opacity-50': isPromoApplied(promo.id) || !isPromoAvailable(promo),
                                         'cursor-not-allowed': !isPromoAvailable(promo)
                                     }"
                                     @click="isPromoAvailable(promo) && !isPromoApplied(promo.id) && applyPromotion(promo)">
                                    <div class="flex-1 min-w-0 pr-2">
                                        <div class="text-gray-300 text-sm truncate">{{ promo.name }}</div>
                                        <div class="text-gray-500 text-xs truncate">
                                            <template v-if="promo.description">{{ promo.description }}</template>
                                            <template v-else>{{ getPromoConditions(promo) }}</template>
                                        </div>
                                        <!-- Warning if not available -->
                                        <div v-if="!isPromoAvailable(promo)" class="text-yellow-500 text-xs mt-0.5">
                                            {{ getPromoUnavailableReason(promo) }}
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <span class="text-gray-400 text-sm whitespace-nowrap">
                                            <template v-if="promo.type === 'discount_percent'">{{ promo.discount_value }}%</template>
                                            <template v-else-if="promo.type === 'discount_fixed'">{{ formatPrice(promo.discount_value) }}</template>
                                            <template v-else-if="promo.type === 'progressive_discount'">до {{ getMaxProgressivePercent(promo) }}%</template>
                                            <template v-else>{{ getPromoIcon(promo.type) }}</template>
                                        </span>
                                        <button :disabled="isPromoApplied(promo.id) || !isPromoAvailable(promo)"
                                                class="w-6 h-6 flex items-center justify-center rounded bg-dark-700 hover:bg-accent text-gray-400 hover:text-white transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Empty state -->
                            <div v-if="!loading && availableCertificates.length === 0 && manualPromotions.length === 0 && !settings.preset_percentages?.length"
                                 class="p-8 text-center text-gray-500 text-sm">
                                Нет доступных скидок
                            </div>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="px-4 py-3 border-t border-dark-700 space-y-2" data-testid="discount-footer">
                        <!-- Warning for non-stackable -->
                        <p v-if="hasNonStackableDiscount" class="text-yellow-500 text-xs text-center" data-testid="non-stackable-warning">
                            Применённая скидка не совместима с другими
                        </p>

                        <!-- Actions -->
                        <div class="flex gap-2">
                            <button v-if="appliedDiscounts.length > 0"
                                    @click="clearAllDiscounts"
                                    class="flex-1 py-2.5 bg-red-500/20 hover:bg-red-500/30 border border-red-500/30 rounded-lg text-red-400 font-medium text-sm transition-colors"
                                    data-testid="clear-all-discounts-btn">
                                Убрать все
                            </button>
                            <button @click="confirmDiscounts"
                                    :disabled="!canConfirm"
                                    class="flex-1 py-2.5 bg-accent hover:bg-blue-600 disabled:bg-dark-700 disabled:text-gray-500 rounded-lg text-white font-medium text-sm transition-colors"
                                    data-testid="confirm-discounts-btn">
                                Применить
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>

        <!-- Manager PIN Modal -->
        <Transition name="modal">
            <div v-if="showPinModal" class="fixed inset-0 bg-black/80 flex items-center justify-center z-[60]" @click.self="showPinModal = false" data-testid="manager-pin-modal">
                <div class="bg-dark-900 rounded-xl w-full max-w-xs mx-4 p-4" data-testid="manager-pin-content">
                    <h3 class="text-white font-semibold mb-4 text-center">Введите PIN менеджера</h3>
                    <input
                        v-model="managerPin"
                        type="password"
                        maxlength="4"
                        placeholder="****"
                        class="w-full bg-dark-800 border border-dark-600 rounded-lg px-4 py-3 text-white text-center text-2xl tracking-widest focus:border-accent focus:outline-none mb-4"
                        @keyup.enter="verifyPin"
                        autofocus
                        data-testid="manager-pin-input"
                    />
                    <p v-if="pinError" class="text-red-400 text-sm text-center mb-4" data-testid="manager-pin-error">{{ pinError }}</p>
                    <div class="flex gap-2">
                        <button @click="showPinModal = false" class="flex-1 py-2.5 bg-dark-700 hover:bg-dark-600 rounded-lg text-gray-300 font-medium" data-testid="manager-pin-cancel">
                            Отмена
                        </button>
                        <button @click="verifyPin" :disabled="managerPin.length < 4" class="flex-1 py-2.5 bg-accent hover:bg-blue-600 disabled:bg-dark-700 rounded-lg text-white font-medium" data-testid="manager-pin-confirm">
                            OK
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { authFetch } from '../../services/auth';

const props = defineProps({
    modelValue: Boolean,
    tableId: { type: Number, default: null },
    orderId: { type: Number, default: null },
    subtotal: { type: Number, default: 0 },
    currentDiscount: { type: Number, default: 0 },
    currentDiscountPercent: { type: Number, default: 0 },
    currentDiscountReason: { type: String, default: '' },
    currentPromoCode: { type: String, default: '' },
    currentAppliedDiscounts: { type: Array, default: () => [] }, // Массив applied_discounts из заказа
    customerId: { type: Number, default: null },
    customerName: { type: String, default: '' },
    customerLoyaltyLevel: { type: Object, default: null },
    customerBonusBalance: { type: Number, default: 0 }, // Баланс бонусов клиента
    bonusSettings: { type: Object, default: null }, // Настройки бонусной системы
    currentBonusToSpend: { type: Number, default: 0 }, // Уже выбранные бонусы для списания
    orderType: { type: String, default: 'dine_in' },
    // Товары заказа для расчёта скидок (для доставки где нет orderId)
    items: { type: Array, default: () => [] }
});

const emit = defineEmits(['update:modelValue', 'apply']);

// UI State
const loading = ref(false);
const showDetails = ref(false);
const searchQuery = ref('');
const searchError = ref('');
const promoLoading = ref(false);
const promoStatus = ref(''); // '', 'success', 'error'
const promoSuccessMessage = ref('');
const customValue = ref(null);
const customDiscountType = ref('percent');

// Bonus spending
const bonusToSpend = ref(0); // Значение в поле ввода
const confirmedBonusToSpend = ref(0); // Подтверждённое значение после нажатия "Списать"
const showBonusInput = ref(false);
const localBonusSettings = ref(null); // Локальные настройки бонусов (если не переданы через props)

// Effective bonus settings (из props или загруженные локально)
const effectiveBonusSettings = computed(() => props.bonusSettings || localBonusSettings.value);

// Max bonus that can be spent (spend_rate - процент от суммы заказа)
const maxBonusToSpend = computed(() => {
    if (!effectiveBonusSettings.value?.is_enabled || props.customerBonusBalance <= 0) return 0;

    const balance = props.customerBonusBalance;
    const spendRate = effectiveBonusSettings.value.spend_rate || 50; // По умолчанию 50%
    const subtotalAfterDiscount = Math.max(0, props.subtotal - totalDiscountAmount.value);
    const maxByPercent = Math.floor(subtotalAfterDiscount * spendRate / 100);

    return Math.min(balance, maxByPercent);
});

// Validate bonus input
const validateBonusInput = () => {
    if (bonusToSpend.value < 0) bonusToSpend.value = 0;
    if (bonusToSpend.value > maxBonusToSpend.value) bonusToSpend.value = maxBonusToSpend.value;
    bonusToSpend.value = Math.floor(bonusToSpend.value);
};

// Apply bonus and close panel
const applyBonus = () => {
    validateBonusInput();
    confirmedBonusToSpend.value = bonusToSpend.value; // Подтверждаем списание
    showBonusInput.value = false;
};

// Settings
const settings = ref({
    preset_percentages: [5, 10, 15, 20],
    max_discount_without_pin: 20,
    allow_custom_percent: true,
    allow_fixed_amount: true,
    enable_rounding: true, // Округление включено по умолчанию
    rounding_step: 10,
    reasons: []
});

// Available items
const availableCertificates = ref([]);
const availablePromotions = ref([]);
const discountReasons = ref([]);
const selectedReasonId = ref('');

// Get selected reason object
const selectedReason = computed(() => {
    if (!selectedReasonId.value) return null;
    return discountReasons.value.find(r => r.id === selectedReasonId.value) || null;
});

// Applied discounts
const appliedDiscounts = ref([]);
const discountBreakdown = ref({ discounts: [], bonus_earned: 0 });

// PIN modal
const showPinModal = ref(false);
const managerPin = ref('');
const pinError = ref('');
const pinVerified = ref(false);
const pendingDiscount = ref(null);

// Computed
const totalDiscountAmount = computed(() => {
    return appliedDiscounts.value.reduce((sum, d) => sum + (parseFloat(d.amount) || 0), 0);
});

const totalDiscountPercent = computed(() => {
    if (props.subtotal === 0) return 0;
    return Math.round((totalDiscountAmount.value / props.subtotal) * 100 * 10) / 10;
});

const roundingAmount = computed(() => {
    if (!settings.value.enable_rounding) return 0;
    const afterDiscount = props.subtotal - totalDiscountAmount.value;
    // Округляем ВНИЗ до целого рубля (убираем копейки в пользу клиента)
    const rounded = Math.floor(afterDiscount);
    const diff = rounded - afterDiscount;
    // diff будет отрицательным (например -0.50 для 50 копеек)
    // Возвращаем как есть (с копейками)
    return diff;
});

const finalTotal = computed(() => {
    return Math.max(0, props.subtotal - totalDiscountAmount.value - confirmedBonusToSpend.value + roundingAmount.value);
});

const hasNonStackableDiscount = computed(() => {
    return appliedDiscounts.value.some(d => d.stackable === false);
});

const effectiveDiscountPercent = computed(() => {
    // Суммируем все проценты от скидок (уровень + промокоды + ручные)
    // parseFloat гарантирует что значения - числа, а не строки
    return appliedDiscounts.value.reduce((sum, d) => sum + (parseFloat(d.percent) || 0), 0);
});

const needsManagerApproval = computed(() => {
    return effectiveDiscountPercent.value > settings.value.max_discount_without_pin && !pinVerified.value;
});

const canConfirm = computed(() => {
    return true;
});

// Только ручные акции (не автоматические) для выбора кассиром
const manualPromotions = computed(() => {
    return availablePromotions.value.filter(p => !p.is_automatic);
});

const filteredPromotions = computed(() => {
    const promotions = manualPromotions.value;
    if (!searchQuery.value) return promotions;
    const q = searchQuery.value.toLowerCase();
    return promotions.filter(p => p.name.toLowerCase().includes(q));
});

// Methods
const formatPrice = (price) => {
    return new Intl.NumberFormat('ru-RU').format(Math.round(price || 0)) + ' ₽';
};

// Форматирование округления с копейками (0,50 ₽)
const formatRounding = (amount) => {
    if (!amount || amount === 0) return '0 ₽';
    return new Intl.NumberFormat('ru-RU', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(amount) + ' ₽';
};

const getDiscountIcon = (type) => {
    const icons = {
        'level': '★',
        'promo_code': '🏷️',
        'promotion': '⭐',
        'certificate': '🎫',
        'bonus': '💎',
        'bonus_multiply': '✨',
        'bonus_add': '💎',
        'gift': '🎁',
        'birthday': '🎂'
    };
    return icons[type] || '';
};

const getPromoIcon = (type) => {
    const icons = {
        'discount_percent': '🏷️',
        'discount_fixed': '💰',
        'birthday': '🎂',
        'free_delivery': '🚚',
        'gift': '🎁',
        'bonus_multiplier': '✨'
    };
    return icons[type] || '🎉';
};

// Safely get discount name (handles objects)
const getDiscountName = (discount) => {
    if (!discount) return '';
    if (typeof discount.name === 'string') return discount.name;
    if (typeof discount.name === 'object') {
        return discount.name.label || discount.name.name || discount.name.title || '';
    }
    return discount.label || discount.title || `Скидка`;
};

const isPromoApplied = (promoId) => {
    return appliedDiscounts.value.some(d => d.sourceId === promoId && d.sourceType === 'promotion');
};

// Проверка доступности акции для текущего заказа
const isPromoAvailable = (promo) => {
    // Проверка минимальной суммы
    if (promo.min_order_amount && props.subtotal < parseFloat(promo.min_order_amount)) {
        return false;
    }

    // Проверка расписания (дни недели, время)
    if (promo.schedule && Object.keys(promo.schedule).length > 0) {
        const now = new Date();
        const dayOfWeek = now.getDay();
        const timeNow = now.toTimeString().slice(0, 5);

        // Проверка дней недели
        if (promo.schedule.days && promo.schedule.days.length > 0) {
            if (!promo.schedule.days.includes(dayOfWeek)) {
                return false;
            }
        }

        // Проверка времени
        if (promo.schedule.time_from && promo.schedule.time_to) {
            if (timeNow < promo.schedule.time_from || timeNow > promo.schedule.time_to) {
                return false;
            }
        }
    }

    // Проверка типа заказа
    if (promo.order_types && promo.order_types.length > 0) {
        if (!promo.order_types.includes(props.orderType)) {
            return false;
        }
    }

    // Проверка первого заказа
    if (promo.is_first_order_only) {
        // TODO: нужно проверять через API или передавать из props
        return false;
    }

    // Проверка дня рождения
    if (promo.is_birthday_only) {
        // TODO: нужно проверять через customer
        return false;
    }

    return true;
};

// Причина недоступности акции
const getPromoUnavailableReason = (promo) => {
    if (promo.min_order_amount && props.subtotal < parseFloat(promo.min_order_amount)) {
        return `Минимум ${formatPrice(promo.min_order_amount)}`;
    }

    if (promo.schedule && Object.keys(promo.schedule).length > 0) {
        const now = new Date();
        const dayOfWeek = now.getDay();
        const timeNow = now.toTimeString().slice(0, 5);

        if (promo.schedule.days && promo.schedule.days.length > 0) {
            if (!promo.schedule.days.includes(dayOfWeek)) {
                const dayNames = ['Вс', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб'];
                const activeDays = promo.schedule.days.map(d => dayNames[d]).join(', ');
                return `Только ${activeDays}`;
            }
        }

        if (promo.schedule.time_from && promo.schedule.time_to) {
            if (timeNow < promo.schedule.time_from || timeNow > promo.schedule.time_to) {
                return `Только ${promo.schedule.time_from}-${promo.schedule.time_to}`;
            }
        }
    }

    if (promo.order_types && promo.order_types.length > 0 && !promo.order_types.includes(props.orderType)) {
        const typeNames = { dine_in: 'В зале', delivery: 'Доставка', pickup: 'Самовывоз' };
        const activeTypes = promo.order_types.map(t => typeNames[t] || t).join(', ');
        return `Только: ${activeTypes}`;
    }

    if (promo.is_first_order_only) {
        return 'Только для первого заказа';
    }

    if (promo.is_birthday_only) {
        return 'Только в день рождения';
    }

    return 'Недоступно';
};

// Краткое описание условий акции
const getPromoConditions = (promo) => {
    const conditions = [];

    if (promo.min_order_amount) {
        conditions.push(`от ${formatPrice(promo.min_order_amount)}`);
    }

    if (promo.schedule?.time_from && promo.schedule?.time_to) {
        conditions.push(`${promo.schedule.time_from}-${promo.schedule.time_to}`);
    }

    if (promo.order_types && promo.order_types.length > 0 && promo.order_types.length < 3) {
        const typeNames = { dine_in: 'В зале', delivery: 'Доставка', pickup: 'Самовывоз' };
        conditions.push(promo.order_types.map(t => typeNames[t] || t).join(', '));
    }

    return conditions.join(' • ') || '';
};

// Максимальный процент для прогрессивной скидки
const getMaxProgressivePercent = (promo) => {
    if (!promo.progressive_tiers || !Array.isArray(promo.progressive_tiers)) return 0;
    return Math.max(...promo.progressive_tiers.map(t => t.discount_percent || 0));
};

const isQuickPercentApplied = (pct) => {
    return appliedDiscounts.value.some(d => d.type === 'percent' && d.percent === pct && d.sourceType === 'quick');
};

const close = () => {
    emit('update:modelValue', false);
};

// Load data
// skipAutoApplyLevel - если true, не пересчитываем скидку уровня (используем сохранённые значения)
async function loadData(skipAutoApplyLevel = false) {
    loading.value = true;
    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

        // Load settings
        const settingsRes = await authFetch('/api/settings/manual-discounts', {
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken }
        });
        const settingsData = await settingsRes.json();
        if (settingsData.success && settingsData.data) {
            // Сохраняем enable_rounding если он уже был установлен (из восстановленных скидок)
            const currentEnableRounding = settings.value.enable_rounding;
            settings.value = { ...settings.value, ...settingsData.data };
            if (currentEnableRounding) {
                settings.value.enable_rounding = true;
            }
        }

        // Load promotions
        const promosRes = await authFetch('/api/loyalty/promotions/active', {
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken }
        });
        const promosData = await promosRes.json();
        if (promosData.success && promosData.data) {
            availablePromotions.value = promosData.data;
        }

        // Load discount breakdown if customer or items
        if ((props.customerId || props.items.length > 0) && props.subtotal > 0) {
            const breakdownRes = await authFetch('/api/loyalty/calculate-discount', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({
                    customer_id: props.customerId,
                    order_total: props.subtotal,
                    order_type: props.orderType,
                    items: props.items
                })
            });
            const breakdownData = await breakdownRes.json();
            if (breakdownData.success && breakdownData.data) {
                discountBreakdown.value = breakdownData.data;

                // Auto-apply loyalty level discount
                if (breakdownData.data.discounts) {
                    for (const d of breakdownData.data.discounts) {
                        if (d.type === 'level' && d.amount > 0) {
                            // Проверяем, нет ли уже скидки уровня (восстановленной или существующей)
                            const existingLevelIdx = appliedDiscounts.value.findIndex(
                                ad => ad.sourceType === 'level' || ad.type === 'level'
                            );
                            if (existingLevelIdx >= 0) {
                                // Уже есть скидка уровня - если восстановили из сохранённых, оставляем как есть
                                if (skipAutoApplyLevel) {
                                    // Не трогаем сохранённые значения
                                    continue;
                                }
                                // Иначе обновляем данные
                                appliedDiscounts.value[existingLevelIdx] = {
                                    ...appliedDiscounts.value[existingLevelIdx],
                                    name: d.name || 'Скидка уровня',
                                    type: 'level',
                                    sourceType: 'level',
                                    amount: parseFloat(d.amount) || 0,
                                    percent: parseFloat(d.percent) || 0,
                                    auto: true
                                };
                            } else {
                                // Проверяем по проценту (для старого формата 'existing')
                                const levelPercent = parseFloat(d.percent) || 0;
                                const existingByPercent = appliedDiscounts.value.findIndex(
                                    ad => ad.sourceType === 'existing' && (parseFloat(ad.percent) || 0) === levelPercent
                                );
                                if (existingByPercent >= 0) {
                                    appliedDiscounts.value[existingByPercent] = {
                                        ...appliedDiscounts.value[existingByPercent],
                                        name: d.name || 'Скидка уровня',
                                        type: 'level',
                                        sourceType: 'level',
                                        percent: levelPercent,
                                        auto: true
                                    };
                                } else {
                                    // Добавляем новую скидку уровня
                                    appliedDiscounts.value.push({
                                        name: d.name || 'Скидка уровня',
                                        type: 'level',
                                        amount: parseFloat(d.amount) || 0,
                                        percent: parseFloat(d.percent) || 0,
                                        stackable: true,
                                        sourceType: 'level',
                                        sourceId: null,
                                        auto: true
                                    });
                                }
                            }
                        }
                        // Auto-apply automatic promotions
                        else if (d.type === 'promotion' && d.amount > 0 && d.auto) {
                            const existingPromoIdx = appliedDiscounts.value.findIndex(
                                ad => ad.sourceType === 'promotion' && ad.sourceId === d.promotion_id
                            );
                            if (existingPromoIdx < 0) {
                                // Добавляем автоматическую акцию
                                appliedDiscounts.value.push({
                                    name: d.name || 'Акция',
                                    type: d.discount_type || 'discount_percent',
                                    amount: parseFloat(d.amount) || 0,
                                    percent: parseFloat(d.percent) || 0,
                                    stackable: d.stackable !== false,
                                    sourceType: 'promotion',
                                    sourceId: d.promotion_id,
                                    auto: true,
                                    applies_to: d.applies_to,
                                    applicable_categories: d.applicable_categories,
                                    applicable_dishes: d.applicable_dishes,
                                });
                            }
                        }
                    }
                }
            }
        }

        // Load discount reasons (just labels, not discounts themselves)
        if (settings.value.reasons?.length > 0) {
            discountReasons.value = settings.value.reasons.map((r, i) => {
                const name = typeof r === 'string' ? r : (r.label || r.name || r.title || `Причина ${i + 1}`);
                const id = typeof r === 'string' ? `reason-${i}` : (r.id || `reason-${i}`);
                return { id, name };
            });
        }

        // Add default reasons if none exist
        if (discountReasons.value.length === 0) {
            discountReasons.value = [
                { id: 'birthday', name: 'День рождения' },
                { id: 'regular', name: 'Постоянный клиент' },
                { id: 'complaint', name: 'Жалоба/компенсация' },
                { id: 'manager', name: 'Скидка менеджера' },
                { id: 'staff', name: 'Сотрудник' },
                { id: 'promo', name: 'Акция ресторана' },
                { id: 'other', name: 'Другое' }
            ];
        }

    } catch (e) {
        console.error('Failed to load discount data:', e);
    } finally {
        loading.value = false;
    }
}

// Единый источник истины - вызов бекенда для расчёта скидки
// Если нет tableId/orderId (доставка), считаем локально
async function calculateDiscountFromBackend(discountPercent, discountMaxAmount = null, discountFixed = 0) {
    // Для доставки (без tableId/orderId) - локальный расчёт
    if (!props.tableId || !props.orderId) {
        const subtotalValue = props.subtotal || 0;
        let discountAmount = 0;

        if (discountFixed > 0) {
            // Фиксированная скидка
            discountAmount = Math.min(discountFixed, subtotalValue);
        } else if (discountPercent > 0) {
            // Процентная скидка
            discountAmount = Math.round(subtotalValue * discountPercent / 100);
            // Применяем максимум если задан
            if (discountMaxAmount && discountAmount > discountMaxAmount) {
                discountAmount = discountMaxAmount;
            }
        }

        return {
            success: true,
            subtotal: subtotalValue,
            discount_amount: discountAmount,
            total: subtotalValue - discountAmount
        };
    }

    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
        const response = await fetch(`/pos/table/${props.tableId}/order/${props.orderId}/discount/preview`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({
                discount_percent: discountPercent,
                discount_max_amount: discountMaxAmount,
                discount_fixed: discountFixed
            })
        });
        const data = await response.json();
        if (data.success) {
            return data;
        }
    } catch (e) {
        console.error('Failed to calculate discount:', e);
    }
    return null;
}

// Reset promo status when typing
const resetPromoStatus = () => {
    promoStatus.value = '';
    promoSuccessMessage.value = '';
    searchError.value = '';
};

// Apply methods
const applySearchQuery = async () => {
    if (!searchQuery.value || promoLoading.value) return;

    promoLoading.value = true;
    promoStatus.value = '';
    searchError.value = '';
    promoSuccessMessage.value = '';

    // Try as promo code first
    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
        const response = await authFetch('/api/loyalty/promo-codes/validate', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({
                code: searchQuery.value.toUpperCase(),
                customer_id: props.customerId,
                order_total: props.subtotal,
                order_type: props.orderType
            })
        });
        const data = await response.json();

        // Промокод найден но не прошёл валидацию - показываем причину
        if (!data.success && data.message) {
            promoStatus.value = 'error';
            searchError.value = data.message;
            promoLoading.value = false;
            setTimeout(() => {
                if (promoStatus.value === 'error') {
                    promoStatus.value = '';
                    searchError.value = '';
                }
            }, 5000); // 5 секунд чтобы успеть прочитать
            return;
        }

        if (data.success && data.data) {
            const promo = data.data.promo_code;
            const discount = data.data.discount || 0;
            const isStackable = promo?.stackable ?? true;

            // Проверяем, не применён ли уже промокод (только один промокод на заказ)
            const existingPromoCode = appliedDiscounts.value.find(d => d.sourceType === 'promo_code');
            if (existingPromoCode) {
                promoStatus.value = 'error';
                searchError.value = 'Промокод уже применён. Удалите текущий, чтобы применить другой.';
                promoLoading.value = false;
                setTimeout(() => {
                    if (promoStatus.value === 'error') {
                        promoStatus.value = '';
                        searchError.value = '';
                    }
                }, 4000);
                return;
            }

            // Check if non-stackable already applied
            if (hasNonStackableDiscount.value && !isStackable) {
                promoStatus.value = 'error';
                searchError.value = 'Уже применена несовместимая скидка';
                promoLoading.value = false;
                setTimeout(() => {
                    if (promoStatus.value === 'error') {
                        promoStatus.value = '';
                        searchError.value = '';
                    }
                }, 3000);
                return;
            }

            // Clear other discounts if this one is non-stackable
            if (!isStackable) {
                appliedDiscounts.value = appliedDiscounts.value.filter(d => d.auto);
            }

            // Проверяем тип промокода
            const giftDish = data.data.gift_dish;
            const frontendType = data.data.frontend_type;
            const isGiftPromo = !!giftDish;
            const isBonusMultiply = frontendType === 'bonus_multiply';
            const isBonusAdd = frontendType === 'bonus_add';
            const isBirthday = promo?.is_birthday_only || false;

            // Определяем название промокода
            const promoCode = searchQuery.value.toUpperCase();
            let promoName = 'Промокод ' + promoCode;
            if (isBirthday) {
                promoName = `Промокод ${promoCode} (День рождения)`;
            } else if (isGiftPromo) {
                promoName = `Подарок: ${giftDish.name}`;
            } else if (isBonusMultiply) {
                promoName = `Бонусы x${promo.value}`;
            } else if (isBonusAdd) {
                promoName = `+${promo.value} бонусов`;
            }

            // Определяем тип для отображения
            let discountType = 'promo_code';
            if (isBirthday) discountType = 'birthday';
            else if (isGiftPromo) discountType = 'gift';
            else if (isBonusMultiply) discountType = 'bonus_multiply';
            else if (isBonusAdd) discountType = 'bonus_add';

            appliedDiscounts.value.push({
                name: promoName,
                type: discountType,
                amount: parseFloat(discount) || 0,
                percent: promo?.type === 'percent' ? parseFloat(promo.value) || 0 : 0,
                maxDiscount: promo?.max_discount ? parseFloat(promo.max_discount) : null,
                stackable: isStackable,
                sourceType: 'promo_code',
                sourceId: promo?.id,
                code: promoCode,
                frontendType: frontendType,
                isBirthday: isBirthday,
                bonusMultiplier: isBonusMultiply ? parseFloat(promo.value) || 1 : null,
                bonusAdd: isBonusAdd ? parseFloat(promo.value) || 0 : null,
                // Данные о подарочном товаре
                giftDish: giftDish ? {
                    id: giftDish.id,
                    name: giftDish.name,
                    price: giftDish.price,
                    category: giftDish.category
                } : null
            });

            promoStatus.value = 'success';
            let successMsg = `Скидка ${formatPrice(discount)} применена`;
            if (isBirthday) {
                successMsg = `Скидка на день рождения ${formatPrice(discount)} применена!`;
            } else if (isGiftPromo) {
                successMsg = `Подарок "${giftDish.name}" будет добавлен`;
            } else if (isBonusMultiply) {
                successMsg = `Бонусы будут умножены на ${promo.value}`;
            } else if (isBonusAdd) {
                successMsg = `+${promo.value} бонусов будет начислено`;
            }
            promoSuccessMessage.value = successMsg;
            promoLoading.value = false;
            searchQuery.value = '';
            // Auto-reset status after 3 seconds
            setTimeout(() => {
                if (promoStatus.value === 'success') {
                    promoStatus.value = '';
                    promoSuccessMessage.value = '';
                }
            }, 3000);
            return;
        }
    } catch (e) {
        // Not a promo code, continue
    }

    // Try as certificate
    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
        const response = await authFetch('/api/gift-certificates/check', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({
                code: searchQuery.value.toUpperCase()
            })
        });
        const data = await response.json();

        if (data.success && data.data) {
            const cert = data.data;
            applyCertificate(cert);
            promoStatus.value = 'success';
            promoSuccessMessage.value = `Сертификат на ${formatPrice(cert.balance || cert.amount)} применён`;
            promoLoading.value = false;
            searchQuery.value = '';
            // Auto-reset status after 3 seconds
            setTimeout(() => {
                if (promoStatus.value === 'success') {
                    promoStatus.value = '';
                    promoSuccessMessage.value = '';
                }
            }, 3000);
            return;
        }
    } catch (e) {
        // Not a certificate
    }

    promoStatus.value = 'error';
    searchError.value = 'Промокод или сертификат не найден';
    promoLoading.value = false;
    // Auto-reset error status after 3 seconds
    setTimeout(() => {
        if (promoStatus.value === 'error') {
            promoStatus.value = '';
            searchError.value = '';
        }
    }, 3000);
};

const applyCertificate = (cert) => {
    const certValue = parseFloat(cert.balance) || parseFloat(cert.amount) || 0;
    const amount = Math.min(certValue, props.subtotal - totalDiscountAmount.value);
    if (amount <= 0) return;

    appliedDiscounts.value.push({
        name: 'Сертификат: ' + cert.code,
        type: 'certificate',
        amount: amount,
        percent: 0,
        stackable: true,
        sourceType: 'certificate',
        sourceId: cert.id,
        code: cert.code
    });
};

const applyPromotion = (promo) => {
    if (isPromoApplied(promo.id)) return;
    if (!isPromoAvailable(promo)) return;

    const discountValue = parseFloat(promo.discount_value) || 0;
    const maxDiscount = promo.max_discount ? parseFloat(promo.max_discount) : null;
    let amount = 0;
    let percent = 0;

    // Учитываем уже применённые скидки
    const currentTotal = appliedDiscounts.value.reduce((sum, d) => sum + (d.amount || 0), 0);
    const remainingAmount = props.subtotal - currentTotal;

    switch (promo.type) {
        case 'discount_percent':
            percent = discountValue;
            amount = Math.round(remainingAmount * discountValue / 100);
            // Применяем лимит
            if (maxDiscount && amount > maxDiscount) {
                amount = maxDiscount;
            }
            break;

        case 'discount_fixed':
            amount = Math.min(discountValue, remainingAmount);
            break;

        case 'progressive_discount':
            // Находим подходящий уровень
            if (promo.progressive_tiers && Array.isArray(promo.progressive_tiers)) {
                const tiers = [...promo.progressive_tiers].sort((a, b) => (b.min_amount || 0) - (a.min_amount || 0));
                for (const tier of tiers) {
                    if (props.subtotal >= (tier.min_amount || 0)) {
                        percent = tier.discount_percent || 0;
                        amount = Math.round(remainingAmount * percent / 100);
                        if (maxDiscount && amount > maxDiscount) {
                            amount = maxDiscount;
                        }
                        break;
                    }
                }
            }
            break;

        case 'gift':
            // Для подарков - скидка = 0, но добавляем информацию о подарке
            if (promo.gift_dish_id) {
                // TODO: загрузить информацию о подарочном блюде
            }
            break;

        default:
            amount = Math.round(remainingAmount * discountValue / 100);
    }

    // Не добавляем если скидка 0
    if (amount <= 0 && promo.type !== 'gift') return;

    // Проверяем stackable
    if (promo.is_exclusive || promo.stackable === false) {
        // Удаляем другие неавтоматические скидки
        appliedDiscounts.value = appliedDiscounts.value.filter(d => d.auto);
    }

    appliedDiscounts.value.push({
        name: promo.name,
        type: 'promotion',
        amount: amount,
        percent: percent,
        maxDiscount: maxDiscount,
        stackable: promo.stackable ?? true,
        sourceType: 'promotion',
        sourceId: promo.id,
        promoType: promo.type,
        // Данные для пересчёта на бэкенде
        applies_to: promo.applies_to,
        applicable_categories: promo.applicable_categories,
        applicable_dishes: promo.applicable_dishes,
        requires_all_dishes: promo.requires_all_dishes,
        excluded_categories: promo.excluded_categories,
        excluded_dishes: promo.excluded_dishes,
    });
};

const applyQuickPercent = async (pct) => {
    if (hasNonStackableDiscount.value) return;

    // Remove existing quick percent AND custom discount (only one manual discount allowed)
    appliedDiscounts.value = appliedDiscounts.value.filter(d => d.sourceType !== 'quick' && d.sourceType !== 'custom');

    // Check if needs PIN
    if (pct > settings.value.max_discount_without_pin && !pinVerified.value) {
        pendingDiscount.value = { type: 'percent', value: pct, sourceType: 'quick' };
        showPinModal.value = true;
        return;
    }

    // Расчёт через бекенд (единый источник истины)
    const result = await calculateDiscountFromBackend(pct);
    if (!result) return;

    let amount = result.discount_amount;

    // Calculate remaining available discount (учитываем другие скидки, например промокоды)
    const currentTotal = appliedDiscounts.value.reduce((sum, d) => sum + d.amount, 0);
    const maxAvailable = result.subtotal - currentTotal;
    amount = Math.min(amount, maxAvailable);

    if (amount <= 0) return;

    const reasonName = selectedReason.value?.name || '';
    const discountName = reasonName ? `${reasonName} (${pct}%)` : `Скидка ${pct}%`;

    appliedDiscounts.value.push({
        name: discountName,
        type: 'percent',
        amount: amount,
        percent: pct,
        stackable: true,
        sourceType: 'quick',
        sourceId: null,
        reason: selectedReason.value?.name
    });
};

const applyCustomDiscount = async () => {
    if (!customValue.value || hasNonStackableDiscount.value) return;

    // Remove existing manual discounts (only one allowed - either quick or custom)
    appliedDiscounts.value = appliedDiscounts.value.filter(d => d.sourceType !== 'custom' && d.sourceType !== 'quick');

    const reasonName = selectedReason.value?.name || '';
    let amount, percent, discountName;

    if (customDiscountType.value === 'percent') {
        // Percent discount - расчёт через бекенд
        percent = Math.min(customValue.value, 100);

        // Check if needs PIN
        if (percent > settings.value.max_discount_without_pin && !pinVerified.value) {
            pendingDiscount.value = { type: 'percent', value: percent, sourceType: 'custom_percent' };
            showPinModal.value = true;
            return;
        }

        const result = await calculateDiscountFromBackend(percent);
        if (!result) return;

        amount = result.discount_amount;

        // Учитываем другие скидки
        const currentTotal = appliedDiscounts.value.reduce((sum, d) => sum + d.amount, 0);
        const maxAvailable = result.subtotal - currentTotal;
        amount = Math.min(amount, maxAvailable);

        discountName = reasonName ? `${reasonName} (${percent}%)` : `Ручная ${percent}%`;
    } else {
        // Fixed amount discount - расчёт через бекенд
        const result = await calculateDiscountFromBackend(0, null, customValue.value);
        if (!result) return;

        amount = result.discount_amount;

        // Учитываем другие скидки
        const currentTotal = appliedDiscounts.value.reduce((sum, d) => sum + d.amount, 0);
        const maxAvailable = result.subtotal - currentTotal;
        amount = Math.min(amount, maxAvailable);

        if (amount <= 0) return;
        percent = 0;
        discountName = reasonName || 'Ручная скидка';
    }

    if (amount <= 0) return;

    appliedDiscounts.value.push({
        name: discountName,
        type: customDiscountType.value,
        amount: amount,
        percent: percent,
        stackable: true,
        sourceType: 'custom',
        sourceId: null,
        reason: selectedReason.value?.name
    });

    customValue.value = null;
};

const removeAppliedDiscount = (idx) => {
    const discount = appliedDiscounts.value[idx];
    if (discount.auto) return; // Can't remove auto discounts
    appliedDiscounts.value.splice(idx, 1);
};

const clearAllDiscounts = () => {
    appliedDiscounts.value = appliedDiscounts.value.filter(d => d.auto);
    pinVerified.value = false;
};

const toggleRounding = () => {
    settings.value.enable_rounding = !settings.value.enable_rounding;
};

const verifyPin = async () => {
    if (managerPin.value.length < 4) return;
    pinError.value = '';

    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
        const response = await authFetch('/api/staff/verify-pin', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({
                pin: managerPin.value,
                role: 'manager'
            })
        });
        const data = await response.json();

        if (data.success) {
            pinVerified.value = true;
            showPinModal.value = false;
            managerPin.value = '';

            // Apply pending discount
            if (pendingDiscount.value) {
                if (pendingDiscount.value.sourceType === 'quick' || pendingDiscount.value.sourceType === 'custom_percent') {
                    const pct = pendingDiscount.value.value;
                    const amount = Math.round(props.subtotal * pct / 100);
                    const reasonName = selectedReason.value?.name || '';
                    const isQuick = pendingDiscount.value.sourceType === 'quick';
                    const discountName = reasonName
                        ? `${reasonName} (${pct}%)`
                        : (isQuick ? `Скидка ${pct}%` : `Ручная ${pct}%`);
                    appliedDiscounts.value.push({
                        name: discountName,
                        type: 'percent',
                        amount: amount,
                        percent: pct,
                        stackable: true,
                        sourceType: isQuick ? 'quick' : 'custom',
                        sourceId: null,
                        reason: selectedReason.value?.name
                    });
                    customValue.value = null;
                } else {
                    appliedDiscounts.value.push({
                        name: pendingDiscount.value.name,
                        type: pendingDiscount.value.type,
                        amount: pendingDiscount.value.amount,
                        percent: pendingDiscount.value.type === 'percent' ? pendingDiscount.value.value : 0,
                        stackable: true,
                        sourceType: 'manual',
                        sourceId: pendingDiscount.value.id
                    });
                }
                pendingDiscount.value = null;
            }
        } else {
            pinError.value = data.message || 'Неверный PIN';
        }
    } catch (e) {
        pinError.value = 'Ошибка проверки PIN';
    }
};

const confirmDiscounts = () => {
    // Calculate final values
    const totalAmount = totalDiscountAmount.value;
    const totalPercent = effectiveDiscountPercent.value;
    const promoCode = appliedDiscounts.value.find(d => d.sourceType === 'promo_code')?.code || '';
    // Get max discount if any discount has it (e.g., promo code with max_discount)
    const maxDiscount = appliedDiscounts.value.find(d => d.maxDiscount)?.maxDiscount || null;

    // Get gift item if any
    const giftDiscount = appliedDiscounts.value.find(d => d.giftDish);
    const giftItem = giftDiscount?.giftDish || null;

    // Build reason from applied discounts
    const reason = appliedDiscounts.value.map(d => d.name).join(', ');

    // Собираем финальный массив скидок (включая округление)
    const finalDiscounts = [...appliedDiscounts.value];

    // Добавляем округление если есть копейки (в пользу клиента - отрицательное значение)
    if (roundingAmount.value < 0) {
        finalDiscounts.push({
            name: 'Округление',
            type: 'rounding',
            amount: Math.abs(roundingAmount.value),
            percent: 0,
            stackable: true,
            sourceType: 'rounding',
            sourceId: null,
            auto: true // Нельзя удалить
        });
    }

    emit('apply', {
        discountAmount: totalAmount,
        discountPercent: totalPercent,
        discountMaxAmount: maxDiscount,
        discountReason: reason,
        promoCode: promoCode,
        appliedDiscounts: finalDiscounts,
        giftItem: giftItem, // Подарочный товар для добавления в заказ
        bonusToSpend: confirmedBonusToSpend.value // Бонусы для списания (только подтверждённые)
    });

    close();
};

// Recalculate discounts when subtotal changes - через бекенд (единый источник истины)
watch(() => props.subtotal, async (newSubtotal) => {
    if (!props.modelValue || newSubtotal <= 0 || appliedDiscounts.value.length === 0) return;

    let runningTotal = 0;
    const updatedDiscounts = [];

    for (const discount of appliedDiscounts.value) {
        let newAmount;

        if (discount.type === 'percent' || discount.percent > 0) {
            // Пересчёт процентной скидки через бекенд
            const result = await calculateDiscountFromBackend(
                discount.percent,
                discount.maxDiscount || null
            );
            if (result) {
                newAmount = result.discount_amount;
            } else {
                // Fallback если API не отвечает
                newAmount = Math.round(newSubtotal * discount.percent / 100);
                if (discount.maxDiscount && newAmount > discount.maxDiscount) {
                    newAmount = discount.maxDiscount;
                }
            }
        } else {
            // Fixed discount - пересчёт через бекенд
            const result = await calculateDiscountFromBackend(0, null, discount.amount);
            newAmount = result ? result.discount_amount : discount.amount;
        }

        // Ensure we don't exceed subtotal
        const maxAvailable = newSubtotal - runningTotal;
        newAmount = Math.min(newAmount, maxAvailable);
        newAmount = Math.max(0, newAmount);

        runningTotal += newAmount;

        if (newAmount > 0) {
            updatedDiscounts.push({ ...discount, amount: newAmount });
        }
    }

    appliedDiscounts.value = updatedDiscounts;
});

// Load bonus settings if not provided via props
const loadBonusSettings = async () => {
    if (props.bonusSettings) return; // Уже передано через props
    try {
        const response = await authFetch('/api/loyalty/bonus-settings');
        const data = await response.json();
        if (data.success && data.data) {
            localBonusSettings.value = data.data;
        } else if (data.is_enabled !== undefined) {
            // Fallback если API вернул данные напрямую
            localBonusSettings.value = data;
        }
        console.log('Loaded bonus settings:', localBonusSettings.value);
    } catch (e) {
        console.warn('Failed to load bonus settings:', e);
    }
};

// Watch for modal open
watch(() => props.modelValue, async (val) => {
    if (val) {
        // Load bonus settings if not provided
        await loadBonusSettings();

        // Debug: check bonus props
        console.log('DiscountModal opened with:', {
            customerId: props.customerId,
            customerName: props.customerName,
            customerBonusBalance: props.customerBonusBalance,
            propsBonusSettings: props.bonusSettings,
            effectiveBonusSettings: effectiveBonusSettings.value,
            bonusEnabled: effectiveBonusSettings.value?.is_enabled
        });
        // Reset state
        appliedDiscounts.value = [];
        searchQuery.value = '';
        searchError.value = '';
        promoLoading.value = false;
        promoStatus.value = '';
        promoSuccessMessage.value = '';
        customValue.value = null;
        customDiscountType.value = settings.value.allow_custom_percent ? 'percent' : 'fixed';
        showDetails.value = false;
        pinVerified.value = false;
        managerPin.value = '';
        pinError.value = '';
        pendingDiscount.value = null;
        selectedReasonId.value = '';
        // Enterprise: инициализируем бонусы из сервера (единый источник правды)
        const savedBonus = props.currentBonusToSpend || 0;
        bonusToSpend.value = savedBonus;
        confirmedBonusToSpend.value = savedBonus;
        showBonusInput.value = savedBonus > 0; // Показываем панель если бонусы уже выбраны

        // Флаг: были ли восстановлены скидки из сохранённых данных
        let restoredFromSaved = false;

        // Restore from currentAppliedDiscounts if available (новый формат)
        if (props.currentAppliedDiscounts && props.currentAppliedDiscounts.length > 0) {
            // Фильтруем округление и записи с нулевой суммой - округление будет пересчитано автоматически
            const discountsWithoutRounding = props.currentAppliedDiscounts.filter(d =>
                d.sourceType !== 'rounding' && d.type !== 'rounding' && (d.amount > 0 || d.percent > 0)
            );
            appliedDiscounts.value = discountsWithoutRounding.map(d => ({ ...d }));

            // Если было округление - включаем настройку
            const hadRounding = props.currentAppliedDiscounts.some(d => d.sourceType === 'rounding' || d.type === 'rounding');
            if (hadRounding) {
                settings.value.enable_rounding = true;
            }

            restoredFromSaved = true;
        }
        // Fallback: restore from old format props
        else if (props.currentDiscount > 0) {
            // Проверяем, был ли применён промокод
            if (props.currentPromoCode) {
                // Восстанавливаем как промокод
                const reason = props.currentDiscountReason || '';
                const isBirthday = reason.toLowerCase().includes('день рождения') || reason.toLowerCase().includes('birthday');

                appliedDiscounts.value.push({
                    name: isBirthday ? 'День рождения' : (reason || `Промокод ${props.currentPromoCode}`),
                    type: isBirthday ? 'birthday' : 'promo_code',
                    amount: parseFloat(props.currentDiscount) || 0,
                    percent: parseFloat(props.currentDiscountPercent) || 0,
                    stackable: true,
                    sourceType: 'promo_code',
                    sourceId: null,
                    code: props.currentPromoCode,
                    isBirthday: isBirthday
                });
            } else {
                // Обычная ручная скидка
                let discountName;
                if (props.currentDiscountPercent) {
                    discountName = `Скидка ${props.currentDiscountPercent}%`;
                } else {
                    discountName = 'Скидка';
                }

                appliedDiscounts.value.push({
                    name: discountName,
                    type: props.currentDiscountPercent ? 'percent' : 'fixed',
                    amount: parseFloat(props.currentDiscount) || 0,
                    percent: parseFloat(props.currentDiscountPercent) || 0,
                    stackable: true,
                    sourceType: 'existing',
                    sourceId: null
                });
            }
        }

        await loadData(restoredFromSaved);
    }
});
</script>

<style scoped>
/* Sidebar slide animation */
.slide-enter-active {
    transition: all 0.3s ease-out;
}
.slide-leave-active {
    transition: all 0.2s ease-in;
}
.slide-enter-from .sidebar-panel,
.slide-leave-to .sidebar-panel {
    transform: translateX(100%);
}

/* Expand animation for details */
.expand-enter-active,
.expand-leave-active {
    transition: all 0.2s ease;
    overflow: hidden;
}
.expand-enter-from,
.expand-leave-to {
    opacity: 0;
    max-height: 0;
}
.expand-enter-to,
.expand-leave-from {
    max-height: 200px;
}

/* PIN modal animation */
.modal-enter-active,
.modal-leave-active {
    transition: opacity 0.2s ease;
}
.modal-enter-from,
.modal-leave-to {
    opacity: 0;
}

.bg-dark-850 {
    background-color: rgb(20, 20, 25);
}

.sidebar-panel {
    transition: transform 0.3s ease-out;
}

/* Hide number input spinners */
.no-spinners::-webkit-outer-spin-button,
.no-spinners::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}
.no-spinners {
    -moz-appearance: textfield;
}
</style>
