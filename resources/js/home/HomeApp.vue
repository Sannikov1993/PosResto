<template>
    <div class="min-h-screen bg-gradient-to-br from-slate-900 to-slate-800 text-white">
        <div class="container mx-auto px-4 py-12">
            <!-- Header -->
            <div class="text-center mb-16">
                <img src="/images/logo/menulab_logo_dark_bg.svg" alt="MenuLab" class="h-20 mx-auto mb-6" />
                <p class="text-gray-400 text-lg">Система управления рестораном</p>
                <div class="mt-4 flex items-center justify-center gap-2">
                    <span :class="['w-2 h-2 rounded-full animate-pulse', apiOnline ? 'bg-green-500' : 'bg-red-500']"></span>
                    <span :class="['text-sm', apiOnline ? 'text-green-400' : 'text-red-400']">
                        {{ apiOnline ? 'API v2.1.0 Running' : 'API Offline' }}
                    </span>
                </div>
            </div>

            <!-- Main Modules -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-12">
                <a v-for="mod in mainModules" :key="mod.href" :href="mod.href"
                   :class="['card rounded-2xl p-6 block', mod.gradient]">
                    <div class="flex items-center gap-4 mb-4">
                        <div class="w-14 h-14 bg-white/20 rounded-xl flex items-center justify-center">
                            <component :is="mod.icon" class="w-8 h-8" />
                        </div>
                        <div>
                            <h2 class="text-xl font-semibold">{{ mod.title }}</h2>
                            <p class="text-white/70 text-sm">{{ mod.subtitle }}</p>
                        </div>
                    </div>
                    <p class="text-white/80 text-sm">{{ mod.description }}</p>
                </a>
            </div>

            <!-- Additional Modules -->
            <h3 class="text-xl font-semibold mb-6 text-gray-300">Дополнительные модули</h3>
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-12">
                <a v-for="mod in additionalModules" :key="mod.href" :href="mod.href"
                   class="card bg-slate-800/50 rounded-xl p-4 text-center hover:bg-slate-700/50">
                    <div class="text-3xl mb-2">{{ mod.icon }}</div>
                    <div class="font-medium text-sm">{{ mod.title }}</div>
                </a>
            </div>

            <!-- Tools -->
            <h3 class="text-xl font-semibold mb-6 text-gray-300">Инструменты</h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-12">
                <a v-for="tool in tools" :key="tool.href" :href="tool.href"
                   class="card bg-slate-800/50 rounded-xl p-4 text-center hover:bg-slate-700/50">
                    <div class="text-2xl mb-2">{{ tool.icon }}</div>
                    <div class="font-medium text-sm">{{ tool.title }}</div>
                </a>
            </div>

            <!-- Footer -->
            <div class="text-center text-gray-500 text-sm">
                <p>MenuLab CRM v2.1.0 | Vue 3 + Laravel</p>
                <p class="mt-2">
                    <a href="/api" class="text-blue-400 hover:underline">API Documentation</a>
                </p>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, h } from 'vue';
import axios from 'axios';

const apiOnline = ref(true);

// SVG Icons as render functions
const PosIcon = () => h('svg', { fill: 'none', stroke: 'currentColor', viewBox: '0 0 24 24', class: 'w-8 h-8' }, [
    h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'stroke-width': '2', d: 'M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z' })
]);

const WaiterIcon = () => h('svg', { fill: 'none', stroke: 'currentColor', viewBox: '0 0 24 24', class: 'w-8 h-8' }, [
    h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'stroke-width': '2', d: 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z' })
]);

const KitchenIcon = () => h('svg', { fill: 'none', stroke: 'currentColor', viewBox: '0 0 24 24', class: 'w-8 h-8' }, [
    h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'stroke-width': '2', d: 'M17.657 18.657A8 8 0 016.343 7.343S7 9 9 10c0-2 .5-5 2.986-7C14 5 16.09 5.777 17.656 7.343A7.975 7.975 0 0120 13a7.975 7.975 0 01-2.343 5.657z' })
]);

