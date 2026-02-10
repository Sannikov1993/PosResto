<template>
    <Teleport to="body">
        <div
            class="fixed inset-0 z-[99990] flex items-center justify-center bg-dark-950/95 backdrop-blur-md pointer-events-auto"
            data-testid="lock-screen"
            @keydown="handleKeydown"
        >
            <div class="w-full max-w-2xl p-8">
                <!-- Часы -->
                <div class="text-center mb-8">
                    <div class="text-7xl font-light text-white tracking-wider mb-3" data-testid="lock-clock">
                        {{ currentTime }}
                    </div>
                    <div class="text-gray-400 text-lg">Экран заблокирован</div>
                    <div v-if="lockedByUser" class="text-gray-500 text-sm mt-1">
                        Последний пользователь: {{ lockedByUser.name }}
                    </div>
                </div>

                <!-- Режим: Сетка сотрудников -->
                <div v-if="mode === 'grid'" data-testid="lock-users-grid">
                    <!-- Loading -->
                    <div v-if="loadingUsers" class="text-center py-12">
                        <div class="inline-block w-12 h-12 border-4 border-gray-700 border-t-accent rounded-full animate-spin"></div>
                        <p class="text-gray-400 mt-4">Загрузка...</p>
                    </div>

                    <!-- Users Grid -->
                    <div v-else-if="users.length > 0" class="space-y-6">
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                            <button
                                v-for="u in users"
                                :key="u.id"
                                @click="selectUser(u)"
                                :data-testid="`lock-user-${u.id}`"
                                class="group bg-dark-800/60 hover:bg-dark-700/80 border-2 border-gray-700/50 hover:border-accent rounded-xl p-5 transition-all"
                            >
                                <div class="flex flex-col items-center">
                                    <div v-if="u.avatar" class="w-16 h-16 rounded-full overflow-hidden mb-3 border-2 border-gray-700 group-hover:border-accent transition-colors">
                                        <img :src="u.avatar" :alt="u.name" class="w-full h-full object-cover" />
                                    </div>
                                    <div v-else class="w-16 h-16 rounded-full bg-gradient-to-br from-accent to-purple-600 flex items-center justify-center mb-3 text-xl font-bold text-white">
                                        {{ getUserInitials(u.name) }}
                                    </div>
                                    <h3 class="text-white font-semibold text-center text-sm mb-0.5">{{ u.name }}</h3>
                                    <p class="text-gray-500 text-xs">{{ u.role_label }}</p>
                                </div>
                            </button>
                        </div>

                        <div class="text-center pt-4 border-t border-gray-700/50">
                            <button
                                @click="mode = 'password'"
                                data-testid="lock-show-password"
                                class="text-accent hover:text-accent/80 transition-colors text-sm"
                            >
                                Войти по логину и паролю
                            </button>
                        </div>
                    </div>

                    <!-- Нет сотрудников -->
                    <div v-else class="text-center py-12">
                        <div class="text-gray-500 mb-4">Не удалось загрузить список сотрудников</div>
                        <button
                            @click="mode = 'password'"
                            class="px-6 py-3 bg-accent hover:bg-accent/90 text-white font-semibold rounded-xl transition-colors"
                        >
                            Войти по логину и паролю
                        </button>
                    </div>
                </div>

                <!-- Режим: PIN ввод -->
                <div v-else-if="mode === 'pin'" class="flex justify-center">
                    <div class="bg-dark-800/60 rounded-2xl p-8 w-80 border border-gray-700/50">
                        <button
                            @click="mode = 'grid'; selectedUser = null; pin = ''; error = ''"
                            class="text-gray-400 hover:text-white mb-4 flex items-center gap-2 transition-colors text-sm"
                        >
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                            </svg>
                            Назад
                        </button>

                        <div class="text-center mb-6">
                            <div v-if="selectedUser?.avatar" class="w-16 h-16 rounded-full overflow-hidden mx-auto mb-3 border-2 border-accent">
                                <img :src="selectedUser.avatar" :alt="selectedUser.name" class="w-full h-full object-cover" />
                            </div>
                            <div v-else class="w-16 h-16 rounded-full bg-gradient-to-br from-accent to-purple-600 flex items-center justify-center mx-auto mb-3 text-xl font-bold text-white">
                                {{ getUserInitials(selectedUser?.name || '') }}
                            </div>
                            <h2 class="text-lg font-semibold text-white">{{ selectedUser?.name }}</h2>
                            <p class="text-gray-500 text-xs mt-1">Введите PIN-код</p>
                        </div>

                        <!-- PIN Display -->
                        <div class="flex justify-center gap-2 mb-5" data-testid="lock-pin-display">
                            <div
                                v-for="i in 4"
                                :key="i"
                                :class="[
                                    'w-11 h-11 rounded-xl border-2 flex items-center justify-center text-lg font-bold transition-all',
                                    pin.length >= i
                                        ? 'border-accent bg-accent/20 text-white'
                                        : 'border-gray-600 bg-dark-900/50 text-gray-600'
                                ]"
                            >
                                {{ pin.length >= i ? '●' : '' }}
                            </div>
                        </div>

                        <!-- Error -->
                        <p v-if="error" class="text-red-400 text-sm text-center mb-4" data-testid="lock-error">
                            {{ error }}
                        </p>

                        <!-- Numpad -->
                        <div class="grid grid-cols-3 gap-2" data-testid="lock-numpad">
                            <button
                                v-for="n in [1,2,3,4,5,6,7,8,9,'',0,'⌫']"
                                :key="n"
                                @click="handleKeyPress(n)"
                                :disabled="loading || n === ''"
                                :class="[
                                    'h-13 rounded-xl font-semibold text-lg transition-all',
                                    n === ''
                                        ? 'invisible'
                                        : n === '⌫'
                                            ? 'bg-red-600/20 text-red-400 hover:bg-red-600/30'
                                            : 'bg-dark-900/50 text-white hover:bg-gray-700 active:scale-95',
                                    loading && 'opacity-50 cursor-not-allowed'
                                ]"
                            >
                                {{ n }}
                            </button>
                        </div>

                        <!-- Loading -->
                        <div v-if="loading" class="mt-4 text-center text-gray-400 text-sm">
                            Проверка...
                        </div>
                    </div>
                </div>

                <!-- Режим: Логин/Пароль -->
                <div v-else-if="mode === 'password'" class="flex justify-center">
                    <div class="bg-dark-800/60 rounded-2xl p-8 w-96 border border-gray-700/50">
                        <button
                            @click="mode = 'grid'; error = ''"
                            class="text-gray-400 hover:text-white mb-4 flex items-center gap-2 transition-colors text-sm"
                        >
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                            </svg>
                            Назад
                        </button>

                        <div class="text-center mb-6">
                            <h2 class="text-lg font-semibold text-white">Вход по паролю</h2>
                        </div>

                        <form @submit.prevent="handlePasswordLogin" class="space-y-4" data-testid="lock-password-form">
                            <input
                                v-model="form.login"
                                type="text"
                                placeholder="Логин"
                                required
                                autocomplete="username"
                                :disabled="loading"
                                class="w-full px-4 py-3 bg-dark-900/50 border border-gray-700 rounded-xl text-white placeholder-gray-500 focus:border-accent focus:outline-none transition-colors disabled:opacity-50"
                            />
                            <input
                                v-model="form.password"
                                type="password"
                                placeholder="Пароль"
                                required
                                autocomplete="current-password"
                                :disabled="loading"
                                class="w-full px-4 py-3 bg-dark-900/50 border border-gray-700 rounded-xl text-white placeholder-gray-500 focus:border-accent focus:outline-none transition-colors disabled:opacity-50"
                            />

                            <p v-if="error" class="text-red-400 text-sm text-center">{{ error }}</p>

                            <button
                                type="submit"
                                :disabled="loading || !form.login || !form.password"
                                class="w-full py-3 bg-accent hover:bg-accent/90 text-white font-semibold rounded-xl transition-all disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                {{ loading ? 'Вход...' : 'Войти' }}
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<script setup lang="ts">
import { ref, watch, onMounted, onUnmounted, PropType } from 'vue';
import { useAuthStore } from '../stores/auth';
import auth from '@/utils/auth';
import { createLogger } from '../../shared/services/logger.js';

