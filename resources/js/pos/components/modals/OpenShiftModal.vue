<template>
    <Teleport to="body">
        <div
            v-if="show"
            class="fixed inset-0 bg-black/70 flex items-center justify-center z-[9999] p-4"
            @click.self="close"
        >
            <div class="bg-dark-800 rounded-2xl p-6 w-full max-w-md">
                <h2 class="text-xl font-semibold mb-6">Открытие смены</h2>

                <div class="space-y-4">
                    <!-- Информация о предыдущей смене -->
                    <div v-if="lastShiftInfo && lastShiftInfo.closing_amount > 0" class="bg-dark-900 rounded-lg p-4">
                        <div class="flex justify-between items-center">
                            <div>
                                <p class="text-sm text-gray-400">Остаток с пред. смены</p>
                                <p v-if="lastShiftInfo.shift_number" class="text-xs text-gray-500">
                                    Смена #{{ lastShiftInfo.shift_number }}
                                </p>
                            </div>
                            <span class="text-lg font-medium text-accent">{{ formatMoney(lastShiftInfo.closing_amount) }} ₽</span>
                        </div>
                        <button
                            @click="openingAmount = lastShiftInfo.closing_amount"
                            class="mt-3 w-full py-2 bg-accent/20 hover:bg-accent/30 text-accent rounded-lg text-sm font-medium transition-colors"
                        >
                            Использовать эту сумму
                        </button>
                    </div>

                    <div>
                        <label class="block text-sm text-gray-400 mb-2">Наличные в кассе</label>
                        <input
                            v-model.number="openingAmount"
                            type="number"
                            min="0"
                            step="100"
                            class="w-full bg-dark-900 border border-gray-700 rounded-lg px-4 py-3 text-lg"
                            placeholder="0"
                        />
                    </div>

                    <p class="text-sm text-gray-500">
                        Укажите сумму наличных в кассе на момент открытия смены
                    </p>
                </div>

                <div class="flex gap-3 mt-6">
                    <button
                        @click="close"
                        class="flex-1 px-4 py-3 bg-dark-900 hover:bg-gray-700 rounded-lg"
                    >
                        Отмена
                    </button>
                    <button
                        @click="openShift"
                        :disabled="loading"
                        class="flex-1 px-4 py-3 bg-accent hover:bg-blue-600 rounded-lg text-white font-medium"
                    >
                        {{ loading ? 'Открытие...' : 'Открыть смену' }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<script setup>
import { ref, watch } from 'vue';
import api from '../../api';
import { useAuthStore } from '../../stores/auth';

const props = defineProps({
    show: Boolean
});

const emit = defineEmits(['update:show', 'opened']);

const authStore = useAuthStore();

const openingAmount = ref(0);
const loading = ref(false);
const lastShiftInfo = ref(null);
const loadingLastShift = ref(false);

const formatMoney = (n) => {
    const num = parseFloat(n);
    if (isNaN(num) || !num) return '0';
    return Math.floor(num).toLocaleString('ru-RU');
};

const close = () => {
    emit('update:show', false);
};

const loadLastShiftBalance = async () => {
    loadingLastShift.value = true;
    try {
        lastShiftInfo.value = await api.shifts.getLastBalance();
        // Автоматически подставляем сумму если есть остаток
        if (lastShiftInfo.value?.closing_amount > 0) {
            openingAmount.value = lastShiftInfo.value.closing_amount;
        }
    } catch (e) {
        console.error('Error loading last shift balance:', e);
        lastShiftInfo.value = null;
    } finally {
        loadingLastShift.value = false;
    }
};

const openShift = async () => {
    loading.value = true;
    try {
        // Передаём ID текущего пользователя как кассира
        const cashierId = authStore.user?.id || null;
        const result = await api.shifts.open(openingAmount.value, cashierId);
        if (result) {
            window.$toast?.('Смена открыта', 'success');
            emit('opened', result);
            close();
        }
    } catch (error) {
        window.$toast?.(error.response?.data?.message || 'Ошибка открытия смены', 'error');
    } finally {
        loading.value = false;
    }
};

// Load last shift balance when modal opens
watch(() => props.show, (val) => {
    if (val) {
        openingAmount.value = 0;
        lastShiftInfo.value = null;
        loadLastShiftBalance();
    }
});
</script>
