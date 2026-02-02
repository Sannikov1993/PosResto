<template>
  <div class="bg-dark-800 rounded-xl p-3 flex items-center gap-3 relative">
    <!-- Status bar -->
    <div :class="['absolute left-0 top-0 bottom-0 w-1 rounded-l-xl', statusBarClass]"></div>

    <!-- Quantity controls (for new items) -->
    <div v-if="item.status === 'new'" class="flex items-center gap-1 ml-1">
      <button
        @click="$emit('decrement', item)"
        class="w-8 h-8 rounded-lg bg-dark-700 text-gray-400 flex items-center justify-center active:bg-dark-600"
      >
        -
      </button>
      <span class="w-8 text-center font-bold text-orange-400">{{ item.quantity }}</span>
      <button
        @click="$emit('increment', item)"
        class="w-8 h-8 rounded-lg bg-dark-700 text-gray-400 flex items-center justify-center active:bg-dark-600"
      >
        +
      </button>
    </div>

    <!-- Quantity badge (for sent items) -->
    <div v-else class="w-8 h-8 rounded-lg bg-orange-500/20 text-orange-400 flex items-center justify-center font-bold ml-1">
      {{ item.quantity }}
    </div>

    <!-- Info -->
    <div class="flex-1 min-w-0">
      <p class="font-medium truncate">{{ item.dish?.name || item.name }}</p>
      <p class="text-sm text-gray-500">{{ formatMoney(item.price) }}</p>
    </div>

    <!-- Total -->
    <div class="text-right">
      <p class="font-medium">{{ formatMoney(item.total) }}</p>
      <p v-if="item.status !== 'new'" :class="['text-xs', statusTextClass]">{{ statusLabel }}</p>
    </div>

    <!-- Remove (for new items) -->
    <button
      v-if="item.status === 'new'"
      @click="$emit('remove', item)"
      class="text-red-400 p-2 -m-2"
    >
      ✕
    </button>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { formatMoney, getOrderItemStatusLabel } from '@/waiter/utils/formatters';
import type { OrderItem } from '@/waiter/types';

const props = defineProps<{
  item: OrderItem;
}>();

defineEmits<{
  increment: [item: OrderItem];
  decrement: [item: OrderItem];
  remove: [item: OrderItem];
}>();

const statusLabel = computed(() => getOrderItemStatusLabel(props.item.status));

const statusBarClass = computed(() => {
  const classes: Record<string, string> = {
    new: 'bg-blue-500',
    pending: 'bg-purple-500',
    cooking: 'bg-orange-500',
    ready: 'bg-green-500',
    served: 'bg-gray-500',
  };
  return classes[props.item.status] || 'bg-gray-500';
});

const statusTextClass = computed(() => {
  const classes: Record<string, string> = {
    pending: 'text-purple-400',
    cooking: 'text-orange-400',
    ready: 'text-green-400',
    served: 'text-gray-400',
  };
  return classes[props.item.status] || 'text-gray-400';
});
</script>
