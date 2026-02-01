<template>
    <div class="pin-pad">
        <div class="pin-display">
            <div
                v-for="i in 4"
                :key="i"
                class="pin-dot"
                :class="{ filled: pin.length >= i }"
            ></div>
        </div>

        <p v-if="error" class="error-message">{{ error }}</p>

        <div class="keypad">
            <button
                v-for="num in [1,2,3,4,5,6,7,8,9]"
                :key="num"
                @click="addDigit(num)"
                class="key"
                type="button"
            >
                {{ num }}
            </button>
            <button @click="clear" class="key key-clear" type="button">⌫</button>
            <button @click="addDigit(0)" class="key" type="button">0</button>
            <button @click="$emit('cancel')" class="key key-cancel" type="button">✕</button>
        </div>
    </div>
</template>

<script setup>
import { ref, watch } from 'vue'

const emit = defineEmits(['complete', 'cancel'])

const pin = ref('')
const error = ref('')

watch(pin, (newPin) => {
    if (newPin.length === 4) {
        setTimeout(() => {
            emit('complete', newPin)
        }, 100)
    }
})

function addDigit(digit) {
    if (pin.value.length < 4) {
        pin.value += digit
        error.value = ''
    }
}

function clear() {
    pin.value = ''
    error.value = ''
}

function showError(message) {
    error.value = message
    pin.value = ''
}

defineExpose({ showError })
</script>

<style scoped>
.pin-pad {
    max-width: 320px;
    margin: 0 auto;
}

.pin-display {
    display: flex;
    justify-content: center;
    gap: 1rem;
    margin-bottom: 2rem;
}

.pin-dot {
    width: 16px;
    height: 16px;
    border-radius: 50%;
    border: 2px solid #ddd;
    transition: all 0.2s;
}

.pin-dot.filled {
    background: #3b82f6;
    border-color: #3b82f6;
    box-shadow: 0 0 8px rgba(59, 130, 246, 0.5);
}

.error-message {
    color: #ef4444;
    text-align: center;
    margin-bottom: 1rem;
    font-size: 0.875rem;
    font-weight: 500;
}

.keypad {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.75rem;
}

.key {
    aspect-ratio: 1;
    border: 1px solid #e5e7eb;
    border-radius: 0.5rem;
    background: white;
    font-size: 1.5rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    color: #111827;
}

.key:hover {
    background: #f3f4f6;
    border-color: #3b82f6;
}

.key:active {
    transform: scale(0.95);
    background: #e5e7eb;
}

.key-clear, .key-cancel {
    background: #f3f4f6;
    font-size: 1.25rem;
}

.key-cancel {
    color: #ef4444;
}

.key-cancel:hover {
    background: #fee2e2;
    border-color: #ef4444;
}
</style>
