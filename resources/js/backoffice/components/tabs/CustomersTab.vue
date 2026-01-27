<template>
    <div>
        <div class="bg-white rounded-xl shadow-sm p-6">
            <!-- Header -->
            <div class="flex items-center justify-between mb-6">
                <div class="flex items-center gap-4">
                    <input v-model="search"
                           type="text"
                           placeholder="Поиск по имени или телефону..."
                           class="w-80 px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                    <select v-model="loyaltyFilter" class="px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                        <option value="">Все уровни</option>
                        <option value="Бронзовый">Бронзовый</option>
                        <option value="Серебряный">Серебряный</option>
                        <option value="Золотой">Золотой</option>
                        <option value="Платиновый">Платиновый</option>
                    </select>
                </div>
                <button @click="openCustomerModal()" class="px-4 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition">
                    + Добавить клиента
                </button>
            </div>

            <!-- Stats Cards -->
            <div class="grid grid-cols-4 gap-4 mb-6">
                <div class="bg-gray-50 rounded-lg p-4">
                    <div class="text-2xl font-bold">{{ store.customers.length }}</div>
                    <div class="text-sm text-gray-500">Всего клиентов</div>
                </div>
                <div class="bg-green-50 rounded-lg p-4">
                    <div class="text-2xl font-bold text-green-600">{{ activeCustomers }}</div>
                    <div class="text-sm text-gray-500">Активных</div>
                </div>
                <div class="bg-yellow-50 rounded-lg p-4">
                    <div class="text-2xl font-bold text-yellow-600">{{ totalBonuses }}</div>
                    <div class="text-sm text-gray-500">Всего бонусов</div>
                </div>
                <div class="bg-blue-50 rounded-lg p-4">
                    <div class="text-2xl font-bold text-blue-600">{{ formatMoney(avgSpent) }}</div>
                    <div class="text-sm text-gray-500">Средний чек</div>
                </div>
            </div>

            <!-- Table -->
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="text-left text-sm text-gray-500 border-b">
                            <th class="pb-3 font-medium">Клиент</th>
                            <th class="pb-3 font-medium">Телефон</th>
                            <th class="pb-3 font-medium">Заказов</th>
                            <th class="pb-3 font-medium">Общая сумма</th>
                            <th class="pb-3 font-medium">Бонусы</th>
                            <th class="pb-3 font-medium">Уровень</th>
                            <th class="pb-3 font-medium">Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="customer in filteredCustomers" :key="customer.id"
                            class="border-b hover:bg-gray-50 transition cursor-pointer"
                            @click="openCustomerDetails(customer)">
                            <td class="py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full bg-orange-100 flex items-center justify-center text-orange-600 font-semibold">
                                        {{ customer.name?.charAt(0)?.toUpperCase() || '?' }}
                                    </div>
                                    <div>
                                        <div class="font-medium">{{ customer.name }}</div>
                                        <div class="text-sm text-gray-500">{{ customer.email }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="py-4">{{ customer.phone }}</td>
                            <td class="py-4">{{ customer.total_orders || 0 }}</td>
                            <td class="py-4">{{ formatMoney(customer.orders_total || customer.total_spent || 0) }}</td>
                            <td class="py-4">
                                <span class="font-medium text-orange-500">{{ customer.bonus_balance || 0 }}</span>
                            </td>
                            <td class="py-4">
                                <span :class="['px-2 py-1 rounded text-xs font-medium', getLoyaltyClass(customer.current_loyalty_level)]">
                                    {{ getLoyaltyLabel(customer.current_loyalty_level) }}
                                </span>
                            </td>
                            <td class="py-4">
                                <div class="flex items-center gap-2">
                                    <button @click.stop="openCustomerModal(customer)" class="p-1.5 hover:bg-gray-100 rounded">
                                        <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                                        </svg>
                                    </button>
                                    <button @click.stop="addBonus(customer)" class="p-1.5 hover:bg-gray-100 rounded" title="Начислить бонусы">
                                        <svg class="w-4 h-4 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="filteredCustomers.length === 0">
                            <td colspan="7" class="py-12 text-center text-gray-400">
                                <div class="text-4xl mb-2">👤</div>
                                <p>Клиенты не найдены</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Customer Modal -->
        <Teleport to="body">
            <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="showModal = false">
                <div class="bg-white rounded-2xl w-[500px] max-h-[90vh] overflow-hidden shadow-2xl">
                    <div class="p-6 border-b flex items-center justify-between">
                        <h3 class="text-lg font-semibold">{{ form.id ? 'Редактировать клиента' : 'Новый клиент' }}</h3>
                        <button @click="showModal = false" class="text-gray-400 hover:text-gray-600 text-xl">&times;</button>
                    </div>
                    <div class="p-6 space-y-4 overflow-y-auto max-h-[60vh]">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Имя *</label>
                            <input v-model="form.name" @blur="formatCustomerName" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent" placeholder="Иван Иванов">
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Телефон *</label>
                                <div class="relative">
                                    <input
                                        :value="form.phone"
                                        @input="onPhoneInput"
                                        @keypress="onlyDigits"
                                        type="tel"
                                        inputmode="numeric"
                                        class="w-full px-4 py-2 pr-10 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent transition-colors"
                                        :class="[
                                            form.phone && !isPhoneValid ? 'border-red-500' : 'border-gray-300',
                                            form.phone && isPhoneValid ? 'border-green-500' : ''
                                        ]"
                                        placeholder="+7 (___) ___-__-__"
                                    >
                                    <!-- Status icon -->
                                    <div class="absolute right-3 top-1/2 -translate-y-1/2">
                                        <svg v-if="form.phone && isPhoneValid" class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                        </svg>
                                        <svg v-else-if="form.phone && !isPhoneValid" class="w-5 h-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                                        </svg>
                                    </div>
                                </div>
                                <p v-if="form.phone && !isPhoneValid" class="text-red-500 text-xs mt-1">
                                    Ещё {{ phoneDigitsRemaining }} {{ phoneDigitsRemaining === 1 ? 'цифра' : phoneDigitsRemaining < 5 ? 'цифры' : 'цифр' }}
                                </p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                                <input v-model="form.email" type="email" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent" placeholder="email@example.com">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Дата рождения</label>
                            <input v-model="form.birthday" type="date" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Комментарий</label>
                            <textarea v-model="form.notes" rows="2" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent" placeholder="Аллергия на орехи, предпочитает угловой стол..."></textarea>
                        </div>
                        <div v-if="form.id">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Уровень лояльности</label>
                            <select v-model="form.loyalty_level" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                                <option value="bronze">Бронзовый</option>
                                <option value="silver">Серебряный</option>
                                <option value="gold">Золотой</option>
                                <option value="platinum">Платиновый</option>
                            </select>
                        </div>
                    </div>
                    <div class="p-6 border-t bg-gray-50 flex justify-end gap-3">
                        <button @click="showModal = false" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded-lg transition">Отмена</button>
                        <button @click="saveCustomer" class="px-4 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition disabled:opacity-50" :disabled="!form.name || !form.phone || !isPhoneValid">
                            {{ form.id ? 'Сохранить' : 'Создать' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Customer Details Modal -->
        <Teleport to="body">
            <div v-if="showDetailsModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="showDetailsModal = false">
                <div class="bg-white rounded-2xl w-[600px] max-h-[90vh] overflow-hidden shadow-2xl">
                    <div class="p-6 border-b flex items-center justify-between">
                        <div class="flex items-center gap-4">
                            <div class="w-14 h-14 rounded-full bg-orange-100 flex items-center justify-center text-2xl text-orange-600 font-semibold">
                                {{ selectedCustomer?.name?.charAt(0)?.toUpperCase() || '?' }}
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold">{{ selectedCustomer?.name }}</h3>
                                <p class="text-sm text-gray-500">{{ selectedCustomer?.phone }}</p>
                            </div>
                        </div>
                        <button @click="showDetailsModal = false" class="text-gray-400 hover:text-gray-600 text-xl">&times;</button>
                    </div>
                    <div class="p-6 overflow-y-auto max-h-[60vh]">
                        <!-- Stats -->
                        <div class="grid grid-cols-3 gap-4 mb-6">
                            <div class="bg-gray-50 rounded-lg p-4 text-center">
                                <div class="text-2xl font-bold">{{ customerOrders.length }}</div>
                                <div class="text-sm text-gray-500">Заказов</div>
                            </div>
                            <div class="bg-green-50 rounded-lg p-4 text-center">
                                <div class="text-2xl font-bold text-green-600">{{ formatMoney(paidOrdersTotal) }}</div>
                                <div class="text-sm text-gray-500">Потрачено</div>
                            </div>
                            <div class="bg-orange-50 rounded-lg p-4 text-center">
                                <div class="text-2xl font-bold text-orange-600">{{ selectedCustomer?.bonus_balance || 0 }}</div>
                                <div class="text-sm text-gray-500">Бонусов</div>
                            </div>
                        </div>

                        <!-- Info -->
                        <div class="space-y-4">
                            <div class="flex items-center justify-between py-2 border-b">
                                <span class="text-gray-500">Email</span>
                                <span>{{ selectedCustomer?.email || '-' }}</span>
                            </div>
                            <div class="flex items-center justify-between py-2 border-b">
                                <span class="text-gray-500">День рождения</span>
                                <span>{{ selectedCustomer?.birthday ? formatDate(selectedCustomer.birthday) : '-' }}</span>
                            </div>
                            <div class="flex items-center justify-between py-2 border-b">
                                <span class="text-gray-500">Уровень</span>
                                <span :class="['px-2 py-1 rounded text-xs font-medium', getLoyaltyClass(selectedCustomer?.current_loyalty_level)]">
                                    {{ getLoyaltyLabel(selectedCustomer?.current_loyalty_level) }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between py-2 border-b">
                                <span class="text-gray-500">Первый заказ</span>
                                <span>{{ selectedCustomer?.first_order_at ? formatDate(selectedCustomer.first_order_at) : '-' }}</span>
                            </div>
                            <div class="flex items-center justify-between py-2 border-b">
                                <span class="text-gray-500">Последний заказ</span>
                                <span>{{ selectedCustomer?.last_order_at ? formatDate(selectedCustomer.last_order_at) : '-' }}</span>
                            </div>
                            <div v-if="selectedCustomer?.notes" class="py-2">
                                <span class="text-gray-500 block mb-1">Заметки</span>
                                <p class="bg-gray-50 rounded-lg p-3 text-sm">{{ selectedCustomer.notes }}</p>
                            </div>
                        </div>

                        <!-- Полная история клиента -->
                        <div class="mt-6">
                            <h4 class="text-sm font-semibold text-gray-700 mb-3 flex items-center gap-2">
                                <svg class="w-4 h-4 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                </svg>
                                История
                            </h4>

                            <div v-if="loadingHistory" class="text-center py-4">
                                <div class="animate-spin w-6 h-6 border-2 border-orange-500 border-t-transparent rounded-full mx-auto"></div>
                            </div>

                            <div v-else-if="unifiedHistory.length === 0" class="text-center py-4 text-gray-400 text-sm">
                                Нет истории
                            </div>

                            <div v-else class="space-y-2 max-h-64 overflow-y-auto">
                                <!-- Заказ -->
                                <div v-for="item in unifiedHistory" :key="item.key"
                                     :class="[
                                         'py-2 px-3 rounded-lg text-sm',
                                         item.type === 'order' ? 'bg-gray-50' : 'bg-orange-50'
                                     ]">
                                    <!-- Заказ -->
                                    <template v-if="item.type === 'order'">
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center gap-2">
                                                <span class="text-lg">
                                                    {{ item.order_type === 'delivery' ? '🚗' : item.order_type === 'pickup' ? '🛍️' : '🍽️' }}
                                                </span>
                                                <div>
                                                    <div class="font-medium flex items-center gap-2">
                                                        Заказ #{{ item.order_number }}
                                                        <span :class="[
                                                            'px-1.5 py-0.5 text-[10px] rounded font-medium',
                                                            item.order_type === 'delivery' ? 'bg-orange-100 text-orange-600' :
                                                            item.order_type === 'pickup' ? 'bg-purple-100 text-purple-600' :
                                                            'bg-emerald-100 text-emerald-600'
                                                        ]">
                                                            {{ item.order_type === 'delivery' ? 'Доставка' : item.order_type === 'pickup' ? 'Самовывоз' : 'Зал' }}
                                                        </span>
                                                        <!-- Статус заказа -->
                                                        <span v-if="item.status === 'cancelled'" class="px-1.5 py-0.5 text-[10px] rounded font-medium bg-red-100 text-red-600">
                                                            Отменён
                                                        </span>
                                                        <span v-else-if="item.payment_status !== 'paid'" class="px-1.5 py-0.5 text-[10px] rounded font-medium bg-yellow-100 text-yellow-700">
                                                            Не оплачен
                                                        </span>
                                                    </div>
                                                    <div class="text-xs text-gray-400">
                                                        {{ formatDateTime(item.date) }} • {{ item.items_count }} поз.
                                                        <span v-if="item.cancellation_reason" class="ml-1 text-red-400">
                                                            ({{ item.cancellation_reason }})
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="text-right">
                                                <div :class="[
                                                    'font-semibold',
                                                    item.status === 'cancelled' ? 'text-gray-400 line-through' : 'text-gray-800'
                                                ]">
                                                    {{ formatMoney(item.total) }}
                                                </div>
                                                <div v-if="item.bonus_earned > 0 || item.bonus_spent > 0" class="text-xs">
                                                    <span v-if="item.bonus_earned > 0" class="text-green-600">+{{ item.bonus_earned }}</span>
                                                    <span v-if="item.bonus_earned > 0 && item.bonus_spent > 0"> / </span>
                                                    <span v-if="item.bonus_spent > 0" class="text-red-500">-{{ item.bonus_spent }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </template>

                                    <!-- Ручная бонусная операция -->
                                    <template v-else>
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center gap-2">
                                                <span class="text-lg">{{ item.type_icon || (item.amount > 0 ? '➕' : '➖') }}</span>
                                                <div>
                                                    <div class="font-medium">{{ item.description || item.type_label || (item.amount > 0 ? 'Начисление бонусов' : 'Списание бонусов') }}</div>
                                                    <div class="text-xs text-gray-400">{{ formatDateTime(item.date) }}</div>
                                                </div>
                                            </div>
                                            <div :class="[
                                                'font-semibold',
                                                item.amount > 0 ? 'text-green-600' : 'text-red-500'
                                            ]">
                                                {{ item.amount > 0 ? '+' : '' }}{{ item.amount }}
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="p-6 border-t bg-gray-50 flex justify-end gap-3">
                        <button @click="openCustomerModal(selectedCustomer)" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded-lg transition">Редактировать</button>
                        <button @click="addBonus(selectedCustomer)" class="px-4 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition">Начислить бонусы</button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Add Bonus Modal -->
        <Teleport to="body">
            <div v-if="showBonusModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="showBonusModal = false">
                <div class="bg-white rounded-2xl w-[400px] p-6 shadow-2xl">
                    <h3 class="text-lg font-semibold mb-4">Начисление бонусов</h3>
                    <p class="text-gray-500 mb-4">Клиент: {{ bonusCustomer?.name }}</p>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Количество бонусов</label>
                            <input v-model.number="bonusAmount" type="number" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent" placeholder="100">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Причина</label>
                            <input v-model="bonusReason" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent" placeholder="День рождения, акция...">
                        </div>
                    </div>
                    <div class="flex gap-3 mt-6">
                        <button @click="showBonusModal = false" class="flex-1 px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded-lg transition">Отмена</button>
                        <button @click="saveBonusAdd" :disabled="!bonusAmount" class="flex-1 px-4 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition disabled:opacity-50">
                            Начислить
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useBackofficeStore } from '../../stores/backoffice';

