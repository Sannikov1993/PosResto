<template>
    <div class="h-full flex flex-col items-center justify-center p-6 bg-dark-900">
        <div class="text-center mb-8">
            <img src="/images/logo/menulab_logo_dark_bg.svg" alt="MenuLab" class="h-16 mx-auto mb-4" />
            <p class="text-gray-500 text-lg">Официант</p>
        </div>

        <!-- PIN Mode -->
        <div v-if="mode === 'pin'" class="w-full max-w-xs">
            <p class="text-center text-gray-400 mb-4">Введите PIN-код</p>

            <!-- PIN Display -->
            <div class="flex justify-center gap-3 mb-6">
                <div v-for="i in 4" :key="i"
                     :class="['w-4 h-4 rounded-full transition-all',
                              pin.length >= i ? 'bg-orange-500 scale-110' : 'bg-gray-700']">
                </div>
            </div>

            <!-- Error -->
            <p v-if="error" class="text-red-500 text-center text-sm mb-4">{{ error }}</p>

            <!-- Numpad -->
            <div class="grid grid-cols-3 gap-3 mb-4">
                <button v-for="n in 9" :key="n"
                        @click="inputPin(n)"
                        class="h-16 rounded-2xl bg-dark-800 text-2xl font-semibold active:bg-dark-700 transition">
                    {{ n }}
                </button>
                <button @click="clearPin"
                        class="h-16 rounded-2xl bg-dark-800 text-gray-500 active:bg-dark-700 transition">
                    C
                </button>
                <button @click="inputPin(0)"
                        class="h-16 rounded-2xl bg-dark-800 text-2xl font-semibold active:bg-dark-700 transition">
                    0
                </button>
                <button @click="backspace"
                        class="h-16 rounded-2xl bg-dark-800 text-gray-500 active:bg-dark-700 transition">
                    ⌫
                </button>
            </div>

            <button
                v-if="hasDeviceToken"
                @click="mode = 'password'"
                class="w-full text-orange-500 text-sm">
                Войти по логину и паролю
            </button>
        </div>

        <!-- Password Mode -->
        <div v-else class="w-full max-w-sm">
            <form @submit.prevent="handlePasswordLogin" class="space-y-4">
                <div>
                    <input
                        v-model="form.login"
                        type="text"
                        placeholder="Логин"
                        required
                        class="w-full px-4 py-3 rounded-xl bg-dark-800 border border-gray-700 focus:border-orange-500 focus:outline-none"
                    />
                </div>

                <div>
                    <input
                        v-model="form.password"
                        type="password"
                        placeholder="Пароль"
                        required
                        class="w-full px-4 py-3 rounded-xl bg-dark-800 border border-gray-700 focus:border-orange-500 focus:outline-none"
                    />
                </div>

                <div class="flex items-center">
                    <input
                        v-model="form.rememberDevice"
                        type="checkbox"
                        id="remember"
                        class="mr-2"
                    />
                    <label for="remember" class="text-sm text-gray-400">
                        Запомнить это устройство
                    </label>
                </div>

                <p v-if="error" class="text-red-500 text-center text-sm">{{ error }}</p>

                <button
                    type="submit"
                    :disabled="loading"
                    class="w-full py-3 rounded-xl bg-orange-500 font-semibold hover:bg-orange-600 transition disabled:opacity-50">
                    {{ loading ? 'Вход...' : 'Войти' }}
                </button>
            </form>

            <button
                v-if="hasDeviceToken"
                @click="mode = 'pin'"
                class="w-full mt-4 text-orange-500 text-sm">
                ← Назад к PIN
            </button>
        </div>
    </div>
</template>

<script setup>
import { ref, watch, onMounted } from 'vue';
import auth from '@/utils/auth';

const emit = defineEmits(['login']);

// ✅ Показываем PIN только если есть device_token
const hasDeviceToken = ref(false);
const mode = ref('password'); // 'pin' or 'password'
const pin = ref('');
const error = ref('');
const loading = ref(false);

const form = ref({
    login: '',
    password: '',
    rememberDevice: true,
});

onMounted(() => {
    // Проверяем наличие device_token при монтировании
    hasDeviceToken.value = !!localStorage.getItem('device_token');
    mode.value = hasDeviceToken.value ? 'pin' : 'password';
});

const inputPin = (num) => {
    if (pin.value.length < 4) {
        pin.value += num.toString();
    }
};

const clearPin = () => {
    pin.value = '';
    error.value = '';
};

const backspace = () => {
    pin.value = pin.value.slice(0, -1);
};

// Auto-submit when 4 digits entered
watch(pin, async (val) => {
    if (val.length === 4) {
        loading.value = true;
        error.value = '';

        try {
            const response = await auth.loginByPin(val);
            if (response.success) {
                emit('login', response.data);
            } else {
                error.value = response.message || 'Неверный PIN-код';
                pin.value = '';
            }
        } catch (e) {
            error.value = e.response?.data?.message || 'Ошибка авторизации';
            pin.value = '';
        } finally {
            loading.value = false;
        }
    }
});

const handlePasswordLogin = async () => {
    loading.value = true;
    error.value = '';

    try {
        const response = await auth.login(
            form.value.login,
            form.value.password,
            form.value.rememberDevice
        );

        if (response.success) {
            emit('login', response.data);
        } else {
            error.value = response.message || 'Ошибка входа';
        }
    } catch (e) {
        error.value = e.response?.data?.message || 'Неверный логин или пароль';
    } finally {
        loading.value = false;
    }
};
</script>
