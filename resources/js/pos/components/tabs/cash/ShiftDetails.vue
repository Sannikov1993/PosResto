<template>
    <div class="flex-1 flex min-h-0">
        <!-- Левая колонка - статистика -->
        <div class="w-80 border-r border-gray-800 flex flex-col flex-shrink-0 bg-dark-900/50">
            <!-- Header с кнопкой назад -->
            <div class="flex items-center justify-between px-4 py-3 border-b border-gray-800">
                <button
                    @click="$emit('back')"
                    class="text-accent hover:text-orange-400 flex items-center gap-1 font-medium"
                >
                    ‹ Смены
                </button>
                <span class="text-accent font-medium">{{ formatDate(shift.opened_at) }}</span>
            </div>

            <!-- Статистика смены -->
            <div class="flex-1 overflow-y-auto p-4 space-y-4">
                <!-- Статус смены -->
                <div class="flex items-center gap-2 text-sm flex-wrap">
                    <span
                        :class="[
                            'w-2 h-2 rounded-full',
                            shift.status === 'open' ? 'bg-green-500 animate-pulse' : 'bg-gray-500'
                        ]"
                    ></span>
                    <span :class="shift.status === 'open' ? 'text-green-400' : 'text-gray-400'">
                        {{ shift.status === 'open' ? 'Открыта' : 'Закрыта' }}
                    </span>
                    <span class="text-gray-500">{{ formatTime(shift.opened_at) }}</span>
                    <template v-if="shift.closed_at">
                        <span class="text-gray-600">→</span>
                        <span class="text-gray-500">{{ formatTime(shift.closed_at) }}</span>
                    </template>
                    <span class="text-gray-600">·</span>
                    <span class="text-gray-500">{{ shiftDuration }}</span>
                </div>

                <!-- Кассир -->
                <div v-if="shift.cashier" class="flex items-center gap-2 text-sm mt-2">
                    <span class="text-gray-500">Кассир:</span>
                    <span class="text-white">{{ shift.cashier.name }}</span>
                </div>

                <!-- Выручка -->
                <div class="space-y-2 pt-2 border-t border-gray-800">
                    <div class="flex justify-between items-center">
                        <span class="text-gray-400 text-sm flex items-center gap-2">
                            <span class="w-1 h-4 bg-green-500 rounded"></span>
                            Выручка
                        </span>
                        <span class="text-white font-semibold">
                            {{ formatMoney(shift.total_revenue) }} ₽
                        </span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-500 text-sm pl-3">Заказов</span>
                        <span class="text-gray-400">{{ shift.orders_count || 0 }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-500 text-sm pl-3">Средний чек</span>
                        <span class="text-gray-400">{{ formatMoney(shift.avg_check || 0) }} ₽</span>
                    </div>
                </div>

                <!-- Форма оплаты -->
                <div class="space-y-2 pt-2 border-t border-gray-800">
                    <p class="text-xs text-gray-500 mb-2">Форма оплаты</p>
                    <button
                        @click="togglePaymentFilter('card')"
                        :class="[
                            'w-full flex justify-between items-center px-3 py-2 rounded-lg transition-all',
                            paymentFilter === 'card'
                                ? 'bg-blue-900/40 ring-1 ring-blue-500'
                                : 'bg-dark-800 hover:bg-dark-700'
                        ]"
                    >
                        <span class="text-gray-400 text-sm flex items-center gap-2">💳 Картой</span>
                        <span class="text-gray-300 font-medium">{{ formatMoney(shift.total_card) }} ₽</span>
                    </button>
                    <button
                        @click="togglePaymentFilter('cash')"
                        :class="[
                            'w-full flex justify-between items-center px-3 py-2 rounded-lg transition-all',
                            paymentFilter === 'cash'
                                ? 'bg-yellow-900/40 ring-1 ring-yellow-500'
                                : 'bg-dark-800 hover:bg-dark-700'
                        ]"
                    >
                        <span class="text-gray-400 text-sm flex items-center gap-2">💵 Наличные</span>
                        <span class="text-gray-300 font-medium">{{ formatMoney(shift.total_cash) }} ₽</span>
                    </button>
                    <button
                        v-if="totalMixed > 0"
                        @click="togglePaymentFilter('mixed')"
                        :class="[
                            'w-full flex justify-between items-center px-3 py-2 rounded-lg transition-all',
                            paymentFilter === 'mixed'
                                ? 'bg-purple-900/40 ring-1 ring-purple-500'
                                : 'bg-dark-800 hover:bg-dark-700'
                        ]"
                    >
                        <span class="text-gray-400 text-sm flex items-center gap-2">💳+💵 Смешанные</span>
                        <span class="text-gray-300 font-medium">{{ formatMoney(totalMixed) }} ₽</span>
                    </button>
                    <button
                        @click="togglePaymentFilter('online')"
                        :class="[
                            'w-full flex justify-between items-center px-3 py-2 rounded-lg transition-all',
                            paymentFilter === 'online'
                                ? 'bg-cyan-900/40 ring-1 ring-cyan-500'
                                : 'bg-dark-800 hover:bg-dark-700'
                        ]"
                    >
                        <span class="text-gray-400 text-sm flex items-center gap-2">🌐 Онлайн</span>
                        <span :class="shift.total_online > 0 ? 'text-gray-300' : 'text-gray-600'" class="font-medium">
                            {{ formatMoney(shift.total_online || 0) }} ₽
                        </span>
                    </button>
                </div>

                <!-- Операции с кассой -->
                <div v-if="totalDeposits > 0 || totalWithdrawals > 0 || totalRefunds > 0" class="space-y-2 pt-2 border-t border-gray-800">
                    <p class="text-xs text-gray-500 mb-2">Операции с кассой</p>
                    <button
                        v-if="totalDeposits > 0"
                        @click="toggleFilter('deposit')"
                        :class="[
                            'w-full flex justify-between items-center px-3 py-2 rounded-lg transition-all',
                            activeFilter === 'deposit'
                                ? 'bg-green-900/40 ring-1 ring-green-500'
                                : 'bg-dark-800 hover:bg-dark-700'
                        ]"
                    >
                        <span class="text-green-400 flex items-center gap-2 text-sm">↓ Внесено</span>
                        <span class="text-green-400 font-medium">+{{ formatMoney(totalDeposits) }} ₽</span>
                    </button>
                    <button
                        v-if="totalWithdrawals > 0"
                        @click="toggleFilter('withdrawal')"
                        :class="[
                            'w-full flex justify-between items-center px-3 py-2 rounded-lg transition-all',
                            activeFilter === 'withdrawal'
                                ? 'bg-red-900/40 ring-1 ring-red-500'
                                : 'bg-dark-800 hover:bg-dark-700'
                        ]"
                    >
                        <span class="text-red-400 flex items-center gap-2 text-sm">↑ Изъято</span>
                        <span class="text-red-400 font-medium">-{{ formatMoney(totalWithdrawals) }} ₽</span>
                    </button>
                    <button
                        v-if="totalRefunds > 0"
                        @click="toggleFilter('refund')"
                        :class="[
                            'w-full flex justify-between items-center px-3 py-2 rounded-lg transition-all',
                            activeFilter === 'refund'
                                ? 'bg-orange-900/40 ring-1 ring-orange-500'
                                : 'bg-dark-800 hover:bg-dark-700'
                        ]"
                    >
                        <span class="text-orange-400 flex items-center gap-2 text-sm">↩ Возвраты</span>
                        <span class="text-orange-400 font-medium">-{{ formatMoney(totalRefunds) }} ₽</span>
                    </button>
                </div>

                <!-- Итог в кассе -->
                <div class="pt-2 border-t border-gray-800">
                    <div class="flex justify-between items-center bg-dark-800 rounded-lg px-3 py-2">
                        <span class="text-gray-400 text-sm">💰 В кассе</span>
                        <span class="text-white font-semibold text-lg">{{ formatMoney(shift.current_cash || 0) }} ₽</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Правая колонка - список операций -->
        <div class="flex-1 flex flex-col min-h-0">
            <!-- Tabs фильтрации -->
            <div class="flex items-center gap-1 px-4 py-2 border-b border-gray-800 bg-dark-900/50 flex-shrink-0">
                <button
                    v-for="tab in filterTabs"
                    :key="tab.value"
                    @click="setFilter(tab.value)"
                    :class="[
                        'px-3 py-1.5 rounded-lg text-xs font-medium transition-all',
                        activeFilter === tab.value
                            ? tab.activeClass
                            : 'text-gray-400 hover:text-white hover:bg-dark-700'
                    ]"
                >
                    {{ tab.label }}
                    <span v-if="tab.count > 0" class="ml-1 opacity-70">({{ tab.count }})</span>
                </button>

                <!-- Индикатор фильтра по оплате -->
                <span
                    v-if="paymentFilter"
                    class="ml-2 px-2 py-1 rounded text-xs bg-dark-700"
                    :class="{
                        'text-blue-400': paymentFilter === 'card',
                        'text-yellow-400': paymentFilter === 'cash',
                        'text-purple-400': paymentFilter === 'mixed',
                        'text-cyan-400': paymentFilter === 'online'
                    }"
                >
                    {{ paymentFilter === 'card' ? '💳' : paymentFilter === 'cash' ? '💵' : paymentFilter === 'mixed' ? '💳+💵' : '🌐' }}
                </span>

                <!-- Кнопка сброса -->
                <button
                    v-if="hasActiveFilters"
                    @click="clearAllFilters"
                    class="ml-auto px-2 py-1 text-gray-500 hover:text-white text-xs"
                >
                    ✕ Сбросить
                </button>
            </div>

            <!-- Список операций -->
            <div class="flex-1 overflow-y-auto">
                <template v-for="op in filteredOperations" :key="op.id">
                    <!-- Внесение -->
                    <div
                        v-if="op.type === 'deposit'"
                        class="flex items-center gap-2 px-4 py-2 border-b border-gray-800/50 bg-green-900/20 border-l-2 border-green-500"
                    >
                        <span class="text-gray-500 text-xs w-12">{{ formatTime(op.time) }}</span>
                        <span class="text-green-400 text-sm">↓</span>
                        <span class="text-green-400 font-medium w-24">+{{ formatMoney(op.amount) }} ₽</span>
                        <div class="flex-1 min-w-0">
                            <span class="text-green-400 text-xs font-medium mr-2">ВНЕСЕНИЕ</span>
                            <span v-if="op.description" class="text-gray-500 text-sm">{{ op.description }}</span>
                        </div>
                    </div>

                    <!-- Изъятие -->
                    <div
                        v-else-if="op.type === 'withdrawal'"
                        class="flex items-center gap-2 px-4 py-2 border-b border-gray-800/50 bg-red-900/20 border-l-2 border-red-500"
                    >
                        <span class="text-gray-500 text-xs w-12">{{ formatTime(op.time) }}</span>
                        <span class="text-red-400 text-sm">↑</span>
                        <span class="text-red-400 font-medium w-24">-{{ formatMoney(op.amount) }} ₽</span>
                        <div class="flex-1 min-w-0">
                            <span class="text-red-400 text-xs font-medium mr-2">ИЗЪЯТИЕ</span>
                            <span class="text-gray-400 text-xs mr-2">{{ withdrawalCategories[op.category] || '' }}</span>
                            <span v-if="op.description" class="text-gray-500 text-sm">{{ op.description }}</span>
                        </div>
                    </div>

                    <!-- Предоплата -->
                    <div
                        v-else-if="op.type === 'prepayment'"
                        class="flex items-center gap-2 px-4 py-2 border-b border-gray-800/50 bg-purple-900/20 border-l-2 border-purple-500"
                    >
                        <span class="text-gray-500 text-xs w-12">{{ formatTime(op.time) }}</span>
                        <span class="text-purple-400 text-sm">⏰</span>
                        <span class="text-white font-medium w-24">{{ formatMoney(op.amount) }} ₽</span>
                        <span class="text-gray-500 text-sm">{{ getPaymentIcon(op.payment_method) }}</span>
                        <div class="flex-1 min-w-0">
                            <span class="text-purple-400 text-xs font-medium mr-2">ПРЕДОПЛАТА</span>
                            <span class="text-gray-500 text-sm truncate">{{ op.data.description }}</span>
                        </div>
                    </div>

                    <!-- Возврат депозита -->
                    <div
                        v-else-if="op.type === 'refund'"
                        class="flex items-center gap-2 px-4 py-2 border-b border-gray-800/50 bg-orange-900/20 border-l-2 border-orange-500"
                    >
                        <span class="text-gray-500 text-xs w-12">{{ formatTime(op.time) }}</span>
                        <span class="text-orange-400 text-sm">↩</span>
                        <span class="text-orange-400 font-medium w-24">-{{ formatMoney(op.amount) }} ₽</span>
                        <span class="text-gray-500 text-sm">{{ getPaymentIcon(op.payment_method) }}</span>
                        <div class="flex-1 min-w-0">
                            <span class="text-orange-400 text-xs font-medium mr-2">ВОЗВРАТ</span>
                            <span class="text-gray-500 text-sm truncate">{{ op.description }}</span>
                        </div>
                    </div>

                    <!-- Заказ -->
                    <div
                        v-else-if="op.type === 'order'"
                        class="border-b border-gray-800/50 hover:bg-dark-900/50 cursor-pointer"
                        @click="toggleOrderDetails(op)"
                    >
                        <div class="flex items-center gap-2 px-4 py-2">
                            <span class="text-gray-500 text-xs w-12">{{ formatTime(op.time) }}</span>
                            <span class="text-green-400 text-sm">✓</span>
                            <span class="text-white font-medium w-24">{{ formatMoney(op.amount) }} ₽</span>
                            <span class="text-gray-500 text-sm">{{ getPaymentIcon(op.payment_method) }}</span>
                            <span v-if="hasDiscounts(op)" class="text-purple-400 text-xs" title="Есть скидки">%</span>
                            <div class="flex-1 min-w-0 flex items-center gap-2">
                                <span
                                    v-if="op.data.type"
                                    :class="[
                                        'px-1.5 py-0.5 text-[10px] rounded uppercase tracking-wide flex-shrink-0',
                                        op.data.type === 'delivery' ? 'bg-orange-900/50 text-orange-400' :
                                        op.data.type === 'pickup' ? 'bg-purple-900/50 text-purple-400' :
                                        'bg-emerald-900/50 text-emerald-400'
                                    ]"
                                >
                                    {{ getOrderTypeLabel(op.data.type) }}
                                </span>
                                <span class="text-gray-400 text-xs">#{{ op.data.order_number }}</span>
                                <span v-if="op.guestNumbers && op.guestNumbers.length" class="text-purple-400 text-xs">
                                    Гость {{ op.guestNumbers.join(', ') }}
                                </span>
                                <span v-else-if="op.data.table" class="text-blue-400 text-xs">
                                    {{ op.data.table.name || 'Стол ' + op.data.table.number }}
                                </span>
                                <span class="text-gray-500 text-sm truncate">{{ getOperationItemsText(op) }}</span>
                            </div>
                            <span class="text-gray-600 text-xs">{{ expandedOrder === op.id ? '▲' : '▼' }}</span>
                        </div>
                        <!-- Развёрнутый список товаров -->
                        <div v-if="expandedOrder === op.id" class="px-4 pb-3 bg-dark-800/50">
                            <div class="border-t border-gray-800 pt-2">
                                <div
                                    v-for="item in getOperationItems(op)"
                                    :key="item.id || item.name"
                                    class="flex items-center justify-between py-1 text-sm"
                                >
                                    <div class="flex items-center gap-2">
                                        <span class="text-gray-400">{{ item.quantity }}×</span>
                                        <span class="text-gray-300">{{ item.name }}</span>
                                        <span v-if="item.guest_number" class="text-purple-400/70 text-xs">(Гость {{ item.guest_number }})</span>
                                    </div>
                                    <span class="text-gray-400">{{ formatMoney(item.price * item.quantity) }} ₽</span>
                                </div>
                                <div v-if="!getOperationItems(op).length" class="text-gray-600 text-sm py-2">
                                    Нет данных о товарах
                                </div>

                                <!-- Скидки -->
                                <template v-if="hasDiscounts(op)">
                                    <div class="border-t border-gray-700 mt-2 pt-2">
                                        <!-- Сумма до скидок -->
                                        <div v-if="op.data.subtotal && op.data.subtotal != op.amount" class="flex justify-between py-1 text-sm">
                                            <span class="text-gray-500">Сумма до скидок</span>
                                            <span class="text-gray-400">{{ formatMoney(op.data.subtotal) }} ₽</span>
                                        </div>
                                        <!-- Скидка уровня лояльности -->
                                        <div v-if="op.data.loyalty_discount_amount > 0" class="flex justify-between py-1 text-sm">
                                            <span class="text-purple-400">
                                                Скидка "{{ op.data.loyalty_level?.name || 'Уровень' }}"
                                                <span class="text-purple-400/70">({{ op.data.loyalty_level?.discount_percent || calculatePercent(op.data.loyalty_discount_amount, op.data.subtotal) }}%)</span>
                                            </span>
                                            <span class="text-purple-400">-{{ formatMoney(op.data.loyalty_discount_amount) }} ₽</span>
                                        </div>
                                        <!-- Ручная скидка -->
                                        <div v-if="op.data.discount_amount > 0" class="flex justify-between py-1 text-sm">
                                            <span class="text-green-400">
                                                Скидка<template v-if="op.data.discount_reason">: {{ op.data.discount_reason }}</template>
                                                <span v-if="!op.data.discount_reason?.includes('%')" class="text-green-400/70">({{ calculatePercent(op.data.discount_amount, op.data.subtotal) }}%)</span>
                                            </span>
                                            <span class="text-green-400">-{{ formatMoney(op.data.discount_amount) }} ₽</span>
                                        </div>
                                        <!-- Бонусы -->
                                        <div v-if="op.data.bonus_used > 0" class="flex justify-between py-1 text-sm">
                                            <span class="text-yellow-400">
                                                Оплата бонусами
                                                <span class="text-yellow-400/70">({{ calculatePercent(op.data.bonus_used, op.data.subtotal) }}%)</span>
                                            </span>
                                            <span class="text-yellow-400">-{{ formatMoney(op.data.bonus_used) }} ₽</span>
                                        </div>
                                        <!-- Итого -->
                                        <div class="flex justify-between py-1 text-sm font-medium border-t border-gray-700 mt-1 pt-1">
                                            <span class="text-white">Итого к оплате</span>
                                            <span class="text-white">{{ formatMoney(op.data.total || op.amount) }} ₽</span>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </template>

                <!-- Пустое состояние -->
                <div
                    v-if="!filteredOperations.length"
                    class="flex flex-col items-center justify-center py-20 text-gray-500"
                >
                    <template v-if="hasActiveFilters && allOperations.length">
                        <p class="text-4xl mb-4">🔍</p>
                        <p>Нет операций по фильтру</p>
                        <button
                            @click="clearAllFilters"
                            class="mt-3 px-4 py-2 bg-dark-700 hover:bg-dark-600 rounded-lg text-sm"
                        >
                            Показать все
                        </button>
                    </template>
                    <template v-else>
                        <p class="text-4xl mb-4">💰</p>
                        <p>Нет операций</p>
                    </template>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
    shift: {
        type: Object,
        required: true
    },
    orders: {
        type: Array,
        default: () => []
    },
    prepayments: {
        type: Array,
        default: () => []
    }
});