const store = useBackofficeStore();

// State
const search = ref('');
const loyaltyFilter = ref('');
const showModal = ref(false);
const showDetailsModal = ref(false);
const showBonusModal = ref(false);
const selectedCustomer = ref(null);
const bonusCustomer = ref(null);
const bonusAmount = ref(0);
const bonusReason = ref('');
const phoneError = ref('');
const bonusHistory = ref([]);
const customerOrders = ref([]);
const loadingHistory = ref(false);

// Form
const form = ref({
    id: null,
    name: '',
    phone: '',
    email: '',
    birthday: '',
    notes: '',
    loyalty_level: 'bronze'
});

// Computed
const filteredCustomers = computed(() => {
    let list = store.customers;

    if (search.value) {
        const s = search.value.toLowerCase();
        list = list.filter(c =>
            c.name?.toLowerCase().includes(s) ||
            c.phone?.includes(s) ||
            c.email?.toLowerCase().includes(s)
        );
    }

    if (loyaltyFilter.value) {
        list = list.filter(c => c.current_loyalty_level?.name === loyaltyFilter.value);
    }

    return list;
});

const activeCustomers = computed(() => {
    const thirtyDaysAgo = new Date();
    thirtyDaysAgo.setDate(thirtyDaysAgo.getDate() - 30);
    return store.customers.filter(c => c.last_order_at && new Date(c.last_order_at) > thirtyDaysAgo).length;
});

