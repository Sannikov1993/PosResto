<template>
    <div class="h-full flex items-center justify-center bg-dark-950" role="main" aria-labelledby="login-title">
        <div class="bg-dark-800 rounded-2xl p-8 w-80 border border-gray-700" role="form" aria-label="����� ����� �� PIN-����">
            <div class="text-center mb-8">
                <img src="/images/logo/poslab_icon.svg" alt="PosLab" class="w-16 h-16 mx-auto mb-4" />
                <h1 id="login-title" class="text-xl font-semibold text-white">PosLab</h1>
                <p class="text-gray-400 text-sm mt-1">Введите PIN-код</p>
            </div>

            <!-- PIN Display -->
            <div class="flex justify-center gap-2 mb-6">
                <div
                    v-for="i in 4"
                    :key="i"
                    :class="[
                        'w-12 h-12 rounded-xl border-2 flex items-center justify-center text-xl font-bold transition-all',
                        pin.length >= i
                            ? 'border-accent bg-accent/20 text-white'
                            : 'border-gray-600 bg-dark-900 text-gray-600'
                    ]"
                >
                    {{ pin.length >= i ? '●' : '' }}
                </div>
            </div>

            <!-- Error Message -->
            <p v-if="error" class="text-red-400 text-sm text-center mb-4" role="alert" aria-live="assertive">
                {{ error }}
            </p>

            <!-- Number Pad -->
            <div class="grid grid-cols-3 gap-2">
                <button
                    v-for="n in [1,2,3,4,5,6,7,8,9,'',0,'⌫']"
                    :key="n"
                    @click="handleKeyPress(n)"
                    :disabled="loading || n === ''"
                    :class="[
                        'h-14 rounded-xl font-semibold text-lg transition-all',
                        n === ''
                            ? 'invisible'
                            : n === '⌫'
                                ? 'bg-red-600/20 text-red-400 hover:bg-red-600/30'
                                : 'bg-dark-900 text-white hover:bg-gray-700 active:scale-95'
                    ]"
                >
                    {{ n }}
                </button>
            </div>

            <!-- Loading -->
            <div v-if="loading" class="mt-4 text-center text-gray-400" role="status" aria-live="polite">
                Проверка...
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, watch } from 'vue';
import { useAuthStore } from '../stores/auth';

const emit = defineEmits(['login']);

const authStore = useAuthStore();

const pin = ref('');
const error = ref('');
const loading = ref(false);

const handleKeyPress = (key) => {
    error.value = '';

    if (key === '⌫') {
        pin.value = pin.value.slice(0, -1);
    } else if (typeof key === 'number' && pin.value.length < 4) {
        pin.value += key;
    }
};

// Auto-submit when 4 digits entered
watch(pin, async (newPin) => {
    if (newPin.length === 4) {
        loading.value = true;
        error.value = '';

        const result = await authStore.loginWithPin(newPin);

        if (result.success) {
            emit('login', authStore.user);
        } else {
            error.value = result.message || 'Неверный PIN-код';
            pin.value = '';
        }

        loading.value = false;
    }
});
</script>