const log = createLogger('LockScreen');

const props = defineProps({
    lockedByUser: { type: Object as PropType<Record<string, any>>, default: null },
});

const emit = defineEmits(['unlock', 'user-switch']);

const authStore = useAuthStore();

// State
const mode = ref('grid'); // 'grid' | 'pin' | 'password'
const users = ref<any[]>([]);
const loadingUsers = ref(true);
const selectedUser = ref<any>(null);
const pin = ref('');
const error = ref('');
const loading = ref(false);
const form = ref({ login: '', password: '' });
const currentTime = ref('');

let clockInterval: any = null;

// Обновление часов
function updateClock() {
    const now = new Date();
    currentTime.value = now.toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' });
}

// Загрузка списка сотрудников (только авторизованные на этом терминале)
async function loadUsers() {
    loadingUsers.value = true;
    try {
        const deviceToken = localStorage.getItem('device_token');
        const response = await auth.getDeviceUsers('pos');
        let list = response.data || response || [];
        if (!Array.isArray(list)) list = [];

        // Если текущий заблокировавший пользователь не в списке — добавим его
        // (гарантирует enterprise-фильтрацию с первой блокировки)
        if (props.lockedByUser && !list.find((u: any) => u.id === props.lockedByUser.id)) {
            list = [props.lockedByUser, ...list];
        }

        users.value = list;
    } catch (e: any) {
        log.error('Failed to load users:', e);
        // Fallback: показываем хотя бы заблокировавшего пользователя
        users.value = props.lockedByUser ? [props.lockedByUser] : [];
    } finally {
        loadingUsers.value = false;
    }
}