const totalBonuses = computed(() => {
    return store.customers.reduce((sum, c) => sum + (c.bonus_balance || 0), 0);
});

const avgSpent = computed(() => {
    if (!store.customers.length) return 0;
    const total = store.customers.reduce((sum, c) => sum + (c.orders_total || c.total_spent || 0), 0);
    return total / store.customers.length;
});

// Сумма только оплаченных заказов
const paidOrdersTotal = computed(() => {
    return customerOrders.value
        .filter(o => o.payment_status === 'paid' && o.status !== 'cancelled')
        .reduce((sum, o) => sum + (o.total || 0), 0);
});

// Объединённая история клиента (заказы + ручные бонусные операции)
const unifiedHistory = computed(() => {
    const items = [];

    // Добавляем все заказы
    for (const order of customerOrders.value) {
        items.push({
            key: `order-${order.id}`,
            type: 'order',
            id: order.id,
            order_number: order.order_number || order.daily_number,
            order_type: order.type,
            status: order.status,
            payment_status: order.payment_status,
            total: order.total,
            bonus_earned: order.bonus_earned || 0,
            bonus_spent: order.bonus_spent || 0,
            items_count: order.items_count,
            date: order.created_at,
            sortDate: new Date(order.created_at).getTime(),
            cancelled_at: order.cancelled_at,
            cancellation_reason: order.cancellation_reason
        });
    }

    // Добавляем только ручные бонусные операции (без order_id)
    for (const bonus of bonusHistory.value) {
        if (!bonus.order_id) {
            items.push({
                key: `bonus-${bonus.id}`,
                type: 'bonus',
                id: bonus.id,
                bonus_type: bonus.type,
                type_label: bonus.type_label,
                type_icon: bonus.type_icon,
                amount: bonus.amount,
                description: bonus.description,
                date: bonus.created_at,
                sortDate: new Date(bonus.created_at).getTime()
            });
        }
    }

    // Сортируем по дате (новые сверху)
    items.sort((a, b) => b.sortDate - a.sortDate);

    return items;
});

