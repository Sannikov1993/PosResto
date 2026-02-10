<template>
    <div class="deposit-picker-wrapper" :class="{ 'embedded-mode': embedded }">
        <!-- Trigger button (hidden in embedded mode) -->
        <button v-if="!embedded"
                @click="toggleOpen" ref="triggerRef"
                class="w-full flex items-center justify-between px-4 py-3 bg-[#252a3a] rounded-xl border border-gray-700 hover:border-gray-600 transition-colors">
            <div class="flex items-center gap-3">
                <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                </svg>
                <span class="text-white text-lg font-medium">{{ displayText }}</span>
            </div>
            <div class="flex items-center gap-2">
                <span v-if="modelValue > 0 && paymentMethod" class="text-gray-500 text-sm">
                    {{ paymentMethod === 'cash' ? 'наличные' : 'картой' }}
                </span>
                <svg :class="['w-4 h-4 text-gray-400 transition-transform', isOpen ? 'rotate-180' : '']" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </div>
        </button>

        <!-- Overlay panel (always visible in embedded mode) -->
        <Transition name="slide-down">
            <div v-if="isOpen || embedded" class="deposit-overlay" :class="{ 'embedded': embedded }" :style="!embedded ? overlayStyle : {}">
                <!-- Header -->
                <div class="deposit-header">
                    <div class="flex items-center gap-3">
                        <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                        <span class="text-white font-semibold">Депозит</span>
                    </div>
                    <button @click="close" class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-white/10 text-gray-400 hover:text-white transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <!-- Amount display with +/- -->
                <div class="deposit-amount">
                    <div class="flex items-center justify-center gap-4">
                        <button @click="decrementAmount"
                                :disabled="tempAmount <= 0"
                                :class="[
                                    'w-14 h-14 rounded-xl flex items-center justify-center text-2xl font-bold transition-colors',
                                    tempAmount <= 0 ? 'bg-gray-700/50 text-gray-600 cursor-not-allowed' : 'bg-gray-700 hover:bg-gray-600 text-white'
                                ]">
                            −
                        </button>
                        <div class="text-center min-w-[140px]">
                            <div class="text-4xl font-bold text-white">{{ formatAmount(tempAmount) }}</div>
                            <div class="text-sm text-gray-500 mt-1">шаг {{ formatAmount(step) }}</div>
                        </div>
                        <button @click="incrementAmount"
                                :class="[
                                    'w-14 h-14 rounded-xl flex items-center justify-center text-2xl font-bold transition-colors',
                                    'bg-gray-700 hover:bg-gray-600 text-white'
                                ]">
                            +
                        </button>
                    </div>
                </div>

                <!-- Quick amounts grid -->
                <div class="deposit-quick">
                    <div class="grid grid-cols-4 gap-2 p-4">
                        <button v-for="amount in quickAmounts" :key="amount"
                                @click="selectQuick(amount)"
                                :class="[
                                    'h-11 rounded-xl font-medium transition-all text-sm',
                                    amount === tempAmount
                                        ? 'bg-blue-500 text-white ring-2 ring-blue-400 ring-offset-2 ring-offset-[#1a1f2e]'
                                        : 'bg-gray-700/50 text-white hover:bg-gray-600'
                                ]">
                            {{ formatAmount(amount) }}
                        </button>
                    </div>
                    <!-- No deposit button -->
                    <div class="px-4 pb-4">
                        <button @click="selectQuick(0)"
                                :class="[
                                    'w-full h-10 rounded-xl font-medium transition-all text-sm',
                                    tempAmount === 0
                                        ? 'bg-gray-600 text-white ring-2 ring-gray-500 ring-offset-2 ring-offset-[#1a1f2e]'
                                        : 'bg-gray-700/30 text-gray-400 hover:bg-gray-700/50 hover:text-white'
                                ]">
                            Без депозита
                        </button>
                    </div>
                </div>

                <!-- Payment method toggle -->
                <div v-if="tempAmount > 0" class="deposit-method">
                    <div class="flex gap-2 p-4">
                        <button @click="tempMethod = 'cash'"
                                :class="[
                                    'flex-1 flex items-center justify-center gap-2 py-3 rounded-xl font-medium transition-all',
                                    tempMethod === 'cash'
                                        ? 'bg-blue-500/20 text-blue-400 ring-1 ring-blue-500'
                                        : 'bg-gray-700/30 text-gray-400 hover:bg-gray-700/50'
                                ]">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                            </svg>
                            Наличные
                        </button>
                        <button @click="tempMethod = 'card'"
                                :class="[
                                    'flex-1 flex items-center justify-center gap-2 py-3 rounded-xl font-medium transition-all',
                                    tempMethod === 'card'
                                        ? 'bg-blue-500/20 text-blue-400 ring-1 ring-blue-500'
                                        : 'bg-gray-700/30 text-gray-400 hover:bg-gray-700/50'
                                ]">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                            </svg>
                            Картой
                        </button>
                    </div>
                </div>

                <!-- Confirm button -->
                <div class="deposit-footer">
                    <button @click="confirm"
                            :class="[
                                'w-full py-3 rounded-xl text-sm font-medium transition-colors',
                                tempAmount > 0 ? 'bg-blue-500 hover:bg-blue-600 text-white' : 'bg-blue-500 hover:bg-blue-600 text-white'
                            ]">
                        {{ tempAmount > 0 ? `Подтвердить — ${formatAmount(tempAmount)}` : 'Сохранить без депозита' }}
                    </button>
                </div>
            </div>
        </Transition>
    </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, nextTick } from 'vue';

