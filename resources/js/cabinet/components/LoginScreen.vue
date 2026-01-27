<template>
    <div class="min-h-screen bg-gradient-to-br from-orange-500 to-orange-600 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-8">
            <div class="text-center mb-8">
                <img src="/images/logo/poslab_icon.svg" alt="PosLab" class="w-16 h-16 mx-auto mb-4" />
                <h1 class="text-2xl font-bold text-gray-900">Личный кабинет</h1>
                <p class="text-gray-500 mt-1">Войдите для продолжения</p>
            </div>

            <!-- Login Method Toggle -->
            <div class="flex gap-2 mb-6 bg-gray-100 rounded-lg p-1">
                <button @click="loginMethod = 'pin'"
                        :class="['flex-1 py-2 rounded-md text-sm font-medium transition',
                                 loginMethod === 'pin' ? 'bg-white text-orange-600 shadow' : 'text-gray-600']">
                    По PIN
                </button>
                <button @click="loginMethod = 'password'"
                        :class="['flex-1 py-2 rounded-md text-sm font-medium transition',
                                 loginMethod === 'password' ? 'bg-white text-orange-600 shadow' : 'text-gray-600']">
                    По паролю
                </button>
            </div>

            <!-- PIN Login -->
            <form v-if="loginMethod === 'pin'" @submit.prevent="loginWithPin" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Введите PIN</label>
                    <div class="flex justify-center gap-3">
                        <input v-for="i in 4" :key="i"
                               :ref="el => pinRefs[i-1] = el"
                               v-model="pin[i-1]"
                               type="text"
                               maxlength="1"
                               inputmode="numeric"
                               pattern="[0-9]"
                               @input="handlePinInput($event, i-1)"
                               @keydown.backspace="handlePinBackspace($event, i-1)"
                               class="w-14 h-14 text-center text-2xl font-bold border-2 rounded-xl focus:border-orange-500 focus:ring-2 focus:ring-orange-200 transition" />
                    </div>
                </div>

                <button type="submit"
                        :disabled="loading || pin.join('').length < 4"
                        class="w-full py-3 bg-orange-500 text-white rounded-xl font-semibold hover:bg-orange-600 transition disabled:opacity-50 disabled:cursor-not-allowed">
                    {{ loading ? 'Вход...' : 'Войти' }}
                </button>
            </form>

            <!-- Password Login -->
            <form v-else @submit.prevent="loginWithPassword" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Email или телефон</label>
                    <input v-model="credentials.login"
                           type="text"
                           class="w-full px-4 py-3 border rounded-xl focus:border-orange-500 focus:ring-2 focus:ring-orange-200 transition"
                           placeholder="email@example.com" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Пароль</label>
                    <input v-model="credentials.password"
                           type="password"
                           class="w-full px-4 py-3 border rounded-xl focus:border-orange-500 focus:ring-2 focus:ring-orange-200 transition"
                           placeholder="Введите пароль" />
                </div>

                <button type="submit"
                        :disabled="loading || !credentials.login || !credentials.password"
                        class="w-full py-3 bg-orange-500 text-white rounded-xl font-semibold hover:bg-orange-600 transition disabled:opacity-50 disabled:cursor-not-allowed">
                    {{ loading ? 'Вход...' : 'Войти' }}
                </button>
            </form>

            <!-- Error Message -->
            <div v-if="error" class="mt-4 p-3 bg-red-50 text-red-600 rounded-lg text-center text-sm">
                {{ error }}
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive } from 'vue';

const emit = defineEmits(['login']);

const loginMethod = ref('pin');
const loading = ref(false);
const error = ref('');

// PIN login
const pin = ref(['', '', '', '']);
const pinRefs = ref([]);

// Password login
const credentials = reactive({
    login: '',
    password: '',
});

function handlePinInput(event, index) {
    const value = event.target.value.replace(/\D/g, '');
    pin.value[index] = value;

    if (value && index < 3) {
        pinRefs.value[index + 1]?.focus();
    }

    // Auto submit when all digits entered
    if (pin.value.join('').length === 4) {
        loginWithPin();
    }
}

function handlePinBackspace(event, index) {
    if (!pin.value[index] && index > 0) {
        pinRefs.value[index - 1]?.focus();
    }
}

async function loginWithPin() {
    const pinCode = pin.value.join('');
    if (pinCode.length < 4) return;

    loading.value = true;
    error.value = '';

    try {
        const response = await fetch('/api/auth/login-pin', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify({ pin: pinCode }),
        });

        const data = await response.json();

        if (data.success) {
            emit('login', data.user, data.token);
        } else {
            error.value = data.message || 'Неверный PIN';
            pin.value = ['', '', '', ''];
            pinRefs.value[0]?.focus();
        }
    } catch (e) {
        error.value = 'Ошибка соединения';
        pin.value = ['', '', '', ''];
    } finally {
        loading.value = false;
    }
}

async function loginWithPassword() {
    if (!credentials.login || !credentials.password) return;

    loading.value = true;
    error.value = '';

    try {
        const response = await fetch('/api/auth/login', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                email: credentials.login,
                password: credentials.password,
            }),
        });

        const data = await response.json();

        if (data.success) {
            emit('login', data.user, data.token);
        } else {
            error.value = data.message || 'Неверные данные';
        }
    } catch (e) {
        error.value = 'Ошибка соединения';
    } finally {
        loading.value = false;
    }
}
</script>
