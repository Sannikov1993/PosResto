<template>
  <div class="w-full max-w-xs">
    <p class="text-center text-gray-400 mb-4">{{ label }}</p>

    <!-- PIN Display -->
    <div class="flex justify-center gap-3 mb-6">
      <div
        v-for="i in pinLength"
        :key="i"
        :class="[
          'w-4 h-4 rounded-full transition-all',
          modelValue.length >= i ? 'bg-orange-500 scale-110' : 'bg-gray-700'
        ]"
      ></div>
    </div>

    <!-- Error -->
    <p v-if="error" class="text-red-500 text-center text-sm mb-4">{{ error }}</p>

    <!-- Numpad -->
    <div class="grid grid-cols-3 gap-3 mb-4">
      <button
        v-for="n in 9"
        :key="n"
        @click="inputDigit(n)"
        :disabled="disabled"
        class="h-16 rounded-2xl bg-dark-800 text-2xl font-semibold active:bg-dark-700 transition disabled:opacity-50"
        :data-testid="`pin-key-${n}`"
      >
        {{ n }}
      </button>
      <button
        @click="clear"
        :disabled="disabled"
        class="h-16 rounded-2xl bg-dark-800 text-gray-500 active:bg-dark-700 transition disabled:opacity-50"
        data-testid="pin-clear"
      >
        C
      </button>
      <button
        @click="inputDigit(0)"
        :disabled="disabled"
        class="h-16 rounded-2xl bg-dark-800 text-2xl font-semibold active:bg-dark-700 transition disabled:opacity-50"
        data-testid="pin-key-0"
      >
        0
      </button>
      <button
        @click="backspace"
        :disabled="disabled"
        class="h-16 rounded-2xl bg-dark-800 text-gray-500 active:bg-dark-700 transition disabled:opacity-50"
        data-testid="pin-backspace"
      >
        ⌫
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
const props = withDefaults(defineProps<{
  modelValue: string;
  pinLength?: number;
  label?: string;
  error?: string;
  disabled?: boolean;
}>(), {
  pinLength: 4,
  label: 'Введите PIN-код',
  disabled: false,
});

const emit = defineEmits<{
  'update:modelValue': [value: string];
  complete: [pin: string];
}>();

function inputDigit(digit: number): void {
  if (props.modelValue.length >= props.pinLength) return;

  const newValue = props.modelValue + digit.toString();
  emit('update:modelValue', newValue);

  if (newValue.length === props.pinLength) {
    emit('complete', newValue);
  }
}

function backspace(): void {
  emit('update:modelValue', props.modelValue.slice(0, -1));
}

function clear(): void {
  emit('update:modelValue', '');
}
</script>
