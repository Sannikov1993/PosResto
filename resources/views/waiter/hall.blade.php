@extends('waiter.layout')

@section('title', 'Зал')

@section('content')
<div id="hall-app" class="h-full flex flex-col bg-dark-900">
    <!-- Header -->
    <header class="bg-dark-800 px-4 py-3 safe-top flex items-center justify-between shrink-0">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-orange-500 rounded-full flex items-center justify-center font-bold">
                {{ auth()->user()->name[0] ?? 'W' }}
            </div>
            <div>
                <p class="font-semibold">{{ auth()->user()->name ?? 'Официант' }}</p>
                <p class="text-xs text-gray-400">На смене</p>
            </div>
        </div>
        <a href="{{ route('waiter.profile') }}" class="text-gray-400">
            ⚙️
        </a>
    </header>

    <!-- Zone Tabs -->
    <div class="px-4 py-2 flex gap-2 overflow-x-auto scroll-y bg-dark-800 shrink-0">
        <button v-for="zone in zones" :key="zone.id"
                @click="selectedZone = zone.id"
                :class="['px-4 py-2 rounded-full text-sm font-medium whitespace-nowrap transition-all',
                         selectedZone === zone.id ? 'bg-orange-500 text-white' : 'text-gray-400 bg-dark-700']">
            @{{ zone.name }}
        </button>
    </div>

    <!-- Legend -->
    <div class="px-4 py-2 flex items-center justify-center gap-4 text-xs text-gray-500 shrink-0">
        <span class="flex items-center gap-1">
            <span class="w-3 h-3 rounded-full bg-green-500"></span> Свободен
        </span>
        <span class="flex items-center gap-1">
            <span class="w-3 h-3 rounded-full bg-orange-500"></span> Занят
        </span>
        <span class="flex items-center gap-1">
            <span class="w-3 h-3 rounded-full bg-yellow-500"></span> Бронь
        </span>
    </div>

    <!-- Floor Map -->
    <div class="flex-1 p-4 overflow-auto">
        <div class="grid grid-cols-3 gap-4">
            <a v-for="table in tablesInZone" :key="table.id"
               :href="`/waiter/table/${table.id}`"
               :class="['aspect-square rounded-2xl flex flex-col items-center justify-center transition-all touch-active',
                        getTableClass(table)]">
                <span class="text-2xl font-bold">@{{ table.number }}</span>
                <span class="text-sm opacity-70">@{{ table.seats }} мест</span>
                <span v-if="table.orders_count" class="mt-1 px-2 py-0.5 bg-white/20 rounded-full text-xs">
                    @{{ table.orders_count }} заказ
                </span>
            </a>
        </div>

        <!-- Empty State -->
        <div v-if="tablesInZone.length === 0" class="flex flex-col items-center justify-center h-full text-gray-500">
            <span class="text-5xl mb-4">🪑</span>
            <p>Нет столов в этой зоне</p>
        </div>
    </div>

    <!-- Bottom Navigation -->
    <nav class="bg-dark-800 border-t border-dark-700 px-4 py-3 safe-bottom shrink-0">
        <div class="flex justify-around">
            <a href="{{ route('waiter.hall') }}" class="flex flex-col items-center text-orange-500">
                <span class="text-2xl">🪑</span>
                <span class="text-xs mt-1">Зал</span>
            </a>
            <a href="{{ route('waiter.orders') }}" class="flex flex-col items-center text-gray-400 relative">
                <span class="text-2xl">📋</span>
                <span class="text-xs mt-1">Заказы</span>
                <span v-if="activeOrdersCount > 0"
                      class="absolute -top-1 -right-1 w-5 h-5 bg-red-500 rounded-full text-xs flex items-center justify-center">
                    @{{ activeOrdersCount }}
                </span>
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
        const zones = ref(@json($zones));
        const selectedZone = ref(zones.value[0]?.id || null);
        const activeOrdersCount = ref(0);

        const tablesInZone = computed(() => {
            const zone = zones.value.find(z => z.id === selectedZone.value);
            return zone?.tables || [];
        });

        const getTableClass = (table) => {
            if (table.status === 'reserved') return 'bg-yellow-500/20 border-2 border-yellow-500';
            if (table.orders_count > 0) return 'bg-orange-500/20 border-2 border-orange-500';
            return 'bg-green-500/20 border-2 border-green-500';
        };

        const loadActiveOrders = async () => {
            const data = await api('/waiter/orders');
            if (data.success) {
                activeOrdersCount.value = data.data.length;
            }
        };

        onMounted(() => {
            loadActiveOrders();
            // Refresh every 30 seconds
            setInterval(loadActiveOrders, 30000);
        });

        return {
            zones, selectedZone, tablesInZone, activeOrdersCount,
            getTableClass
        };
    }
}).mount('#hall-app');
</script>
@endsection