const props = defineProps({
    modelValue: {
        type: Number,
        default: 0
    },
    paymentMethod: {
        type: String,
        default: 'cash' // 'cash' or 'card'
    },
    panelWidth: {
        type: String,
        default: '384px'
    },
    step: {
        type: Number,
        default: 500
    },
    embedded: {
        type: Boolean,
        default: false
    }
});

const emit = defineEmits(['update:modelValue', 'update:paymentMethod', 'close']);

const isOpen = ref(false);
const triggerRef = ref<any>(null);
const overlayTop = ref(0);
const tempAmount = ref(0);
const tempMethod = ref('cash');

// Quick amount presets
const quickAmounts = [500, 1000, 1500, 2000, 2500, 3000, 5000, 10000];

// Display text for button
const displayText = computed(() => {
    if (props.modelValue <= 0) return 'Без депозита';
    return formatAmount(props.modelValue);
});

// Format amount with currency
const formatAmount = (amount: any) => {
    if (amount >= 10000) {
        return (amount / 1000) + 'К ₽';
    }
    return amount.toLocaleString('ru-RU') + ' ₽';
};

// Overlay style
const overlayStyle = computed(() => ({
    width: props.panelWidth,
    top: overlayTop.value + 'px'
}));

// Methods
const toggleOpen = () => {
    if (isOpen.value) {
        close();
    } else {
        open();
    }
};

const open = () => {
    tempAmount.value = props.modelValue;
    tempMethod.value = props.paymentMethod || 'cash';

    nextTick(() => {
        if (triggerRef.value) {
            const rect = triggerRef.value.getBoundingClientRect();
            overlayTop.value = rect.bottom + 8;
        }
    });

    isOpen.value = true;
};

const close = () => {
    if (props.embedded) {
        emit('close');
    } else {
        isOpen.value = false;
    }
};

const selectQuick = (amount: any) => {
    tempAmount.value = amount;
};

const decrementAmount = () => {
    if (tempAmount.value >= props.step) {
        tempAmount.value -= props.step;
    } else {
        tempAmount.value = 0;
    }
};

const incrementAmount = () => {
    tempAmount.value += props.step;
};

const confirm = () => {
    emit('update:modelValue', tempAmount.value);
    if (tempAmount.value > 0) {
        emit('update:paymentMethod', tempMethod.value);
    }
    close();
};

// Sync temp values when props change
watch(() => props.modelValue, (val) => {
    if (!isOpen.value) {
        tempAmount.value = val;
    }
}, { immediate: true });

watch(() => props.paymentMethod, (val) => {
    if (!isOpen.value) {
        tempMethod.value = val || 'cash';
    }
}, { immediate: true });
</script>

<style scoped>
.deposit-picker-wrapper {
    position: static;
}

.deposit-picker-wrapper.embedded-mode {
    height: 100%;
    display: flex;
    flex-direction: column;
}

.deposit-picker-wrapper.embedded-mode .deposit-overlay {
    position: relative;
    top: auto !important;
    right: auto;
    bottom: auto;
    width: 100% !important;
    height: 100%;
    box-shadow: none;
    border-top: none;
}

/* Overlay */
.deposit-overlay {
    position: fixed;
    right: 0;
    bottom: 0;
    z-index: 10000;
    background: #1a1f2e;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    border-top: 1px solid rgba(55, 65, 81, 0.5);
    box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.3);
}

/* Header */
.deposit-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 20px;
    border-bottom: 1px solid rgba(55, 65, 81, 0.5);
    flex-shrink: 0;
}

/* Amount display */
.deposit-amount {
    padding: 24px 20px;
    background: rgba(37, 42, 58, 0.3);
    border-bottom: 1px solid rgba(55, 65, 81, 0.5);
    flex-shrink: 0;
}

/* Quick amounts */
.deposit-quick {
    flex-shrink: 0;
    border-bottom: 1px solid rgba(55, 65, 81, 0.5);
}

/* Payment method */
.deposit-method {
    flex-shrink: 0;
    border-bottom: 1px solid rgba(55, 65, 81, 0.5);
    background: rgba(37, 42, 58, 0.2);
}

/* Footer */
.deposit-footer {
    padding: 16px 20px;
    flex-shrink: 0;
}

/* Slide transition */
.slide-down-enter-active,
.slide-down-leave-active {
    transition: transform 0.25s ease, opacity 0.25s ease;
}

.slide-down-enter-from {
    transform: translateY(-20px);
    opacity: 0;
}

.slide-down-leave-to {
    transform: translateY(-20px);
    opacity: 0;
}
</style>
