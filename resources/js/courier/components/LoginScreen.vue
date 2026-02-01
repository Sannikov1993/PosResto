<template>
    <div class="min-h-screen flex flex-col items-center justify-center p-6 bg-gradient-to-b from-purple-600 to-purple-800">
        <div class="text-center mb-8">
            <div class="w-20 h-20 bg-white rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-lg">
                <svg class="w-12 h-12 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0"/>
                </svg>
            </div>
            <h1 class="text-2xl font-bold text-white">MenuLab Курьер</h1>
            <p class="text-purple-200 mt-1">{{ mode === 'pin' ? 'Введите PIN-код' : 'Вход по паролю' }}</p>
        </div>

        <!-- PIN Mode -->
        <div v-if="mode === 'pin'" class="w-full max-w-xs">
            <!-- PIN Display -->
            <div class="flex justify-center gap-3 mb-6">
                <div v-for="i in 4" :key="i"
                     :class="['w-4 h-4 rounded-full transition-all',
                              pin.length >= i ? 'bg-white scale-110' : 'bg-white/30']">
                </div>
            </div>

            <!-- Error -->
            <p v-if="error" class="text-red-200 text-center text-sm mb-4">{{ error }}</p>

            <!-- Numpad -->
            <div class="grid grid-cols-3 gap-3 mb-4">
                <button v-for="n in 9" :key="n"
                        @click="inputPin(n)"
                        class="h-16 rounded-2xl bg-white/20 text-2xl font-semibold text-white hover:bg-white/30 active:bg-white/40 transition">
                    {{ n }}
                </button>
                <button @click="clearPin"
                        class="h-16 rounded-2xl bg-white/10 text-white/70 hover:bg-white/20 transition">
                    C
                </button>
                <button @click="inputPin(0)"
                        class="h-16 rounded-2xl bg-white/20 text-2xl font-semibold text-white hover:bg-white/30 active:bg-white/40 transition">
                    0
                </button>
                <button @click="backspace"
                        class="h-16 rounded-2xl bg-white/10 text-white/70 hover:bg-white/20 transition">
                    ⌫
                </button>
            </div>

            <button
                v-if="hasDeviceToken"
                @click="mode = 'password'"
                class="w-full text-white/90 text-sm hover:text-white transition">
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
                        class="w-full px-4 py-3 rounded-xl bg-white/20 text-white placeholder-white/60 border border-white/30 focus:border-white focus:outline-none backdrop-blur"
                    />
                </div>

                <div>
                    <input
                        v-model="form.password"
                        type="password"
                        placeholder="Пароль"
                        required
                        class="w-full px-4 py-3 rounded-xl bg-white/20 text-white placeholder-white/60 border border-white/30 focus:border-white focus:outline-none backdrop-blur"
                    />
                </div>

                <div class="flex items-center">
                    <input
                        v-model="form.rememberDevice"
                        type="checkbox"
                        id="remember"
                        class="mr-2 w-4 h-4"
                    />
                    <label for="remember" class="text-sm text-white/90">
                        Запомнить это устройство
                    </label>
                </div>

                <p v-if="error" class="text-red-200 text-center text-sm">{{ error }}</p>

                <button
                    type="submit"
                    :disabled="loading"
                    class="w-full py-3 rounded-xl bg-white text-purple-600 font-semibold hover:bg-purple-50 transition disabled:opacity-50">
                    {{ loading ? 'Вход...' : 'Войти' }}
                </button>
            </form>

            <button
                v-if="hasDeviceToken"
                @click="mode = 'pin'"
                class="w-full mt-4 text-white/90 text-sm hover:text-white transition">
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
