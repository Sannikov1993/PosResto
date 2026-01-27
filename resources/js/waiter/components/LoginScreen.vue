<template>
    <div class="h-full flex flex-col items-center justify-center p-6 bg-dark-900">
        <div class="text-center mb-8">
            <img src="/images/logo/poslab_logo_dark_bg.svg" alt="PosLab" class="h-16 mx-auto mb-4" />
            <p class="text-gray-500 text-lg">Официант</p>
        </div>

        <div class="w-full max-w-xs">
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
            <div class="grid grid-cols-3 gap-3">
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
        </div>
    </div>
</template>

<script setup>
import { ref, watch } from 'vue';
import axios from 'axios';

const emit = defineEmits(['login']);

const pin = ref('');
const error = ref('');
const loading = ref(false);

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
            const res = await axios.post('/api/auth/pin', { pin: val });
            if (res.data.success) {
                localStorage.setItem('waiter_user', JSON.stringify(res.data.user));
                emit('login', res.data.user);
            } else {
                error.value = 'Неверный PIN-код';
                pin.value = '';
            }
        } catch (e) {
            error.value = 'Ошибка авторизации';
            pin.value = '';
        } finally {
            loading.value = false;
        }
    }
});
</script>
