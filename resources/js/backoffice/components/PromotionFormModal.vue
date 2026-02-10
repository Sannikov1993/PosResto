<template>
    <Teleport to="body">
        <div v-if="show" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" @click.self="$emit('close')">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-4xl max-h-[95vh] overflow-hidden flex flex-col">
                <!-- Header -->
                <div class="px-6 py-4 border-b bg-gradient-to-r from-orange-500 to-red-500 text-white flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <span class="text-2xl">{{ form.id ? '✏️' : '🎉' }}</span>
                        <div>
                            <input
                                v-model="form.name"
                                type="text"
                                placeholder="Введите название акции"
                                class="bg-transparent border-b border-white/50 focus:border-white outline-none text-lg font-semibold placeholder-white/70 w-64"
                            />
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <span class="text-sm">Используется</span>
                            <input type="checkbox" v-model="form.is_active" class="w-5 h-5 rounded text-orange-500">
                        </label>
                        <button @click="$emit('close')" class="p-2 hover:bg-white/20 rounded-lg transition">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Body -->
                <div class="flex flex-1 overflow-hidden">
                    <!-- Left Panel - Main Settings -->
                    <div class="flex-1 p-6 overflow-y-auto space-y-6">
                        <!-- Тип применения -->
                        <div class="bg-gray-50 rounded-xl p-4 border border-gray-200">
                            <h4 class="text-gray-700 font-semibold mb-3 flex items-center gap-2">
                                <span>⚡</span> Как применяется?
                            </h4>
                            <div class="flex gap-3">
                                <button @click="form.is_automatic = true"
                                        :class="['flex-1 px-4 py-3 rounded-xl font-medium transition border-2 text-center',
                                                 form.is_automatic ? 'bg-green-100 border-green-500 text-green-700' : 'bg-white border-gray-200 hover:border-green-300 text-gray-600']">
                                    <span class="block text-lg">⚡</span>
                                    <span class="block text-sm">Автоматически</span>
                                    <span class="block text-xs text-gray-500 mt-1">Применяется сама</span>
                                </button>
                                <button @click="form.is_automatic = false"
                                        :class="['flex-1 px-4 py-3 rounded-xl font-medium transition border-2 text-center',
                                                 !form.is_automatic ? 'bg-purple-100 border-purple-500 text-purple-700' : 'bg-white border-gray-200 hover:border-purple-300 text-gray-600']">
                                    <span class="block text-lg">👆</span>
                                    <span class="block text-sm">По выбору</span>
                                    <span class="block text-xs text-gray-500 mt-1">Клиент выбирает</span>
                                </button>
                            </div>
                            <p v-if="!form.is_automatic" class="mt-2 text-xs text-purple-600">
                                Акция будет показана клиенту как опция, которую можно выбрать
                            </p>
                        </div>

                        <!-- Что получит клиент? -->
                        <div class="bg-orange-50 rounded-xl p-4 border border-orange-200">
                            <h4 class="text-orange-600 font-semibold mb-3 flex items-center gap-2">
                                <span>🎁</span> Что получит клиент?
                            </h4>

                            <!-- Тип скидки -->
                            <div class="grid grid-cols-3 gap-2 mb-4">
                                <button
                                    v-for="(label, key) in rewardTypes"
                                    :key="key"
                                    @click="form.type = key === 'bonus' ? 'bonus' : ((key as any) === 'discount_percent' ? 'discount_percent' : form.type); form.reward_type = key"
                                    :class="[
                                        'px-3 py-2 rounded-lg text-sm font-medium transition border',
                                        form.reward_type === key
                                            ? 'bg-orange-500 text-white border-orange-500'
                                            : 'bg-white text-gray-700 border-gray-200 hover:border-orange-300'
                                    ]"
                                >
                                    {{ label }}
                                </button>
                            </div>

                            <!-- Скидка % или ₽ -->
                            <div v-if="form.reward_type === 'discount'" class="space-y-3">
                                <div class="flex items-center gap-3">
                                    <select v-model="form.type" class="px-3 py-2 border rounded-lg bg-white">
                                        <option value="discount_percent">Скидка %</option>
                                        <option value="discount_fixed">Скидка ₽</option>
                                        <option value="progressive_discount">Прогрессивная скидка</option>
                                    </select>
                                    <input
                                        v-if="form.type !== 'progressive_discount'"
                                        v-model.number="form.discount_value"
                                        type="number"
                                        min="0"
                                        :placeholder="form.type === 'discount_percent' ? '10' : '500'"
                                        class="w-24 px-3 py-2 border rounded-lg"
                                    />
                                    <span class="text-gray-500">{{ form.type === 'discount_percent' ? '%' : '₽' }}</span>
                                    <span class="text-gray-500">на</span>
                                    <select v-model="form.applies_to" class="px-3 py-2 border rounded-lg bg-white">
                                        <option value="whole_order">весь чек</option>
                                        <option value="categories">категории</option>
                                        <option value="dishes">блюда</option>
                                    </select>
                                    <button
                                        v-if="form.applies_to !== 'whole_order'"
                                        @click="showAppliesModal = true"
                                        class="text-orange-500 text-sm hover:underline"
                                    >
                                        выбрать
                                    </button>
                                </div>

                                <!-- Выбранные категории/товары -->
                                <div v-if="form.applies_to === 'categories' && form.applicable_categories?.length" class="text-sm text-gray-600">
                                    Категории: <span class="text-orange-600">{{ getApplicableCategoriesNames() }}</span>
                                </div>
                                <div v-if="form.applies_to === 'dishes' && form.applicable_dishes?.length" class="text-sm text-gray-600">
                                    Блюда: <span class="text-orange-600">{{ form.applicable_dishes.length }} шт.</span>
                                </div>

                                <!-- Комбо-режим: требуются ВСЕ товары -->
                                <div v-if="form.applies_to === 'dishes' && form.applicable_dishes?.length > 1" class="flex items-center gap-2 pt-2">
                                    <label class="flex items-center gap-2 cursor-pointer text-sm">
                                        <input type="checkbox" v-model="form.requires_all_dishes" class="rounded text-orange-500">
                                        <span class="text-gray-700 font-medium">Комбо</span>
                                        <span class="text-gray-500">(требуются ВСЕ товары)</span>
                                    </label>
                                </div>

                                <!-- Исключения -->
                                <div class="flex items-center gap-2 pt-2 border-t border-gray-200">
                                    <span class="text-gray-500 text-sm">Исключить:</span>
                                    <button @click="showExcludeDishesModal = true" class="text-sm px-2 py-1 rounded border hover:border-orange-300"
                                            :class="form.excluded_dishes?.length ? 'bg-red-50 border-red-200 text-red-600' : 'border-gray-200 text-gray-600'">
                                        Товары {{ form.excluded_dishes?.length ? `(${form.excluded_dishes.length})` : '' }}
                                    </button>
                                    <button @click="showExcludeCategoriesModal = true" class="text-sm px-2 py-1 rounded border hover:border-orange-300"
                                            :class="form.excluded_categories?.length ? 'bg-red-50 border-red-200 text-red-600' : 'border-gray-200 text-gray-600'">
                                        Категории {{ form.excluded_categories?.length ? `(${form.excluded_categories.length})` : '' }}
                                    </button>
                                </div>

                                <!-- Прогрессивная шкала -->
                                <div v-if="form.type === 'progressive_discount'" class="bg-white rounded-lg p-3 border">
                                    <div class="text-sm text-gray-600 mb-2">Пороги скидки:</div>
                                    <div v-for="(tier, idx) in form.progressive_tiers" :key="idx" class="flex items-center gap-2 mb-2">
                                        <span class="text-gray-500">От</span>
                                        <input v-model.number="tier.min_amount" type="number" class="w-24 px-2 py-1 border rounded" placeholder="1000">
                                        <span class="text-gray-500">₽ →</span>
                                        <input v-model.number="tier.discount_percent" type="number" class="w-16 px-2 py-1 border rounded" placeholder="5">
                                        <span class="text-gray-500">%</span>
                                        <button @click="form.progressive_tiers.splice(idx, 1)" class="text-red-500 hover:text-red-700">✕</button>
                                    </div>
                                    <button @click="form.progressive_tiers.push({min_amount: 0, discount_percent: 0})" class="text-orange-500 text-sm hover:underline">
                                        + Добавить порог
                                    </button>
                                </div>

                                <!-- Максимальная скидка -->
                                <div class="flex items-center gap-2">
                                    <label class="flex items-center gap-2 cursor-pointer text-sm">
                                        <input type="checkbox" v-model="hasMaxDiscount" class="rounded">
                                        <span class="text-gray-600">Ограничить максимум</span>
                                    </label>
                                    <input
                                        v-if="hasMaxDiscount"
                                        v-model.number="form.max_discount"
                                        type="number"
                                        min="0"
                                        placeholder="500"
                                        class="w-24 px-2 py-1 border rounded-lg text-sm"
                                    />
                                    <span v-if="hasMaxDiscount" class="text-gray-500 text-sm">₽</span>
                                </div>
                            </div>

                            <!-- Бонусы -->
                            <div v-if="form.reward_type === 'bonus'" class="space-y-3">
                                <div class="flex items-center gap-3">
                                    <span class="text-gray-600">Бонусы %</span>
                                    <input v-model.number="form.discount_value" type="number" min="0" class="w-20 px-3 py-2 border rounded-lg" placeholder="5">
                                    <span class="text-gray-500">начислять на весь чек</span>
                                </div>
                                <div v-if="excludedCategoriesNames.length" class="text-sm text-gray-500">
                                    кроме: <span class="text-orange-600">{{ excludedCategoriesNames.join(', ') }}</span>
                                    <button @click="showExcludeCategoriesModal = true" class="text-orange-500 ml-2 hover:underline">изменить</button>
                                </div>
                                <button v-else @click="showExcludeCategoriesModal = true" class="text-orange-500 text-sm hover:underline">
                                    + Исключить категории
                                </button>

                                <div class="grid grid-cols-2 gap-3 pt-3 border-t">
                                    <div>
                                        <label class="block text-sm text-gray-600 mb-1">Активация</label>
                                        <select v-model="bonusSettings.activation_delay" class="w-full px-3 py-2 border rounded-lg bg-white text-sm">
                                            <option :value="0">сразу после покупки</option>
                                            <option :value="1">через 1 день</option>
                                            <option :value="7">через 7 дней</option>
                                            <option :value="14">через 14 дней</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-sm text-gray-600 mb-1">Срок использования</label>
                                        <select v-model="bonusSettings.expiry_days" class="w-full px-3 py-2 border rounded-lg bg-white text-sm">
                                            <option :value="null">бессрочно</option>
                                            <option :value="30">30 дней</option>
                                            <option :value="60">60 дней</option>
                                            <option :value="90">90 дней</option>
                                            <option :value="365">1 год</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Подарок -->
                            <div v-if="form.reward_type === 'gift'" class="flex items-center gap-3">
                                <span class="text-gray-600">Подарок:</span>
                                <select v-model="form.gift_dish_id" class="flex-1 px-3 py-2 border rounded-lg bg-white">
                                    <option :value="null">Выберите блюдо</option>
                                    <option v-for="dish in orderableDishes" :key="dish.id" :value="dish.id">{{ dish.variant_name ? (dish.parent?.name || dish.name) + ' ' + dish.variant_name : dish.name }}</option>
                                </select>
                            </div>

                            <!-- Бесплатная доставка -->
                            <div v-if="form.reward_type === 'free_delivery'" class="text-gray-600">
                                Бесплатная доставка при выполнении условий
                            </div>
                        </div>

                        <!-- Добавить условия -->
                        <div class="bg-gray-50 rounded-xl p-4 border">
                            <h4 class="text-gray-700 font-semibold mb-4 flex items-center gap-2">
                                <span>⚙️</span> Добавить условия
                            </h4>

                            <div class="grid grid-cols-2 gap-6">
                                <!-- Условия покупки -->
                                <div>
                                    <h5 class="font-medium text-gray-800 mb-3">Купить</h5>
                                    <div class="space-y-2">
                                        <button @click="toggleCondition('min_quantity')" :class="conditionBtnClass('min_quantity')">
                                            В количестве
                                        </button>
                                        <button @click="toggleCondition('min_amount')" :class="conditionBtnClass('min_amount')">
                                            На сумму
                                        </button>
                                        <button @click="toggleCondition('schedule')" :class="conditionBtnClass('schedule')">
                                            Когда
                                        </button>
                                        <button @click="toggleCondition('order_types')" :class="conditionBtnClass('order_types')">
                                            Тип заказа
                                        </button>
                                    </div>
                                </div>

                                <!-- Условия клиентов -->
                                <div>
                                    <h5 class="font-medium text-gray-800 mb-3">Клиентам</h5>
                                    <div class="space-y-2">
                                        <button @click="toggleCondition('loyalty_levels')" :class="conditionBtnClass('loyalty_levels')">
                                            Владельцам карт
                                        </button>
                                        <button @click="form.is_birthday_only = !form.is_birthday_only" :class="conditionBtnClass('birthday')">
                                            В день рождения
                                        </button>
                                        <button @click="form.is_first_order_only = !form.is_first_order_only" :class="conditionBtnClass('first_order')">
                                            За первый заказ
                                        </button>
                                    </div>

                                    <h5 class="font-medium text-gray-800 mb-3 mt-4">Взаимодействие</h5>
                                    <div class="space-y-2">
                                        <button @click="form.stackable = !form.stackable" :class="conditionBtnClass('stackable')">
                                            Суммируется
                                        </button>
                                        <button @click="toggleCondition('priority')" :class="conditionBtnClass('priority')">
                                            Приоритет применения
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Активные условия -->
                        <div v-if="hasActiveConditions" class="bg-blue-50 rounded-xl p-4 border border-blue-200 space-y-3">
                            <h4 class="text-blue-600 font-semibold">Активные условия</h4>

                            <!-- Мин. количество -->
                            <div v-if="activeConditions.min_quantity" class="flex items-center gap-2">
                                <span class="text-gray-600">Минимум позиций:</span>
                                <input v-model.number="form.min_items_count" type="number" min="1" class="w-20 px-2 py-1 border rounded">
                                <button @click="activeConditions.min_quantity = false; form.min_items_count = null" class="text-red-500">✕</button>
                            </div>

                            <!-- Мин. сумма -->
                            <div v-if="activeConditions.min_amount" class="flex items-center gap-2">
                                <span class="text-gray-600">Минимальная сумма:</span>
                                <input v-model.number="form.min_order_amount" type="number" min="0" class="w-24 px-2 py-1 border rounded">
                                <span class="text-gray-500">₽</span>
                                <button @click="activeConditions.min_amount = false; form.min_order_amount = null" class="text-red-500">✕</button>
                            </div>

                            <!-- Расписание -->
                            <div v-if="activeConditions.schedule" class="space-y-2">
                                <div class="flex items-center gap-2">
                                    <span class="text-gray-600">Дни недели:</span>
                                    <div class="flex gap-1">
                                        <button
                                            v-for="(day, idx) in daysOfWeek"
                                            :key="idx"
                                            @click="toggleDay(idx)"
                                            :class="[
                                                'w-8 h-8 rounded text-xs font-medium transition',
                                                (form.schedule?.days || []).includes(idx)
                                                    ? 'bg-orange-500 text-white'
                                                    : 'bg-gray-200 text-gray-600 hover:bg-gray-300'
                                            ]"
                                        >
                                            {{ day.slice(0, 2) }}
                                        </button>
                                    </div>
                                    <button @click="activeConditions.schedule = false; form.schedule = null" class="text-red-500">✕</button>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="text-gray-600">Время:</span>
                                    <input v-model="scheduleTimeFrom" type="time" class="px-2 py-1 border rounded">
                                    <span>—</span>
                                    <input v-model="scheduleTimeTo" type="time" class="px-2 py-1 border rounded">
                                </div>
                            </div>

                            <!-- Тип заказа -->
                            <div v-if="activeConditions.order_types" class="flex items-center gap-2 flex-wrap">
                                <span class="text-gray-600">Тип заказа:</span>
                                <label v-for="(label, key) in orderTypes" :key="key" class="flex items-center gap-1">
                                    <input type="checkbox" :value="key" v-model="form.order_types" class="rounded">
                                    <span class="text-sm">{{ label }}</span>
                                </label>
                                <button @click="activeConditions.order_types = false; form.order_types = []" class="text-red-500">✕</button>
                            </div>

                            <!-- Уровни лояльности -->
                            <div v-if="activeConditions.loyalty_levels" class="flex items-center gap-2 flex-wrap">
                                <span class="text-gray-600">Уровни:</span>
                                <label v-for="level in loyaltyLevels" :key="level.id" class="flex items-center gap-1">
                                    <input type="checkbox" :value="level.id" v-model="form.loyalty_levels" class="rounded">
                                    <span class="text-sm">{{ level.name }}</span>
                                </label>
                                <button @click="activeConditions.loyalty_levels = false; form.loyalty_levels = []" class="text-red-500">✕</button>
                            </div>

                            <!-- День рождения -->
                            <div v-if="form.is_birthday_only" class="space-y-2">
                                <div class="flex items-center gap-2">
                                    <span class="text-green-600">✓ Ко дню рождения</span>
                                    <button @click="form.is_birthday_only = false; form.birthday_days_before = 0; form.birthday_days_after = 0" class="text-red-500">✕</button>
                                </div>
                                <div class="flex items-center gap-2 text-sm">
                                    <input type="number" v-model.number="form.birthday_days_before" min="0" max="30"
                                           class="w-16 px-2 py-1 border rounded text-center">
                                    <span class="text-gray-600">дн. до ДР и</span>
                                    <input type="number" v-model.number="form.birthday_days_after" min="0" max="30"
                                           class="w-16 px-2 py-1 border rounded text-center">
                                    <span class="text-gray-600">дн. после</span>
                                </div>
                            </div>

                            <!-- Первый заказ -->
                            <div v-if="form.is_first_order_only" class="flex items-center gap-2">
                                <span class="text-green-600">✓ За первый заказ</span>
                                <button @click="form.is_first_order_only = false" class="text-red-500">✕</button>
                            </div>

                            <!-- Суммируется -->
                            <div v-if="form.stackable" class="flex items-center gap-2">
                                <span class="text-green-600">✓ Суммируется с другими скидками</span>
                            </div>
                            <div v-else class="flex items-center gap-2">
                                <span class="text-orange-600">✗ Не суммируется (эксклюзивная)</span>
                            </div>

                            <!-- Приоритет -->
                            <div v-if="activeConditions.priority" class="flex items-center gap-2">
                                <span class="text-gray-600">Приоритет:</span>
                                <input v-model.number="form.priority" type="number" min="0" class="w-20 px-2 py-1 border rounded">
                                <span class="text-gray-400 text-sm">(чем больше, тем выше)</span>
                                <button @click="activeConditions.priority = false; form.priority = 0" class="text-red-500">✕</button>
                            </div>
                        </div>
                    </div>

                    <!-- Right Panel - Description & Settings -->
                    <div class="w-72 bg-gray-50 border-l p-4 overflow-y-auto space-y-4">
                        <div>
                            <button
                                @click="rightTab = 'description'"
                                :class="['w-full text-left px-3 py-2 rounded-lg transition', rightTab === 'description' ? 'bg-orange-100 text-orange-700 font-medium' : 'hover:bg-gray-100']"
                            >
                                📝 Описание
                            </button>
                            <button
                                @click="rightTab = 'dates'"
                                :class="['w-full text-left px-3 py-2 rounded-lg transition', rightTab === 'dates' ? 'bg-orange-100 text-orange-700 font-medium' : 'hover:bg-gray-100']"
                            >
                                📅 Даты
                            </button>
                            <button
                                @click="rightTab = 'limits'"
                                :class="['w-full text-left px-3 py-2 rounded-lg transition', rightTab === 'limits' ? 'bg-orange-100 text-orange-700 font-medium' : 'hover:bg-gray-100']"
                            >
                                🔒 Лимиты
                            </button>
                            <button
                                @click="rightTab = 'promo'"
                                :class="['w-full text-left px-3 py-2 rounded-lg transition', rightTab === 'promo' ? 'bg-orange-100 text-orange-700 font-medium' : 'hover:bg-gray-100']"
                            >
                                📢 Реклама
                            </button>
                        </div>

                        <div class="pt-3 border-t">
                            <!-- Description -->
                            <div v-if="rightTab === 'description'" class="space-y-3">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Описание</label>
                                    <textarea
                                        v-model="form.description"
                                        rows="4"
                                        placeholder="Описание для клиентов..."
                                        class="w-full px-3 py-2 border rounded-lg text-sm"
                                    ></textarea>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Внутренние заметки</label>
                                    <textarea
                                        v-model="form.internal_notes"
                                        rows="2"
                                        placeholder="Заметки для персонала..."
                                        class="w-full px-3 py-2 border rounded-lg text-sm bg-yellow-50"
                                    ></textarea>
                                </div>
                            </div>

                            <!-- Dates -->
                            <div v-if="rightTab === 'dates'" class="space-y-3">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Дата начала</label>
                                    <input v-model="form.starts_at" type="date" class="w-full px-3 py-2 border rounded-lg text-sm">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Дата окончания</label>
                                    <input v-model="form.ends_at" type="date" class="w-full px-3 py-2 border rounded-lg text-sm">
                                </div>
                                <p class="text-xs text-gray-500">Оставьте пустым для бессрочной акции</p>
                            </div>

                            <!-- Limits -->
                            <div v-if="rightTab === 'limits'" class="space-y-3">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Общий лимит использований</label>
                                    <input v-model.number="form.usage_limit" type="number" min="0" class="w-full px-3 py-2 border rounded-lg text-sm" placeholder="Без лимита">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Лимит на клиента</label>
                                    <input v-model.number="form.usage_per_customer" type="number" min="0" class="w-full px-3 py-2 border rounded-lg text-sm" placeholder="Без лимита">
                                </div>
                                <div v-if="form.usage_count" class="text-sm text-gray-500">
                                    Использовано: {{ form.usage_count }} раз
                                </div>
                            </div>

                            <!-- Promo -->
                            <div v-if="rightTab === 'promo'" class="space-y-3">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Рекламный текст</label>
                                    <textarea
                                        v-model="form.promo_text"
                                        rows="3"
                                        placeholder="Текст для рекламы..."
                                        class="w-full px-3 py-2 border rounded-lg text-sm"
                                    ></textarea>
                                </div>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" v-model="form.is_featured" class="rounded">
                                    <span class="text-sm">Выделить (Featured)</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" v-model="form.auto_apply" class="rounded">
                                    <span class="text-sm">Автоприменение</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="px-6 py-4 border-t bg-gray-50 flex justify-between items-center">
                    <button
                        v-if="form.id"
                        @click="$emit('delete', form.id)"
                        class="px-4 py-2 text-red-600 hover:bg-red-50 rounded-lg transition"
                    >
                        🗑️ Удалить
                    </button>
                    <div v-else></div>
                    <div class="flex gap-3">
                        <button @click="$emit('close')" class="px-6 py-2 bg-gray-200 hover:bg-gray-300 rounded-lg font-medium transition">
                            Отмена
                        </button>
                        <button
                            @click="save"
                            :disabled="!form.name"
                            class="px-6 py-2 bg-orange-500 hover:bg-orange-600 disabled:bg-gray-300 text-white rounded-lg font-medium transition"
                        >
                            💾 Сохранить
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal: Select Categories/Dishes for applies_to -->
        <div v-if="showAppliesModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-[60]" @click.self="showAppliesModal = false">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-lg max-h-[85vh] overflow-hidden flex flex-col">
                <div class="px-4 py-3 border-b bg-orange-50">
                    <div class="flex justify-between items-center mb-2">
                        <span class="font-semibold text-orange-700">{{ form.applies_to === 'categories' ? 'Выберите категории' : 'Выберите товары' }}</span>
                        <button @click="showAppliesModal = false" class="text-gray-500 hover:text-gray-700 text-xl">&times;</button>
                    </div>
                    <!-- Поиск -->
                    <div class="relative">
                        <input
                            v-model="appliesSearch"
                            type="text"
                            :placeholder="form.applies_to === 'categories' ? 'Поиск категории...' : 'Поиск товара...'"
                            class="w-full pl-9 pr-4 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-orange-300 focus:border-orange-400"
                        />
                        <svg class="absolute left-3 top-2.5 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                    </div>
                </div>
                <!-- Кнопки выбора и счётчик -->
                <div class="px-4 py-2 border-b bg-gray-50 flex items-center justify-between">
                    <div class="flex gap-2">
                        <button @click="selectAllApplies" class="text-sm text-orange-600 hover:text-orange-700 font-medium">
                            Выбрать все
                        </button>
                        <span class="text-gray-300">|</span>
                        <button @click="clearAllApplies" class="text-sm text-gray-500 hover:text-gray-700">
                            Снять все
                        </button>
                    </div>
                    <span class="text-sm text-gray-500">
                        Выбрано: <span class="font-medium text-orange-600">{{ form.applies_to === 'categories' ? form.applicable_categories?.length || 0 : form.applicable_dishes?.length || 0 }}</span>
                    </span>
                </div>
                <div class="flex-1 overflow-y-auto p-4 space-y-1">
                    <template v-if="form.applies_to === 'categories'">
                        <label
                            v-for="cat in filteredAppliesCategories"
                            :key="cat.id"
                            class="flex items-center gap-2 p-2 rounded hover:bg-orange-50 cursor-pointer transition"
                        >
                            <input type="checkbox" :value="cat.id" v-model="form.applicable_categories" class="rounded text-orange-500 focus:ring-orange-400">
                            <span>{{ cat.name }}</span>
                            <span class="text-gray-400 text-xs ml-auto">{{ getCategoryDishCount(cat.id) }} товаров</span>
                        </label>
                        <div v-if="filteredAppliesCategories.length === 0" class="text-center text-gray-400 py-4">
                            Ничего не найдено
                        </div>
                    </template>
                    <template v-else>
                        <!-- Группировка по категориям -->
                        <template v-for="cat in categoriesWithFilteredDishes" :key="cat.id">
                            <div class="sticky top-0 bg-gray-100 px-3 py-1.5 rounded font-medium text-sm text-gray-600 mt-2 first:mt-0">
                                {{ cat.name }}
                            </div>
                            <label
                                v-for="dish in cat.dishes"
                                :key="dish.id"
                                class="flex items-center gap-2 p-2 pl-4 rounded hover:bg-orange-50 cursor-pointer transition"
                            >
                                <input type="checkbox" :value="dish.id" v-model="form.applicable_dishes" class="rounded text-orange-500 focus:ring-orange-400">
                                <span>{{ dish.variant_name ? (dish.parent?.name || dish.name) + ' ' + dish.variant_name : dish.name }}</span>
                                <span class="text-gray-400 text-sm ml-auto">{{ dish.price }}₽</span>
                            </label>
                        </template>
                        <div v-if="categoriesWithFilteredDishes.length === 0" class="text-center text-gray-400 py-4">
                            Ничего не найдено
                        </div>
                    </template>
                </div>
                <div class="px-4 py-3 border-t flex justify-end bg-gray-50">
                    <button @click="showAppliesModal = false" class="px-5 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600 font-medium transition">
                        Готово
                    </button>
                </div>
            </div>
        </div>

        <!-- Modal: Exclude Categories -->
        <div v-if="showExcludeCategoriesModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-[60]" @click.self="showExcludeCategoriesModal = false">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-lg max-h-[85vh] overflow-hidden flex flex-col">
                <div class="px-4 py-3 border-b bg-red-50">
                    <div class="flex justify-between items-center mb-2">
                        <span class="font-semibold text-red-700">Исключить категории</span>
                        <button @click="showExcludeCategoriesModal = false" class="text-gray-500 hover:text-gray-700 text-xl">&times;</button>
                    </div>
                    <!-- Поиск -->
                    <div class="relative">
                        <input
                            v-model="excludeCategoriesSearch"
                            type="text"
                            placeholder="Поиск категории..."
                            class="w-full pl-9 pr-4 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-red-300 focus:border-red-400"
                        />
                        <svg class="absolute left-3 top-2.5 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                    </div>
                </div>
                <!-- Кнопки выбора и счётчик -->
                <div class="px-4 py-2 border-b bg-gray-50 flex items-center justify-between">
                    <div class="flex gap-2">
                        <button @click="selectAllExcludeCategories" class="text-sm text-red-600 hover:text-red-700 font-medium">
                            Выбрать все
                        </button>
                        <span class="text-gray-300">|</span>
                        <button @click="form.excluded_categories = []" class="text-sm text-gray-500 hover:text-gray-700">
                            Снять все
                        </button>
                    </div>
                    <span class="text-sm text-gray-500">
                        Исключено: <span class="font-medium text-red-600">{{ form.excluded_categories?.length || 0 }}</span>
                    </span>
                </div>
                <div class="flex-1 overflow-y-auto p-4 space-y-1">
                    <label
                        v-for="cat in filteredExcludeCategories"
                        :key="cat.id"
                        class="flex items-center gap-2 p-2 rounded hover:bg-red-50 cursor-pointer transition"
                    >
                        <input type="checkbox" :value="cat.id" v-model="form.excluded_categories" class="rounded text-red-500 focus:ring-red-400">
                        <span>{{ cat.name }}</span>
                        <span class="text-gray-400 text-xs ml-auto">{{ getCategoryDishCount(cat.id) }} товаров</span>
                    </label>
                    <div v-if="filteredExcludeCategories.length === 0" class="text-center text-gray-400 py-4">
                        Ничего не найдено
                    </div>
                </div>
                <div class="px-4 py-3 border-t flex justify-end bg-gray-50">
                    <button @click="showExcludeCategoriesModal = false" class="px-5 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 font-medium transition">
                        Готово
                    </button>
                </div>
            </div>
        </div>

        <!-- Modal: Exclude Dishes -->
        <div v-if="showExcludeDishesModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-[60]" @click.self="showExcludeDishesModal = false">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-lg max-h-[85vh] overflow-hidden flex flex-col">
                <div class="px-4 py-3 border-b bg-red-50">
                    <div class="flex justify-between items-center mb-2">
                        <span class="font-semibold text-red-700">Исключить товары</span>
                        <button @click="showExcludeDishesModal = false" class="text-gray-500 hover:text-gray-700 text-xl">&times;</button>
                    </div>
                    <!-- Поиск -->
                    <div class="relative">
                        <input
                            v-model="excludeDishesSearch"
                            type="text"
                            placeholder="Поиск товара..."
                            class="w-full pl-9 pr-4 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-red-300 focus:border-red-400"
                        />
                        <svg class="absolute left-3 top-2.5 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                    </div>
                </div>
                <!-- Кнопки выбора и счётчик -->
                <div class="px-4 py-2 border-b bg-gray-50 flex items-center justify-between">
                    <div class="flex gap-2">
                        <button @click="selectAllExcludeDishes" class="text-sm text-red-600 hover:text-red-700 font-medium">
                            Выбрать все
                        </button>
                        <span class="text-gray-300">|</span>
                        <button @click="form.excluded_dishes = []" class="text-sm text-gray-500 hover:text-gray-700">
                            Снять все
                        </button>
                    </div>
                    <span class="text-sm text-gray-500">
                        Исключено: <span class="font-medium text-red-600">{{ form.excluded_dishes?.length || 0 }}</span>
                    </span>
                </div>
                <div class="flex-1 overflow-y-auto p-4 space-y-1">
                    <!-- Группировка по категориям -->
                    <template v-for="cat in categoriesWithFilteredExcludeDishes" :key="cat.id">
                        <div class="sticky top-0 bg-gray-100 px-3 py-1.5 rounded font-medium text-sm text-gray-600 mt-2 first:mt-0">
                            {{ cat.name }}
                        </div>
                        <label
                            v-for="dish in cat.dishes"
                            :key="dish.id"
                            class="flex items-center gap-2 p-2 pl-4 rounded hover:bg-red-50 cursor-pointer transition"
                        >
                            <input type="checkbox" :value="dish.id" v-model="form.excluded_dishes" class="rounded text-red-500 focus:ring-red-400">
                            <span>{{ dish.variant_name ? (dish.parent?.name || dish.name) + ' ' + dish.variant_name : dish.name }}</span>
                            <span class="text-gray-400 text-sm ml-auto">{{ dish.price }}₽</span>
                        </label>
                    </template>
                    <div v-if="categoriesWithFilteredExcludeDishes.length === 0" class="text-center text-gray-400 py-4">
                        Ничего не найдено
                    </div>
                </div>
                <div class="px-4 py-3 border-t flex justify-end bg-gray-50">
                    <button @click="showExcludeDishesModal = false" class="px-5 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 font-medium transition">
                        Готово
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<script setup lang="ts">
import { ref, reactive, computed, watch, PropType } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    promotion: { type: Object as PropType<Record<string, any>>, default: null },
    categories: { type: Array as PropType<any[]>, default: () => [] },
    dishes: { type: Array as PropType<any[]>, default: () => [] },
    zones: { type: Array as PropType<any[]>, default: () => [] },
    loyaltyLevels: { type: Array as PropType<any[]>, default: () => [] },
});