// Проверка валидности телефона
const isPhoneValid = computed(() => {
    const digits = (form.value.phone || '').replace(/\D/g, '');
    return digits.length >= 11;
});

// Сколько цифр осталось ввести
const phoneDigitsRemaining = computed(() => {
    const digits = (form.value.phone || '').replace(/\D/g, '');
    return Math.max(0, 11 - digits.length);
});

// Methods

// Блокировка ввода букв - только цифры
function onlyDigits(e) {
    const char = String.fromCharCode(e.which || e.keyCode);
    if (!/[\d]/.test(char)) {
        e.preventDefault();
    }
}

// Форматирование телефона
function formatPhoneDisplay(phone) {
    if (!phone) return '';
    let digits = phone.replace(/\D/g, '');

    // 8 -> 7
    if (digits.startsWith('8') && digits.length > 1) {
        digits = '7' + digits.slice(1);
    }
    // Добавляем 7 если нет
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
}

// Обработчик ввода телефона
function onPhoneInput(event) {
    const input = event.target;
    const inputValue = input.value;
    const cursorPos = input.selectionStart;

    form.value.phone = formatPhoneDisplay(inputValue);

    // Валидация
    const digits = form.value.phone.replace(/\D/g, '');
    if (form.value.phone && digits.length < 11) {
        phoneError.value = 'Введите полный номер телефона';
    } else {
        phoneError.value = '';
    }

    // Восстановление позиции курсора
    setTimeout(() => {
        const digitsBeforeCursor = inputValue.slice(0, cursorPos).replace(/\D/g, '').length;
        let newPos = 0;
        let digitCount = 0;
        for (let i = 0; i < form.value.phone.length; i++) {
            if (/\d/.test(form.value.phone[i])) {
                digitCount++;
                if (digitCount === digitsBeforeCursor) {
                    newPos = i + 1;
                    break;
                }
            }
            newPos = i + 1;
        }
        if (newPos < 4 && form.value.phone.length >= 4) newPos = 4;
        if (newPos > form.value.phone.length) newPos = form.value.phone.length;
        input.setSelectionRange(newPos, newPos);
    }, 0);
}

