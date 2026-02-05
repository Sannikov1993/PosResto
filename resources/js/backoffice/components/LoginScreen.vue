<template>
    <div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-blue-500 via-blue-600 to-blue-700" data-testid="login-screen">
        <div class="bg-white p-8 rounded-2xl shadow-2xl w-[440px]" data-testid="login-card">
            <div class="text-center mb-8">
                <img src="/images/logo/menulab_icon.svg" alt="MenuLab" class="w-16 h-16 mx-auto mb-4" />
                <h1 class="text-2xl font-bold text-gray-900">MenuLab BackOffice</h1>
                <p class="text-gray-500 mt-1">
                    {{ needsSetup ? 'Первоначальная настройка' : (mode === 'register' ? 'Регистрация' : 'Управление рестораном') }}
                </p>
            </div>

            <!-- Загрузка -->
            <div v-if="checking" class="text-center py-8 text-gray-400">
                Проверка системы...
            </div>

            <!-- Форма регистрации (первоначальная настройка) -->
            <form v-else-if="needsSetup" @submit.prevent="handleSetup" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Название ресторана</label>
                    <input v-model="setupForm.restaurant_name" type="text" class="login-input" placeholder="Мой ресторан" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Ваше имя</label>
                    <input v-model="setupForm.owner_name" type="text" class="login-input" placeholder="Иван Иванов" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Email</label>
                    <input v-model="setupForm.email" type="email" class="login-input" placeholder="admin@example.com" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Телефон <span class="text-gray-400">(необязательно)</span></label>
                    <input v-model="setupForm.phone" type="tel" class="login-input" placeholder="+7 (999) 123-45-67">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Пароль</label>
                    <input v-model="setupForm.password" type="password" class="login-input" placeholder="Минимум 6 символов" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Подтверждение пароля</label>
                    <input v-model="setupForm.password_confirmation" type="password" class="login-input" placeholder="Повторите пароль" required>
                </div>
                <button type="submit" :disabled="loading" class="login-btn w-full mt-2">
                    {{ loading ? 'Создание...' : 'Создать ресторан' }}
                </button>
                <p v-if="error" class="text-red-500 text-center text-sm">{{ error }}</p>
            </form>

            <!-- Форма регистрации нового тенанта (SaaS) -->
            <form v-else-if="mode === 'register'" @submit.prevent="handleRegister" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Название организации</label>
                    <input v-model="registerForm.organization_name" type="text" class="login-input" placeholder="ООО Ресторан" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Название ресторана <span class="text-gray-400">(необязательно)</span></label>
                    <input v-model="registerForm.restaurant_name" type="text" class="login-input" placeholder="Ресторан на Тверской">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Ваше имя</label>
                    <input v-model="registerForm.owner_name" type="text" class="login-input" placeholder="Иван Иванов" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Email</label>
                    <input v-model="registerForm.email" type="email" class="login-input" placeholder="ivan@restaurant.ru" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Телефон <span class="text-gray-400">(необязательно)</span></label>
                    <input v-model="registerForm.phone" type="tel" class="login-input" placeholder="+7 (999) 123-45-67">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Пароль</label>
                    <input v-model="registerForm.password" type="password" class="login-input" placeholder="Минимум 6 символов" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Подтверждение пароля</label>
                    <input v-model="registerForm.password_confirmation" type="password" class="login-input" placeholder="Повторите пароль" required>
                </div>
                <button type="submit" :disabled="loading" class="login-btn w-full mt-2">
                    {{ loading ? 'Регистрация...' : 'Зарегистрироваться' }}
                </button>
                <p v-if="error" class="text-red-500 text-center text-sm">{{ error }}</p>
                <p v-if="success" class="text-green-600 text-center text-sm">{{ success }}</p>

                <div class="text-center pt-2 border-t border-gray-100">
                    <span class="text-gray-500 text-sm">Уже есть аккаунт?</span>
                    <button type="button" @click="mode = 'login'" class="text-blue-600 hover:text-blue-700 text-sm font-medium ml-1">
                        Войти
                    </button>
                </div>
            </form>

            <!-- Обычная форма входа -->
            <form v-else @submit.prevent="handleLogin" class="space-y-4" data-testid="login-form">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Email или телефон</label>
                    <input v-model="form.email" type="text" class="login-input" placeholder="admin@menulab.ru" required data-testid="email-input">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Пароль</label>
                    <input v-model="form.password" type="password" class="login-input" placeholder="Введите пароль" required data-testid="password-input">
                </div>
                <button type="submit" :disabled="loading" class="login-btn w-full mt-2" data-testid="login-submit">
                    {{ loading ? 'Вход...' : 'Войти в систему' }}
                </button>
                <p v-if="error" class="text-red-500 text-center text-sm" data-testid="login-error">{{ error }}</p>

                <div class="text-center pt-2 border-t border-gray-100">
                    <span class="text-gray-500 text-sm">Нет аккаунта?</span>
                    <button type="button" @click="mode = 'register'" class="text-blue-600 hover:text-blue-700 text-sm font-medium ml-1" data-testid="switch-to-register">
                        Зарегистрироваться
                    </button>
                </div>
            </form>

            <!-- Trial info -->
            <div v-if="mode === 'register' && !needsSetup" class="mt-4 p-3 bg-blue-50 rounded-lg text-center">
                <p class="text-sm text-blue-700">
                    <span class="font-medium">14 дней бесплатно!</span>
                    <br>
                    <span class="text-blue-600">Полный доступ ко всем функциям</span>
                </p>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useBackofficeStore } from '../stores/backoffice';