const emit = defineEmits(['close', 'save', 'delete']);

// Form
const defaultForm = () => ({
    id: null as any,
    name: '',
    type: 'discount_percent',
    reward_type: 'discount',
    applies_to: 'whole_order',
    discount_value: null as any,
    progressive_tiers: [] as any[],
    max_discount: null as any,
    min_order_amount: null as any,
    min_items_count: null as any,
    applicable_categories: [] as any[],
    applicable_dishes: [] as any[],
    requires_all_dishes: false,
    excluded_dishes: [] as any[],
    excluded_categories: [] as any[],
    gift_dish_id: null as any,
    starts_at: null as any,
    ends_at: null as any,
    schedule: null as any,
    bonus_settings: null as any,
    usage_limit: null as any,
    usage_per_customer: null as any,
    usage_count: 0,
    order_types: [] as any[],
    payment_methods: [] as any[],
    source_channels: [] as any[],
    stackable: true,
    auto_apply: true,
    is_automatic: true,
    is_exclusive: false,
    priority: 0,
    is_active: true,
    is_featured: false,
    is_first_order_only: false,
    is_birthday_only: false,
    birthday_days_before: 0,
    birthday_days_after: 0,
    requires_promo_code: false,
    loyalty_levels: [] as any[],
    excluded_customers: [] as any[],
    zones: [] as any[],
    tables_list: [] as any[],
    description: '',
    promo_text: '',
    internal_notes: '',
});

