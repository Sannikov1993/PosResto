<template>
    <div class="guest-picker-wrapper" :class="{ 'embedded-mode': embedded }">
        <!-- Trigger button (hidden in embedded mode) -->
        <button v-if="!embedded"
                @click="toggleOpen" ref="triggerRef"
                class="w-full flex items-center justify-between px-4 py-3 bg-[#252a3a] rounded-xl border border-gray-700 hover:border-gray-600 transition-colors">
            <div class="flex items-center gap-3">
                <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                </svg>
                <span class="text-white text-lg font-medium">{{ displayText }}</span>
            </div>
            <div class="flex items-center gap-2">
                <span v-if="tableSeats" class="text-gray-500 text-sm">макс. {{ tableSeats }}</span>
                <svg :class="['w-4 h-4 text-gray-400 transition-transform', isOpen ? 'rotate-180' : '']" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </div>
        </button>

        <!-- Overlay panel (always visible in embedded mode) -->
        <Transition name="slide-down">
            <div v-if="isOpen || embedded" class="guest-overlay" :class="{ 'embedded': embedded }" :style="!embedded ? overlayStyle : {}">
                <!-- Header -->
                <div class="guest-header">
                    <div class="flex items-center gap-3">
                        <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                        <span class="text-white font-semibold">Количество гостей</span>
                    </div>
                    <button @click="close" class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-white/10 text-gray-400 hover:text-white transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <!-- Current selection -->
                <div class="guest-current">
                    <div class="flex items-center justify-center gap-4">
                        <button @click="decrementTemp"
                                :disabled="tempValue <= 1"
                                :class="[
                                    'w-12 h-12 rounded-xl flex items-center justify-center text-2xl font-bold transition-colors',
                                    tempValue <= 1 ? 'bg-gray-700/50 text-gray-600 cursor-not-allowed' : 'bg-gray-700 hover:bg-gray-600 text-white'
                                ]">
                            −
                        </button>
                        <div class="text-center min-w-[100px]">
                            <div class="text-4xl font-bold text-white">{{ tempValue }}</div>
                            <div class="text-sm text-gray-400 mt-1">{{ tempDisplayText }}</div>
                        </div>
                        <button @click="incrementTemp"
                                :disabled="tempValue >= 50"
                                :class="[
                                    'w-12 h-12 rounded-xl flex items-center justify-center text-2xl font-bold transition-colors',
                                    tempValue >= 50 ? 'bg-gray-700/50 text-gray-600 cursor-not-allowed' : 'bg-gray-700 hover:bg-gray-600 text-white'
                                ]">
                            +
                        </button>
                    </div>

                    <!-- Warning if over capacity -->
                    <div v-if="tempValue > tableSeats && tableSeats"
                         class="mt-3 flex items-center justify-center gap-2 text-orange-400 text-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                        <span>Превышает вместимость стола ({{ tableSeats }} мест)</span>
                    </div>
                </div>

                <!-- Quick select grid -->
                <div class="guest-grid">
                    <div class="grid grid-cols-5 gap-2 p-4">
                        <button v-for="num in quickOptions" :key="num"
                                @click="selectQuick(num)"
                                :class="[
                                    'h-12 rounded-xl font-medium transition-all',
                                    num === tempValue
                                        ? 'bg-blue-500 text-white ring-2 ring-blue-400 ring-offset-2 ring-offset-[#1a1f2e]'
                                        : num > tableSeats && tableSeats
                                            ? 'bg-gray-700/30 text-gray-500 hover:bg-gray-700/50'
                                            : 'bg-gray-700/50 text-white hover:bg-gray-600'
                                ]">
                            {{ num }}
                        </button>
                    </div>
                </div>

                <!-- Guest icons visualization -->
                <div class="guest-visual">
                    <div class="flex flex-wrap items-center justify-center gap-1 px-4 py-3">
                        <svg v-for="i in Math.min(tempValue, 20)" :key="i"
                             class="w-6 h-6 text-blue-400"
                             fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/>
                        </svg>
                        <span v-if="tempValue > 20" class="text-blue-400 text-sm font-medium ml-2">+{{ tempValue - 20 }}</span>
                    </div>
                </div>

                <!-- Confirm button -->
                <div class="guest-footer">
                    <button @click="confirm"
                            class="w-full py-3 rounded-xl text-sm font-medium transition-colors bg-blue-500 hover:bg-blue-600 text-white">
                        Подтвердить — {{ tempValue }} {{ tempDisplayText }}
                    </button>
                </div>
            </div>
        </Transition>
    </div>