import { usePermissionsStore } from '../../shared/stores/permissions';
import { setSession as setUnifiedSession } from '../../shared/services/auth';

const emit = defineEmits(['login']);
const store = useBackofficeStore();
const permissionsStore = usePermissionsStore();

const checking = ref(true);
const needsSetup = ref(false);
const mode = ref('login'); // 'login' | 'register'

const form = ref({
    email: '',
    password: ''
});
const setupForm = ref({
    restaurant_name: '',
    owner_name: '',
    email: '',
    phone: '',
    password: '',
    password_confirmation: ''
});
const registerForm = ref({
    organization_name: '',
    restaurant_name: '',
    owner_name: '',
    email: '',
    phone: '',
    password: '',
    password_confirmation: ''
});
const loading = ref(false);
const error = ref('');
const success = ref('');

onMounted(async () => {
    try {
        const res = await fetch('/api/auth/setup-status');
        const data = await res.json();
        needsSetup.value = data.needs_setup === true;
    } catch (e) {
        needsSetup.value = false;
    } finally {
        checking.value = false;
    }
});

const handleSetup = async () => {
    error.value = '';

    if (setupForm.value.password !== setupForm.value.password_confirmation) {
        error.value = 'Пароли не совпадают';
        return;
    }

    if (setupForm.value.password.length < 6) {
        error.value = 'Пароль должен содержать минимум 6 символов';
        return;
    }

    loading.value = true;

    try {
        const res = await fetch('/api/auth/setup', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({
                restaurant_name: setupForm.value.restaurant_name,
                owner_name: setupForm.value.owner_name,
                email: setupForm.value.email,
                phone: setupForm.value.phone || null,
                password: setupForm.value.password
            })
        });

        const data = await res.json();

        if (data.success && data.data) {
            store.token = data.data.token;
            store.user = data.data.user;
            store.isAuthenticated = true;

            // Save to unified auth (enables SSO with POS)
            setUnifiedSession({
                token: data.data.token,
                user: data.data.user,
                permissions: data.data.permissions || [],
                limits: data.data.limits || {},
                interfaceAccess: data.data.interface_access || {},
            }, { app: 'backoffice' });

            // Also save to legacy keys for backward compatibility
            localStorage.setItem('backoffice_token', data.data.token);
            store.permissions = data.data.permissions || [];
            store.limits = data.data.limits || {};
            store.interfaceAccess = data.data.interface_access || {};
            localStorage.setItem('backoffice_permissions', JSON.stringify(store.permissions));
            localStorage.setItem('backoffice_limits', JSON.stringify(store.limits));
            localStorage.setItem('backoffice_interface_access', JSON.stringify(store.interfaceAccess));

            // Set restaurant ID via centralized store (syncs to all apps automatically)
            if (data.data.user?.restaurant_id) {
                permissionsStore.setRestaurantId(data.data.user.restaurant_id);
            }
            emit('login');
        } else {
            error.value = data.message || 'Ошибка настройки';
        }
    } catch (e) {
        error.value = e.message || 'Ошибка соединения';
    } finally {
        loading.value = false;
    }
};

