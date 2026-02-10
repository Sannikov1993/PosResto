<template>
    <div>
        <!-- Period Selector -->
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-2">
                <button v-for="p in periods" :key="p.key"
                        @click="period = p.key; loadAnalytics()"
                        :class="['px-4 py-2 rounded-lg font-medium transition',
                                 period === p.key ? 'bg-orange-500 text-white' : 'bg-white text-gray-600 hover:bg-gray-100']">
                    {{ p.label }}
                </button>
            </div>
            <div class="flex items-center gap-4">
                <input v-model="dateFrom" type="date" class="px-3 py-2 border rounded-lg">
                <span class="text-gray-400">—</span>
                <input v-model="dateTo" type="date" class="px-3 py-2 border rounded-lg">
                <button @click="loadAnalytics()" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg transition">
                    Применить
                </button>
            </div>
        </div>

        <!-- Key Metrics -->
        <div class="grid grid-cols-4 gap-6 mb-6">
            <div class="bg-white rounded-xl shadow-sm p-6">
                <div class="text-sm text-gray-500 mb-1">Выручка</div>
                <div class="text-3xl font-bold text-green-600">{{ formatMoney(stats.revenue) }}</div>
                <div :class="['text-sm mt-2', stats.revenueChange >= 0 ? 'text-green-500' : 'text-red-500']">
                    {{ stats.revenueChange >= 0 ? '↑' : '↓' }} {{ Math.abs(stats.revenueChange || 0) }}% vs пред. период
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-6">
                <div class="text-sm text-gray-500 mb-1">Заказов</div>
                <div class="text-3xl font-bold text-gray-900">{{ stats.ordersCount || 0 }}</div>
                <div :class="['text-sm mt-2', stats.ordersChange >= 0 ? 'text-green-500' : 'text-red-500']">
                    {{ stats.ordersChange >= 0 ? '↑' : '↓' }} {{ Math.abs(stats.ordersChange || 0) }}% vs пред. период
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-6">
                <div class="text-sm text-gray-500 mb-1">Средний чек</div>
                <div class="text-3xl font-bold text-blue-600">{{ formatMoney(stats.avgCheck) }}</div>
                <div :class="['text-sm mt-2', stats.avgCheckChange >= 0 ? 'text-green-500' : 'text-red-500']">
                    {{ stats.avgCheckChange >= 0 ? '↑' : '↓' }} {{ Math.abs(stats.avgCheckChange || 0) }}% vs пред. период
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-6">
                <div class="text-sm text-gray-500 mb-1">Гостей</div>
                <div class="text-3xl font-bold text-purple-600">{{ stats.guestsCount || 0 }}</div>
                <div class="text-sm text-gray-400 mt-2">
                    ~{{ formatMoney(stats.revenuePerGuest || 0) }} на гостя
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <!-- Revenue Chart -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h3 class="text-lg font-semibold mb-4">Выручка за период</h3>
                <div class="h-64 flex items-end justify-between gap-2">
                    <div v-for="(day, idx) in chartData" :key="idx" class="flex-1 flex flex-col items-center">
                        <div class="w-full bg-orange-500 rounded-t transition-all duration-300"
                             :style="{ height: getBarHeight(day.revenue) + 'px' }"></div>
                        <div class="text-xs text-gray-500 mt-2">{{ day.label }}</div>
                    </div>
                </div>
            </div>

            <!-- Top Dishes -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h3 class="text-lg font-semibold mb-4">Топ блюд</h3>
                <div class="space-y-3">
                    <div v-for="(dish, index) in topDishes" :key="dish.id" class="flex items-center justify-between">
                        <div class="flex items-center">
                            <span class="w-6 h-6 bg-orange-100 rounded-full flex items-center justify-center text-xs font-medium text-orange-600 mr-3">
                                {{ index + 1 }}
                            </span>
                            <span>{{ dish.name }}</span>
                        </div>
                        <div class="flex items-center gap-4">
                            <span class="text-gray-500">{{ dish.quantity }} шт</span>
                            <span class="font-medium">{{ formatMoney(dish.revenue) }}</span>
                        </div>
                    </div>
                    <div v-if="topDishes.length === 0" class="text-center py-8 text-gray-400">
                        Нет данных за выбранный период
                    </div>
                </div>
            </div>
        </div>

        <!-- More Stats -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- By Payment Method -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h3 class="text-lg font-semibold mb-4">По способу оплаты</h3>
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="text-xl">💵</span>
                            <span>Наличные</span>
                        </div>
                        <span class="font-medium">{{ formatMoney(stats.cashPayments) }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="text-xl">💳</span>
                            <span>Карта</span>
                        </div>
                        <span class="font-medium">{{ formatMoney(stats.cardPayments) }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="text-xl">📱</span>
                            <span>Онлайн</span>
                        </div>
                        <span class="font-medium">{{ formatMoney(stats.onlinePayments) }}</span>
                    </div>
                </div>
            </div>

            <!-- By Order Type -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h3 class="text-lg font-semibold mb-4">По типу заказа</h3>
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="text-xl">🪑</span>
                            <span>В зале</span>
                        </div>
                        <span class="font-medium">{{ stats.dineInOrders || 0 }} ({{ formatMoney(stats.dineInRevenue) }})</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="text-xl">🚚</span>
                            <span>Доставка</span>
                        </div>
                        <span class="font-medium">{{ stats.deliveryOrders || 0 }} ({{ formatMoney(stats.deliveryRevenue) }})</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="text-xl">🥡</span>
                            <span>Самовывоз</span>
                        </div>
                        <span class="font-medium">{{ stats.takeawayOrders || 0 }} ({{ formatMoney(stats.takeawayRevenue) }})</span>
                    </div>
                </div>
            </div>

            <!-- By Time -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h3 class="text-lg font-semibold mb-4">Пиковые часы</h3>
                <div class="space-y-2">
                    <div v-for="hour in peakHours" :key="hour.hour" class="flex items-center gap-3">
                        <span class="text-sm text-gray-500 w-12">{{ hour.hour }}:00</span>
                        <div class="flex-1 bg-gray-100 rounded-full h-4 overflow-hidden">
                            <div class="bg-orange-500 h-full transition-all" :style="{ width: hour.percent + '%' }"></div>
                        </div>
                        <span class="text-sm font-medium w-16 text-right">{{ hour.orders }} зак.</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Staff Performance -->
        <div class="bg-white rounded-xl shadow-sm p-6 mt-6">
            <h3 class="text-lg font-semibold mb-4">Эффективность персонала</h3>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="text-left text-sm text-gray-500 border-b">
                            <th class="pb-3 font-medium">Сотрудник</th>
                            <th class="pb-3 font-medium">Роль</th>
                            <th class="pb-3 font-medium text-right">Заказов</th>
                            <th class="pb-3 font-medium text-right">Выручка</th>
                            <th class="pb-3 font-medium text-right">Средний чек</th>
                            <th class="pb-3 font-medium text-right">Часов</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="staff in staffStats" :key="staff.id" class="border-b">
                            <td class="py-3 font-medium">{{ staff.name }}</td>
                            <td class="py-3 text-gray-500">{{ staff.role }}</td>
                            <td class="py-3 text-right">{{ staff.orders }}</td>
                            <td class="py-3 text-right font-medium text-green-600">{{ formatMoney(staff.revenue) }}</td>
                            <td class="py-3 text-right">{{ formatMoney(staff.avgCheck) }}</td>
                            <td class="py-3 text-right">{{ staff.hours }}ч</td>
                        </tr>
                        <tr v-if="staffStats.length === 0">
                            <td colspan="6" class="py-8 text-center text-gray-400">Нет данных</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useBackofficeStore } from '../../stores/backoffice';

// Helper для локальной даты (не UTC!)
const getLocalDateString = (date = new Date()) => {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
};

const store = useBackofficeStore();

// State
const period = ref('week');
const dateFrom = ref('');
const dateTo = ref('');
const loading = ref(false);

const stats = ref({
    revenue: 0,
    revenueChange: 0,
    ordersCount: 0,
    ordersChange: 0,
    avgCheck: 0,
    avgCheckChange: 0,
    guestsCount: 0,
    revenuePerGuest: 0,
    cashPayments: 0,
    cardPayments: 0,
    onlinePayments: 0,
    dineInOrders: 0,
    dineInRevenue: 0,
    deliveryOrders: 0,
    deliveryRevenue: 0,
    takeawayOrders: 0,
    takeawayRevenue: 0
});

const chartData = ref<any[]>([]);
const topDishes = ref<any[]>([]);
const peakHours = ref<any[]>([]);
const staffStats = ref<any[]>([]);

// Constants
const periods = [
    { key: 'today', label: 'Сегодня' },
    { key: 'week', label: 'Неделя' },
    { key: 'month', label: 'Месяц' },
    { key: 'quarter', label: 'Квартал' }
];

// Methods
function formatMoney(val: any) {
    return new Intl.NumberFormat('ru-RU', { style: 'currency', currency: 'RUB', maximumFractionDigits: 0 }).format(val || 0);
}

function getBarHeight(value: any) {
    if (!chartData.value.length) return 0;
    const max = Math.max(...chartData.value.map((d: any) => d.revenue));
    if (max === 0) return 0;
    return Math.round((value / max) * 180);
}

async function loadAnalytics() {
    loading.value = true;
    try {
        const params = new URLSearchParams();
        params.append('period', period.value);
        if (dateFrom.value) params.append('from', dateFrom.value);
        if (dateTo.value) params.append('to', dateTo.value);

        const res = await store.api(`/backoffice/analytics?${params.toString()}`);

        if ((res as any).stats) stats.value = (res as any).stats;
        if ((res as any).chart) chartData.value = (res as any).chart;
        if ((res as any).topDishes) topDishes.value = (res as any).topDishes;
        if ((res as any).peakHours) peakHours.value = (res as any).peakHours;
        if ((res as any).staffStats) staffStats.value = (res as any).staffStats;

    } catch (e: any) {
        console.error('Failed to load analytics:', e);
        // Load mock data for demo
        loadMockData();
    } finally {
        loading.value = false;
    }
}

function loadMockData() {
    stats.value = {
        revenue: 245680,
        revenueChange: 12,
        ordersCount: 156,
        ordersChange: 8,
        avgCheck: 1575,
        avgCheckChange: 4,
        guestsCount: 312,
        revenuePerGuest: 787,
        cashPayments: 98500,
        cardPayments: 125180,
        onlinePayments: 22000,
        dineInOrders: 98,
        dineInRevenue: 165400,
        deliveryOrders: 42,
        deliveryRevenue: 58280,
        takeawayOrders: 16,
        takeawayRevenue: 22000
    };

    const days = ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];
    chartData.value = days.map((d: any) => ({
        label: d,
        revenue: Math.floor(Math.random() * 50000) + 20000
    }));

    topDishes.value = [
        { id: 1, name: 'Цезарь с курицей', quantity: 45, revenue: 22500 },
        { id: 2, name: 'Пицца Маргарита', quantity: 38, revenue: 19000 },
        { id: 3, name: 'Стейк Рибай', quantity: 22, revenue: 33000 },
        { id: 4, name: 'Борщ', quantity: 56, revenue: 14000 },
        { id: 5, name: 'Тирамису', quantity: 34, revenue: 10200 }
    ];

    peakHours.value = [
        { hour: 12, orders: 24, percent: 80 },
        { hour: 13, orders: 30, percent: 100 },
        { hour: 14, orders: 18, percent: 60 },
        { hour: 19, orders: 28, percent: 93 },
        { hour: 20, orders: 22, percent: 73 }
    ];

    staffStats.value = [
        { id: 1, name: 'Иван Петров', role: 'Официант', orders: 42, revenue: 65000, avgCheck: 1547, hours: 38 },
        { id: 2, name: 'Мария Сидорова', role: 'Официант', orders: 38, revenue: 58500, avgCheck: 1539, hours: 36 },
        { id: 3, name: 'Алексей Козлов', role: 'Кассир', orders: 76, revenue: 122000, avgCheck: 1605, hours: 42 }
    ];
}

// Init
onMounted(() => {
    // Set default dates
    const today = new Date();
    const weekAgo = new Date(today);
    weekAgo.setDate(weekAgo.getDate() - 7);

    dateTo.value = getLocalDateString(today);
    dateFrom.value = getLocalDateString(weekAgo);

    loadAnalytics();
});
</script>
