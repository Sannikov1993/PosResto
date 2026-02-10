<template>
    <Teleport to="body">
        <div
            v-if="show"
            class="fixed inset-0 bg-black/70 flex items-center justify-center z-[9999] p-4"
            @click.self="close"
        >
            <div class="bg-dark-800 rounded-2xl w-full max-w-md overflow-hidden" data-testid="cash-operation-modal">
                <!-- Header -->
                <div :class="[
                    'px-6 py-4 border-b border-gray-700',
                    isDeposit ? 'bg-green-900/30' : 'bg-red-900/30'
                ]">
                    <h2 class="text-xl font-semibold flex items-center gap-2">
                        <span :class="isDeposit ? 'text-green-400' : 'text-red-400'">
                            {{ isDeposit ? '↓' : '↑' }}
                        </span>
                        {{ isDeposit ? 'Внесение в кассу' : 'Изъятие из кассы' }}
                    </h2>
                </div>

                <div class="p-6 space-y-5">
                    <!-- Крупное отображение суммы -->
                    <div class="text-center">
                        <div
                            data-testid="cash-amount-display"
                            :class="[
                                'text-5xl font-bold py-4 rounded-xl transition-colors',
                                amountString ? 'text-white' : 'text-gray-600',
                                !isDeposit && insufficientFunds ? 'text-red-400 bg-red-900/20' : 'bg-dark-900'
                            ]"
                        >
                            {{ displayAmount }} <span class="text-2xl text-gray-500">₽</span>
                        </div>
                    </div>

                    <!-- Быстрые суммы -->
                    <div class="flex gap-2">
                        <button
                            v-for="preset in quickAmounts"
                            :key="preset"
                            @click="setAmount(preset)"
                            :data-testid="`quick-amount-${preset}`"
                            class="flex-1 py-2 bg-dark-700 hover:bg-dark-600 rounded-lg text-sm font-medium transition-colors"
                        >
                            {{ preset >= 1000 ? (preset / 1000) + 'к' : preset }}
                        </button>
                        <button
                            v-if="!isDeposit"
                            @click="setAmount(currentCash)"
                            data-testid="withdrawal-all-btn"
                            class="flex-1 py-2 bg-accent/20 hover:bg-accent/30 text-accent rounded-lg text-sm font-medium transition-colors"
                        >
                            Всё
                        </button>
                    </div>

                    <!-- Numpad -->
                    <div class="grid grid-cols-3 gap-2">
                        <button
                            v-for="key in numpadKeys"
                            :key="key"
                            @click="handleNumpad(key)"
                            :data-testid="`numpad-${key === '⌫' ? 'backspace' : key}`"
                            :class="[
                                'py-4 rounded-xl text-xl font-medium transition-all active:scale-95',
                                key === 'C' ? 'bg-red-900/30 text-red-400 hover:bg-red-900/50' :
                                key === '⌫' ? 'bg-dark-700 text-gray-400 hover:bg-dark-600' :
                                'bg-dark-900 hover:bg-dark-700'
                            ]"
                        >
                            {{ key }}
                        </button>
                    </div>

                    <!-- Категория (только для изъятия) -->
                    <div v-if="!isDeposit">
                        <label class="block text-sm text-gray-400 mb-2">Категория</label>
                        <div class="flex bg-dark-900 rounded-xl p-1">
                            <button
                                v-for="cat in categories"
                                :key="cat.value"
                                @click="category = cat.value"
                                :data-testid="`withdrawal-category-${cat.value}`"
                                :class="[
                                    'flex-1 flex flex-col items-center gap-1 py-2 px-1 rounded-lg transition-all',
                                    category === cat.value
                                        ? 'bg-accent text-white shadow-lg'
                                        : 'text-gray-400 hover:text-white hover:bg-dark-700'
                                ]"
                            >
                                <span class="text-lg">{{ cat.icon }}</span>
                                <span class="text-xs">{{ cat.label }}</span>
                            </button>
                        </div>
                    </div>

                    <!-- Комментарий -->
                    <div>
                        <label class="block text-sm text-gray-400 mb-2">Комментарий</label>
                        <input
                            v-model="description"
                            type="text"
                            data-testid="cash-operation-comment"
                            class="w-full bg-dark-900 border border-gray-700 rounded-xl px-4 py-3 focus:border-accent focus:outline-none transition-colors"
                            :placeholder="commentPlaceholder"
                        />
                    </div>

                    <!-- Прогресс-бар остатка -->
                    <div class="bg-dark-900 rounded-xl p-4 space-y-3">
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-400">{{ isDeposit ? 'Сейчас в кассе' : 'В кассе' }}</span>
                            <span class="text-white font-medium">{{ formatMoney(currentCash) }} ₽</span>
                        </div>

                        <!-- Progress bar -->
                        <div class="relative h-3 bg-dark-700 rounded-full overflow-hidden">
                            <div
                                class="absolute inset-y-0 left-0 rounded-full transition-all duration-300"
                                :class="isDeposit ? 'bg-green-500' : (insufficientFunds ? 'bg-red-500' : 'bg-accent')"
                                :style="{ width: progressWidth + '%' }"
                            ></div>
                            <!-- Маркер текущей позиции -->
                            <div
                                v-if="!isDeposit && amount > 0 && !insufficientFunds"
                                class="absolute inset-y-0 w-0.5 bg-white/50"
                                :style="{ left: markerPosition + '%' }"
                            ></div>
                        </div>

                        <div class="flex justify-between text-sm">
                            <span class="text-gray-400">После операции</span>
                            <span :class="[
                                'font-medium',
                                insufficientFunds ? 'text-red-400' : (isDeposit ? 'text-green-400' : 'text-white')
                            ]">
                                {{ formatMoney(afterOperation) }} ₽
                            </span>
                        </div>

                        <!-- Предупреждение -->
                        <div
                            v-if="!isDeposit && insufficientFunds"
                            data-testid="insufficient-funds-warning"
                            class="flex items-center gap-2 text-red-400 text-sm bg-red-900/20 rounded-lg px-3 py-2"
                        >
                            <span>⚠️</span>
                            <span>Недостаточно средств в кассе</span>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="flex gap-3 p-6 pt-0">
                    <button
                        @click="close"
                        class="flex-1 px-4 py-3.5 bg-dark-900 hover:bg-gray-700 rounded-xl font-medium transition-colors"
                    >
                        Отмена
                    </button>
                    <button
                        @click="submit"
                        :disabled="loading || !canSubmit"
                        data-testid="cash-operation-submit"
                        :class="[
                            'flex-1 px-4 py-3.5 rounded-xl font-medium transition-all',
                            isDeposit
                                ? 'bg-green-600 hover:bg-green-500 text-white'
                                : 'bg-red-600 hover:bg-red-500 text-white',
                            (loading || !canSubmit) && 'opacity-50 cursor-not-allowed'
                        ]"
                    >
                        <span v-if="loading">Выполнение...</span>
                        <span v-else>
                            {{ isDeposit ? 'Внести' : 'Снять' }}
                            <template v-if="amount > 0">{{ formatMoney(amount) }} ₽</template>
                        </span>
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import api from '../../api';