const handleLogin = async () => {
    loading.value = true;
    error.value = '';

    const result = await store.login(form.value.email, form.value.password);

    if (result.success) {
        emit('login');
    } else {
        error.value = result.message || 'Ошибка входа';
    }

    loading.value = false;
};

const handleRegister = async () => {
    error.value = '';
    success.value = '';

    if (registerForm.value.password !== registerForm.value.password_confirmation) {
        error.value = 'Пароли не совпадают';
        return;
    }

    if (registerForm.value.password.length < 6) {
        error.value = 'Пароль должен содержать минимум 6 символов';
        return;
    }

    loading.value = true;

    try {
        const res = await fetch('/api/register/tenant', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({
                organization_name: registerForm.value.organization_name,
                restaurant_name: registerForm.value.restaurant_name || null,
                owner_name: registerForm.value.owner_name,
                email: registerForm.value.email,
                phone: registerForm.value.phone || null,
                password: registerForm.value.password,
                password_confirmation: registerForm.value.password_confirmation
            })
        });

        const data = await res.json();

        if (data.success && data.data) {
            // Автоматический вход после регистрации
            store.token = data.data.token;
            store.user = data.data.user;
            store.isAuthenticated = true;

            // Save to unified auth (enables SSO with POS)
            setUnifiedSession({
                token: data.data.token,
                user: data.data.user,
                permissions: data.data.permissions || [],
                limits: data.data.limits || {},
                interfaceAccess: data.data.interface_access || {},
            }, { app: 'backoffice' });

            // Also save to legacy keys for backward compatibility
            localStorage.setItem('backoffice_token', data.data.token);

            // Сохраняем права (если есть)
            if (data.data.permissions) {
                store.permissions = data.data.permissions;
                localStorage.setItem('backoffice_permissions', JSON.stringify(data.data.permissions));
            }
            if (data.data.limits) {
                store.limits = data.data.limits;
                localStorage.setItem('backoffice_limits', JSON.stringify(data.data.limits));
            }
            if (data.data.interface_access) {
                store.interfaceAccess = data.data.interface_access;
                localStorage.setItem('backoffice_interface_access', JSON.stringify(data.data.interface_access));
            }

            // Сохраняем модули (если есть)
            if (data.data.pos_modules) {
                store.posModules = data.data.pos_modules;
                localStorage.setItem('backoffice_pos_modules', JSON.stringify(data.data.pos_modules));
            }
            if (data.data.backoffice_modules) {
                store.backofficeModules = data.data.backoffice_modules;
                localStorage.setItem('backoffice_modules', JSON.stringify(data.data.backoffice_modules));
            }

            // Initialize PermissionsStore for sidebar filtering
            permissionsStore.init({
                permissions: data.data.permissions || [],
                limits: data.data.limits || {},
                interfaceAccess: data.data.interface_access || {},
                posModules: data.data.pos_modules || [],
                backofficeModules: data.data.backoffice_modules || [],
                role: data.data.user?.role || 'owner',
            });

            // Set restaurant ID via centralized store (syncs to all apps automatically)
            if (data.data.user?.restaurant_id) {
                permissionsStore.setRestaurantId(data.data.user.restaurant_id);
            }

            emit('login');
        } else {
            error.value = data.message || 'Ошибка регистрации';
        }
    } catch (e) {
        error.value = e.message || 'Ошибка соединения';
    } finally {
        loading.value = false;
    }
};
</script>

<style scoped>
.login-input {
    width: 100%;
    padding: 0.625rem 0.875rem;
    border: 1px solid #e5e7eb;
    border-radius: 0.75rem;
    font-size: 0.875rem;
    color: #111827;
    background: #fff;
    transition: border-color 0.2s, box-shadow 0.2s;
}
.login-input::placeholder {
    color: #9ca3af;
}
.login-input:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
}
.login-btn {
    padding: 0.625rem 1.25rem;
    background: linear-gradient(to right, #3b82f6, #2563eb);
    color: #fff;
    font-weight: 600;
    font-size: 0.875rem;
    border: none;
    border-radius: 0.75rem;
    cursor: pointer;
    transition: all 0.2s;
}
.login-btn:hover {
    background: linear-gradient(to right, #2563eb, #1d4ed8);
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
}
.login-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}
</style>
