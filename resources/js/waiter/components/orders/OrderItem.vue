<template>
  <div class="bg-dark-800 rounded-xl p-3 flex items-center gap-3 relative">
    <!-- Status bar -->
    <div :class="['absolute left-0 top-0 bottom-0 w-1 rounded-l-xl', statusBarClass]"></div>

    <!-- Quantity -->
    <div class="w-8 h-8 rounded-lg bg-orange-500/20 text-orange-400 flex items-center justify-center font-bold ml-1">
      {{ item.quantity }}
    </div>

    <!-- Info -->
    <div class="flex-1 min-w-0">
      <p class="font-medium truncate">{{ item.dish?.name || item.name }}</p>
      <p class="text-sm text-gray-500">{{ formatMoney(item.price) }}</p>
      <p v-if="item.comment" class="text-xs text-gray-600 truncate">{{ item.comment }}</p>
    </div>

    <!-- Actions -->
    <div class="flex items-center gap-2">
      <!-- Status badge -->
      <span :class="['text-xs px-2 py-1 rounded', statusBadgeClass]">
        {{ statusLabel }}
      </span>

      <!-- Remove button (only for new items) -->
      <button
        v-if="canRemove"
        @click="$emit('remove', item)"
        class="text-red-400 p-2 -m-2"
      >
        ✕
      </button>

      <!-- Mark served (for ready items) -->
      <button
        v-if="canMarkServed"
        @click="$emit('markServed', item)"
        class="text-green-400 p-2 -m-2"
      >
        ✓
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { formatMoney, getOrderItemStatusLabel, getOrderItemStatusColor } from '@/waiter/utils/formatters';
import type { OrderItem } from '@/waiter/types';

const props = defineProps<{
  item: OrderItem;
  allowRemove?: boolean;
  allowMarkServed?: boolean;
}>();

defineEmits<{
  remove: [item: OrderItem];
  markServed: [item: OrderItem];
}>();

const statusLabel = computed(() => getOrderItemStatusLabel(props.item.status));
const statusBadgeClass = computed(() => getOrderItemStatusColor(props.item.status));

const statusBarClass = computed(() => {
  const classes: Record<string, string> = {
    new: 'bg-blue-500',
    pending: 'bg-purple-500',
    cooking: 'bg-orange-500',
    ready: 'bg-green-500',
    served: 'bg-gray-500',
    cancelled: 'bg-red-500',
  };
  return classes[props.item.status] || 'bg-gray-500';
});

const canRemove = computed(() => {
  return props.allowRemove !== false && props.item.status === 'new';
});

const canMarkServed = computed(() => {
  return props.allowMarkServed !== false && props.item.status === 'ready';
});
</script>
