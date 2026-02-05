<template>
    <Teleport to="body">
        <div
            v-if="show"
            class="fixed inset-0 bg-black/70 flex items-center justify-center z-[9999] p-4"
            @click.self="close"
            data-testid="close-shift-modal"
        >
            <div class="bg-dark-800 rounded-2xl p-6 w-full max-w-md" data-testid="close-shift-content">
                <h2 class="text-xl font-semibold mb-6">Закрытие смены</h2>

                <div v-if="shift" class="space-y-4">
                    <!-- Статистика смены -->
                    <div class="bg-dark-900 rounded-lg p-4 space-y-2">
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-400">Выручка</span>
                            <span class="text-white font-medium">{{ formatMoney(shift.total_revenue) }} ₽</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-400">Наличные</span>
                            <span class="text-white">{{ formatMoney(shift.total_cash) }} ₽</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-400">Карты</span>
                            <span class="text-white">{{ formatMoney(shift.total_card) }} ₽</span>
                        </div>
                        <div v-if="parseFloat(shift.total_online) > 0" class="flex justify-between text-sm">
                            <span class="text-gray-400">Онлайн</span>
                            <span class="text-white">{{ formatMoney(shift.total_online) }} ₽</span>
                        </div>
                        <div class="flex justify-between text-sm border-t border-gray-700 pt-2 mt-2">
                            <span class="text-gray-400">Ожидаемо в кассе</span>
                            <span class="text-accent font-medium">{{ formatMoney(expectedCash) }} ₽</span>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm text-gray-400 mb-2">Фактическая сумма в кассе</label>
                        <input
                            v-model.number="closingAmount"
                            type="number"
                            min="0"
                            step="100"
                            data-testid="closing-amount-input"
                            class="w-full bg-dark-900 border border-gray-700 rounded-lg px-4 py-3 text-lg"
                            :placeholder="expectedCash"
                        />
                    </div>

                    <!-- Расхождение -->
                    <div v-if="difference !== 0" :class="['p-3 rounded-lg', difference > 0 ? 'bg-green-900/30' : 'bg-red-900/30']">
                        <span :class="difference > 0 ? 'text-green-400' : 'text-red-400'">
                            {{ difference > 0 ? 'Излишек' : 'Недостача' }}: {{ formatMoney(Math.abs(difference)) }} ₽
                        </span>
                    </div>
                </div>

                <div class="flex gap-3 mt-6">
                    <button
                        @click="close"
                        data-testid="close-shift-cancel-btn"
                        class="flex-1 px-4 py-3 bg-dark-900 hover:bg-gray-700 rounded-lg"
                    >
                        Отмена
                    </button>
                    <button
                        @click="closeShift"
                        :disabled="loading"
                        data-testid="close-shift-submit-btn"
                        class="flex-1 px-4 py-3 bg-red-600 hover:bg-red-700 rounded-lg text-white font-medium"
                    >
                        {{ loading ? 'Закрытие...' : 'Закрыть смену' }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import api from '../../api';

const props = defineProps({
    show: Boolean,
    shift: Object
});

const emit = defineEmits(['update:show', 'closed']);

const closingAmount = ref(0);
const loading = ref(false);

const expectedCash = computed(() => {
    if (!props.shift) return 0;
    // Используем current_cash из модели, который уже правильно вычислен на бэкенде
    return parseFloat(props.shift.current_cash) || 0;
});

const difference = computed(() => {
    return closingAmount.value - expectedCash.value;
});

const close = () => {
    emit('update:show', false);
};

const closeShift = async () => {
    if (!props.shift) return;

    loading.value = true;
    try {
        const result = await api.shifts.close(props.shift.id, closingAmount.value);
        if (result) {
            window.$toast?.('Смена закрыта', 'success');
            emit('closed', result);
            close();
        }
    } catch (error) {
        window.$toast?.(error.response?.data?.message || 'Ошибка закрытия смены', 'error');
    } finally {
        loading.value = false;
    }
};

const formatMoney = (n) => {
    const num = parseFloat(n);
    if (isNaN(num) || !num) return '0';
    return Math.floor(num).toLocaleString('ru-RU');
};

// Set default closing amount when opened
watch(() => props.show, (val) => {
    if (val && props.shift) {
        // Используем nextTick чтобы expectedCash успел вычислиться
        setTimeout(() => {
            closingAmount.value = expectedCash.value || 0;
        }, 0);
    }
});
</script>
