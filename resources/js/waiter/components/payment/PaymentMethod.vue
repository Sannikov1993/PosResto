<template>
  <button
    @click="$emit('select', method)"
    :class="[
      'flex-1 py-4 rounded-xl font-medium transition flex flex-col items-center gap-2',
      selected
        ? 'bg-orange-500 text-white'
        : 'bg-dark-700 text-gray-400 hover:bg-dark-600'
    ]"
    :data-testid="`pay-${method}-btn`"
  >
    <span class="text-2xl">{{ icon }}</span>
    <span>{{ label }}</span>
  </button>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import type { PaymentMethod } from '@/waiter/types';

const props = defineProps<{
  method: PaymentMethod;
  selected?: boolean;
}>();

defineEmits<{
  select: [method: PaymentMethod];
}>();

const icon = computed(() => {
  const icons: Record<PaymentMethod, string> = {
    cash: '💵',
    card: '💳',
    mixed: '🔄',
  };
  return icons[props.method] || '💰';
});

const label = computed(() => {
  const labels: Record<PaymentMethod, string> = {
    cash: 'Наличные',
    card: 'Карта',
    mixed: 'Сплит',
  };
  return labels[props.method] || props.method;
});
</script>
