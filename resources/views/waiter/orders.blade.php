@extends('waiter.layout')

@section('title', 'Мои заказы')

@section('content')
<div id="orders-app" class="h-full flex flex-col bg-dark-900">
    <!-- Header -->
    <header class="bg-dark-800 px-4 py-3 safe-top flex items-center justify-between shrink-0">
        <h1 class="text-xl font-bold">Мои заказы</h1>
        <button @click="refreshOrders" class="text-gray-400">🔄</button>
    </header>

    <!-- Filter Tabs -->
    <div class="px-4 py-2 flex gap-2 overflow-x-auto scroll-y bg-dark-800 shrink-0">
        <button v-for="filter in filters" :key="filter.value"
                @click="activeFilter = filter.value"
                :class="['px-4 py-2 rounded-full text-sm font-medium whitespace-nowrap transition-all flex items-center gap-2',
                         activeFilter === filter.value ? 'bg-orange-500 text-white' : 'text-gray-400 bg-dark-700']">
            <span>@{{ filter.icon }}</span>
            <span>@{{ filter.label }}</span>
            <span v-if="getFilterCount(filter.value)"
                  class="px-1.5 py-0.5 bg-white/20 rounded-full text-xs">
                @{{ getFilterCount(filter.value) }}
            </span>
        </button>
    </div>

    <!-- Orders List -->
    <div class="flex-1 scroll-y p-4 space-y-3">
        <a v-for="order in filteredOrders" :key="order.id"
           :href="`/waiter/table/${order.table_id}`"
           :class="['block bg-dark-800 rounded-2xl p-4 transition-all touch-active',
                    order.items?.some(i => i.status === 'ready') ? 'ring-2 ring-green-500' : '']">
            <div class="flex items-center justify-between mb-2">
                <div class="flex items-center gap-3">
                    <span class="text-lg font-bold">#@{{ order.order_number }}</span>
                    <span :class="['px-2 py-1 rounded-lg text-xs font-medium', getStatusClass(order.status)]">
                        @{{ getStatusLabel(order.status) }}
                    </span>
                </div>
                <span class="text-gray-400 text-sm">@{{ order.time_elapsed }}</span>
            </div>

            <div class="flex items-center gap-4 text-sm text-gray-400 mb-3">
                <span>🪑 Стол @{{ order.table?.number }}</span>
                <span>@{{ order.items?.length || 0 }} позиций</span>
            </div>

            <div class="flex items-center justify-between">
                <div class="flex gap-2">
                    <span v-if="order.ready_count" class="px-2 py-1 bg-green-500/20 text-green-400 rounded text-xs">
                        ✅ @{{ order.ready_count }} готово
                    </span>
                </div>
                <span class="text-xl font-bold text-orange-500">@{{ formatMoney(order.total) }}</span>
            </div>
        </a>

        <!-- Empty State -->
        <div v-if="filteredOrders.length === 0" class="flex flex-col items-center justify-center h-64 text-gray-500">
            <span class="text-5xl mb-4">📋</span>
            <p>Нет заказов</p>
        </div>
    </div>

    <!-- Bottom Navigation -->
    <nav class="bg-dark-800 border-t border-dark-700 px-4 py-3 safe-bottom shrink-0">
        <div class="flex justify-around">
            <a href="{{ route('waiter.hall') }}" class="flex flex-col items-center text-gray-400">
                <span class="text-2xl">🪑</span>
                <span class="text-xs mt-1">Зал</span>
            </a>
            <a href="{{ route('waiter.orders') }}" class="flex flex-col items-center text-orange-500 relative">
                <span class="text-2xl">📋</span>
                <span class="text-xs mt-1">Заказы</span>
            </a>
            <a href="{{ route('waiter.profile') }}" class="flex flex-col items-center text-gray-400">
                <span class="text-2xl">👤</span>
                <span class="text-xs mt-1">Профиль</span>
            </a>
        </div>
    </nav>
</div>
@endsection

@section('scripts')
<script>
const { createApp, ref, computed, onMounted } = Vue;

createApp({
    setup() {
        const orders = ref([]);
        const activeFilter = ref('all');

        const filters = [
            { value: 'all', label: 'Все', icon: '📋' },
            { value: 'new', label: 'Новые', icon: '🆕' },
            { value: 'cooking', label: 'Готовят', icon: '👨‍🍳' },
            { value: 'ready', label: 'Готово', icon: '✅' },
        ];

        const filteredOrders = computed(() => {
            if (activeFilter.value === 'all') return orders.value;
            return orders.value.filter(o => o.status === activeFilter.value);
        });

        const getFilterCount = (filter) => {
            if (filter === 'all') return orders.value.length;
            return orders.value.filter(o => o.status === filter).length;
        };

        const getStatusClass = (status) => ({
            'new': 'bg-blue-500/20 text-blue-400',
            'cooking': 'bg-orange-500/20 text-orange-400',
            'ready': 'bg-green-500/20 text-green-400',
            'served': 'bg-purple-500/20 text-purple-400',
        }[status] || 'bg-gray-500/20 text-gray-400');

        const getStatusLabel = (status) => ({
            'new': 'Новый',
            'cooking': 'Готовится',
            'ready': 'Готов',
            'served': 'Выдан',
        }[status] || status);

        const formatMoney = (amount) => window.formatMoney(amount);

        const loadOrders = async () => {
            const data = await api('/waiter/orders');
            if (data.success) {
                orders.value = data.data;
            }
        };

        const refreshOrders = () => {
            loadOrders();
        };

        onMounted(() => {
            loadOrders();
            setInterval(loadOrders, 15000);
        });

        return {
            orders, activeFilter, filters, filteredOrders,
            getFilterCount, getStatusClass, getStatusLabel, formatMoney,
            refreshOrders
        };
    }
}).mount('#orders-app');
</script>
@endsection