const BackofficeIcon = () => h('svg', { fill: 'none', stroke: 'currentColor', viewBox: '0 0 24 24', class: 'w-8 h-8' }, [
    h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'stroke-width': '2', d: 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z' }),
    h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'stroke-width': '2', d: 'M15 12a3 3 0 11-6 0 3 3 0 016 0z' })
]);

const InventoryIcon = () => h('svg', { fill: 'none', stroke: 'currentColor', viewBox: '0 0 24 24', class: 'w-8 h-8' }, [
    h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'stroke-width': '2', d: 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4' })
]);

const AnalyticsIcon = () => h('svg', { fill: 'none', stroke: 'currentColor', viewBox: '0 0 24 24', class: 'w-8 h-8' }, [
    h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'stroke-width': '2', d: 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z' })
]);

// Main modules with new Vue routes
const mainModules = [
    {
        href: '/pos',
        title: 'POS Терминал',
        subtitle: 'Касса и заказы',
        description: 'Работа с заказами, оплата, управление столами и кассой',
        gradient: 'bg-gradient-to-br from-orange-600 to-red-700',
        icon: PosIcon
    },
    {
        href: '/waiter',
        title: 'Официант',
        subtitle: 'Панель официанта',
        description: 'Мобильный интерфейс для официантов',
        gradient: 'bg-gradient-to-br from-blue-600 to-indigo-700',
        icon: WaiterIcon
    },
    {
        href: '/kitchen',
        title: 'Кухня',
        subtitle: 'KDS система',
        description: 'Kitchen Display System - экран для поваров',
        gradient: 'bg-gradient-to-br from-green-600 to-emerald-700',
        icon: KitchenIcon
    },
    {
        href: '/backoffice',
        title: 'Бэк-офис',
        subtitle: 'Полное управление',
        description: 'Меню, персонал, склад, настройки системы',
        gradient: 'bg-gradient-to-br from-purple-600 to-violet-700',
        icon: BackofficeIcon
    },
    {
        href: '/backoffice?tab=inventory',
        title: 'Склад',
        subtitle: 'Инвентарь',
        description: 'Управление запасами, накладные, инвентаризация',
        gradient: 'bg-gradient-to-br from-amber-600 to-yellow-700',
        icon: InventoryIcon
    },
    {
        href: '/backoffice?tab=analytics',
        title: 'Аналитика',
        subtitle: 'Отчёты и графики',
        description: 'ABC-анализ, прогнозы, сравнение периодов',
        gradient: 'bg-gradient-to-br from-cyan-600 to-teal-700',
        icon: AnalyticsIcon
    }
];

// Additional modules
const additionalModules = [
    { href: '/reservations', icon: '📅', title: 'Брони' },
    { href: '/backoffice?tab=delivery', icon: '🛵', title: 'Доставка' },
    { href: '/backoffice?tab=staff', icon: '👥', title: 'Персонал' },
    { href: '/backoffice?tab=loyalty', icon: '🎁', title: 'Лояльность' },
    { href: '/backoffice?tab=customers', icon: '📊', title: 'CRM' },
    { href: '/backoffice?tab=settings', icon: '🖨️', title: 'Принтеры' }
];

// Tools
const tools = [
    { href: '/floor-editor', icon: '🗺️', title: 'Редактор зала' },
    { href: '/guest-menu', icon: '📱', title: 'Гостевое меню' },
    { href: '/guest-admin', icon: '⚙️', title: 'Админ гость-меню' },
    { href: '/realtime-monitor', icon: '📡', title: 'Real-time Monitor' }
];

// Check API status on mount
onMounted(async () => {
    try {
        await axios.get('/api/');
        apiOnline.value = true;
    } catch (e) {
        apiOnline.value = false;
    }
});
</script>

<style scoped>
.card {
    transition: all 0.3s ease;
}
.card:hover {
    transform: translateY(-5px);
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
}
</style>
