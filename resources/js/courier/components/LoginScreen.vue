<template>
    <div class="min-h-screen flex flex-col items-center justify-center p-4 bg-gradient-to-b from-purple-600 to-purple-800">
        <div class="w-full max-w-sm">
            <div class="text-center mb-8">
                <div class="w-20 h-20 bg-white rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-lg">
                    <svg class="w-12 h-12 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0"/>
                    </svg>
                </div>
                <h1 class="text-2xl font-bold text-white">PosLab Курьер</h1>
                <p class="text-purple-200 mt-1">Введите PIN-код для входа</p>
            </div>

            <!-- PIN Input Display -->
            <div class="flex justify-center gap-3 mb-6">
                <div v-for="i in 4" :key="i"
                     class="w-14 h-14 rounded-xl bg-white/20 flex items-center justify-center">
                    <div v-if="pin.length >= i" class="w-4 h-4 rounded-full bg-white"></div>
                </div>
            </div>

            <!-- Error message -->
            <div v-if="loginError" class="text-center text-red-300 mb-4 text-sm">
                {{ loginError }}
            </div>

            <!-- PIN Keypad -->
            <div class="grid grid-cols-3 gap-3 max-w-xs mx-auto">
                <button v-for="n in 9" :key="n" @click="enterPin(n)"
                        class="w-full aspect-square rounded-xl bg-white/20 text-white text-2xl font-semibold hover:bg-white/30 active:bg-white/40 transition-colors">
                    {{ n }}
                </button>
                <button @click="clearPin"
                        class="w-full aspect-square rounded-xl bg-white/10 text-white text-sm hover:bg-white/20 transition-colors">
                    Очистить
                </button>
                <button @click="enterPin(0)"
                        class="w-full aspect-square rounded-xl bg-white/20 text-white text-2xl font-semibold hover:bg-white/30 active:bg-white/40 transition-colors">
                    0
                </button>
                <button @click="submitPin" :disabled="pin.length < 4 || store.isLoading"
                        class="w-full aspect-square rounded-xl bg-white text-purple-600 text-sm font-semibold hover:bg-purple-50 disabled:opacity-50 transition-colors flex items-center justify-center">
                    <svg v-if="store.isLoading" class="w-6 h-6 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span v-else>Войти</span>
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { useCourierStore } from '../stores/courier';

const store = useCourierStore();
const pin = ref('');
const loginError = ref('');

function enterPin(digit) {
    if (pin.value.length < 4) {
        pin.value += digit;
    }
}

function clearPin() {
    pin.value = '';
    loginError.value = '';
}

async function submitPin() {
    if (pin.value.length < 4) return;

    loginError.value = '';
    const result = await store.login(pin.value);

    if (!result.success) {
        loginError.value = result.message;
        pin.value = '';
    }
}
</script>
