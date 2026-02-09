<template>
    <div class="h-full flex flex-col" data-testid="customers-tab">
        <!-- Header -->
        <div class="flex items-center gap-4 px-4 py-3 border-b border-gray-800 bg-dark-900">
            <h1 class="text-lg font-semibold">Клиенты</h1>
            <span class="text-sm text-gray-400">{{ customers.length }} записей</span>
            <input
                v-model="search"
                type="text"
                placeholder="Поиск по имени или телефону..."
                data-testid="customer-search-input"
                class="ml-auto bg-dark-800 border border-gray-700 rounded-lg px-3 py-2 text-sm w-64"
            />
            <button
                @click="openAddModal"
                data-testid="add-customer-btn"
                class="px-4 py-2 bg-accent hover:bg-blue-600 rounded-lg text-sm text-white"
            >
                + Добавить
            </button>
        </div>

        <!-- Customer List -->
        <div class="flex-1 overflow-y-auto">
            <!-- Loading state -->
            <div v-if="loading" class="flex flex-col items-center justify-center h-full text-gray-500">
                <div class="animate-spin w-8 h-8 border-4 border-accent border-t-transparent rounded-full mb-4"></div>
                <p>Загрузка клиентов...</p>
            </div>

            <!-- Empty state -->
            <div v-else-if="filteredCustomers.length === 0" class="flex flex-col items-center justify-center h-full text-gray-500">
                <p class="text-4xl mb-4">👥</p>
                <p v-if="search">Клиенты не найдены</p>
                <p v-else>Нет клиентов</p>
            </div>

            <!-- Customer list -->
            <div v-else class="divide-y divide-gray-800">
                <div
                    v-for="customer in filteredCustomers"
                    :key="customer.id"
                    @click="openDetailModal(customer)"
                    :data-testid="`customer-card-${customer.id}`"
                    class="flex items-center gap-4 px-4 py-3 hover:bg-dark-900/50 cursor-pointer"
                >
                    <div class="w-10 h-10 rounded-full bg-accent/20 flex items-center justify-center text-accent font-medium">
                        {{ getInitials(customer) }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2">
                            <p class="font-medium truncate">{{ customer.name || 'Без имени' }}</p>
                            <span v-if="customer.is_blacklisted" class="px-1.5 py-0.5 bg-red-600/20 text-red-400 rounded text-xs">
                                В чёрном списке
                            </span>
                            <span v-if="customer.loyalty_level" class="px-1.5 py-0.5 bg-amber-600/20 text-amber-400 rounded text-xs">
                                {{ customer.loyalty_level.icon }} {{ customer.loyalty_level.name }}
                            </span>
                            <!-- Теги клиента -->
                            <span
                                v-for="tag in (customer.tags || []).slice(0, 2)"
                                :key="tag"
                                :class="['px-1.5 py-0.5 rounded text-xs', tagColors[tag] || 'bg-purple-600/20 text-purple-400']"
                            >
                                {{ getTagLabel(tag) }}
                            </span>
                            <span
                                v-if="(customer.tags || []).length > 2"
                                class="px-1.5 py-0.5 bg-gray-600/20 text-gray-400 rounded text-xs"
                            >
                                +{{ customer.tags.length - 2 }}
                            </span>
                        </div>
                        <p class="text-sm text-gray-400">{{ formatPhoneDisplay(customer.phone) || 'Нет телефона' }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm">{{ customer.orders_count || customer.total_orders || 0 }} заказов</p>
                        <p class="text-xs text-gray-500">{{ formatMoney(customer.orders_total || customer.total_spent || 0) }} ₽</p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm text-accent">{{ formatMoney(customer.bonus_balance || 0) }}</p>
                        <p class="text-xs text-gray-500">бонусов</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add/Edit Customer Modal -->
        <Teleport to="body">
            <div v-if="showAddModal" class="fixed inset-0 bg-black/70 flex items-center justify-center z-50" data-testid="add-customer-modal">
                <div class="bg-dark-900 rounded-xl w-full max-w-lg max-h-[90vh] overflow-hidden" data-testid="add-customer-content">
                    <div class="flex items-center justify-between p-4 border-b border-gray-800">
                        <h2 class="text-lg font-semibold">
                            {{ editingCustomer ? 'Редактировать клиента' : 'Новый клиент' }}
                        </h2>
                        <button @click="closeAddModal" class="text-gray-400 hover:text-white">✕</button>
                    </div>

                    <div class="p-4 space-y-4 overflow-y-auto max-h-[calc(90vh-140px)]">
                        <!-- Быстрый поиск по телефону (только при создании) -->
                        <div v-if="!editingCustomer" class="bg-dark-800 rounded-lg p-3">
                            <label class="block text-sm text-gray-400 mb-2">Быстрый поиск по телефону</label>
                            <div class="flex gap-2">
                                <input
                                    :value="phoneSearch"
                                    @input="onPhoneInput"
                                    type="tel"
                                    class="flex-1 bg-dark-700 border border-gray-600 rounded-lg px-3 py-2"
                                    placeholder="+7 (___) ___-__-__"
                                />
                                <button
                                    @click="searchByPhone"
                                    :disabled="phoneSearch.replace(/\D/g, '').length < 6"
                                    class="px-4 py-2 bg-accent/20 text-accent rounded-lg hover:bg-accent/30 disabled:opacity-50"
                                >
                                    Найти
                                </button>
                            </div>
                            <!-- Результат поиска -->
                            <div v-if="foundCustomer" class="mt-3 p-3 bg-amber-600/10 border border-amber-600/30 rounded-lg">
                                <p class="text-amber-400 text-sm font-medium mb-1">Клиент найден!</p>
                                <p class="text-sm">{{ foundCustomer.name || 'Без имени' }} - {{ formatPhoneDisplay(foundCustomer.phone) }}</p>
                                <button
                                    @click="openDetailModal(foundCustomer); closeAddModal()"
                                    class="mt-2 text-sm text-accent hover:underline"
                                >
                                    Открыть карточку клиента
                                </button>
                            </div>
                            <div v-else-if="searchPerformed && !foundCustomer" class="mt-2 text-sm text-green-400">
                                Клиент не найден. Можно создать нового.
                            </div>
                        </div>

                        <!-- Основная информация -->
                        <div class="border-t border-gray-700 pt-4">
                            <p class="text-xs text-gray-500 uppercase tracking-wider mb-3">Основная информация</p>

                            <div class="grid grid-cols-2 gap-4">
                                <!-- Имя -->
                                <div class="col-span-2">
                                    <label class="block text-sm text-gray-400 mb-1">Имя *</label>
                                    <input
                                        v-model="form.name"
                                        @blur="formatCustomerName"
                                        type="text"
                                        data-testid="customer-name-input"
                                        class="w-full bg-dark-800 border border-gray-700 rounded-lg px-3 py-2"
                                        :class="{ 'border-red-500': errors.name }"
                                        placeholder="Имя клиента"
                                    />
                                    <p v-if="errors.name" class="text-red-400 text-xs mt-1">{{ errors.name }}</p>
                                </div>

                                <!-- Телефон -->
                                <div>
                                    <label class="block text-sm text-gray-400 mb-1">Телефон *</label>
                                    <div class="relative">
                                        <input
                                            :value="form.phone"
                                            @input="onFormPhoneInput"
                                            @keypress="onlyDigits"
                                            type="tel"
                                            inputmode="numeric"
                                            data-testid="customer-phone-input"
                                            class="w-full bg-dark-800 rounded-lg px-3 py-2 pr-8 transition-colors"
                                            :class="[
                                                form.phone && !isPhoneValid ? 'border border-red-500' : 'border border-gray-700',
                                                form.phone && isPhoneValid ? 'border-green-500' : ''
                                            ]"
                                            placeholder="+7 (___) ___-__-__"
                                        />
                                        <!-- Status icon -->
                                        <div class="absolute right-3 top-1/2 -translate-y-1/2">
                                            <svg v-if="form.phone && isPhoneValid" class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                            </svg>
                                            <svg v-else-if="form.phone && !isPhoneValid" class="w-4 h-4 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                                            </svg>
                                        </div>
                                    </div>
                                    <p v-if="form.phone && !isPhoneValid" class="text-red-400 text-xs mt-1">
                                        Ещё {{ phoneDigitsRemaining }} {{ phoneDigitsRemaining === 1 ? 'цифра' : phoneDigitsRemaining < 5 ? 'цифры' : 'цифр' }}
                                    </p>
                                </div>

                                <!-- Email -->
                                <div>
                                    <label class="block text-sm text-gray-400 mb-1">Email</label>
                                    <input
                                        v-model="form.email"
                                        type="email"
                                        class="w-full bg-dark-800 border border-gray-700 rounded-lg px-3 py-2"
                                        :class="{ 'border-red-500': errors.email }"
                                        placeholder="email@example.com"
                                    />
                                    <p v-if="errors.email" class="text-red-400 text-xs mt-1">{{ errors.email }}</p>
                                </div>

                                <!-- Дата рождения -->
                                <div>
                                    <label class="block text-sm text-gray-400 mb-1">Дата рождения</label>
                                    <input
                                        v-model="form.birthday"
                                        type="date"
                                        class="w-full bg-dark-800 border border-gray-700 rounded-lg px-3 py-2"
                                    />
                                </div>

                                <!-- Источник -->
                                <div>
                                    <label class="block text-sm text-gray-400 mb-1">Откуда узнал</label>
                                    <select
                                        v-model="form.source"
                                        class="w-full bg-dark-800 border border-gray-700 rounded-lg px-3 py-2"
                                    >
                                        <option value="">Не указано</option>
                                        <option value="recommendation">Рекомендация</option>
                                        <option value="instagram">Instagram</option>
                                        <option value="vk">ВКонтакте</option>
                                        <option value="telegram">Telegram</option>
                                        <option value="2gis">2ГИС</option>
                                        <option value="yandex_maps">Яндекс Карты</option>
                                        <option value="website">Сайт</option>
                                        <option value="walk_in">Проходил мимо</option>
                                        <option value="corporate">Корпоративный</option>
                                        <option value="other">Другое</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Сворачиваемая секция: Дополнительно -->
                        <div class="border-t border-gray-700 pt-4">
                            <button
                                @click="showExtra = !showExtra"
                                class="flex items-center justify-between w-full text-left"
                            >
                                <p class="text-xs text-gray-500 uppercase tracking-wider">Дополнительно</p>
                                <span class="text-gray-500">{{ showExtra ? '▲' : '▼' }}</span>
                            </button>

                            <div v-show="showExtra" class="mt-4 space-y-4">
                                <!-- Теги -->
                                <div>
                                    <label class="block text-sm text-gray-400 mb-2">Теги клиента</label>
                                    <div class="flex flex-wrap gap-2">
                                        <button
                                            v-for="(label, key) in availableTags"
                                            :key="key"
                                            @click="toggleTag(key)"
                                            :class="[
                                                'px-3 py-1.5 rounded-lg text-sm transition-colors',
                                                form.tags.includes(key)
                                                    ? 'bg-purple-600 text-white'
                                                    : 'bg-dark-800 text-gray-400 hover:bg-dark-700'
                                            ]"
                                        >
                                            {{ label }}
                                        </button>
                                    </div>
                                </div>

                                <!-- Предпочтения/Аллергии -->
                                <div>
                                    <label class="block text-sm text-gray-400 mb-1">Предпочтения / Аллергии</label>
                                    <textarea
                                        v-model="form.preferences"
                                        class="w-full bg-dark-800 border border-gray-700 rounded-lg px-3 py-2"
                                        rows="2"
                                        placeholder="Не ест свинину, аллергия на орехи..."
                                    ></textarea>
                                </div>

                                <!-- Заметки -->
                                <div>
                                    <label class="block text-sm text-gray-400 mb-1">Заметки</label>
                                    <textarea
                                        v-model="form.notes"
                                        class="w-full bg-dark-800 border border-gray-700 rounded-lg px-3 py-2"
                                        rows="2"
                                        placeholder="Заметки о клиенте..."
                                    ></textarea>
                                </div>

                                <!-- Согласия -->
                                <div class="space-y-2">
                                    <label class="flex items-center gap-3 cursor-pointer">
                                        <input
                                            v-model="form.sms_consent"
                                            type="checkbox"
                                            class="w-4 h-4 rounded accent-accent"
                                        />
                                        <span class="text-sm">Согласие на SMS-рассылку</span>
                                    </label>
                                    <label class="flex items-center gap-3 cursor-pointer">
                                        <input
                                            v-model="form.email_consent"
                                            type="checkbox"
                                            class="w-4 h-4 rounded accent-accent"
                                        />
                                        <span class="text-sm">Согласие на Email-рассылку</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex gap-3 p-4 border-t border-gray-800">
                        <button
                            @click="closeAddModal"
                            class="flex-1 py-2.5 bg-dark-800 text-gray-400 rounded-lg hover:bg-dark-700"
                            data-testid="cancel-add-customer-btn"
                        >
                            Отмена
                        </button>
                        <button
                            @click="saveCustomer"
                            :disabled="!canSave || saving"
                            class="flex-1 py-2.5 bg-accent text-white rounded-lg hover:bg-blue-600 disabled:opacity-50 disabled:cursor-not-allowed font-medium"
                            data-testid="save-customer-btn"
                        >
                            {{ saving ? 'Сохранение...' : 'Сохранить' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Customer Detail Modal -->
        <Teleport to="body">
            <div v-if="showDetailModal && selectedCustomer" class="fixed inset-0 bg-black/70 flex items-center justify-center z-50" data-testid="customer-detail-modal">
                <div class="bg-dark-900 rounded-xl w-full max-w-lg max-h-[90vh] overflow-hidden" data-testid="customer-detail-content">
                    <div class="flex items-center justify-between p-4 border-b border-gray-800">
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 rounded-full bg-accent/20 flex items-center justify-center text-accent text-lg font-medium">
                                {{ getInitials(selectedCustomer) }}
                            </div>
                            <div>
                                <h2 class="text-lg font-semibold">{{ selectedCustomer.name || 'Без имени' }}</h2>
                                <p class="text-sm text-gray-400">{{ formatPhoneDisplay(selectedCustomer.phone) }}</p>
                            </div>
                        </div>
                        <button @click="closeDetailModal" class="text-gray-400 hover:text-white">✕</button>
                    </div>

                    <div class="p-4 overflow-y-auto max-h-[calc(90vh-200px)]">
                        <!-- Stats -->
                        <div class="grid grid-cols-3 gap-4 mb-6">
                            <div class="bg-dark-800 rounded-lg p-3 text-center">
                                <div class="text-xl font-bold text-accent">{{ selectedCustomer.orders_count || selectedCustomer.total_orders || 0 }}</div>
                                <div class="text-xs text-gray-500">Заказов</div>
                            </div>
                            <div class="bg-dark-800 rounded-lg p-3 text-center">
                                <div class="text-xl font-bold text-green-400">{{ formatMoney(selectedCustomer.orders_total || selectedCustomer.total_spent || 0) }}</div>
                                <div class="text-xs text-gray-500">Всего ₽</div>
                            </div>
                            <div class="bg-dark-800 rounded-lg p-3 text-center">
                                <div class="text-xl font-bold text-amber-400">{{ formatMoney(selectedCustomer.bonus_balance || 0) }}</div>
                                <div class="text-xs text-gray-500">Бонусы</div>
                            </div>
                        </div>

                        <!-- Теги -->
                        <div v-if="selectedCustomer.tags && selectedCustomer.tags.length > 0" class="flex flex-wrap gap-2 mb-4">
                            <span
                                v-for="tag in selectedCustomer.tags"
                                :key="tag"
                                :class="['px-2 py-1 rounded text-sm', tagColors[tag] || 'bg-purple-600/20 text-purple-400']"
                            >
                                {{ getTagLabel(tag) }}
                            </span>
                        </div>

                        <!-- Info -->
                        <div class="bg-dark-800 rounded-lg p-4 mb-4">
                            <h3 class="font-medium mb-3">Информация</h3>
                            <div class="space-y-2 text-sm">
                                <div v-if="selectedCustomer.email" class="flex justify-between">
                                    <span class="text-gray-400">Email:</span>
                                    <span>{{ selectedCustomer.email }}</span>
                                </div>
                                <div v-if="selectedCustomer.birthday || selectedCustomer.birth_date" class="flex justify-between">
                                    <span class="text-gray-400">Дата рождения:</span>
                                    <span>{{ formatDate(selectedCustomer.birthday || selectedCustomer.birth_date) }}</span>
                                </div>
                                <div v-if="selectedCustomer.source" class="flex justify-between">
                                    <span class="text-gray-400">Источник:</span>
                                    <span>{{ getSourceLabel(selectedCustomer.source) }}</span>
                                </div>
                                <div v-if="selectedCustomer.loyalty_level" class="flex justify-between">
                                    <span class="text-gray-400">Уровень лояльности:</span>
                                    <span class="text-amber-400">{{ selectedCustomer.loyalty_level.icon }} {{ selectedCustomer.loyalty_level.name }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-400">Зарегистрирован:</span>
                                    <span>{{ formatDate(selectedCustomer.created_at) }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Предпочтения -->
                        <div v-if="selectedCustomer.preferences" class="bg-dark-800 rounded-lg p-4 mb-4">
                            <h3 class="font-medium mb-2">Предпочтения / Аллергии</h3>
                            <p class="text-sm text-gray-400">{{ selectedCustomer.preferences }}</p>
                        </div>

                        <!-- Notes -->
                        <div v-if="selectedCustomer.notes" class="bg-dark-800 rounded-lg p-4 mb-4">
                            <h3 class="font-medium mb-2">Заметки</h3>
                            <p class="text-sm text-gray-400">{{ selectedCustomer.notes }}</p>
                        </div>

                        <!-- Unified History (Orders + Bonuses) -->
                        <div class="bg-dark-800 rounded-lg p-4">
                            <h3 class="font-medium mb-3">История</h3>
                            <div v-if="loadingOrders || loadingBonuses" class="text-sm text-gray-500 text-center py-4">
                                <div class="animate-spin w-5 h-5 border-2 border-accent border-t-transparent rounded-full mx-auto mb-2"></div>
                                Загрузка...
                            </div>
                            <div v-else-if="unifiedHistory.length === 0" class="text-sm text-gray-500 text-center py-4">
                                Нет истории
                            </div>
                            <div v-else class="space-y-2">
                                <div
                                    v-for="item in unifiedHistory.slice(0, 15)"
                                    :key="item.key"
                                    class="py-2 border-b border-gray-700/50 last:border-0"
                                >
                                    <!-- Order row -->
                                    <template v-if="item.type === 'order'">
                                        <div class="flex items-center justify-between text-sm">
                                            <div class="flex items-center gap-2">
                                                <span class="text-lg">🧾</span>
                                                <div>
                                                    <div class="flex items-center gap-2">
                                                        <span class="text-white font-medium">#{{ item.order_number }}</span>
                                                        <span v-if="item.payment_status === 'paid'" class="text-[10px] px-1.5 py-0.5 bg-green-600/20 text-green-400 rounded">
                                                            оплачен
                                                        </span>
                                                        <span v-else class="text-[10px] px-1.5 py-0.5 bg-yellow-600/20 text-yellow-400 rounded">
                                                            не оплачен
                                                        </span>
                                                    </div>
                                                    <!-- Bonus info for order -->
                                                    <div v-if="item.bonus_earned || item.bonus_spent" class="flex items-center gap-3 mt-0.5">
                                                        <span v-if="item.bonus_earned" class="text-xs text-green-400">
                                                            +{{ item.bonus_earned }} бонусов
                                                        </span>
                                                        <span v-if="item.bonus_spent" class="text-xs text-amber-400">
                                                            −{{ item.bonus_spent }} списано
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="text-right">
                                                <div class="font-medium text-white">{{ formatMoney(item.total) }} ₽</div>
                                                <div class="text-xs text-gray-500">{{ formatDate(item.created_at) }}</div>
                                            </div>
                                        </div>
                                    </template>

                                    <!-- Manual bonus operation row -->
                                    <template v-else-if="item.type === 'bonus'">
                                        <div class="flex items-center justify-between text-sm">
                                            <div class="flex items-center gap-2">
                                                <span class="text-lg">{{ item.icon }}</span>
                                                <div>
                                                    <div class="text-gray-300">{{ item.label }}</div>
                                                    <div v-if="item.description" class="text-xs text-gray-500">{{ item.description }}</div>
                                                </div>
                                            </div>
                                            <div class="text-right">
                                                <div :class="item.amount >= 0 ? 'text-green-400' : 'text-red-400'" class="font-medium">
                                                    {{ item.amount >= 0 ? '+' : '' }}{{ item.amount }}
                                                </div>
                                                <div class="text-xs text-gray-500">{{ formatDate(item.created_at) }}</div>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex gap-3 p-4 border-t border-gray-800">
                        <button
                            @click="editCustomer(selectedCustomer)"
                            class="flex-1 py-2 bg-dark-800 text-gray-300 rounded-lg hover:bg-dark-700"
                        >
                            Редактировать
                        </button>
                        <button
                            @click="toggleBlacklist(selectedCustomer)"
                            :class="[
                                'flex-1 py-2 rounded-lg',
                                selectedCustomer.is_blacklisted
                                    ? 'bg-green-600/20 text-green-400 hover:bg-green-600/30'
                                    : 'bg-red-600/20 text-red-400 hover:bg-red-600/30'
                            ]"
                        >
                            {{ selectedCustomer.is_blacklisted ? 'Убрать из ЧС' : 'В чёрный список' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch, nextTick } from 'vue';
import { usePosStore } from '../../stores/pos';
import api from '../../api';
import { createLogger } from '../../../shared/services/logger.js';

const log = createLogger('POS:Customers');

const posStore = usePosStore();

// State
const search = ref('');
const loading = ref(false);
const saving = ref(false);
const showAddModal = ref(false);
const showDetailModal = ref(false);
const showExtra = ref(false);
const selectedCustomer = ref(null);
const editingCustomer = ref(null);
const customerOrders = ref([]);
const loadingOrders = ref(false);
const bonusHistory = ref([]);
const loadingBonuses = ref(false);

// Поиск по телефону
const phoneSearch = ref('');
const foundCustomer = ref(null);
const searchPerformed = ref(false);

// Доступные теги
const availableTags = {
    vip: 'VIP',
    corporate: 'Корпоративный',
    blogger: 'Блогер',
    regular: 'Постоянный',
    problem: 'Проблемный'
};

// Цвета тегов
const tagColors = {
    vip: 'bg-amber-600/20 text-amber-400',
    corporate: 'bg-blue-600/20 text-blue-400',
    blogger: 'bg-pink-600/20 text-pink-400',
    regular: 'bg-green-600/20 text-green-400',
    problem: 'bg-red-600/20 text-red-400'
};

// Источники
const sourceLabels = {
    recommendation: 'Рекомендация',
    instagram: 'Instagram',
    vk: 'ВКонтакте',
    telegram: 'Telegram',
    '2gis': '2ГИС',
    yandex_maps: 'Яндекс Карты',
    website: 'Сайт',
    walk_in: 'Проходил мимо',
    corporate: 'Корпоративный',
    other: 'Другое'
};

// Form state
const form = reactive({
    name: '',
    phone: '',
    email: '',
    birthday: '',
    source: '',
    notes: '',
    preferences: '',
    tags: [],
    sms_consent: true,
    email_consent: false
});

// Ошибки валидации
const errors = reactive({
    name: '',
    phone: '',
    email: ''
});

// Computed
const customers = computed(() => posStore.customers);

const filteredCustomers = computed(() => {
    if (!search.value) return customers.value;
    const q = search.value.toLowerCase();
    return customers.value.filter(c =>
        c.name?.toLowerCase().includes(q) ||
        c.phone?.includes(q) ||
        c.email?.toLowerCase().includes(q)
    );
});

const isPhoneValid = computed(() => {
    const digits = form.phone.replace(/\D/g, '');
    return digits.length >= 11;
});

const phoneDigitsRemaining = computed(() => {
    const digits = form.phone.replace(/\D/g, '');
    return Math.max(0, 11 - digits.length);
});

const canSave = computed(() => {
    return form.name && form.phone && isPhoneValid.value && !errors.phone && !errors.email;
});

// Объединённая история (заказы + бонусы)
const unifiedHistory = computed(() => {
    const items = [];

    // Добавляем заказы - они уже содержат bonus_earned и bonus_spent из API
    for (const order of customerOrders.value) {
        items.push({
            key: `order-${order.id}`,
            type: 'order',
            order_number: order.order_number || order.daily_number,
            total: order.total,
            payment_status: order.payment_status,
            created_at: order.created_at,
            bonus_earned: order.bonus_earned || 0,
            bonus_spent: order.bonus_spent || 0,
            sortDate: new Date(order.created_at).getTime()
        });
    }

    // Добавляем только ручные бонусные операции (без привязки к заказу)
    // Бонусы с order_id уже показаны в заказах
    for (const bonus of bonusHistory.value) {
        if (!bonus.order_id) {
            items.push({
                key: `bonus-${bonus.id}`,
                type: 'bonus',
                amount: bonus.amount,
                label: bonus.type_label || getBonusLabel(bonus.type),
                description: bonus.description || bonus.note,
                icon: bonus.type_icon || getBonusIcon(bonus.type, bonus.amount),
                created_at: bonus.created_at,
                sortDate: new Date(bonus.created_at).getTime()
            });
        }
    }

    // Сортируем по дате (новые сверху)
    items.sort((a, b) => b.sortDate - a.sortDate);

    return items;
});

// Вспомогательные функции для бонусов
const getBonusLabel = (type) => {
    const labels = {
        earn: 'Начисление бонусов',
        spend: 'Списание бонусов',
        manual_add: 'Ручное начисление',
        manual_subtract: 'Ручное списание',
        expire: 'Сгорание бонусов',
        refund: 'Возврат бонусов',
        birthday: 'Бонус на день рождения',
        welcome: 'Приветственный бонус',
        promo: 'Промо-бонус'
    };
    return labels[type] || 'Операция с бонусами';
};

const getBonusIcon = (type, amount) => {
    if (type === 'birthday') return '🎂';
    if (type === 'welcome') return '🎁';
    if (type === 'promo') return '🎉';
    if (type === 'expire') return '⏰';
    if (type === 'refund') return '↩️';
    return amount >= 0 ? '➕' : '➖';
};

// Methods
const getInitials = (customer) => {
    if (!customer.name) return '?';
    const parts = customer.name.split(' ');
    if (parts.length >= 2) return (parts[0][0] + parts[1][0]).toUpperCase();
    return customer.name.substring(0, 2).toUpperCase();
};

const formatMoney = (n) => Math.floor(n || 0).toLocaleString('ru-RU');

const formatDate = (dt) => {
    if (!dt) return '';
    return new Date(dt).toLocaleDateString('ru-RU');
};

const getTagLabel = (key) => availableTags[key] || key;

const getSourceLabel = (key) => sourceLabels[key] || key;

// Форматирование имени (первая буква каждого слова заглавная)
const formatCustomerName = () => {
    if (!form.name) return;
    const words = form.name.trim().replace(/\s+/g, ' ').split(' ');
    form.name = words.map(word => {
        if (!word) return '';
        return word.charAt(0).toUpperCase() + word.slice(1).toLowerCase();
    }).join(' ');
};

// Блокировка ввода букв - только цифры
const onlyDigits = (e) => {
    const char = String.fromCharCode(e.which || e.keyCode);
    if (!/[\d]/.test(char)) {
        e.preventDefault();
    }
};

// Обработка ввода телефона в форме
const onFormPhoneInput = (event) => {
    const input = event.target;
    const rawValue = input.value;
    const cursorPos = input.selectionStart;

    // Считаем цифры ПОСЛЕ курсора — это стабильный якорь,
    // не зависящий от авто-подстановки "7" в начало
    const digitsAfterCursor = rawValue.slice(cursorPos).replace(/\D/g, '').length;

    form.phone = formatPhoneDisplay(rawValue);

    nextTick(() => {
        const formatted = form.phone;
        let targetPos = formatted.length;

        if (digitsAfterCursor > 0) {
            let count = 0;
            for (let i = formatted.length - 1; i >= 0; i--) {
                if (/\d/.test(formatted[i])) {
                    count++;
                    if (count === digitsAfterCursor) {
                        targetPos = i;
                        break;
                    }
                }
            }
        }

        // Не ставить курсор левее "+7 ("
        if (targetPos < 4 && formatted.length >= 4) targetPos = 4;

        input.setSelectionRange(targetPos, targetPos);
    });

    validatePhone();
};

// Валидация
const validatePhone = () => {
    const digits = form.phone.replace(/\D/g, '');
    if (form.phone && digits.length < 11) {
        errors.phone = 'Введите полный номер телефона';
    } else {
        errors.phone = '';
    }
};

const validateEmail = () => {
    if (form.email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email)) {
        errors.email = 'Некорректный email';
    } else {
        errors.email = '';
    }
};

// Watch для валидации email
watch(() => form.email, validateEmail);

// Форматирование любого телефона для отображения
const formatPhoneDisplay = (phone) => {
    if (!phone) return '';
    let digits = phone.replace(/\D/g, '');

    if (digits.startsWith('8')) {
        digits = '7' + digits.slice(1);
    }
    if (digits.length > 0 && !digits.startsWith('7')) {
        digits = '7' + digits;
    }

    digits = digits.slice(0, 11);

    let formatted = '';
    if (digits.length > 0) formatted = '+' + digits[0];
    if (digits.length > 1) formatted += ' (' + digits.slice(1, 4);
    if (digits.length >= 4) formatted += ') ' + digits.slice(4, 7);
    if (digits.length >= 7) formatted += '-' + digits.slice(7, 9);
    if (digits.length >= 9) formatted += '-' + digits.slice(9, 11);

    return formatted;
};

// Обработка ввода телефона в поиске
const onPhoneInput = (event) => {
    foundCustomer.value = null;
    searchPerformed.value = false;

    const input = event.target;
    const rawValue = input.value;
    const cursorPos = input.selectionStart;

    // Считаем цифры ПОСЛЕ курсора — стабильный якорь
    const digitsAfterCursor = rawValue.slice(cursorPos).replace(/\D/g, '').length;

    phoneSearch.value = formatPhoneDisplay(rawValue);

    nextTick(() => {
        const formatted = phoneSearch.value;
        let targetPos = formatted.length;

        if (digitsAfterCursor > 0) {
            let count = 0;
            for (let i = formatted.length - 1; i >= 0; i--) {
                if (/\d/.test(formatted[i])) {
                    count++;
                    if (count === digitsAfterCursor) {
                        targetPos = i;
                        break;
                    }
                }
            }
        }

        // Не ставить курсор левее "+7 ("
        if (targetPos < 4 && formatted.length >= 4) targetPos = 4;

        input.setSelectionRange(targetPos, targetPos);
    });
};

// Поиск по телефону
const searchByPhone = async () => {
    const digits = phoneSearch.value.replace(/\D/g, '');
    if (digits.length < 6) return;

    try {
        const results = await api.customers.search(digits);
        const list = Array.isArray(results) ? results : (results.data || []);
        foundCustomer.value = list.length > 0 ? list[0] : null;
        searchPerformed.value = true;

        // Если не найден - копируем телефон в форму
        if (!foundCustomer.value) {
            form.phone = formatPhoneDisplay(phoneSearch.value);
        }
    } catch (error) {
        log.error('Error searching customer:', error);
        if (error.response?.status === 401) {
            window.$toast?.('Ошибка авторизации. Попробуйте перезайти.', 'error');
        } else {
            window.$toast?.(error.response?.data?.message || 'Ошибка поиска', 'error');
        }
        searchPerformed.value = true;
    }
};

// Управление тегами
const toggleTag = (tag) => {
    const idx = form.tags.indexOf(tag);
    if (idx === -1) {
        form.tags.push(tag);
    } else {
        form.tags.splice(idx, 1);
    }
};

// Модальные окна
const openAddModal = () => {
    editingCustomer.value = null;
    form.name = '';
    form.phone = '';
    form.email = '';
    form.birthday = '';
    form.source = '';
    form.notes = '';
    form.preferences = '';
    form.tags = [];
    form.sms_consent = true;
    form.email_consent = false;
    errors.name = '';
    errors.phone = '';
    errors.email = '';
    phoneSearch.value = '';
    foundCustomer.value = null;
    searchPerformed.value = false;
    showExtra.value = false;
    showAddModal.value = true;
};

const closeAddModal = () => {
    showAddModal.value = false;
    editingCustomer.value = null;
};

const editCustomer = (customer) => {
    editingCustomer.value = customer;
    form.name = customer.name || '';
    form.phone = formatPhoneDisplay(customer.phone) || '';
    form.email = customer.email || '';
    form.birthday = customer.birthday || customer.birth_date || '';
    form.source = customer.source || '';
    form.notes = customer.notes || '';
    form.preferences = customer.preferences || '';
    form.tags = customer.tags || [];
    form.sms_consent = customer.sms_consent ?? true;
    form.email_consent = customer.email_consent ?? false;
    errors.name = '';
    errors.phone = '';
    errors.email = '';
    showExtra.value = true;
    showDetailModal.value = false;
    showAddModal.value = true;
};

const saveCustomer = async () => {
    if (!canSave.value) return;

    // Форматируем имя перед сохранением
    formatCustomerName();

    saving.value = true;
    try {
        const data = {
            name: form.name,
            phone: form.phone,
            email: form.email || null,
            birth_date: form.birthday || null,
            source: form.source || null,
            notes: form.notes || null,
            preferences: form.preferences || null,
            tags: form.tags.length > 0 ? form.tags : null,
            sms_consent: form.sms_consent,
            email_consent: form.email_consent
        };

        if (editingCustomer.value) {
            await api.customers.update(editingCustomer.value.id, data);
            window.$toast?.('Клиент обновлён', 'success');
        } else {
            await api.customers.create(data);
            window.$toast?.('Клиент добавлен', 'success');
        }

        closeAddModal();
        await posStore.loadCustomers();
    } catch (error) {
        log.error('Error saving customer:', error);
        const message = error.response?.data?.message || 'Ошибка сохранения';
        window.$toast?.(message, 'error');

        // Если клиент уже существует - показываем его
        if (error.response?.data?.data) {
            foundCustomer.value = error.response.data.data;
        }
    } finally {
        saving.value = false;
    }
};

const openDetailModal = async (customer) => {
    selectedCustomer.value = customer;
    showDetailModal.value = true;
    customerOrders.value = [];
    bonusHistory.value = [];
    loadingOrders.value = true;
    loadingBonuses.value = true;

    // Загружаем историю заказов и бонусов параллельно
    try {
        const [orders, bonuses] = await Promise.all([
            api.customers.getOrders(customer.id).catch(() => []),
            api.customers.getBonusHistory(customer.id).catch(() => [])
        ]);
        customerOrders.value = Array.isArray(orders) ? orders : (orders.data || []);
        bonusHistory.value = Array.isArray(bonuses) ? bonuses : (bonuses.data || []);
    } catch (error) {
        log.error('Error loading customer data:', error);
    } finally {
        loadingOrders.value = false;
        loadingBonuses.value = false;
    }
};

const closeDetailModal = () => {
    showDetailModal.value = false;
    selectedCustomer.value = null;
    customerOrders.value = [];
    bonusHistory.value = [];
    loadingOrders.value = false;
    loadingBonuses.value = false;
};

const toggleBlacklist = async (customer) => {
    const action = customer.is_blacklisted ? 'убрать из чёрного списка' : 'добавить в чёрный список';
    if (!confirm(`Вы уверены, что хотите ${action} клиента "${customer.name}"?`)) return;

    try {
        await api.customers.toggleBlacklist(customer.id);
        customer.is_blacklisted = !customer.is_blacklisted;
        window.$toast?.(
            customer.is_blacklisted ? 'Клиент добавлен в чёрный список' : 'Клиент убран из чёрного списка',
            'success'
        );
        await posStore.loadCustomers();
    } catch (error) {
        log.error('Error toggling blacklist:', error);
        window.$toast?.('Ошибка', 'error');
    }
};

// Lifecycle
onMounted(async () => {
    loading.value = true;
    try {
        await posStore.loadCustomers();
    } finally {
        loading.value = false;
    }
});
</script>