// Выбор пользователя из сетки
function selectUser(u: any) {
    selectedUser.value = u;
    mode.value = 'pin';
    pin.value = '';
    error.value = '';
}

// Обработка нажатия клавиши нампада
function handleKeyPress(key: any) {
    error.value = '';
    if (key === '⌫') {
        pin.value = pin.value.slice(0, -1);
    } else if (typeof key === 'number' && pin.value.length < 4) {
        pin.value += key;
    }
}

// Обработка клавиатуры
function handleKeydown(event: any) {
    if (mode.value === 'pin') {
        if (event.key >= '0' && event.key <= '9' && pin.value.length < 4) {
            pin.value += event.key;
            error.value = '';
        } else if (event.key === 'Backspace') {
            pin.value = pin.value.slice(0, -1);
            error.value = '';
        } else if (event.key === 'Escape') {
            mode.value = 'grid';
            selectedUser.value = null;
            pin.value = '';
            error.value = '';
        }
    } else if (mode.value === 'grid' && event.key === 'Escape') {
        // Esc в grid — ничего (экран заблокирован)
    }
}

// Auto-submit PIN при 4 цифрах
watch(pin, async (newPin) => {
    if (newPin.length !== 4) return;

    loading.value = true;
    error.value = '';

    try {
        const result = await authStore.loginWithPin(newPin, selectedUser.value?.id);

        if (result.success) {
            // Тот же пользователь? → unlock. Другой? → user-switch
            const isSameUser = props.lockedByUser?.id === authStore.user?.id;
            if (isSameUser) {
                emit('unlock');
            } else {
                emit('user-switch', {
                    user: authStore.user,
                    token: authStore.token,
                    permissions: authStore.permissions,
                    limits: authStore.limits,
                    interfaceAccess: authStore.interfaceAccess,
                    posModules: authStore.posModules,
                    backofficeModules: authStore.backofficeModules,
                });
            }
        } else {
            if (result.reason === 'interface_access_denied') {
                error.value = result.message || 'Нет доступа к POS';
            } else if (result.require_full_login) {
                error.value = '';
                mode.value = 'password';
                form.value.login = selectedUser.value?.email || '';
            } else {
                error.value = result.message || 'Неверный PIN-код';
            }
            pin.value = '';
        }
    } catch (e: any) {
        error.value = 'Ошибка соединения';
        pin.value = '';
    } finally {
        loading.value = false;
    }
});

// Логин по паролю
async function handlePasswordLogin() {
    if (!form.value.login || !form.value.password) {
        error.value = 'Введите логин и пароль';
        return;
    }

    loading.value = true;
    error.value = '';

    try {
        const response = await auth.login(form.value.login, form.value.password, true);

        if ((response as any).success) {
            const result = await authStore.loginWithPassword(response as any);
            if (result.success) {
                const isSameUser = props.lockedByUser?.id === authStore.user?.id;
                if (isSameUser) {
                    emit('unlock');
                } else {
                    emit('user-switch', {
                        user: authStore.user,
                        token: authStore.token,
                        permissions: authStore.permissions,
                        limits: authStore.limits,
                        interfaceAccess: authStore.interfaceAccess,
                        posModules: authStore.posModules,
                        backofficeModules: authStore.backofficeModules,
                    });
                }
            } else {
                error.value = result.message || 'Ошибка создания сессии';
            }
        } else {
            if ((response as any).reason === 'interface_access_denied') {
                error.value = (response as any).message || 'Нет доступа к POS';
            } else {
                error.value = (response as any).message || 'Ошибка входа';
            }
        }
    } catch (err: any) {
        log.error('Login error:', err);
        error.value = err.response?.data?.message
            || err.message
            || 'Неверный логин или пароль';
    } finally {
        loading.value = false;
    }
}

function getUserInitials(name: any) {
    if (!name) return '?';
    const words = name.split(' ');
    if (words.length >= 2) {
        return (words[0][0] + words[1][0]).toUpperCase();
    }
    return name.substring(0, 2).toUpperCase();
}

onMounted(() => {
    // Часы
    updateClock();
    clockInterval = setInterval(updateClock, 1000);

    // Загрузка сотрудников
    loadUsers();

    // Клавиатурные события
    document.addEventListener('keydown', handleKeydown);
});

onUnmounted(() => {
    if (clockInterval) clearInterval(clockInterval);
    document.removeEventListener('keydown', handleKeydown);
});
</script>
