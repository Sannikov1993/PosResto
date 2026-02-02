<template>
  <button
    @click="$emit('select', table)"
    :class="['aspect-square rounded-2xl flex flex-col items-center justify-center transition active:scale-95', statusClass]"
    :data-testid="`table-${table.number}`"
  >
    <span class="text-2xl font-bold">{{ table.number }}</span>
    <span class="text-xs mt-1 opacity-75">{{ table.seats }} мест</span>
    <span v-if="table.current_order" class="text-xs mt-1 font-medium">
      {{ formatMoney(table.current_order.total) }}
    </span>
    <span v-if="guestsCount > 0" class="text-xs mt-0.5 opacity-60">
      {{ guestsCount }} гостей
    </span>
  </button>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { formatMoney } from '@/waiter/utils/formatters';
import type { Table } from '@/waiter/types';

const props = defineProps<{
  table: Table;
}>();

defineEmits<{
  select: [table: Table];
}>();

const statusClass = computed((): string => {
  switch (props.table.status) {
    case 'occupied':
      return 'bg-orange-500/20 border-2 border-orange-500 text-orange-400';
    case 'reserved':
      return 'bg-blue-500/20 border-2 border-blue-500 text-blue-400';
    case 'bill_requested':
      return 'bg-purple-500/20 border-2 border-purple-500 text-purple-400';
    default:
      return 'bg-dark-800 border-2 border-transparent text-white hover:border-gray-700';
  }
});

const guestsCount = computed((): number => {
  return props.table.current_order?.guests_count || 0;
});
</script>