// Форматирование имени (первая буква каждого слова заглавная)
function formatCustomerName() {
    if (!form.value.name) return;
    const words = form.value.name.trim().replace(/\s+/g, ' ').split(' ');
    form.value.name = words.map(word => {
        if (!word) return '';
        return word.charAt(0).toUpperCase() + word.slice(1).toLowerCase();
    }).join(' ');
}
function formatMoney(val) {
    return new Intl.NumberFormat('ru-RU', { style: 'currency', currency: 'RUB', maximumFractionDigits: 0 }).format(val || 0);
}

function formatDate(dateStr) {
    if (!dateStr) return '';
    return new Date(dateStr).toLocaleDateString('ru-RU');
}

function formatDateTime(dateStr) {
    if (!dateStr) return '';
    const date = new Date(dateStr);
    return date.toLocaleDateString('ru-RU') + ' ' + date.toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' });
}

function getLoyaltyClass(level) {
    // Поддержка объекта уровня лояльности из API
    if (level && typeof level === 'object') {
        // Используем цвет из объекта если есть
        if (level.color) {
            return `bg-opacity-20 text-gray-700`;
        }
        // Fallback по имени
        const name = level.name?.toLowerCase() || '';
        if (name.includes('бронз')) return 'bg-amber-100 text-amber-700';
        if (name.includes('сереб')) return 'bg-gray-200 text-gray-700';
        if (name.includes('золот')) return 'bg-yellow-100 text-yellow-700';
        if (name.includes('платин')) return 'bg-purple-100 text-purple-700';
    }
    // Поддержка строковых кодов (legacy)
    const classes = {
        bronze: 'bg-amber-100 text-amber-700',
        silver: 'bg-gray-200 text-gray-700',
        gold: 'bg-yellow-100 text-yellow-700',
        platinum: 'bg-purple-100 text-purple-700'
    };
    return classes[level] || 'bg-gray-100 text-gray-700';
}

