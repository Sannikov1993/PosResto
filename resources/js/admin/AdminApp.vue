<template>
    <div class="min-h-screen bg-gray-100">
        <!-- Загрузка статуса -->
        <div v-if="checkingSetup" class="min-h-screen flex items-center justify-center">
            <div class="text-gray-400">Проверка системы...</div>
        </div>

        <!-- Форма первоначальной настройки -->
        <div v-else-if="needsSetup" class="min-h-screen flex items-center justify-center">
            <div class="bg-white rounded-2xl shadow-xl p-8 w-[440px]">
                <h1 class="text-2xl font-bold text-center mb-2">MenuLab Admin</h1>
                <p class="text-gray-500 text-center mb-6">Первоначальная настройка</p>
                <form @submit.prevent="handleSetup">
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-1">Название ресторана</label>
                        <input v-model="setupForm.restaurant_name" type="text" class="w-full border rounded-lg px-3 py-2" placeholder="Мой ресторан" required>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-1">Ваше имя</label>
                        <input v-model="setupForm.owner_name" type="text" class="w-full border rounded-lg px-3 py-2" placeholder="Иван Иванов" required>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-1">Email</label>
                        <input v-model="setupForm.email" type="email" class="w-full border rounded-lg px-3 py-2" placeholder="admin@example.com" required>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-1">Телефон <span class="text-gray-400">(необязательно)</span></label>
                        <input v-model="setupForm.phone" type="tel" class="w-full border rounded-lg px-3 py-2" placeholder="+7 (999) 123-45-67">
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-1">Пароль</label>
                        <input v-model="setupForm.password" type="password" class="w-full border rounded-lg px-3 py-2" placeholder="Минимум 6 символов" required>
                    </div>
                    <div class="mb-6">
                        <label class="block text-sm font-medium mb-1">Подтверждение пароля</label>
                        <input v-model="setupForm.password_confirmation" type="password" class="w-full border rounded-lg px-3 py-2" placeholder="Повторите пароль" required>
                    </div>
                    <p v-if="setupError" class="text-red-500 text-sm mb-4">{{ setupError }}</p>
                    <button type="submit" :disabled="setupLoading"
                            class="w-full py-3 bg-orange-500 text-white rounded-lg font-medium hover:bg-orange-600 disabled:opacity-50">
                        {{ setupLoading ? 'Создание...' : 'Создать ресторан' }}
                    </button>
                </form>
            </div>
        </div>

        <!-- Login -->
        <div v-else-if="!store.isAuthenticated" class="min-h-screen flex items-center justify-center">
            <div class="bg-white rounded-2xl shadow-xl p-8 w-96">
                <h1 class="text-2xl font-bold text-center mb-6">MenuLab Admin</h1>
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
                <div class="text-white text-xl font-bold mb-8">MenuLab Admin</div>
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
                <TenantsModule v-else-if="store.activeModule === 'tenants' && store.user?.role === 'super_admin'" />
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
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import { useAdminStore } from './stores/admin';
import DashboardModule from './modules/DashboardModule.vue';
import MenuModule from './modules/MenuModule.vue';
import StaffModule from './modules/StaffModule.vue';
import HallModule from './modules/HallModule.vue';
import SettingsModule from './modules/SettingsModule.vue';
import TenantsModule from './modules/TenantsModule.vue';

const store = useAdminStore();

const checkingSetup = ref(true);
const needsSetup = ref(false);

const email = ref('');
const password = ref('');
const error = ref('');

const setupForm = ref({
    restaurant_name: '',
    owner_name: '',
    email: '',
    phone: '',
    password: '',
    password_confirmation: ''
});
const setupLoading = ref(false);
const setupError = ref('');

const baseMenuItems = [
    { key: 'dashboard', label: 'Дашборд', icon: '📊' },
    { key: 'menu', label: 'Меню', icon: '🍽️' },
    { key: 'staff', label: 'Персонал', icon: '👥' },
    { key: 'hall', label: 'Зал', icon: '🪑' },
    { key: 'settings', label: 'Настройки', icon: '⚙️' },
];

const menuItems = computed(() => {
    const items = [...baseMenuItems];
    // Add tenants module for super_admin only
    if (store.user?.role === 'super_admin') {
        items.push({ key: 'tenants', label: 'Тенанты', icon: '🏢' });
    }
    return items;
});

async function handleSetup() {
    setupError.value = '';

    if (setupForm.value.password !== setupForm.value.password_confirmation) {
        setupError.value = 'Пароли не совпадают';
        return;
    }

    if (setupForm.value.password.length < 6) {
        setupError.value = 'Пароль должен содержать минимум 6 символов';
        return;
    }

    setupLoading.value = true;

    try {
        const res = await axios.post('/api/auth/setup', {
            restaurant_name: setupForm.value.restaurant_name,
            owner_name: setupForm.value.owner_name,
            email: setupForm.value.email,
            phone: setupForm.value.phone || null,
            password: setupForm.value.password
        });

        if (res.data.success && res.data.data) {
            store.token = res.data.data.token;
            store.user = res.data.data.user;
            store.isAuthenticated = true;
            localStorage.setItem('admin_token', res.data.data.token);
            localStorage.setItem('admin_user', JSON.stringify(res.data.data.user));
            axios.defaults.headers.common['Authorization'] = `Bearer ${res.data.data.token}`;
            needsSetup.value = false;
            await store.loadStats();
        } else {
            setupError.value = res.data.message || 'Ошибка настройки';
        }
    } catch (e) {
        setupError.value = e.response?.data?.message || e.message || 'Ошибка соединения';
    } finally {
        setupLoading.value = false;
    }
}

async function login() {
    error.value = '';
    const result = await store.login(email.value, password.value);
    if (!result.success) {
        error.value = result.message;
    }
}

onMounted(async () => {
    try {
        const res = await axios.get('/api/auth/setup-status');
        if (res.data.needs_setup === true) {
            needsSetup.value = true;
            checkingSetup.value = false;
            return;
        }
    } catch (e) {
        // If check fails, proceed to login
    }
    checkingSetup.value = false;

    if (store.checkAuth()) {
        await store.loadStats();
    }
});
</script>
