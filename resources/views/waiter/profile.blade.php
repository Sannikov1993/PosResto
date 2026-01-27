@extends('waiter.layout')

@section('title', 'Профиль')

@section('content')
<div id="profile-app" class="h-full flex flex-col bg-dark-900">
    <!-- Header -->
    <header class="bg-dark-800 px-4 py-3 safe-top shrink-0">
        <h1 class="text-xl font-bold">Профиль</h1>
    </header>

    <!-- Profile Content -->
    <div class="flex-1 scroll-y p-4">
        <!-- User Card -->
        <div class="bg-gradient-to-br from-orange-500 to-orange-600 rounded-2xl p-5 mb-4">
            <div class="flex items-center gap-4">
                <div class="w-16 h-16 bg-white/20 rounded-full flex items-center justify-center text-2xl font-bold">
                    {{ auth()->user()->name[0] ?? 'W' }}
                </div>
                <div>
                    <h2 class="text-xl font-bold">{{ auth()->user()->name ?? 'Официант' }}</h2>
                    <p class="text-orange-100">Официант</p>
                    <p class="text-sm text-orange-200 mt-1">🟢 На смене</p>
                </div>
            </div>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-3 gap-3 mb-4">
            <div class="bg-dark-800 rounded-2xl p-4 text-center">
                <p class="text-2xl font-bold" v-text="stats.orders_today">0</p>
                <p class="text-xs text-gray-500 mt-1">Заказов</p>
            </div>
            <div class="bg-dark-800 rounded-2xl p-4 text-center">
                <p class="text-2xl font-bold text-green-400" v-text="formatShort(stats.revenue_today)">0</p>
                <p class="text-xs text-gray-500 mt-1">Выручка</p>
            </div>
            <div class="bg-dark-800 rounded-2xl p-4 text-center">
                <p class="text-2xl font-bold text-yellow-400" v-text="formatShort(stats.tips_today)">0</p>
                <p class="text-xs text-gray-500 mt-1">Чаевые</p>
            </div>
        </div>

        <!-- Menu -->
        <div class="bg-dark-800 rounded-2xl overflow-hidden mb-4">
            <button class="w-full p-4 flex items-center justify-between border-b border-dark-700 touch-active">
                <span class="flex items-center gap-3">
                    <span>📊</span>
                    <span>Моя статистика</span>
                </span>
                <span class="text-gray-500">→</span>
            </button>
            <button class="w-full p-4 flex items-center justify-between border-b border-dark-700 touch-active">
                <span class="flex items-center gap-3">
                    <span>📋</span>
                    <span>История заказов</span>
                </span>
                <span class="text-gray-500">→</span>
            </button>
        </div>

        <!-- Settings -->
        <div class="bg-dark-800 rounded-2xl overflow-hidden mb-4">
            <div class="p-4 flex items-center justify-between border-b border-dark-700">
                <span class="flex items-center gap-3">
                    <span>🔔</span>
                    <span>Уведомления</span>
                </span>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" v-model="settings.notifications" class="sr-only peer">
                    <div class="w-11 h-6 bg-dark-600 rounded-full peer peer-checked:bg-orange-500 transition-colors">
                        <div class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full transition-transform peer-checked:translate-x-5"></div>
                    </div>
                </label>
            </div>
            <div class="p-4 flex items-center justify-between">
                <span class="flex items-center gap-3">
                    <span>🔊</span>
                    <span>Звук</span>
                </span>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" v-model="settings.sound" class="sr-only peer">
                    <div class="w-11 h-6 bg-dark-600 rounded-full peer peer-checked:bg-orange-500 transition-colors">
                        <div class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full transition-transform peer-checked:translate-x-5"></div>
                    </div>
                </label>
            </div>
        </div>

        <!-- Logout -->
        <form action="{{ route('logout') }}" method="POST">
            @csrf
            <button type="submit" class="w-full bg-dark-800 rounded-2xl p-4 text-red-500 font-medium text-center touch-active">
                🚪 Завершить смену
            </button>
        </form>
    </div>

    <!-- Bottom Navigation -->
    <nav class="bg-dark-800 border-t border-dark-700 px-4 py-3 safe-bottom shrink-0">
        <div class="flex justify-around">
            <a href="{{ route('waiter.hall') }}" class="flex flex-col items-center text-gray-400">
                <span class="text-2xl">🪑</span>
                <span class="text-xs mt-1">Зал</span>
            </a>
            <a href="{{ route('waiter.orders') }}" class="flex flex-col items-center text-gray-400">
                <span class="text-2xl">📋</span>
                <span class="text-xs mt-1">Заказы</span>
            </a>
            <a href="{{ route('waiter.profile') }}" class="flex flex-col items-center text-orange-500">
                <span class="text-2xl">👤</span>
                <span class="text-xs mt-1">Профиль</span>
            </a>
        </div>
    </nav>
</div>
@endsection

@section('scripts')
<script>
const { createApp, ref, onMounted } = Vue;

createApp({
    setup() {
        const stats = ref({
            orders_today: 0,
            revenue_today: 0,
            tips_today: 0
        });

        const settings = ref({
            notifications: true,
            sound: true
        });

        const formatShort = (amount) => {
            const num = amount || 0;
            if (num >= 1000) return (num / 1000).toFixed(1) + 'К';
            return num + '₽';
        };

        const loadStats = async () => {
            // TODO: load real stats from API
        };

        onMounted(loadStats);

        return { stats, settings, formatShort };
    }
}).mount('#profile-app');
</script>
@endsection