</template>

<script setup>
import { ref, computed, watch, nextTick, onMounted } from 'vue';

const props = defineProps({
    modelValue: {
        type: Number,
        default: 2
    },
    tableSeats: {
        type: Number,
        default: 4
    },
    panelWidth: {
        type: String,
        default: '384px'
    },
    embedded: {
        type: Boolean,
        default: false
    }
});

const emit = defineEmits(['update:modelValue', 'close']);

const isOpen = ref(false);
const triggerRef = ref(null);
const tempValue = ref(2);
const overlayTop = ref(0);

// Quick options grid (1-10)
const quickOptions = computed(() => Array.from({ length: 10 }, (_, i) => i + 1));

// Get guest word based on count
const getGuestWord = (count) => {
    if (count === 1) return 'гость';
    if (count >= 2 && count <= 4) return 'гостя';
    return 'гостей';
};

// Display text with number
const displayText = computed(() => `${props.modelValue} ${getGuestWord(props.modelValue)}`);
const tempDisplayText = computed(() => getGuestWord(tempValue.value));

// Overlay style with dynamic positioning
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
    tempValue.value = props.modelValue;

    // Calculate position from trigger button
    nextTick(() => {
        if (triggerRef.value) {
            const rect = triggerRef.value.getBoundingClientRect();
            overlayTop.value = rect.bottom + 8; // 8px gap below button
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

const selectQuick = (num) => {
    tempValue.value = num;
};

const decrementTemp = () => {
    if (tempValue.value > 1) {
        tempValue.value--;
    }
};

const incrementTemp = () => {
    if (tempValue.value < 50) {
        tempValue.value++;
    }
};

const confirm = () => {
    emit('update:modelValue', tempValue.value);
    close();
};

// Initialize temp value when opening
watch(() => props.modelValue, (val) => {
    if (!isOpen.value) {
        tempValue.value = val;
    }
}, { immediate: true });

// Initialize in embedded mode
onMounted(() => {
    if (props.embedded) {
        tempValue.value = props.modelValue;
    }
});
</script>

<style scoped>
.guest-picker-wrapper {
    position: static;
}

.guest-picker-wrapper.embedded-mode {
    height: 100%;
    display: flex;
    flex-direction: column;
}

.guest-picker-wrapper.embedded-mode .guest-overlay {
    position: relative;
    top: auto !important;
    right: auto;
    bottom: auto;
    width: 100% !important;
    height: 100%;
    box-shadow: none;
    border-top: none;
}

/* Overlay - starts from button, extends to bottom */
.guest-overlay {
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
.guest-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 20px;
    border-bottom: 1px solid rgba(55, 65, 81, 0.5);
    flex-shrink: 0;
}

/* Current selection with +/- buttons */
.guest-current {
    padding: 20px;
    background: rgba(37, 42, 58, 0.3);
    border-bottom: 1px solid rgba(55, 65, 81, 0.5);
    flex-shrink: 0;
}

/* Quick select grid */
.guest-grid {
    flex-shrink: 0;
    border-bottom: 1px solid rgba(55, 65, 81, 0.5);
}

/* Guest icons visualization */
.guest-visual {
    flex: 1;
    min-height: 60px;
    overflow-y: auto;
    background: rgba(37, 42, 58, 0.2);
}

/* Footer */
.guest-footer {
    padding: 16px 20px;
    border-top: 1px solid rgba(55, 65, 81, 0.5);
    flex-shrink: 0;
}

/* Slide down transition */
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
