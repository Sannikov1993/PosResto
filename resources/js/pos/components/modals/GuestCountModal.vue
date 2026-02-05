<template>
    <Teleport to="body">
        <div v-if="modelValue" class="fixed inset-0 bg-black/80 flex items-center justify-center z-[9999]" data-testid="guest-count-modal">
            <div class="bg-dark-800 rounded-2xl p-4 w-56 shadow-2xl border border-gray-700" data-testid="guest-count-content">
                <!-- Header -->
                <div class="flex justify-between items-center mb-3">
                    <span class="text-gray-300 font-medium">Кол-во гостей</span>
                    <button @click="close" class="text-gray-500 hover:text-white text-xl leading-none">&times;</button>
                </div>

                <!-- Display -->
                <div class="bg-dark-900 rounded-xl p-3 mb-3 flex items-center justify-between border border-gray-700">
                    <span :class="['text-3xl font-semibold flex-1 text-center', value ? 'text-white' : 'text-gray-600']">
                        {{ value || '0' }}
                    </span>
                    <button @click="backspace"
                            class="bg-dark-700 text-gray-400 hover:text-white rounded-lg px-3 py-2 text-sm">
                        ⌫
                    </button>
                </div>

                <!-- Numpad -->
                <div class="grid grid-cols-3 gap-2" data-testid="guest-numpad">
                    <button v-for="n in 9" :key="n"
                            @click="input(n)"
                            :data-testid="`guest-key-${n}`"
                            class="h-12 rounded-xl bg-dark-700 text-gray-200 text-lg font-medium hover:bg-dark-600 transition-colors">
                        {{ n }}
                    </button>
                    <button @click="clear"
                            data-testid="guest-clear-btn"
                            class="h-12 rounded-xl bg-dark-700 text-blue-400 text-lg font-medium hover:bg-dark-600 transition-colors">
                        C
                    </button>
                    <button @click="input(0)"
                            data-testid="guest-key-0"
                            class="h-12 rounded-xl bg-dark-700 text-gray-200 text-lg font-medium hover:bg-dark-600 transition-colors">
                        0
                    </button>
                    <button @click="confirm"
                            data-testid="guest-confirm-btn"
                            :disabled="!value || parseInt(value) < 1"
                            :class="['h-12 rounded-xl text-base font-semibold transition-colors',
                                     !value || parseInt(value) < 1
                                         ? 'bg-gray-600 text-gray-400 cursor-not-allowed'
                                         : 'bg-accent text-white hover:bg-blue-600']">
                        Ок
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<script setup>
import { ref, watch, onMounted, onUnmounted } from 'vue';

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    table: { type: Object, default: null }
});

const emit = defineEmits(['update:modelValue', 'confirm']);

const value = ref('');

// Methods
const close = () => {
    emit('update:modelValue', false);
};

const input = (num) => {
    if (value.value.length < 2) {
        value.value += num.toString();
    }
};

const clear = () => {
    value.value = '';
};

const backspace = () => {
    value.value = value.value.slice(0, -1);
};

const confirm = () => {
    const parsed = parseInt(value.value);
    const guests = (isNaN(parsed) || parsed < 1) ? 1 : parsed;
    emit('confirm', { table: props.table, guests });
    close();
};

// Keyboard handler
const handleKeyboard = (e) => {
    if (!props.modelValue) return;

    // Numbers 0-9
    if (e.key >= '0' && e.key <= '9') {
        e.preventDefault();
        input(parseInt(e.key));
    }

    // Backspace
    if (e.key === 'Backspace') {
        e.preventDefault();
        backspace();
    }

    // Enter
    if (e.key === 'Enter') {
        e.preventDefault();
        confirm();
    }

    // Escape
    if (e.key === 'Escape') {
        e.preventDefault();
        close();
    }

    // C - clear
    if (e.key.toLowerCase() === 'c') {
        e.preventDefault();
        clear();
    }
};

// Watch for modal open/close
watch(() => props.modelValue, (isOpen) => {
    if (isOpen) {
        value.value = '';
    }
});

onMounted(() => {
    window.addEventListener('keydown', handleKeyboard);
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeyboard);
});
</script>