function getLoyaltyLabel(level) {
    // Поддержка объекта уровня лояльности из API
    if (level && typeof level === 'object') {
        return level.name || 'Новый';
    }
    // Поддержка строковых кодов (legacy)
    const labels = {
        bronze: 'Бронзовый',
        silver: 'Серебряный',
        gold: 'Золотой',
        platinum: 'Платиновый'
    };
    return labels[level] || 'Новый';
}

function openCustomerModal(customer = null) {
    showDetailsModal.value = false;
    phoneError.value = '';
    if (customer) {
        form.value = { ...customer };
        // Форматируем телефон для отображения
        if (form.value.phone) {
            form.value.phone = formatPhoneDisplay(form.value.phone);
        }
    } else {
        form.value = {
            id: null,
            name: '',
            phone: '',
            email: '',
            birthday: '',
            notes: '',
            loyalty_level: 'bronze'
        };
    }
    showModal.value = true;
}

async function openCustomerDetails(customer) {
    selectedCustomer.value = customer;
    bonusHistory.value = [];
    customerOrders.value = [];
    loadingHistory.value = true;
    showDetailsModal.value = true;

    // Загружаем историю заказов и бонусов параллельно
    try {
        const [ordersResponse, bonusResponse] = await Promise.all([
            store.api(`/customers/${customer.id}/all-orders`),
            store.api(`/customers/${customer.id}/bonus-history`)
        ]);
        customerOrders.value = ordersResponse?.data || [];
        bonusHistory.value = bonusResponse?.data || [];
    } catch (e) {
        console.error('Error loading customer history:', e);
        customerOrders.value = [];
        bonusHistory.value = [];
    } finally {
        loadingHistory.value = false;
    }
}