defineEmits(['back']);

// Фильтры операций
const activeFilter = ref(null);
const paymentFilter = ref(null);

// Развёрнутый заказ
const expandedOrder = ref(null);

const toggleOrderDetails = (op) => {
    if (expandedOrder.value === op.id) {
        expandedOrder.value = null;
    } else {
        expandedOrder.value = op.id;
    }
};

// Операции внесения/изъятия/возвратов из смены
const cashOperations = computed(() => {
    if (!props.shift.operations) return [];
    return props.shift.operations.filter(op =>
        op.type === 'deposit' || op.type === 'withdrawal' || (op.type === 'expense' && op.category === 'refund')
    );
});

// Суммы внесений и изъятий
const totalDeposits = computed(() => {
    if (!props.shift.operations) return 0;
    return props.shift.operations
        .filter(op => op.type === 'deposit')
        .reduce((sum, op) => sum + (parseFloat(op.amount) || 0), 0);
});

const totalWithdrawals = computed(() => {
    if (!props.shift.operations) return 0;
    return props.shift.operations
        .filter(op => op.type === 'withdrawal')
        .reduce((sum, op) => sum + (parseFloat(op.amount) || 0), 0);
});

// Сумма возвратов
const totalRefunds = computed(() => {
    if (!props.shift.operations) return 0;
    return props.shift.operations
        .filter(op => op.type === 'expense' && op.category === 'refund')
        .reduce((sum, op) => sum + (parseFloat(op.amount) || 0), 0);
});