const form = reactive(defaultForm());

// UI State
const rightTab = ref('description');
const hasMaxDiscount = ref(false);
const showAppliesModal = ref(false);
const showExcludeCategoriesModal = ref(false);
const showExcludeDishesModal = ref(false);

// Search state for modals
const appliesSearch = ref('');
const excludeCategoriesSearch = ref('');
const excludeDishesSearch = ref('');

// Active conditions tracking
const activeConditions = reactive<Record<string, any>>({
    min_quantity: false,
    min_amount: false,
    schedule: false,
    order_types: false,
    payment_methods: false,
    source_channels: false,
    zones: false,
    loyalty_levels: false,
    priority: false,
});

// Bonus settings
const bonusSettings = reactive<Record<string, any>>({
    activation_delay: 0,
    expiry_days: null as any,
    excluded_categories: [] as any[],
});

// Schedule helpers
const scheduleTimeFrom = ref('');
const scheduleTimeTo = ref('');

// Reference data
const rewardTypes = {
    discount: 'Скидка',
    bonus: 'Бонусы',
    gift: 'Подарок',
    free_delivery: 'Бесп. доставка',
};

const orderTypes = {
    dine_in: 'В зале',
    delivery: 'Доставка',
    pickup: 'Самовывоз',
};

const paymentMethods = {
    cash: 'Наличные',
    card: 'Карта',
    online: 'Онлайн',
};

