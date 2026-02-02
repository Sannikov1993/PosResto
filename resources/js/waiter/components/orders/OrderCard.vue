<template>
  <button
    @click="$emit('select', order)"
    class="w-full bg-dark-800 rounded-xl p-4 text-left active:bg-dark-700 transition"
    :data-testid="`order-${order.id}`"
  >
    <div class="flex items-start justify-between mb-2">
      <div>
        <span class="font-bold text-lg">Стол {{ order.table?.number || '?' }}</span>
        <span class="text-gray-500 text-sm ml-2">#{{ order.id }}</span>
      </div>
      <OrderStatusBadge :status="order.status" />
    </div>

    <div class="text-sm text-gray-400 mb-2">
      {{ itemsCount }} · {{ formatMoney(order.total) }}
    </div>

    <!-- Items preview -->
    <div v-if="order.items?.length" class="text-xs text-gray-500 truncate">
      {{ itemsPreview }}
    </div>

    <!-- Ready items indicator -->
    <div v-if="readyItemsCount > 0" class="mt-2 flex items-center gap-1 text-green-400 text-sm">
      <span>✓</span>
      <span>{{ readyItemsCount }} готово к подаче</span>
    </div>

    <!-- Time -->
    <div class="mt-2 text-xs text-gray-500">
      {{ formatRelativeTime(order.created_at) }}
    </div>
  </button>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { formatMoney, formatRelativeTime, formatItemsCount } from '@/waiter/utils/formatters';
import type { Order } from '@/waiter/types';
import OrderStatusBadge from './OrderStatusBadge.vue';

const props = defineProps<{
  order: Order;
}>();

defineEmits<{
  select: [order: Order];
}>();

const itemsCount = computed(() => {
  const count = props.order.items?.length || 0;
  return formatItemsCount(count);
});

const itemsPreview = computed(() => {
  if (!props.order.items?.length) return '';
  return props.order.items
    .slice(0, 3)
    .map(i => i.dish?.name || i.name)
    .join(', ') + (props.order.items.length > 3 ? '...' : '');
});

const readyItemsCount = computed(() => {
  return props.order.items?.filter(i => i.status === 'ready').length || 0;
});
</script>
