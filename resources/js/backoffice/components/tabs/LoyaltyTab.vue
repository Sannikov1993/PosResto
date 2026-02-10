<template>
    <div>
        <!-- Stats Cards -->
        <div class="flex flex-wrap gap-2 mb-4">
            <div class="bg-white rounded-lg shadow-sm px-3 py-2 border-l-3 border-purple-500 flex items-center gap-2">
                <span class="text-lg">🎉</span>
                <div>
                    <p class="text-xs text-purple-600">Акции</p>
                    <p class="text-lg font-bold text-purple-900 leading-tight">{{ activePromotionsCount }}</p>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow-sm px-3 py-2 border-l-3 border-green-500 flex items-center gap-2">
                <span class="text-lg">🎁</span>
                <div>
                    <p class="text-xs text-green-600">Промокоды</p>
                    <p class="text-lg font-bold text-green-900 leading-tight">{{ validPromoCodesCount }}</p>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow-sm px-3 py-2 border-l-3 border-blue-500 flex items-center gap-2">
                <span class="text-lg">⭐</span>
                <div>
                    <p class="text-xs text-blue-600">Уровни</p>
                    <p class="text-lg font-bold text-blue-900 leading-tight">{{ loyaltyLevels.length }}</p>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow-sm px-3 py-2 border-l-3 border-orange-500 flex items-center gap-2">
                <span class="text-lg">💰</span>
                <div>
                    <p class="text-xs text-orange-600">Бонусов</p>
                    <p class="text-lg font-bold text-orange-900 leading-tight">{{ formatMoney(loyaltyStats.bonusEarned || 0) }}</p>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow-sm px-3 py-2 border-l-3 border-pink-500 flex items-center gap-2">
                <span class="text-lg">🎟️</span>
                <div>
                    <p class="text-xs text-pink-600">Сертификаты</p>
                    <p class="text-lg font-bold text-pink-900 leading-tight">{{ activeCertificatesCount }}</p>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="bg-white rounded-xl shadow-sm mb-6 overflow-hidden">
            <div class="flex border-b bg-gray-50">
                <button @click="activeTab = 'promotions'"
                        :class="['px-6 py-4 text-sm font-medium border-b-2 -mb-px flex items-center gap-2 transition',
                                 activeTab === 'promotions' ? 'text-orange-500 border-orange-500 bg-white' : 'text-gray-500 border-transparent hover:text-gray-700']">
                    <span>🎉</span> Акции
                </button>
                <button @click="activeTab = 'promo'"
                        :class="['px-6 py-4 text-sm font-medium border-b-2 -mb-px flex items-center gap-2 transition',
                                 activeTab === 'promo' ? 'text-orange-500 border-orange-500 bg-white' : 'text-gray-500 border-transparent hover:text-gray-700']">
                    <span>🎁</span> Промокоды
                </button>
                <button @click="activeTab = 'levels'"
                        :class="['px-6 py-4 text-sm font-medium border-b-2 -mb-px flex items-center gap-2 transition',
                                 activeTab === 'levels' ? 'text-orange-500 border-orange-500 bg-white' : 'text-gray-500 border-transparent hover:text-gray-700']">
                    <span>⭐</span> Уровни
                </button>
                <button @click="activeTab = 'bonuses'"
                        :class="['px-6 py-4 text-sm font-medium border-b-2 -mb-px flex items-center gap-2 transition',
                                 activeTab === 'bonuses' ? 'text-orange-500 border-orange-500 bg-white' : 'text-gray-500 border-transparent hover:text-gray-700']">
                    <span>💰</span> Бонусы
                </button>
                <button @click="activeTab = 'certificates'"
                        :class="['px-6 py-4 text-sm font-medium border-b-2 -mb-px flex items-center gap-2 transition',
                                 activeTab === 'certificates' ? 'text-orange-500 border-orange-500 bg-white' : 'text-gray-500 border-transparent hover:text-gray-700']">
                    <span>🎟️</span> Сертификаты
                </button>
                <button @click="activeTab = 'discounts'"
                        :class="['px-6 py-4 text-sm font-medium border-b-2 -mb-px flex items-center gap-2 transition',
                                 activeTab === 'discounts' ? 'text-orange-500 border-orange-500 bg-white' : 'text-gray-500 border-transparent hover:text-gray-700']">
                    <span>🏷️</span> Скидки
                </button>
            </div>
        </div>

        <!-- PROMOTIONS TAB -->
        <div v-if="activeTab === 'promotions'">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-semibold">Акции и спецпредложения</h3>
                <button @click="openPromotionModal()" class="px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white rounded-lg font-medium transition">
                    + Создать акцию
                </button>
            </div>

            <div v-if="promotions.length === 0" class="bg-white rounded-xl shadow-sm p-12 text-center">
                <div class="text-6xl mb-4">🎉</div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">Нет акций</h3>
                <p class="text-gray-500 mb-4">Создайте первую акцию для привлечения клиентов</p>
                <button @click="openPromotionModal()" class="px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white rounded-lg font-medium transition">
                    + Создать акцию
                </button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div v-for="promo in promotions" :key="promo.id"
                     class="bg-white rounded-xl shadow-sm overflow-hidden hover:shadow-lg transition group relative">
                    <!-- Status Badge -->
                    <div class="absolute top-3 right-3 z-10">
                        <span :class="['px-2 py-1 rounded-full text-xs font-medium', promo.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600']">
                            {{ promo.is_active ? 'Активна' : 'Неактивна' }}
                        </span>
                    </div>

                    <!-- Image/Icon -->
                    <div class="h-32 bg-gradient-to-br from-orange-400 to-red-500 flex items-center justify-center relative">
                        <span class="text-5xl">{{ getPromotionIcon(promo.type) }}</span>
                        <div v-if="promo.is_featured" class="absolute top-2 left-2 px-2 py-0.5 bg-yellow-400 text-yellow-900 text-xs font-bold rounded">
                            Featured
                        </div>
                    </div>

                    <!-- Content -->
                    <div class="p-4">
                        <div class="flex items-start justify-between mb-2">
                            <div>
                                <h4 class="font-semibold text-gray-900">{{ promo.name }}</h4>
                                <span class="text-xs text-gray-500">{{ getPromotionTypeLabel(promo.type) }}</span>
                            </div>
                            <div class="text-right">
                                <div class="text-xl font-bold text-orange-500">
                                    <template v-if="promo.type === 'progressive_discount'">
                                        {{ getProgressiveRange(promo) }}
                                    </template>
                                    <template v-else-if="promo.type === 'discount_percent'">
                                        {{ promo.discount_value }}%
                                    </template>
                                    <template v-else-if="promo.type === 'free_delivery'">
                                        Бесплатно
                                    </template>
                                    <template v-else>
                                        {{ formatMoney(promo.discount_value) }}
                                    </template>
                                </div>
                            </div>
                        </div>

                        <p class="text-sm text-gray-600 line-clamp-2 mb-3">{{ promo.description || 'Без описания' }}</p>

                        <!-- Conditions -->
                        <div class="flex flex-wrap gap-1 mb-3">
                            <span v-if="promo.min_order_amount" class="px-2 py-0.5 bg-gray-100 text-gray-600 text-xs rounded">
                                От {{ formatMoney(promo.min_order_amount) }}
                            </span>
                            <span v-if="promo.ends_at" class="px-2 py-0.5 bg-blue-100 text-blue-600 text-xs rounded">
                                До {{ formatDate(promo.ends_at) }}
                            </span>
                            <span v-if="promo.usage_limit" class="px-2 py-0.5 bg-purple-100 text-purple-600 text-xs rounded">
                                {{ promo.usage_count || 0 }}/{{ promo.usage_limit }}
                            </span>
                        </div>

                        <!-- Actions -->
                        <div class="flex gap-2 pt-3 border-t">
                            <button @click="togglePromotion(promo)"
                                    :class="['flex-1 py-2 text-sm font-medium rounded-lg transition', promo.is_active ? 'bg-gray-100 text-gray-600 hover:bg-gray-200' : 'bg-green-100 text-green-700 hover:bg-green-200']">
                                {{ promo.is_active ? 'Отключить' : 'Включить' }}
                            </button>
                            <button @click="openPromotionModal(promo)" class="px-4 py-2 bg-orange-100 text-orange-600 rounded-lg hover:bg-orange-200 transition">
                                ✏️
                            </button>
                            <button @click="handleDeletePromotion(promo.id)" class="px-4 py-2 text-red-500 hover:bg-red-50 rounded-lg transition">
                                🗑️
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- PROMO CODES TAB -->
        <div v-if="activeTab === 'promo'">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-semibold">Промокоды</h3>
                <button @click="openPromoCodeModal()" class="px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white rounded-lg font-medium transition">
                    + Создать промокод
                </button>
            </div>

            <div v-if="promoCodes.length === 0" class="bg-white rounded-xl shadow-sm p-12 text-center">
                <div class="text-6xl mb-4">🎁</div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">Нет промокодов</h3>
                <p class="text-gray-500 mb-4">Создайте промокод для клиентов</p>
                <button @click="openPromoCodeModal()" class="px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white rounded-lg font-medium transition">
                    + Создать промокод
                </button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div v-for="code in promoCodes" :key="code.id"
                     class="bg-white rounded-xl shadow-sm p-5 hover:shadow-lg transition group relative">
                    <!-- Status indicator -->
                    <div class="absolute top-3 right-3">
                        <span :class="['w-3 h-3 rounded-full inline-block', code.is_valid ? 'bg-green-500' : 'bg-gray-400']"></span>
                    </div>

                    <!-- Code -->
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-12 h-12 bg-gradient-to-br from-green-400 to-teal-500 rounded-xl flex items-center justify-center text-white text-xl">
                            🎁
                        </div>
                        <div>
                            <div class="font-mono text-xl font-bold text-gray-900">{{ code.code }}</div>
                            <div class="text-sm text-gray-500">{{ code.name }}</div>
                        </div>
                    </div>

                    <!-- Value -->
                    <div class="bg-gray-50 rounded-lg p-3 mb-4">
                        <div class="flex items-center justify-between">
                            <span class="text-gray-600">Скидка</span>
                            <span class="text-lg font-bold text-green-600">
                                {{ ['percent', 'discount_percent'].includes(code.type) ? (code.discount_value || code.value) + '%' : formatMoney(code.discount_value || code.value) }}
                            </span>
                        </div>
                        <div v-if="code.min_order_amount" class="flex items-center justify-between mt-1 text-sm">
                            <span class="text-gray-500">Мин. заказ</span>
                            <span class="text-gray-700">{{ formatMoney(code.min_order_amount) }}</span>
                        </div>
                    </div>

                    <!-- Stats -->
                    <div class="grid grid-cols-2 gap-3 mb-4 text-center">
                        <div class="bg-blue-50 rounded-lg p-2">
                            <div class="text-lg font-bold text-blue-600">{{ code.usage_count || 0 }}</div>
                            <div class="text-xs text-blue-500">Использовано</div>
                        </div>
                        <div class="bg-purple-50 rounded-lg p-2">
                            <div class="text-lg font-bold text-purple-600">{{ code.usage_limit || '∞' }}</div>
                            <div class="text-xs text-purple-500">Лимит</div>
                        </div>
                    </div>

                    <!-- Dates -->
                    <div class="text-sm text-gray-500 mb-4">
                        <div v-if="code.ends_at || code.expires_at" class="flex items-center gap-1">
                            <span>📅</span> До {{ formatDate(code.ends_at || code.expires_at) }}
                        </div>
                        <div v-else class="flex items-center gap-1">
                            <span>♾️</span> Бессрочный
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex gap-2 pt-3 border-t opacity-0 group-hover:opacity-100 transition">
                        <button @click="copyPromoCode(code.code)" class="flex-1 py-2 text-sm bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                            📋 Копировать
                        </button>
                        <button @click="openPromoCodeModal(code)" class="px-3 py-2 bg-orange-100 text-orange-600 rounded-lg hover:bg-orange-200 transition">
                            ✏️
                        </button>
                        <button v-can="'loyalty.delete'" @click="deletePromoCode(code.id)" class="px-3 py-2 text-red-500 hover:bg-red-50 rounded-lg transition">
                            🗑️
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- LEVELS TAB -->
        <div v-if="activeTab === 'levels'">
            <div class="flex items-center justify-between mb-6">
                <div class="flex items-center gap-4">
                    <h3 class="text-lg font-semibold">Уровни лояльности</h3>
                    <!-- Toggle для включения/выключения уровней -->
                    <label class="flex items-center gap-2 cursor-pointer">
                        <div class="relative">
                            <input type="checkbox" v-model="levelsEnabled" @change="toggleLevelsEnabled" class="sr-only">
                            <div :class="['w-11 h-6 rounded-full transition-colors', levelsEnabled ? 'bg-green-500' : 'bg-gray-300']"></div>
                            <div :class="['absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform', levelsEnabled ? 'translate-x-5' : '']"></div>
                        </div>
                        <span :class="['text-sm font-medium', levelsEnabled ? 'text-green-600' : 'text-gray-500']">
                            {{ levelsEnabled ? 'Включено' : 'Выключено' }}
                        </span>
                    </label>
                </div>
                <div class="flex items-center gap-2">
                    <button @click="recalculateLevels" :disabled="!levelsEnabled || recalculating" class="px-4 py-2 bg-blue-500 hover:bg-blue-600 disabled:bg-gray-300 disabled:cursor-not-allowed text-white rounded-lg font-medium transition flex items-center gap-2">
                        <svg v-if="recalculating" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        {{ recalculating ? 'Пересчёт...' : '🔄 Пересчитать' }}
                    </button>
                    <button @click="openLevelModal()" :disabled="!levelsEnabled" class="px-4 py-2 bg-orange-500 hover:bg-orange-600 disabled:bg-gray-300 disabled:cursor-not-allowed text-white rounded-lg font-medium transition">
                        + Добавить уровень
                    </button>
                </div>
            </div>

            <div v-if="loyaltyLevels.length === 0" class="bg-white rounded-xl shadow-sm p-12 text-center">
                <div class="text-6xl mb-4">⭐</div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">Нет уровней</h3>
                <p class="text-gray-500 mb-4">Настройте уровни лояльности для клиентов</p>
                <button @click="openLevelModal()" class="px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white rounded-lg font-medium transition">
                    + Добавить уровень
                </button>
            </div>

            <div :class="['grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 transition-opacity', !levelsEnabled && 'opacity-50 pointer-events-none']">
                <div v-for="(level, index) in loyaltyLevels" :key="level.id"
                     class="bg-white rounded-xl shadow-sm p-5 hover:shadow-lg transition relative overflow-hidden group cursor-pointer"
                     @click="openLevelModal(level)">
                    <!-- Decorative gradient -->
                    <div class="absolute top-0 right-0 w-24 h-24 opacity-10 rounded-full transform translate-x-8 -translate-y-8"
                         :style="{background: level.color || '#6366f1'}"></div>

                    <div class="relative">
                        <!-- Icon & Name -->
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-12 h-12 rounded-xl flex items-center justify-center text-2xl"
                                 :style="{background: (level.color || '#6366f1') + '20'}">
                                {{ level.icon || '⭐' }}
                            </div>
                            <div>
                                <h4 class="font-bold text-gray-900">{{ level.name }}</h4>
                                <span class="text-sm text-gray-500">Уровень {{ index + 1 }}</span>
                            </div>
                        </div>

                        <!-- Requirements -->
                        <div class="bg-gray-50 rounded-lg p-3 mb-4">
                            <div class="text-sm text-gray-500">Для получения</div>
                            <div class="font-semibold text-gray-900">Покупки от {{ formatMoney(level.min_total || level.min_spent || 0) }}</div>
                        </div>

                        <!-- Benefits -->
                        <div class="space-y-2">
                            <div v-if="level.cashback_percent" class="flex items-center gap-2 text-sm">
                                <span class="w-6 h-6 bg-green-100 rounded-full flex items-center justify-center text-xs">💰</span>
                                <span>Кешбэк {{ level.cashback_percent }}%</span>
                            </div>
                            <div v-if="level.discount_percent" class="flex items-center gap-2 text-sm">
                                <span class="w-6 h-6 bg-orange-100 rounded-full flex items-center justify-center text-xs">🏷️</span>
                                <span>Скидка {{ level.discount_percent }}%</span>
                            </div>
                            <div v-if="level.bonus_multiplier > 1" class="flex items-center gap-2 text-sm">
                                <span class="w-6 h-6 bg-purple-100 rounded-full flex items-center justify-center text-xs">✖️</span>
                                <span>Бонусы x{{ level.bonus_multiplier }}</span>
                            </div>
                            <div v-if="level.birthday_bonus" class="flex items-center gap-2 text-sm">
                                <span class="w-6 h-6 bg-pink-100 rounded-full flex items-center justify-center text-xs">🎂</span>
                                <span>Бонус в ДР {{ level.birthday_discount || 0 }}%</span>
                            </div>
                        </div>

                        <!-- Customers count -->
                        <div class="mt-4 pt-3 border-t flex items-center justify-between text-sm">
                            <span class="text-gray-500">Клиентов</span>
                            <span class="font-semibold" :style="{color: level.color || '#6366f1'}">{{ level.customers_count || 0 }}</span>
                        </div>

                        <!-- Edit icon indicator -->
                        <div class="absolute top-3 right-3 p-2 text-gray-400 group-hover:text-orange-500 transition">
                            ✏️
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- BONUSES TAB -->
        <div v-if="activeTab === 'bonuses'">
            <!-- Настройки бонусной системы -->
            <div class="mb-8">
                <div class="flex items-center justify-between mb-6">
                    <div class="flex items-center gap-4">
                        <h3 class="text-lg font-semibold">Настройки бонусной системы</h3>
                        <!-- Toggle для включения/выключения -->
                        <label class="flex items-center gap-2 cursor-pointer">
                            <div class="relative">
                                <input type="checkbox" v-model="bonusSettings.is_enabled" class="sr-only">
                                <div :class="['w-11 h-6 rounded-full transition-colors', bonusSettings.is_enabled ? 'bg-green-500' : 'bg-gray-300']"></div>
                                <div :class="['absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform', bonusSettings.is_enabled ? 'translate-x-5' : '']"></div>
                            </div>
                            <span :class="['text-sm font-medium', bonusSettings.is_enabled ? 'text-green-600' : 'text-gray-500']">
                                {{ bonusSettings.is_enabled ? 'Включено' : 'Выключено' }}
                            </span>
                        </label>
                    </div>
                    <button @click="saveSettings"
                            :disabled="savingSettings"
                            class="px-4 py-2 bg-orange-500 hover:bg-orange-600 disabled:bg-gray-300 text-white rounded-lg font-medium transition flex items-center gap-2">
                        <span v-if="savingSettings">Сохранение...</span>
                        <span v-else>Сохранить</span>
                    </button>
                </div>

                <!-- Информационный блок с примером -->
                <div v-if="bonusSettings.is_enabled" class="bg-gradient-to-r from-orange-50 to-yellow-50 rounded-xl p-5 mb-6 border border-orange-200">
                    <div class="flex items-start gap-4">
                        <div class="text-3xl">💡</div>
                        <div class="flex-1">
                            <h4 class="font-semibold text-gray-800 mb-2">Как это работает для клиента</h4>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                                <div class="bg-white/60 rounded-lg p-3">
                                    <div class="text-gray-500 mb-1">Заказ на 1000₽</div>
                                    <div class="font-semibold text-green-600">
                                        +{{ Math.round(1000 * (bonusSettings.earn_rate || 0) / 100) }} {{ bonusSettings.currency_name || 'бонусов' }}
                                    </div>
                                    <div class="text-xs text-gray-400">кэшбэк {{ bonusSettings.earn_rate || 0 }}%</div>
                                </div>
                                <div class="bg-white/60 rounded-lg p-3">
                                    <div class="text-gray-500 mb-1">Можно списать</div>
                                    <div class="font-semibold text-orange-600">
                                        до {{ Math.round(1000 * (bonusSettings.spend_rate || 0) / 100) }} {{ bonusSettings.currency_name || 'бонусов' }}
                                    </div>
                                    <div class="text-xs text-gray-400">максимум {{ bonusSettings.spend_rate || 0 }}% от заказа</div>
                                </div>
                                <div class="bg-white/60 rounded-lg p-3">
                                    <div class="text-gray-500 mb-1">Курс списания</div>
                                    <div class="font-semibold text-blue-600">
                                        1 бонус = {{ bonusSettings.bonus_to_ruble || 1 }}₽
                                    </div>
                                    <div class="text-xs text-gray-400">при оплате заказа</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            <div :class="['grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 transition-opacity', !bonusSettings.is_enabled && 'opacity-50 pointer-events-none']">
                <!-- Начисление бонусов -->
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <h4 class="font-semibold text-gray-800 mb-4 flex items-center gap-2">
                        <span class="text-2xl">💰</span> Начисление бонусов
                    </h4>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Базовый кэшбэк</label>
                            <div class="flex items-center gap-2">
                                <input v-model.number="bonusSettings.earn_rate" type="number" min="0" max="100" step="0.5"
                                       class="w-24 px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 text-center text-lg font-semibold">
                                <span class="text-gray-600">% от суммы заказа</span>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">При заказе на 1000₽ клиент получит <b>{{ Math.round(1000 * (bonusSettings.earn_rate || 0) / 100) }}</b> бонусов. Уровни лояльности могут увеличить этот %</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Мин. сумма для начисления</label>
                            <div class="flex items-center gap-2">
                                <input v-model.number="bonusSettings.min_order_for_earn" type="number" min="0"
                                       class="w-32 px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 text-center text-lg font-semibold">
                                <span class="text-gray-600">₽</span>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">Бонусы начисляются только при заказе от этой суммы</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Округление бонусов</label>
                            <div class="flex items-center gap-2">
                                <select v-model.number="bonusSettings.earn_rounding"
                                        class="px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500">
                                    <option :value="1">До 1 (без округления)</option>
                                    <option :value="5">До 5</option>
                                    <option :value="10">До 10</option>
                                </select>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">Округление начисленных бонусов</p>
                        </div>
                    </div>
                </div>

                <!-- Использование бонусов -->
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <h4 class="font-semibold text-gray-800 mb-4 flex items-center gap-2">
                        <span class="text-2xl">🛒</span> Списание бонусов
                    </h4>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Максимум оплаты бонусами</label>
                            <div class="flex items-center gap-2">
                                <input v-model.number="bonusSettings.spend_rate" type="number" min="0" max="100"
                                       class="w-24 px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 text-center text-lg font-semibold">
                                <span class="text-gray-600">% от суммы заказа</span>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">При заказе на 1000₽ можно списать максимум <b>{{ Math.round(1000 * (bonusSettings.spend_rate || 0) / 100) }}</b> бонусов</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Мин. сумма для списания</label>
                            <div class="flex items-center gap-2">
                                <input v-model.number="bonusSettings.min_spend_amount" type="number" min="0"
                                       class="w-32 px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 text-center text-lg font-semibold">
                                <span class="text-gray-600">₽</span>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">Бонусы можно списать только при заказе от этой суммы</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Курс списания</label>
                            <div class="flex items-center gap-2">
                                <span class="text-gray-600">1 бонус =</span>
                                <input v-model.number="bonusSettings.bonus_to_ruble" type="number" min="0.01" step="0.01"
                                       class="w-20 px-3 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 text-center">
                                <span class="text-gray-600">₽</span>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">Сколько рублей стоит 1 бонус при списании</p>
                        </div>
                    </div>
                </div>

                <!-- Срок действия и уведомления -->
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <h4 class="font-semibold text-gray-800 mb-4 flex items-center gap-2">
                        <span class="text-2xl">⏰</span> Срок действия
                    </h4>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Срок действия бонусов</label>
                            <div class="flex items-center gap-2">
                                <input v-model.number="bonusSettings.expiry_days" type="number" min="0"
                                       class="w-24 px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 text-center text-lg font-semibold">
                                <span class="text-gray-600">дней</span>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">0 = бессрочно. Бонусы сгорают через указанное количество дней</p>
                        </div>
                        <div class="flex items-center gap-3 pt-2">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" v-model="bonusSettings.notify_before_expiry"
                                       class="w-5 h-5 rounded text-orange-500 focus:ring-orange-500">
                                <span class="text-sm">Уведомлять о сгорании бонусов</span>
                            </label>
                        </div>
                        <div v-if="bonusSettings.notify_before_expiry">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Уведомлять за</label>
                            <div class="flex items-center gap-2">
                                <input v-model.number="bonusSettings.notify_days_before" type="number" min="1" max="30"
                                       class="w-20 px-3 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 text-center">
                                <span class="text-gray-600">дней до сгорания</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Приветственные бонусы -->
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <h4 class="font-semibold text-gray-800 mb-4 flex items-center gap-2">
                        <span class="text-2xl">🎁</span> Приветственные бонусы
                    </h4>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Бонус за регистрацию</label>
                            <div class="flex items-center gap-2">
                                <input v-model.number="bonusSettings.registration_bonus" type="number" min="0"
                                       class="w-32 px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 text-center text-lg font-semibold">
                                <span class="text-gray-600">бонусов</span>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">Начисляется при создании карты клиента</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Бонус ко дню рождения</label>
                            <div class="flex items-center gap-2">
                                <input v-model.number="bonusSettings.birthday_bonus" type="number" min="0"
                                       class="w-32 px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 text-center text-lg font-semibold">
                                <span class="text-gray-600">бонусов</span>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">Начисляется автоматически в день рождения</p>
                        </div>
                    </div>
                </div>

                <!-- Реферальная программа -->
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <h4 class="font-semibold text-gray-800 mb-4 flex items-center gap-2">
                        <span class="text-2xl">👥</span> Реферальная программа
                    </h4>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Бонус пригласившему</label>
                            <div class="flex items-center gap-2">
                                <input v-model.number="bonusSettings.referral_bonus" type="number" min="0"
                                       class="w-32 px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 text-center text-lg font-semibold">
                                <span class="text-gray-600">бонусов</span>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">Получает клиент, который привёл друга</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Бонус приглашённому</label>
                            <div class="flex items-center gap-2">
                                <input v-model.number="bonusSettings.referral_friend_bonus" type="number" min="0"
                                       class="w-32 px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 text-center text-lg font-semibold">
                                <span class="text-gray-600">бонусов</span>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">Получает новый клиент, которого пригласили</p>
                        </div>
                    </div>
                </div>

                <!-- Общие настройки -->
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <h4 class="font-semibold text-gray-800 mb-4 flex items-center gap-2">
                        <span class="text-2xl">⚙️</span> Отображение
                    </h4>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Название бонусов</label>
                            <input v-model="bonusSettings.currency_name" type="text" placeholder="бонусов"
                                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500">
                            <p class="text-xs text-gray-500 mt-1">Как отображать: "100 бонусов", "100 баллов", "100 звёзд"</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Символ</label>
                            <input v-model="bonusSettings.currency_symbol" type="text" placeholder="B" maxlength="5"
                                   class="w-20 px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 text-center">
                            <p class="text-xs text-gray-500 mt-1">Короткое обозначение (B, ★, ₿)</p>
                        </div>
                    </div>
                </div>
            </div>
            </div>

            <!-- История бонусов -->
            <div class="mt-8">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-lg font-semibold">История начисления бонусов</h3>
                    <div class="flex gap-2">
                        <select v-model="bonusFilter" class="px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                            <option value="">Все операции</option>
                            <option value="earn">Начисления</option>
                            <option value="spend">Списания</option>
                            <option value="expire">Сгорания</option>
                        </select>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr class="text-left text-sm text-gray-500">
                                <th class="px-4 py-3 font-medium">Дата</th>
                                <th class="px-4 py-3 font-medium">Клиент</th>
                                <th class="px-4 py-3 font-medium">Тип</th>
                                <th class="px-4 py-3 font-medium">Сумма</th>
                                <th class="px-4 py-3 font-medium">Баланс</th>
                                <th class="px-4 py-3 font-medium">Описание</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="tx in filteredBonusTransactions" :key="tx.id" class="hover:bg-gray-50">
                                <td class="px-4 py-3 text-sm text-gray-500">{{ formatDateTime(tx.created_at) }}</td>
                                <td class="px-4 py-3">
                                    <div class="font-medium">{{ tx.customer?.name || 'Клиент #' + tx.customer_id }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <span :class="['px-2 py-1 rounded-full text-xs font-medium', getBonusTypeBadge(tx.type)]">
                                        {{ getBonusTypeIcon(tx.type) }} {{ getBonusTypeLabel(tx.type) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <span :class="['font-bold', tx.amount >= 0 ? 'text-green-600' : 'text-red-600']">
                                        {{ tx.amount >= 0 ? '+' : '' }}{{ tx.amount }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-gray-600">{{ tx.balance_after }}</td>
                                <td class="px-4 py-3 text-sm text-gray-500">{{ tx.description || '-' }}</td>
                            </tr>
                            <tr v-if="!filteredBonusTransactions.length">
                                <td colspan="6" class="px-4 py-8 text-center text-gray-400">Нет операций</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- CERTIFICATES TAB -->
        <div v-if="activeTab === 'certificates'">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-semibold">Подарочные сертификаты</h3>
                <button @click="openCertificateModal()" class="px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white rounded-lg font-medium transition">
                    + Создать сертификат
                </button>
            </div>

            <!-- Certificate Stats -->
            <div class="grid grid-cols-4 gap-4 mb-6">
                <div class="bg-white rounded-xl shadow-sm p-4">
                    <div class="text-2xl font-bold text-pink-600">{{ certificateStats.total_count || 0 }}</div>
                    <div class="text-sm text-gray-500">Всего</div>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-4">
                    <div class="text-2xl font-bold text-green-600">{{ certificateStats.active_count || 0 }}</div>
                    <div class="text-sm text-gray-500">Активных</div>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-4">
                    <div class="text-2xl font-bold text-blue-600">{{ formatMoney(certificateStats.total_sold || 0) }}</div>
                    <div class="text-sm text-gray-500">Продано</div>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-4">
                    <div class="text-2xl font-bold text-orange-600">{{ formatMoney(certificateStats.total_balance || 0) }}</div>
                    <div class="text-sm text-gray-500">Остаток</div>
                </div>
            </div>

            <!-- Filter & Search -->
            <div class="flex items-center gap-4 mb-6">
                <input v-model="certificateSearch" type="text" placeholder="Поиск по коду или имени..."
                       class="flex-1 max-w-sm px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500">
                <select v-model="certificateFilter" class="px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500">
                    <option value="">Все статусы</option>
                    <option value="active">Активные</option>
                    <option value="used">Использованные</option>
                    <option value="expired">Истёкшие</option>
                    <option value="cancelled">Отменённые</option>
                </select>
            </div>

            <!-- Certificates List -->
            <div v-if="filteredCertificates.length === 0" class="bg-white rounded-xl shadow-sm p-12 text-center">
                <div class="text-6xl mb-4">🎟️</div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">Нет сертификатов</h3>
                <p class="text-gray-500 mb-4">Создайте первый подарочный сертификат</p>
                <button @click="openCertificateModal()" class="px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white rounded-lg font-medium transition">
                    + Создать сертификат
                </button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div v-for="cert in filteredCertificates" :key="cert.id"
                     class="bg-white rounded-xl shadow-sm p-5 hover:shadow-lg transition relative group">
                    <!-- Status Badge -->
                    <div class="absolute top-3 right-3">
                        <span :class="['px-2 py-1 rounded-full text-xs font-medium', getCertificateStatusClass(cert.status)]">
                            {{ getCertificateStatusLabel(cert.status) }}
                        </span>
                    </div>

                    <!-- Code -->
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-12 h-12 bg-gradient-to-br from-pink-400 to-purple-500 rounded-xl flex items-center justify-center text-white text-xl">
                            🎟️
                        </div>
                        <div>
                            <div class="font-mono text-lg font-bold text-gray-900">{{ cert.code }}</div>
                            <div class="text-sm text-gray-500">
                                <span v-if="cert.recipient_name">{{ cert.recipient_name }}</span>
                                <span v-else-if="cert.buyer_name">От: {{ cert.buyer_name }}</span>
                                <span v-else>Без имени</span>
                            </div>
                        </div>
                    </div>

                    <!-- Amount & Balance -->
                    <div class="bg-gray-50 rounded-lg p-3 mb-4">
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-gray-600">Номинал</span>
                            <span class="font-semibold text-gray-900">{{ formatMoney(cert.amount) }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-gray-600">Остаток</span>
                            <span :class="['font-bold', cert.balance > 0 ? 'text-green-600' : 'text-gray-400']">
                                {{ formatMoney(cert.balance) }}
                            </span>
                        </div>
                        <!-- Progress bar -->
                        <div class="mt-2 h-1.5 bg-gray-200 rounded-full overflow-hidden">
                            <div class="h-full bg-green-500 rounded-full transition-all"
                                 :style="{ width: ((cert.balance / cert.amount) * 100) + '%' }"></div>
                        </div>
                    </div>

                    <!-- Info -->
                    <div class="text-sm text-gray-500 space-y-1 mb-4">
                        <div v-if="cert.expires_at" class="flex items-center gap-1">
                            <span>📅</span> До {{ formatDate(cert.expires_at) }}
                        </div>
                        <div v-else class="flex items-center gap-1">
                            <span>♾️</span> Бессрочный
                        </div>
                        <div v-if="cert.sold_at" class="flex items-center gap-1">
                            <span>🛒</span> Продан {{ formatDate(cert.sold_at) }}
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex gap-2 pt-3 border-t opacity-0 group-hover:opacity-100 transition">
                        <button @click="copyCertificateCode(cert.code)" class="flex-1 py-2 text-sm bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                            📋 Код
                        </button>
                        <button @click="viewCertificateHistory(cert)" class="px-3 py-2 bg-blue-100 text-blue-600 rounded-lg hover:bg-blue-200 transition">
                            📜
                        </button>
                        <button v-if="cert.status === 'active'" v-can="'loyalty.edit'" @click="cancelCertificate(cert)"
                                class="px-3 py-2 text-red-500 hover:bg-red-50 rounded-lg transition">
                            ❌
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Certificate Create Modal -->
        <Teleport to="body">
            <div v-if="showCertificateModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" @click.self="showCertificateModal = false">
                <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg max-h-[90vh] overflow-hidden flex flex-col">
                    <div class="px-6 py-4 border-b">
                        <h3 class="text-lg font-semibold">Новый подарочный сертификат</h3>
                    </div>
                    <div class="p-6 space-y-4 overflow-y-auto">
                        <!-- Amount -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Номинал *</label>
                            <div class="flex gap-2">
                                <button v-for="amt in [500, 1000, 2000, 3000, 5000]" :key="amt"
                                        @click="certificateForm.amount = amt"
                                        :class="['px-4 py-2 rounded-lg border font-medium transition',
                                                 certificateForm.amount === amt ? 'bg-orange-500 text-white border-orange-500' : 'bg-white hover:bg-gray-50']">
                                    {{ formatMoney(amt) }}
                                </button>
                            </div>
                            <input v-model.number="certificateForm.amount" type="number" min="100" max="100000" placeholder="Или введите сумму"
                                   class="mt-2 w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500">
                        </div>

                        <!-- Buyer -->
                        <div class="bg-gray-50 rounded-lg p-4">
                            <h4 class="font-medium text-gray-700 mb-3">Покупатель</h4>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-sm text-gray-600 mb-1">Имя</label>
                                    <input v-model="certificateForm.buyer_name" type="text" placeholder="Иван Иванов"
                                           class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500">
                                </div>
                                <div>
                                    <label class="block text-sm text-gray-600 mb-1">Телефон</label>
                                    <input v-model="certificateForm.buyer_phone" type="tel" placeholder="+7"
                                           class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500">
                                </div>
                            </div>
                        </div>

                        <!-- Recipient -->
                        <div class="bg-gray-50 rounded-lg p-4">
                            <h4 class="font-medium text-gray-700 mb-3">Получатель (опционально)</h4>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-sm text-gray-600 mb-1">Имя</label>
                                    <input v-model="certificateForm.recipient_name" type="text" placeholder="Мария"
                                           class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500">
                                </div>
                                <div>
                                    <label class="block text-sm text-gray-600 mb-1">Телефон</label>
                                    <input v-model="certificateForm.recipient_phone" type="tel" placeholder="+7"
                                           class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500">
                                </div>
                            </div>
                        </div>

                        <!-- Payment & Expiry -->
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Способ оплаты *</label>
                                <select v-model="certificateForm.payment_method"
                                        class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500">
                                    <option value="cash">Наличные</option>
                                    <option value="card">Карта</option>
                                    <option value="online">Онлайн</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Срок действия</label>
                                <input v-model="certificateForm.expires_at" type="date"
                                       class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500">
                            </div>
                        </div>

                        <!-- Notes -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Примечание</label>
                            <textarea v-model="certificateForm.notes" rows="2"
                                      class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500"
                                      placeholder="День рождения, корпоратив..."></textarea>
                        </div>
                    </div>
                    <div class="px-6 py-4 border-t flex gap-3">
                        <button @click="showCertificateModal = false"
                                class="flex-1 px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg font-medium transition">
                            Отмена
                        </button>
                        <button @click="saveCertificate"
                                :disabled="!certificateForm.amount || certificateForm.amount < 100"
                                class="flex-1 px-4 py-2 bg-orange-500 hover:bg-orange-600 disabled:bg-gray-300 text-white rounded-lg font-medium transition">
                            Создать
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- DISCOUNTS TAB (Manual Discount Settings for POS) -->
        <div v-if="activeTab === 'discounts'">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-semibold">Настройки ручных скидок (POS)</h3>
                <button @click="saveDiscountSettings"
                        :disabled="savingDiscountSettings"
                        class="px-4 py-2 bg-orange-500 hover:bg-orange-600 disabled:bg-gray-300 text-white rounded-lg font-medium transition flex items-center gap-2">
                    <span v-if="savingDiscountSettings">Сохранение...</span>
                    <span v-else>Сохранить</span>
                </button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Быстрые кнопки скидок -->
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <h4 class="font-semibold text-gray-800 mb-4 flex items-center gap-2">
                        <span class="text-2xl">⚡</span> Быстрые кнопки скидок
                    </h4>
                    <p class="text-sm text-gray-500 mb-4">Кнопки для быстрого выбора скидки в POS</p>

                    <div class="flex flex-wrap gap-2 mb-4">
                        <div v-for="(pct, index) in discountSettings.preset_percentages" :key="index"
                             class="flex items-center gap-1 px-3 py-2 bg-orange-100 text-orange-700 rounded-lg font-medium">
                            <span>{{ pct }}%</span>
                            <button @click="removePresetPercent(index)" class="ml-1 text-orange-500 hover:text-red-500">
                                ×
                            </button>
                        </div>
                        <div class="flex items-center gap-1">
                            <input v-model.number="newPresetPercent" type="number" min="1" max="100" placeholder="%"
                                   class="w-16 px-2 py-2 border rounded-lg text-center text-sm focus:ring-2 focus:ring-orange-500"
                                   @keyup.enter="addPresetPercent">
                            <button @click="addPresetPercent"
                                    :disabled="!newPresetPercent || newPresetPercent < 1 || newPresetPercent > 100"
                                    class="px-3 py-2 bg-green-500 hover:bg-green-600 disabled:bg-gray-300 text-white rounded-lg text-sm font-medium">
                                +
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Лимит без подтверждения -->
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <h4 class="font-semibold text-gray-800 mb-4 flex items-center gap-2">
                        <span class="text-2xl">🔐</span> Подтверждение менеджера
                    </h4>
                    <p class="text-sm text-gray-500 mb-4">Скидка больше этого значения требует PIN менеджера</p>

                    <div class="flex items-center gap-3">
                        <span class="text-gray-600">Макс. скидка без PIN:</span>
                        <input v-model.number="discountSettings.max_discount_without_pin" type="number" min="0" max="100"
                               class="w-20 px-3 py-2 border rounded-lg text-center text-lg font-semibold focus:ring-2 focus:ring-orange-500">
                        <span class="text-gray-600 text-lg font-semibold">%</span>
                    </div>
                    <p class="text-xs text-gray-400 mt-2">0 = всегда требовать PIN, 100 = никогда не требовать</p>
                </div>

                <!-- Опции -->
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <h4 class="font-semibold text-gray-800 mb-4 flex items-center gap-2">
                        <span class="text-2xl">⚙️</span> Опции
                    </h4>

                    <div class="space-y-4">
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="checkbox" v-model="discountSettings.allow_custom_percent"
                                   class="w-5 h-5 rounded text-orange-500 focus:ring-orange-500">
                            <div>
                                <span class="font-medium">Разрешить произвольный %</span>
                                <p class="text-xs text-gray-500">Ввод любого процента скидки вручную</p>
                            </div>
                        </label>

                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="checkbox" v-model="discountSettings.allow_fixed_amount"
                                   class="w-5 h-5 rounded text-orange-500 focus:ring-orange-500">
                            <div>
                                <span class="font-medium">Разрешить фикс. сумму</span>
                                <p class="text-xs text-gray-500">Скидка фиксированной суммой в рублях</p>
                            </div>
                        </label>

                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="checkbox" v-model="discountSettings.require_reason"
                                   class="w-5 h-5 rounded text-orange-500 focus:ring-orange-500">
                            <div>
                                <span class="font-medium">Причина обязательна</span>
                                <p class="text-xs text-gray-500">Нельзя применить скидку без указания причины</p>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Причины скидок -->
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <h4 class="font-semibold text-gray-800 mb-4 flex items-center gap-2">
                        <span class="text-2xl">📝</span> Причины скидок
                    </h4>
                    <p class="text-sm text-gray-500 mb-4">Список причин для выбора при оформлении скидки</p>

                    <div class="space-y-2 mb-4">
                        <div v-for="(reason, index) in discountSettings.reasons" :key="reason.id"
                             class="flex items-center gap-2 p-2 bg-gray-50 rounded-lg group">
                            <input v-model="reason.label" type="text"
                                   class="flex-1 px-2 py-1 border border-transparent rounded focus:border-orange-300 focus:ring-1 focus:ring-orange-500 bg-transparent"
                                   placeholder="Название причины">
                            <button @click="removeReason(index)"
                                    class="p-1 text-gray-400 hover:text-red-500 opacity-0 group-hover:opacity-100 transition">
                                🗑️
                            </button>
                        </div>
                    </div>

                    <div class="flex gap-2">
                        <input v-model="newReasonLabel" type="text" placeholder="Новая причина"
                               class="flex-1 px-3 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500"
                               @keyup.enter="addReason">
                        <button @click="addReason"
                                :disabled="!newReasonLabel"
                                class="px-4 py-2 bg-green-500 hover:bg-green-600 disabled:bg-gray-300 text-white rounded-lg font-medium">
                            + Добавить
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Certificate History Modal -->
        <Teleport to="body">
            <div v-if="showCertificateHistoryModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" @click.self="showCertificateHistoryModal = false">
                <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg max-h-[90vh] overflow-hidden flex flex-col">
                    <div class="px-6 py-4 border-b flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-semibold">История сертификата</h3>
                            <p class="text-sm text-gray-500 font-mono">{{ selectedCertificate?.code }}</p>
                        </div>
                        <button @click="showCertificateHistoryModal = false" class="text-gray-400 hover:text-gray-600 text-xl">&times;</button>
                    </div>
                    <div class="p-6 overflow-y-auto">
                        <div v-if="selectedCertificate?.usages?.length === 0" class="text-center py-8 text-gray-400">
                            Сертификат ещё не использовался
                        </div>
                        <div v-else class="space-y-3">
                            <div v-for="usage in selectedCertificate?.usages" :key="usage.id"
                                 class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                <div>
                                    <div class="font-medium">-{{ formatMoney(usage.amount) }}</div>
                                    <div class="text-sm text-gray-500 flex items-center gap-2">
                                        {{ formatDateTime(usage.created_at) }}
                                        <span v-if="usage.order">
                                            • Заказ #{{ usage.order.order_number || usage.order.daily_number }}
                                            <span v-if="usage.order.type" :class="[
                                                'ml-1 px-1.5 py-0.5 text-[10px] rounded font-medium',
                                                usage.order.type === 'delivery' ? 'bg-orange-100 text-orange-600' :
                                                usage.order.type === 'pickup' ? 'bg-purple-100 text-purple-600' :
                                                'bg-emerald-100 text-emerald-600'
                                            ]">
                                                {{ usage.order.type === 'delivery' ? 'Доставка' : usage.order.type === 'pickup' ? 'Самовывоз' : 'Зал' }}
                                            </span>
                                        </span>
                                    </div>
                                </div>
                                <div class="text-sm text-gray-400">
                                    Остаток: {{ formatMoney(usage.balance_after) }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Promotion Form Modal (Presto-style) -->
        <PromotionFormModal
            :show="showPromotionModal"
            :promotion="currentPromotion"
            :categories="categories"
            :dishes="dishes"
            :zones="zones"
            :loyaltyLevels="loyaltyLevels"
            @close="showPromotionModal = false; currentPromotion = null"
            @save="handleSavePromotion"
            @delete="handleDeletePromotion"
        />

        <!-- Promo Code Modal -->
        <PromoCodeFormModal
            :show="showPromoCodeModal"
            :promoCode="currentPromoCode"
            :loyaltyLevels="loyaltyLevels"
            @close="showPromoCodeModal = false; currentPromoCode = null"
            @save="handleSavePromoCode"
            @delete="handleDeletePromoCode"
        />

        <!-- Level Modal -->
        <Teleport to="body">
            <div v-if="showLevelModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" @click.self="showLevelModal = false">
                <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg max-h-[90vh] overflow-hidden flex flex-col">
                    <div class="px-6 py-4 border-b">
                        <h3 class="text-lg font-semibold">{{ levelForm.id ? 'Редактировать уровень' : 'Новый уровень' }}</h3>
                    </div>
                    <div class="p-6 space-y-4 overflow-y-auto">
                        <div class="grid grid-cols-3 gap-4">
                            <div class="col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Название *</label>
                                <input v-model="levelForm.name" type="text" placeholder="Золотой"
                                       class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Иконка</label>
                                <select v-model="levelForm.icon"
                                        class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                                    <option v-for="icon in ['⭐', '🥉', '🥈', '🥇', '💎', '👑', '🏆']" :key="icon" :value="icon">{{ icon }}</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Мин. сумма покупок</label>
                            <input v-model.number="levelForm.min_total" type="number" min="0"
                                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Цвет</label>
                            <input v-model="levelForm.color" type="color" class="w-full h-10 rounded-lg border cursor-pointer">
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Кешбэк %</label>
                                <input v-model.number="levelForm.cashback_percent" type="number" min="0" max="100"
                                       class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Скидка %</label>
                                <input v-model.number="levelForm.discount_percent" type="number" min="0" max="100"
                                       class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Множитель бонусов</label>
                                <input v-model.number="levelForm.bonus_multiplier" type="number" min="1" step="0.1"
                                       class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Бонус в ДР %</label>
                                <input v-model.number="levelForm.birthday_discount" type="number" min="0" max="100"
                                       class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                            </div>
                        </div>
                    </div>
                    <div class="px-6 py-4 border-t flex gap-3">
                        <button @click="showLevelModal = false"
                                class="flex-1 px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg font-medium transition">
                            Отмена
                        </button>
                        <button v-if="levelForm.id"
                                v-can="'loyalty.delete'"
                                @click="deleteLevel"
                                class="px-4 py-2 text-red-600 hover:bg-red-50 rounded-lg font-medium transition">
                            Удалить
                        </button>
                        <button @click="saveLevel"
                                :disabled="!levelForm.name"
                                class="flex-1 px-4 py-2 bg-orange-500 hover:bg-orange-600 disabled:bg-gray-300 text-white rounded-lg font-medium transition">
                            Сохранить
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useBackofficeStore } from '../../stores/backoffice';
import ProgressiveTiersEditor from '../ProgressiveTiersEditor.vue';
import PromotionFormModal from '../PromotionFormModal.vue';
import PromoCodeFormModal from '../PromoCodeFormModal.vue';

const store = useBackofficeStore();

// State
const activeTab = ref('promotions');
const bonusFilter = ref('');

const promotions = ref<any[]>([]);
const promoCodes = ref<any[]>([]);
const loyaltyLevels = ref<any[]>([]);
const bonusTransactions = ref<any[]>([]);
const loyaltyStats = ref({ bonusEarned: 0, bonusSpent: 0 });

// Bonus settings (maps to bonus_settings table)
const bonusSettings = ref({
    is_enabled: true,
    earn_rate: 5,
    spend_rate: 50,
    expiry_days: 365,
    currency_name: 'бонусов',
    currency_symbol: 'B',
    bonus_to_ruble: 1,
    registration_bonus: 0,
    referral_bonus: 0,
    referral_friend_bonus: 0,
    birthday_bonus: 0,
    min_order_for_earn: 0,
    min_spend_amount: 0,
    earn_rounding: 1,
    notify_before_expiry: true,
    notify_days_before: 7,
});
const savingSettings = ref(false);

// Manual discount settings (for POS)
const discountSettings = ref({
    preset_percentages: [5, 10, 15, 20],
    max_discount_without_pin: 20,
    allow_custom_percent: true,
    allow_fixed_amount: true,
    require_reason: false,
    reasons: [
        { id: 'birthday', label: 'День рождения' },
        { id: 'regular', label: 'Постоянный клиент' },
        { id: 'complaint', label: 'Жалоба/компенсация' },
        { id: 'manager', label: 'Скидка менеджера' },
        { id: 'staff', label: 'Сотрудник' },
        { id: 'promo', label: 'Акция ресторана' },
        { id: 'other', label: 'Другое' },
    ],
});
const savingDiscountSettings = ref(false);
const newPresetPercent = ref<any>(null);
const newReasonLabel = ref('');

// Reference data for promotion form
const categories = ref<any[]>([]);
const dishes = ref<any[]>([]);
const zones = ref<any[]>([]);

// Modals
const showPromotionModal = ref(false);
const showPromoCodeModal = ref(false);
const showLevelModal = ref(false);

// Current promotion being edited
const currentPromotion = ref<any>(null);

// Current promo code being edited
const currentPromoCode = ref<any>(null);

const levelForm = ref({
    id: null, name: '', icon: '⭐', color: '#6366f1',
    min_total: 0, cashback_percent: 0, discount_percent: 0,
    bonus_multiplier: 1, birthday_discount: 0
});

// Включены ли уровни лояльности
const levelsEnabled = ref(true);
const recalculating = ref(false);

// Gift certificates state
const certificates = ref<any[]>([]);
const certificateStats = ref({ total_count: 0, active_count: 0, total_sold: 0, total_balance: 0 });
const certificateSearch = ref('');
const certificateFilter = ref('');
const showCertificateModal = ref(false);
const showCertificateHistoryModal = ref(false);
const selectedCertificate = ref<any>(null);
const certificateForm = ref({
    amount: 1000,
    buyer_name: '',
    buyer_phone: '',
    recipient_name: '',
    recipient_phone: '',
    payment_method: 'cash',
    expires_at: '',
    notes: ''
});

// Computed
const activePromotionsCount = computed(() => promotions.value.filter((p: any) => p.is_active).length);
const validPromoCodesCount = computed(() => promoCodes.value.filter((p: any) => p.is_valid !== false).length);
const activeCertificatesCount = computed(() => certificates.value.filter((c: any) => c.status === 'active').length);

const filteredCertificates = computed(() => {
    let list = certificates.value;
    if (certificateFilter.value) {
        list = list.filter((c: any) => c.status === certificateFilter.value);
    }
    if (certificateSearch.value) {
        const s = certificateSearch.value.toLowerCase();
        list = list.filter((c: any) =>
            c.code?.toLowerCase().includes(s) ||
            c.buyer_name?.toLowerCase().includes(s) ||
            c.recipient_name?.toLowerCase().includes(s) ||
            c.buyer_phone?.includes(s)
        );
    }
    return list;
});

const filteredBonusTransactions = computed(() => {
    if (!bonusFilter.value) return bonusTransactions.value;
    return bonusTransactions.value.filter((tx: any) => tx.type === bonusFilter.value);
});

// Methods
function formatMoney(val: any) {
    return new Intl.NumberFormat('ru-RU', { style: 'currency', currency: 'RUB', maximumFractionDigits: 0 }).format(val || 0);
}

function formatDate(date: any) {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('ru-RU');
}

function formatDateTime(date: any) {
    if (!date) return '-';
    return new Date(date).toLocaleString('ru-RU', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function getPromotionIcon(type: any) {
    const icons: Record<string, string> = {
        discount_percent: '🏷️',
        discount_fixed: '💰',
        progressive_discount: '📈',
        bonus_multiplier: '✖️',
        free_item: '🎁',
        buy_x_get_y: '🎁',
        free_delivery: '🚚',
        gift: '🎁',
        combo: '🍔',
        happy_hour: '⏰',
        first_order: '🆕',
        birthday: '🎂'
    };
    return icons[type] || '🎉';
}

function getPromotionTypeLabel(type: any) {
    const labels: Record<string, string> = {
        discount_percent: 'Скидка %',
        discount_fixed: 'Скидка ₽',
        progressive_discount: 'Прогрессивная',
        bonus_multiplier: 'Бонусы',
        free_item: 'Подарок',
        buy_x_get_y: 'Купи X получи Y',
        free_delivery: 'Бесп. доставка',
        gift: 'Подарок',
        combo: 'Комбо',
        happy_hour: 'Happy Hour',
        first_order: 'Первый заказ',
        birthday: 'День рождения'
    };
    return labels[type] || type;
}

function getProgressiveRange(promo: any) {
    if (!promo.progressive_tiers || promo.progressive_tiers.length === 0) {
        return '—';
    }
    const tiers = [...promo.progressive_tiers].sort((a: any, b: any) => a.min_amount - b.min_amount);
    const minPercent = tiers[0]?.discount_percent || 0;
    const maxPercent = tiers[tiers.length - 1]?.discount_percent || 0;
    if (minPercent === maxPercent) {
        return `${minPercent}%`;
    }
    return `${minPercent}-${maxPercent}%`;
}

function getBonusTypeBadge(type: any) {
    const badges: Record<string, string> = { earn: 'bg-green-100 text-green-700', spend: 'bg-orange-100 text-orange-700', expire: 'bg-gray-100 text-gray-700' };
    return badges[type] || 'bg-gray-100 text-gray-700';
}

function getBonusTypeIcon(type: any) {
    const icons: Record<string, string> = { earn: '💰', spend: '🛒', expire: '⏰' };
    return icons[type] || '💰';
}

function getBonusTypeLabel(type: any) {
    const labels: Record<string, string> = { earn: 'Начисление', spend: 'Списание', expire: 'Сгорание' };
    return labels[type] || type;
}

async function loadLoyalty() {
    try {
        const [promoRes, codesRes, levelsRes, transRes, statsRes, bonusSettingsRes, loyaltySettingsRes] = await Promise.all([
            store.api('/loyalty/promotions'),
            store.api('/loyalty/promo-codes'),
            store.api('/loyalty/levels'),
            store.api('/loyalty/transactions'),
            store.api('/loyalty/stats'),
            store.api('/loyalty/bonus-settings'),
            store.api('/loyalty/settings').catch(() => ({ data: {} }))
        ]) as Record<string, any>[];

        promotions.value = promoRes.data || promoRes || [];
        promoCodes.value = codesRes.data || codesRes || [];
        loyaltyLevels.value = levelsRes.data || levelsRes || [];
        bonusTransactions.value = transRes.data || transRes || [];
        loyaltyStats.value = statsRes.data || statsRes || { bonusEarned: 0 };

        // Загрузка настройки levels_enabled
        const loyaltySettings = loyaltySettingsRes?.data || loyaltySettingsRes || {} as Record<string, any>;
        levelsEnabled.value = loyaltySettings.levels_enabled !== '0' && loyaltySettings.levels_enabled !== false;

        // Load bonus settings from structured table
        const settings = bonusSettingsRes.data || bonusSettingsRes || {} as Record<string, any>;
        bonusSettings.value = {
            is_enabled: settings.is_enabled ?? true,
            earn_rate: parseFloat(settings.earn_rate) || 5,
            spend_rate: parseFloat(settings.spend_rate) || 50,
            expiry_days: parseInt(settings.expiry_days) || 0,
            currency_name: settings.currency_name || 'бонусов',
            currency_symbol: settings.currency_symbol || 'B',
            bonus_to_ruble: parseFloat(settings.bonus_to_ruble) || 1,
            registration_bonus: parseInt(settings.registration_bonus) || 0,
            referral_bonus: parseInt(settings.referral_bonus) || 0,
            referral_friend_bonus: parseInt(settings.referral_friend_bonus) || 0,
            birthday_bonus: parseInt(settings.birthday_bonus) || 0,
            min_order_for_earn: parseFloat(settings.min_order_for_earn) || 0,
            min_spend_amount: parseFloat(settings.min_spend_amount) || 0,
            earn_rounding: parseInt(settings.earn_rounding) || 1,
            notify_before_expiry: settings.notify_before_expiry ?? true,
            notify_days_before: parseInt(settings.notify_days_before) || 7,
        };
    } catch (e: any) {
        console.error('Failed to load loyalty:', e);
        loadMockData();
    }
}

async function saveSettings() {
    savingSettings.value = true;
    try {
        await store.api('/loyalty/bonus-settings', {
            method: 'PUT',
            body: JSON.stringify(bonusSettings.value)
        });
        store.showToast('Настройки бонусов сохранены', 'success');
    } catch (e: any) {
        store.showToast('Ошибка сохранения настроек', 'error');
    } finally {
        savingSettings.value = false;
    }
}

// ============ Discount Settings Methods ============
async function loadDiscountSettings() {
    try {
        const response = await store.api('/settings/manual-discounts') as Record<string, any>;
        if (response.success && response.data) {
            discountSettings.value = response.data;
        }
    } catch (e: any) {
        console.error('Error loading discount settings:', e);
    }
}

async function saveDiscountSettings() {
    savingDiscountSettings.value = true;
    try {
        await store.api('/settings/manual-discounts', {
            method: 'PUT',
            body: JSON.stringify(discountSettings.value)
        });
        store.showToast('Настройки скидок сохранены', 'success');
    } catch (e: any) {
        store.showToast('Ошибка сохранения настроек скидок', 'error');
    } finally {
        savingDiscountSettings.value = false;
    }
}

function addPresetPercent() {
    if (!newPresetPercent.value || newPresetPercent.value < 1 || newPresetPercent.value > 100) return;
    if (discountSettings.value.preset_percentages.includes(newPresetPercent.value)) {
        store.showToast('Такой процент уже добавлен', 'error');
        return;
    }
    discountSettings.value.preset_percentages.push(newPresetPercent.value);
    discountSettings.value.preset_percentages.sort((a: any, b: any) => a - b);
    newPresetPercent.value = null;
}

function removePresetPercent(index: any) {
    discountSettings.value.preset_percentages.splice(index, 1);
}

function addReason() {
    if (!newReasonLabel.value) return;
    const id = newReasonLabel.value.toLowerCase().replace(/\s+/g, '_').replace(/[^a-zа-я0-9_]/g, '');
    if (discountSettings.value.reasons.some((r: any) => r.id === id)) {
        store.showToast('Причина с таким ID уже существует', 'error');
        return;
    }
    discountSettings.value.reasons.push({ id, label: newReasonLabel.value });
    newReasonLabel.value = '';
}

function removeReason(index: any) {
    discountSettings.value.reasons.splice(index, 1);
}

function loadMockData() {
    promotions.value = [
        { id: 1, name: 'Скидка 20% на пиццу', type: 'discount_percent', discount_value: 20, is_active: true, is_featured: true, description: 'Скидка на все пиццы', ends_at: '2024-02-01', usage_count: 45, usage_limit: 100 },
        { id: 2, name: 'Двойные бонусы', type: 'bonus_multiplier', discount_value: 2, is_active: true, description: 'Получите x2 бонусов за заказ' }
    ];

    promoCodes.value = [
        { id: 1, code: 'WELCOME10', name: 'Скидка новичкам', type: 'discount_percent', discount_value: 10, is_valid: true, usage_count: 25, usage_limit: 100, min_order_amount: 500 },
        { id: 2, code: 'PIZZA500', name: 'Скидка 500р', type: 'discount_fixed', discount_value: 500, is_valid: true, usage_count: 12, min_order_amount: 1500, ends_at: '2024-03-01' }
    ];

    loyaltyLevels.value = [
        { id: 1, name: 'Бронзовый', icon: '🥉', color: '#cd7f32', min_total: 0, cashback_percent: 3, customers_count: 245 },
        { id: 2, name: 'Серебряный', icon: '🥈', color: '#c0c0c0', min_total: 10000, cashback_percent: 5, discount_percent: 5, customers_count: 89 },
        { id: 3, name: 'Золотой', icon: '🥇', color: '#ffd700', min_total: 30000, cashback_percent: 7, discount_percent: 10, bonus_multiplier: 1.5, customers_count: 34 },
        { id: 4, name: 'Платиновый', icon: '💎', color: '#e5e4e2', min_total: 100000, cashback_percent: 10, discount_percent: 15, bonus_multiplier: 2, birthday_bonus: true, birthday_discount: 20, customers_count: 8 }
    ];

    bonusTransactions.value = [
        { id: 1, customer_id: 1, customer: { name: 'Иван Петров' }, type: 'earn', amount: 150, balance_after: 1250, description: 'Заказ #1234', created_at: '2024-01-20T14:30:00' },
        { id: 2, customer_id: 2, customer: { name: 'Мария Сидорова' }, type: 'spend', amount: -500, balance_after: 300, description: 'Оплата бонусами', created_at: '2024-01-20T12:15:00' },
        { id: 3, customer_id: 1, customer: { name: 'Иван Петров' }, type: 'earn', amount: 200, balance_after: 1100, description: 'Заказ #1233', created_at: '2024-01-19T18:45:00' }
    ];

    loyaltyStats.value = { bonusEarned: 125000, bonusSpent: 87500 };
}

function openPromotionModal(promo: any = null) {
    currentPromotion.value = promo ? { ...promo } : null;
    showPromotionModal.value = true;
}

async function handleSavePromotion(formData: any) {
    try {
        if (formData.id) {
            await store.api(`/loyalty/promotions/${formData.id}`, {
                method: 'PUT', body: JSON.stringify(formData)
            });
        } else {
            await store.api('/loyalty/promotions', {
                method: 'POST', body: JSON.stringify(formData)
            });
        }
        showPromotionModal.value = false;
        currentPromotion.value = null;
        store.showToast('Акция сохранена', 'success');
        loadLoyalty();
    } catch (e: any) {
        store.showToast('Ошибка сохранения: ' + (e.message || 'Неизвестная ошибка'), 'error');
    }
}

async function handleDeletePromotion(id: any) {
    if (!confirm('Удалить акцию?')) return;
    try {
        await store.api(`/loyalty/promotions/${id}`, { method: 'DELETE' });
        promotions.value = promotions.value.filter((p: any) => p.id !== id);
        showPromotionModal.value = false;
        currentPromotion.value = null;
        store.showToast('Акция удалена', 'success');
    } catch (e: any) {
        store.showToast('Ошибка удаления', 'error');
    }
}

// Load reference data for promotion form
async function loadReferenceData() {
    try {
        const [categoriesRes, dishesRes, zonesRes] = await Promise.all([
            store.api('/backoffice/menu/categories').catch(() => ({ data: [] })),
            store.api('/backoffice/menu/dishes?include_variants=true').catch(() => ({ data: [] })),
            store.api('/backoffice/zones').catch(() => ({ data: [] })),
        ]) as Record<string, any>[];
        categories.value = categoriesRes?.data || categoriesRes || [];
        dishes.value = dishesRes?.data || dishesRes || [];
        zones.value = zonesRes?.data || zonesRes || [];
    } catch (e: any) {
        console.error('Failed to load reference data:', e);
    }
}

async function togglePromotion(promo: any) {
    try {
        await store.api(`/loyalty/promotions/${promo.id}`, {
            method: 'PUT', body: JSON.stringify({ ...promo, is_active: !promo.is_active })
        });
        promo.is_active = !promo.is_active;
        store.showToast(promo.is_active ? 'Акция включена' : 'Акция отключена', 'success');
    } catch (e: any) {
        store.showToast('Ошибка', 'error');
    }
}

function openPromoCodeModal(code: any = null) {
    currentPromoCode.value = code ? { ...code } : null;
    showPromoCodeModal.value = true;
}

async function handleSavePromoCode(formData: any) {
    try {
        if (formData.id) {
            await store.api(`/loyalty/promo-codes/${formData.id}`, {
                method: 'PUT', body: JSON.stringify(formData)
            });
        } else {
            await store.api('/loyalty/promo-codes', {
                method: 'POST', body: JSON.stringify(formData)
            });
        }
        showPromoCodeModal.value = false;
        currentPromoCode.value = null;
        store.showToast('Промокод сохранён', 'success');
        loadLoyalty();
    } catch (e: any) {
        store.showToast('Ошибка сохранения', 'error');
    }
}

async function handleDeletePromoCode(id: any) {
    if (!confirm('Удалить промокод?')) return;
    try {
        await store.api(`/loyalty/promo-codes/${id}`, { method: 'DELETE' });
        promoCodes.value = promoCodes.value.filter((p: any) => p.id !== id);
        showPromoCodeModal.value = false;
        currentPromoCode.value = null;
        store.showToast('Промокод удалён', 'success');
    } catch (e: any) {
        store.showToast('Ошибка удаления', 'error');
    }
}

async function deletePromoCode(id: any) {
    await handleDeletePromoCode(id);
}

function copyPromoCode(code: any) {
    navigator.clipboard.writeText(code);
    store.showToast('Код скопирован', 'success');
}

function openLevelModal(level: any = null) {
    if (level) {
        levelForm.value = { ...level };
    } else {
        levelForm.value = {
            id: null, name: '', icon: '⭐', color: '#6366f1',
            min_total: 0, cashback_percent: 0, discount_percent: 0,
            bonus_multiplier: 1, birthday_discount: 0
        };
    }
    showLevelModal.value = true;
}

async function saveLevel() {
    try {
        if (levelForm.value.id) {
            await store.api(`/loyalty/levels/${levelForm.value.id}`, {
                method: 'PUT', body: JSON.stringify(levelForm.value)
            });
        } else {
            await store.api('/loyalty/levels', {
                method: 'POST', body: JSON.stringify(levelForm.value)
            });
        }
        showLevelModal.value = false;
        store.showToast('Уровень сохранён', 'success');
        loadLoyalty();
    } catch (e: any) {
        store.showToast('Ошибка сохранения', 'error');
    }
}

async function deleteLevel() {
    if (!levelForm.value.id) return;
    if (!confirm('Удалить уровень? Клиенты с этим уровнем останутся без уровня.')) return;

    try {
        await store.api(`/loyalty/levels/${levelForm.value.id}`, { method: 'DELETE' });
        showLevelModal.value = false;
        store.showToast('Уровень удалён', 'success');
        loadLoyalty();
    } catch (e: any) {
        store.showToast(e.message || 'Ошибка удаления', 'error');
    }
}

async function toggleLevelsEnabled() {
    try {
        await store.api('/loyalty/settings', {
            method: 'PUT',
            body: JSON.stringify({ levels_enabled: levelsEnabled.value })
        });
        store.showToast(levelsEnabled.value ? 'Уровни включены' : 'Уровни отключены', 'success');
    } catch (e: any) {
        store.showToast('Ошибка сохранения', 'error');
        levelsEnabled.value = !levelsEnabled.value; // откатываем
    }
}

async function recalculateLevels() {
    recalculating.value = true;
    try {
        const res = await store.api('/loyalty/levels/recalculate', { method: 'POST' });
        store.showToast(res.message || `Обновлено клиентов: ${res.updated}`, 'success');
        loadLoyalty(); // перезагрузить данные
    } catch (e: any) {
        store.showToast('Ошибка пересчёта', 'error');
    } finally {
        recalculating.value = false;
    }
}

// ==================== CERTIFICATES ====================

async function loadCertificates() {
    try {
        const [certsRes, statsRes] = await Promise.all([
            store.api('/gift-certificates'),
            store.api('/gift-certificates/stats')
        ]) as Record<string, any>[];
        certificates.value = certsRes?.data || certsRes || [];
        certificateStats.value = statsRes?.data || statsRes || { total_count: 0, active_count: 0, total_sold: 0, total_balance: 0 };
    } catch (e: any) {
        console.error('Failed to load certificates:', e);
        certificates.value = [];
    }
}

function getCertificateStatusClass(status: any) {
    const classes: Record<string, string> = {
        active: 'bg-green-100 text-green-700',
        pending: 'bg-yellow-100 text-yellow-700',
        used: 'bg-gray-100 text-gray-600',
        expired: 'bg-orange-100 text-orange-700',
        cancelled: 'bg-red-100 text-red-700'
    };
    return classes[status] || 'bg-gray-100 text-gray-600';
}

function getCertificateStatusLabel(status: any) {
    const labels: Record<string, string> = {
        active: 'Активен',
        pending: 'Ожидает',
        used: 'Использован',
        expired: 'Истёк',
        cancelled: 'Отменён'
    };
    return labels[status] || status;
}

function openCertificateModal() {
    certificateForm.value = {
        amount: 1000,
        buyer_name: '',
        buyer_phone: '',
        recipient_name: '',
        recipient_phone: '',
        payment_method: 'cash',
        expires_at: '',
        notes: ''
    };
    showCertificateModal.value = true;
}

async function saveCertificate() {
    if (!certificateForm.value.amount || certificateForm.value.amount < 100) {
        store.showToast('Укажите сумму от 100 руб.', 'error');
        return;
    }

    try {
        await store.api('/gift-certificates', {
            method: 'POST',
            body: JSON.stringify({
                ...certificateForm.value,
                activate: true
            })
        });
        showCertificateModal.value = false;
        store.showToast('Сертификат создан', 'success');
        loadCertificates();
    } catch (e: any) {
        store.showToast('Ошибка создания сертификата', 'error');
    }
}

function copyCertificateCode(code: any) {
    navigator.clipboard.writeText(code);
    store.showToast('Код скопирован', 'success');
}

async function viewCertificateHistory(cert: any) {
    try {
        const res = await store.api(`/gift-certificates/${cert.id}`);
        selectedCertificate.value = res?.data || res;
        showCertificateHistoryModal.value = true;
    } catch (e: any) {
        store.showToast('Ошибка загрузки', 'error');
    }
}

async function cancelCertificate(cert: any) {
    if (!confirm(`Отменить сертификат ${cert.code}?`)) return;
    try {
        await store.api(`/gift-certificates/${cert.id}/cancel`, { method: 'POST' });
        store.showToast('Сертификат отменён', 'success');
        loadCertificates();
    } catch (e: any) {
        store.showToast('Ошибка отмены', 'error');
    }
}

// Init
onMounted(() => {
    loadLoyalty();
    loadReferenceData();
    loadCertificates();
    loadDiscountSettings();
});
</script>