// Все операции объединённые и отсортированные по времени
const allOperations = computed(() => {
    const ops = [];

    // Создаём карту заказов для быстрого поиска
    const ordersMap = {};
    props.orders.forEach(order => {
        ordersMap[order.id] = order;
    });

    // Добавляем операции оплаты заказов из смены (реальные суммы)
    const orderOperations = (props.shift.operations || []).filter(op =>
        op.type === 'income' && op.category === 'order' && op.order_id
    );

    // Группируем операции по order_id для определения частичных оплат
    const operationsByOrder = {};
    orderOperations.forEach(op => {
        if (!operationsByOrder[op.order_id]) {
            operationsByOrder[op.order_id] = [];
        }
        operationsByOrder[op.order_id].push(op);
    });

    // Добавляем операции оплаты
    orderOperations.forEach(op => {
        const order = ordersMap[op.order_id];
        const isPartialPayment = operationsByOrder[op.order_id]?.length > 1;

        // Парсим notes для получения товаров и номеров гостей
        let notesData = null;
        if (op.notes) {
            try {
                notesData = typeof op.notes === 'string' ? JSON.parse(op.notes) : op.notes;
            } catch (e) {
                // notes не JSON
            }
        }

        ops.push({
            id: 'order-op-' + op.id,
            type: 'order',
            time: op.created_at,
            amount: op.amount, // Реальная сумма операции!
            payment_method: op.payment_method,
            isPartialPayment: isPartialPayment,
            items: notesData?.items || null, // Товары из операции
            guestNumbers: notesData?.guest_numbers || null, // Номера гостей
            data: order || { id: op.order_id, order_number: op.description?.match(/#(\d+-\d+)/)?.[1] || op.order_id }
        });
    });

    // Добавляем заказы без операций (для совместимости со старыми данными)
    const orderIdsWithOperations = new Set(orderOperations.map(op => op.order_id));
    props.orders.forEach(order => {
        if (!orderIdsWithOperations.has(order.id)) {
            ops.push({
                id: 'order-' + order.id,
                type: 'order',
                time: order.paid_at || order.created_at,
                amount: order.total,
                payment_method: order.payment_method,
                items: null, // Старые записи без items
                guestNumbers: null,
                data: order
            });
        }
    });

    // Добавляем предоплаты
    props.prepayments.forEach(prep => {
        ops.push({
            id: 'prep-' + prep.id,
            type: 'prepayment',
            time: prep.created_at,
            amount: prep.amount,
            payment_method: prep.payment_method,
            data: prep
        });
    });

    // Добавляем внесения, изъятия и возвраты
    cashOperations.value.forEach(op => {
        // Определяем тип для отображения
        let displayType = op.type;
        if (op.type === 'expense' && op.category === 'refund') {
            displayType = 'refund';
        }

        ops.push({
            id: 'cash-' + op.id,
            type: displayType,
            time: op.created_at,
            amount: op.amount,
            payment_method: op.payment_method || 'cash',
            category: op.category,
            description: op.description,
            data: op
        });
    });

    // Сортируем по времени (новые сверху)
    return ops.sort((a, b) => new Date(b.time) - new Date(a.time));
});

// Сумма смешанных оплат
const totalMixed = computed(() => {
    return allOperations.value
        .filter(op => op.payment_method === 'mixed')
        .reduce((sum, op) => sum + (parseFloat(op.amount) || 0), 0);
});

// Категории изъятий
const withdrawalCategories = {
    purchase: '🛒 Закупка',
    salary: '💼 Зарплата',
    tips: '💵 Чаевые',
    other: '📋 Прочее'
};

// Tabs для фильтрации
const filterTabs = computed(() => [
    {
        value: null,
        label: 'Все',
        count: allOperations.value.length,
        activeClass: 'bg-accent text-white'
    },
    {
        value: 'order',
        label: 'Заказы',
        count: allOperations.value.filter(op => op.type === 'order').length,
        activeClass: 'bg-blue-600 text-white'
    },
    {
        value: 'deposit',
        label: 'Внесения',
        count: allOperations.value.filter(op => op.type === 'deposit').length,
        activeClass: 'bg-green-600 text-white'
    },
    {
        value: 'withdrawal',
        label: 'Изъятия',
        count: allOperations.value.filter(op => op.type === 'withdrawal').length,
        activeClass: 'bg-red-600 text-white'
    },
    {
        value: 'refund',
        label: 'Возвраты',
        count: allOperations.value.filter(op => op.type === 'refund').length,
        activeClass: 'bg-orange-600 text-white'
    },
    {
        value: 'prepayment',
        label: 'Предоплаты',
        count: allOperations.value.filter(op => op.type === 'prepayment').length,
        activeClass: 'bg-purple-600 text-white'
    }
].filter(tab => tab.value === null || tab.count > 0));

// Отфильтрованные операции
const filteredOperations = computed(() => {
    let ops = allOperations.value;

    // Фильтр по типу операции
    if (activeFilter.value) {
        ops = ops.filter(op => op.type === activeFilter.value);
    }

    // Фильтр по способу оплаты
    if (paymentFilter.value) {
        if (paymentFilter.value === 'mixed') {
            // Только смешанные
            ops = ops.filter(op => op.payment_method === 'mixed');
        } else if (paymentFilter.value === 'card') {
            // Карта + смешанные (т.к. в смешанных есть часть картой)
            ops = ops.filter(op => op.payment_method === 'card' || op.payment_method === 'mixed');
        } else if (paymentFilter.value === 'cash') {
            // Наличные + смешанные (т.к. в смешанных есть часть наличными)
            ops = ops.filter(op => op.payment_method === 'cash' || op.payment_method === 'mixed');
        } else {
            // Другие способы (онлайн и т.д.)
            ops = ops.filter(op => op.payment_method === paymentFilter.value);
        }
    }

    return ops;
});

// Проверка активности любого фильтра
const hasActiveFilters = computed(() => activeFilter.value || paymentFilter.value);

// Методы фильтрации
const setFilter = (filter) => {
    activeFilter.value = filter;
};

const toggleFilter = (filter) => {
    if (activeFilter.value === filter) {
        activeFilter.value = null;
    } else {
        activeFilter.value = filter;
    }
};

const togglePaymentFilter = (method) => {
    if (paymentFilter.value === method) {
        paymentFilter.value = null;
    } else {
        paymentFilter.value = method;
    }
};

const clearAllFilters = () => {
    activeFilter.value = null;
    paymentFilter.value = null;
};

const formatDate = (dt) => {
    if (!dt) return '';
    return new Date(dt).toLocaleDateString('ru-RU', { day: '2-digit', month: '2-digit' });
};

const formatTime = (dt) => {
    if (!dt) return '';
    return new Date(dt).toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' });
};

const formatMoney = (n) => {
    const num = parseFloat(n);
    if (!num || isNaN(num)) return '0';
    return Math.floor(num).toLocaleString('ru-RU');
};

const shiftDuration = computed(() => {
    if (!props.shift.opened_at) return '';
    const start = new Date(props.shift.opened_at);
    const end = props.shift.closed_at ? new Date(props.shift.closed_at) : new Date();
    const diffMs = end - start;
    const hours = Math.floor(diffMs / 3600000);
    const minutes = Math.floor((diffMs % 3600000) / 60000);
    return `${hours}ч ${minutes}м`;
});

const getOrderItemsText = (order) => {
    if (!order.items || !order.items.length) return '';
    const names = order.items.slice(0, 2).map(i => i.name || i.dish?.name);
    return names.join(', ') + (order.items.length > 2 ? '...' : '');
};

// Получить товары для операции (из notes или из заказа)
const getOperationItems = (op) => {
    // Если есть товары в операции - используем их
    if (op.items && op.items.length) {
        return op.items;
    }
    // Иначе берём из заказа
    if (op.data?.items && op.data.items.length) {
        return op.data.items.map(i => ({
            id: i.id,
            name: i.name || i.dish?.name || 'Позиция',
            quantity: i.quantity,
            price: i.price,
            guest_number: i.guest_number
        }));
    }
    return [];
};

// Краткий текст товаров для строки операции
const getOperationItemsText = (op) => {
    const items = getOperationItems(op);
    if (!items.length) return '';
    const names = items.slice(0, 2).map(i => i.name);
    return names.join(', ') + (items.length > 2 ? '...' : '');
};

const getOrderTypeLabel = (type) => {
    const labels = {
        delivery: 'Доставка',
        pickup: 'Самовывоз',
        dine_in: 'Зал'
    };
    return labels[type] || type;
};

const getPaymentIcon = (method) => {
    if (method === 'card') return '💳';
    if (method === 'mixed') return '💳+💵';
    return '💵';
};

// Проверка наличия скидок у заказа
const hasDiscounts = (op) => {
    if (!op.data) return false;
    return (op.data.discount_amount > 0) ||
           (op.data.loyalty_discount_amount > 0) ||
           (op.data.bonus_used > 0);
};

// Расчёт процента от суммы
const calculatePercent = (amount, total) => {
    if (!total || total <= 0) return 0;
    return Math.round((amount / total) * 100);
};
</script>