const props = defineProps({
    show: Boolean,
    type: {
        type: String,
        default: 'deposit',
        validator: v => ['deposit', 'withdrawal'].includes(v as any)
    },
    currentCash: {
        type: Number,
        default: 0
    }
});

const emit = defineEmits(['update:show', 'completed']);

// Категории для изъятия
const categories = [
    { value: 'purchase', label: 'Закупка', icon: '🛒' },
    { value: 'salary', label: 'Зарплата', icon: '💼' },
    { value: 'tips', label: 'Чаевые', icon: '💵' },
    { value: 'other', label: 'Прочее', icon: '📋' }
];

// Быстрые суммы
const quickAmounts = [100, 500, 1000, 5000];

// Клавиши numpad
const numpadKeys = ['1', '2', '3', '4', '5', '6', '7', '8', '9', 'C', '0', '⌫'];

// State
const amountString = ref('');
const category = ref('purchase');
const description = ref('');
const loading = ref(false);

// Computed
const isDeposit = computed(() => props.type === 'deposit');

const amount = computed(() => {
    return parseInt(amountString.value) || 0;
});

const displayAmount = computed(() => {
    if (!amountString.value) return '0';
    return parseInt(amountString.value).toLocaleString('ru-RU');
});

const afterOperation = computed(() => {
    if (isDeposit.value) {
        return props.currentCash + amount.value;
    }
    return props.currentCash - amount.value;
});

const insufficientFunds = computed(() => {
    return !isDeposit.value && amount.value > props.currentCash;
});

const canSubmit = computed(() => {
    if (amount.value <= 0) return false;
    if (!isDeposit.value && !category.value) return false;
    if (!isDeposit.value && amount.value > props.currentCash) return false;
    return true;
});

// Прогресс-бар
const progressWidth = computed(() => {
    if (props.currentCash <= 0) return 0;
    if (isDeposit.value) {
        // Для внесения показываем заполнение
        const total = props.currentCash + amount.value;
        if (total <= 0) return 0;
        return Math.min(100, (afterOperation.value / total) * 100);
    } else {
        // Для изъятия показываем остаток
        if (insufficientFunds.value) return 100;
        return Math.max(0, (afterOperation.value / props.currentCash) * 100);
    }
});

const markerPosition = computed(() => {
    if (props.currentCash <= 0) return 0;
    return Math.min(100, ((props.currentCash - amount.value) / props.currentCash) * 100);
});

const commentPlaceholder = computed(() => {
    if (isDeposit.value) {
        return 'Например: размен, инкассация';
    }
    const placeholders = {
        purchase: 'Например: продукты, расходники',
        salary: 'Например: аванс, премия',
        tips: 'Например: чаевые официантам',
        other: 'Укажите причину'
    };
    return (placeholders as Record<string, any>)[category.value] || 'Необязательно';
});

// Methods
const formatMoney = (n: any) => {
    if (n === null || n === undefined) return '0';
    return Math.floor(n).toLocaleString('ru-RU');
};

const handleNumpad = (key: any) => {
    if (key === 'C') {
        amountString.value = '';
    } else if (key === '⌫') {
        amountString.value = amountString.value.slice(0, -1);
    } else {
        // Ограничение на 7 цифр (до 9,999,999)
        if (amountString.value.length < 7) {
            amountString.value += key;
        }
    }
};

const setAmount = (value: any) => {
    amountString.value = String(Math.floor(value));
};

const close = () => {
    emit('update:show', false);
};

const submit = async () => {
    if (!canSubmit.value) return;

    loading.value = true;
    try {
        let result;
        if (isDeposit.value) {
            result = await api.cashOperations.deposit(amount.value, description.value || null);
            window.$toast?.(`Внесено ${formatMoney(amount.value)} ₽`, 'success');
        } else {
            result = await api.cashOperations.withdrawal(amount.value, category.value, description.value || null);
            window.$toast?.(`Снято ${formatMoney(amount.value)} ₽`, 'success');
        }
        emit('completed', result);
        close();
    } catch (err: any) {
        const message = err.response?.data?.message || 'Ошибка выполнения операции';
        window.$toast?.(message, 'error');
    } finally {
        loading.value = false;
    }
};

// Reset form when opened
watch(() => props.show, (val) => {
    if (val) {
        amountString.value = '';
        category.value = 'purchase';
        description.value = '';
    }
});
</script>