const sourceChannels = {
    pos: 'POS',
    website: 'Сайт',
    app: 'Приложение',
    phone: 'Телефон',
};

const daysOfWeek = ['Вс', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб'];

// Computed
const hasActiveConditions = computed(() => {
    return Object.values(activeConditions).some((v: any) => v) ||
           form.is_birthday_only ||
           form.is_first_order_only ||
           form.requires_promo_code ||
           !form.stackable;
});

const excludedCategoriesNames = computed(() => {
    const ids = bonusSettings.excluded_categories || [];
    return props.categories.filter((c: any) => ids.includes(c.id)).map((c: any) => c.name);
});

// Orderable dishes (simple + variant, excluding parent dishes with price 0)
const orderableDishes = computed(() => {
    return props.dishes.filter((dish: any) => dish.product_type !== 'parent');
});

// Filter categories for applies modal
const filteredAppliesCategories = computed(() => {
    const search = appliesSearch.value.toLowerCase().trim();
    if (!search) return props.categories;
    return props.categories.filter((cat: any) => cat.name.toLowerCase().includes(search));
});

// Filter categories for exclude modal
const filteredExcludeCategories = computed(() => {
    const search = excludeCategoriesSearch.value.toLowerCase().trim();
    if (!search) return props.categories;
    return props.categories.filter((cat: any) => cat.name.toLowerCase().includes(search));
});

// Group dishes by category with search filter (for applies modal)
const categoriesWithFilteredDishes = computed(() => {
    const search = appliesSearch.value.toLowerCase().trim();
    const result = [];

    for (const cat of props.categories) {
        const dishes = orderableDishes.value.filter((dish: any) => {
            if (dish.category_id !== cat.id) return false;
            if (!search) return true;
            const dishName = dish.variant_name
                ? `${dish.parent?.name || dish.name} ${dish.variant_name}`
                : dish.name;
            return dishName.toLowerCase().includes(search);
        });

        if (dishes.length > 0) {
            result.push({ ...cat, dishes });
        }
    }

    // Add uncategorized dishes
    const uncategorized = orderableDishes.value.filter((dish: any) => {
        if (dish.category_id) return false;
        if (!search) return true;
        const dishName = dish.variant_name
            ? `${dish.parent?.name || dish.name} ${dish.variant_name}`
            : dish.name;
        return dishName.toLowerCase().includes(search);
    });
    if (uncategorized.length > 0) {
        result.push({ id: null, name: 'Без категории', dishes: uncategorized });
    }

    return result;
});

// Group dishes by category with search filter (for exclude dishes modal)
const categoriesWithFilteredExcludeDishes = computed(() => {
    const search = excludeDishesSearch.value.toLowerCase().trim();
    const result = [];

    for (const cat of props.categories) {
        const dishes = orderableDishes.value.filter((dish: any) => {
            if (dish.category_id !== cat.id) return false;
            if (!search) return true;
            const dishName = dish.variant_name
                ? `${dish.parent?.name || dish.name} ${dish.variant_name}`
                : dish.name;
            return dishName.toLowerCase().includes(search);
        });

        if (dishes.length > 0) {
            result.push({ ...cat, dishes });
        }
    }

    // Add uncategorized dishes
    const uncategorized = orderableDishes.value.filter((dish: any) => {
        if (dish.category_id) return false;
        if (!search) return true;
        const dishName = dish.variant_name
            ? `${dish.parent?.name || dish.name} ${dish.variant_name}`
            : dish.name;
        return dishName.toLowerCase().includes(search);
    });
    if (uncategorized.length > 0) {
        result.push({ id: null, name: 'Без категории', dishes: uncategorized });
    }

    return result;
});

// Get count of dishes in category
function getCategoryDishCount(categoryId: any) {
    return orderableDishes.value.filter((d: any) => d.category_id === categoryId).length;
}

function getApplicableCategoriesNames() {
    const ids = form.applicable_categories || [];
    return props.categories.filter((c: any) => ids.includes(c.id)).map((c: any) => c.name).join(', ');
}

// Select/Clear functions for modals
function selectAllApplies() {
    if (form.applies_to === 'categories') {
        form.applicable_categories = filteredAppliesCategories.value.map((c: any) => c.id);
    } else {
        const allDishIds = [];
        for (const cat of categoriesWithFilteredDishes.value) {
            for (const dish of cat.dishes) {
                allDishIds.push(dish.id);
            }
        }
        form.applicable_dishes = [...new Set([...form.applicable_dishes, ...allDishIds])];
    }
}

function clearAllApplies() {
    if (form.applies_to === 'categories') {
        form.applicable_categories = [];
    } else {
        form.applicable_dishes = [];
    }
}

function selectAllExcludeCategories() {
    form.excluded_categories = filteredExcludeCategories.value.map((c: any) => c.id);
}

function selectAllExcludeDishes() {
    const allDishIds = [];
    for (const cat of categoriesWithFilteredExcludeDishes.value) {
        for (const dish of cat.dishes) {
            allDishIds.push(dish.id);
        }
    }
    form.excluded_dishes = [...new Set([...form.excluded_dishes, ...allDishIds])];
}

// Methods
function toggleCondition(condition: any) {
    activeConditions[condition] = !activeConditions[condition];

    if (activeConditions[condition]) {
        // Initialize defaults when enabling
        switch (condition) {
            case 'schedule':
                if (!form.schedule) {
                    form.schedule = { days: [], time_from: '', time_to: '' };
                }
                break;
            case 'order_types':
                if (!form.order_types?.length) {
                    form.order_types = ['dine_in', 'delivery', 'pickup'];
                }
                break;
            case 'payment_methods':
                if (!form.payment_methods?.length) {
                    form.payment_methods = ['cash', 'card'];
                }
                break;
            case 'source_channels':
                if (!form.source_channels?.length) {
                    form.source_channels = ['pos'];
                }
                break;
        }
    } else {
        // Clear values when disabling
        switch (condition) {
            case 'min_quantity':
                form.min_items_count = null;
                break;
            case 'min_amount':
                form.min_order_amount = null;
                break;
            case 'schedule':
                form.schedule = null;
                scheduleTimeFrom.value = '';
                scheduleTimeTo.value = '';
                break;
            case 'order_types':
                form.order_types = [];
                break;
            case 'payment_methods':
                form.payment_methods = [];
                break;
            case 'source_channels':
                form.source_channels = [];
                break;
            case 'zones':
                form.zones = [];
                break;
            case 'loyalty_levels':
                form.loyalty_levels = [];
                break;
            case 'priority':
                form.priority = 0;
                break;
        }
    }
}

function toggleDay(dayIdx: any) {
    if (!form.schedule) {
        form.schedule = { days: [], time_from: '', time_to: '' };
    }
    const idx = form.schedule.days.indexOf(dayIdx);
    if (idx >= 0) {
        form.schedule.days.splice(idx, 1);
    } else {
        form.schedule.days.push(dayIdx);
        form.schedule.days.sort((a: any, b: any) => a - b);
    }
}

function conditionBtnClass(condition: any) {
    let isActive = false;

    switch (condition) {
        case 'birthday': isActive = form.is_birthday_only; break;
        case 'first_order': isActive = form.is_first_order_only; break;
        case 'promo_code': isActive = form.requires_promo_code; break;
        case 'stackable': isActive = form.stackable; break;
        default: isActive = activeConditions[condition];
    }

    return [
        'block w-full text-left px-3 py-2 rounded-lg text-sm transition',
        isActive
            ? 'bg-orange-100 text-orange-700 font-medium'
            : 'bg-white border border-gray-200 text-gray-600 hover:border-orange-300 hover:bg-orange-50'
    ];
}

function save() {
    // Prepare schedule
    if (activeConditions.schedule && form.schedule) {
        form.schedule.time_from = scheduleTimeFrom.value;
        form.schedule.time_to = scheduleTimeTo.value;
    }

    // Prepare bonus settings
    if (form.reward_type === 'bonus') {
        form.bonus_settings = { ...bonusSettings };
        form.type = 'bonus';
    }

    // Clean up empty arrays
    const data: Record<string, any> = { ...form };
    if (!data.order_types?.length) data.order_types = null;
    if (!data.payment_methods?.length) data.payment_methods = null;
    if (!data.source_channels?.length) data.source_channels = null;
    if (!data.zones?.length) data.zones = null;
    if (!data.loyalty_levels?.length) data.loyalty_levels = null;
    if (!data.progressive_tiers?.length) data.progressive_tiers = null;
    if (!data.applicable_categories?.length) data.applicable_categories = null;
    if (!data.applicable_dishes?.length) data.applicable_dishes = null;
    if (!data.excluded_categories?.length) data.excluded_categories = null;
    if (!data.excluded_dishes?.length) data.excluded_dishes = null;

    // Clear max_discount if checkbox is unchecked
    if (!hasMaxDiscount.value) {
        data.max_discount = null;
    }

    emit('save', data);
}

// Watch for promotion prop changes
watch(() => props.promotion, (promo) => {
    if (promo) {
        Object.assign(form, defaultForm(), promo);

        // Ensure arrays are never null (fix for v-model checkbox groups)
        form.applicable_categories = form.applicable_categories || [];
        form.applicable_dishes = form.applicable_dishes || [];
        form.excluded_dishes = form.excluded_dishes || [];
        form.excluded_categories = form.excluded_categories || [];
        form.order_types = form.order_types || [];
        form.payment_methods = form.payment_methods || [];
        form.source_channels = form.source_channels || [];
        form.zones = form.zones || [];
        form.loyalty_levels = form.loyalty_levels || [];
        form.progressive_tiers = form.progressive_tiers || [];

        // Restore active conditions (проверяем > 0, т.к. 0 - это дефолт)
        activeConditions.min_quantity = form.min_items_count > 0;
        activeConditions.min_amount = form.min_order_amount > 0;
        activeConditions.schedule = !!form.schedule?.days?.length || !!form.schedule?.time_from;
        activeConditions.order_types = !!form.order_types?.length;
        activeConditions.payment_methods = !!form.payment_methods?.length;
        activeConditions.source_channels = !!form.source_channels?.length;
        activeConditions.zones = !!form.zones?.length;
        activeConditions.loyalty_levels = !!form.loyalty_levels?.length;
        activeConditions.priority = form.priority > 0;

        hasMaxDiscount.value = !!form.max_discount;

        if (form.schedule) {
            scheduleTimeFrom.value = form.schedule.time_from || '';
            scheduleTimeTo.value = form.schedule.time_to || '';
        }

        if (form.bonus_settings) {
            Object.assign(bonusSettings, form.bonus_settings);
        }
    } else {
        Object.assign(form, defaultForm());
        Object.keys(activeConditions).forEach((k: any) => activeConditions[k] = false);
        hasMaxDiscount.value = false;
        scheduleTimeFrom.value = '';
        scheduleTimeTo.value = '';
    }
}, { immediate: true });

watch(() => props.show, (show) => {
    if (!show) {
        rightTab.value = 'description';
        // Clear search inputs when modal closes
        appliesSearch.value = '';
        excludeCategoriesSearch.value = '';
        excludeDishesSearch.value = '';
    }
});

// Clear search when sub-modals close
watch(showAppliesModal, (show) => {
    if (!show) appliesSearch.value = '';
});
watch(showExcludeCategoriesModal, (show) => {
    if (!show) excludeCategoriesSearch.value = '';
});
watch(showExcludeDishesModal, (show) => {
    if (!show) excludeDishesSearch.value = '';
});
</script>