async function saveCustomer() {
    if (!form.value.name || !form.value.phone) {
        store.showToast('Заполните имя и телефон', 'error');
        return;
    }

    try {
        const url = form.value.id
            ? `/backoffice/customers/${form.value.id}`
            : '/backoffice/customers';
        const method = form.value.id ? 'PUT' : 'POST';

        await store.api(url, {
            method,
            body: JSON.stringify(form.value)
        });

        showModal.value = false;
        store.loadCustomers();
        store.showToast(form.value.id ? 'Клиент обновлён' : 'Клиент создан', 'success');
    } catch (e) {
        store.showToast('Ошибка сохранения', 'error');
    }
}

function addBonus(customer) {
    bonusCustomer.value = customer;
    bonusAmount.value = 0;
    bonusReason.value = '';
    showBonusModal.value = true;
}

async function saveBonusAdd() {
    if (!bonusAmount.value || !bonusCustomer.value) return;

    try {
        await store.api(`/backoffice/customers/${bonusCustomer.value.id}/bonus`, {
            method: 'POST',
            body: JSON.stringify({
                points: bonusAmount.value,
                reason: bonusReason.value
            })
        });

        showBonusModal.value = false;
        store.loadCustomers();
        store.showToast(`Начислено ${bonusAmount.value} бонусов`, 'success');

        // Обновляем историю, если открыта карточка этого клиента
        if (showDetailsModal.value && selectedCustomer.value?.id === bonusCustomer.value.id) {
            loadingHistory.value = true;
            try {
                const [ordersResponse, bonusResponse] = await Promise.all([
                    store.api(`/customers/${bonusCustomer.value.id}/all-orders`),
                    store.api(`/customers/${bonusCustomer.value.id}/bonus-history`)
                ]);
                customerOrders.value = ordersResponse?.data || [];
                bonusHistory.value = bonusResponse?.data || [];
            } catch (e) {
                console.error('Error reloading customer history:', e);
            } finally {
                loadingHistory.value = false;
            }
        }
    } catch (e) {
        store.showToast('Ошибка начисления', 'error');
    }
}

// Init
onMounted(() => {
    if (store.customers.length === 0) {
        store.loadCustomers();
    }
});
</script>
