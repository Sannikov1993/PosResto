<template>
    <div class="min-h-screen bg-gray-100">
        <!-- Login -->
        <div v-if="!store.isAuthenticated" class="min-h-screen flex items-center justify-center">
            <div class="bg-white rounded-2xl shadow-xl p-8 w-96">
                <h1 class="text-2xl font-bold text-center mb-6">PosResto Admin</h1>
                <form @submit.prevent="login">
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-1">Email</label>
                        <input v-model="email" type="email" class="w-full border rounded-lg px-3 py-2" required>
                    </div>
                    <div class="mb-6">
                        <label class="block text-sm font-medium mb-1">Пароль</label>
                        <input v-model="password" type="password" class="w-full border rounded-lg px-3 py-2" required>
                    </div>
                    <p v-if="error" class="text-red-500 text-sm mb-4">{{ error }}</p>
                    <button type="submit" :disabled="store.loading"
                            class="w-full py-3 bg-orange-500 text-white rounded-lg font-medium hover:bg-orange-600 disabled:opacity-50">
                        {{ store.loading ? 'Вход...' : 'Войти' }}
                    </button>
                </form>
            </div>
        </div>

        <!-- Main App -->
        <div v-else class="flex">
            <!-- Sidebar -->
            <aside class="w-64 bg-neutral-900 min-h-screen p-4">
                <div class="text-white text-xl font-bold mb-8">PosResto Admin</div>
                <nav class="space-y-2">
                    <button v-for="item in menuItems" :key="item.key"
                            @click="store.activeModule = item.key"
                            :class="['w-full text-left px-4 py-2 rounded-lg transition',
                                     store.activeModule === item.key ? 'bg-orange-500 text-white' : 'text-gray-400 hover:text-white']">
                        {{ item.icon }} {{ item.label }}
                    </button>
                </nav>
                <div class="absolute bottom-4 left-4">
                    <button @click="store.logout()" class="text-gray-500 hover:text-red-400 text-sm">Выйти</button>
                </div>
            </aside>

            <!-- Content -->
            <main class="flex-1 p-6">
                <DashboardModule v-if="store.activeModule === 'dashboard'" />
                <MenuModule v-else-if="store.activeModule === 'menu'" />
                <StaffModule v-else-if="store.activeModule === 'staff'" />
                <HallModule v-else-if="store.activeModule === 'hall'" />
                <SettingsModule v-else-if="store.activeModule === 'settings'" />
            </main>
        </div>

        <!-- Toast -->
        <div v-if="store.toast"
             :class="['fixed bottom-6 right-6 px-6 py-3 rounded-xl shadow-lg z-50',
                      store.toast.type === 'success' ? 'bg-green-500 text-white' : 'bg-red-500 text-white']">
            {{ store.toast.message }}
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useAdminStore } from './stores/admin';
import DashboardModule from './modules/DashboardModule.vue';
import MenuModule from './modules/MenuModule.vue';
import StaffModule from './modules/StaffModule.vue';
import HallModule from './modules/HallModule.vue';
import SettingsModule from './modules/SettingsModule.vue';

const store = useAdminStore();

const email = ref('');
const password = ref('');
const error = ref('');

const menuItems = [
    { key: 'dashboard', label: 'Дашборд', icon: '📊' },
    { key: 'menu', label: 'Меню', icon: '🍽️' },
    { key: 'staff', label: 'Персонал', icon: '👥' },
    { key: 'hall', label: 'Зал', icon: '🪑' },
    { key: 'settings', label: 'Настройки', icon: '⚙️' },
];

async function login() {
    error.value = '';
    const result = await store.login(email.value, password.value);
    if (!result.success) {
        error.value = result.message;
    }
}

onMounted(async () => {
    if (store.checkAuth()) {
        await store.loadStats();
    }
});
</script>
