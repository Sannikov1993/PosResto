<template>
  <button
    @click="$emit('select', table)"
    :class="['relative aspect-square rounded-2xl flex flex-col items-center justify-center transition active:scale-95', statusClass]"
    :data-testid="`table-${table.number}`"
  >
    <!-- Pulsing badge for ready items -->
    <div v-if="hasReadyItems" class="absolute -top-1 -right-1">
      <span class="relative flex h-3 w-3">
        <span class="animate-ping absolute h-full w-full rounded-full bg-green-400 opacity-75"></span>
        <span class="relative rounded-full h-3 w-3 bg-green-500"></span>
      </span>
    </div>

    <span class="text-2xl font-bold">{{ table.number }}</span>
    <span class="text-xs mt-1 opacity-75">{{ table.seats }} мест</span>

    <template v-if="table.current_order">
      <span class="text-xs mt-1 font-medium">
        {{ formatMoney(table.current_order.total) }}
      </span>
      <span v-if="guestsCount > 0" class="text-xs mt-0.5 opacity-60">
        {{ guestsCount }} гостей
      </span>
    </template>

    <!-- Duration timer -->
    <div v-if="duration !== null" :class="timerClasses" class="text-xs mt-1 flex items-center gap-1">
      <span>⏱</span>
      <span>{{ formatDuration(duration) }}</span>
    </div>

    <!-- Ready items count -->
    <div v-if="readyCount > 0" class="text-xs text-green-400 mt-0.5">
      🔔 {{ readyCount }} готово
    </div>
  </button>
</template>

<script setup lang="ts">
import { computed, ref, onMounted, onUnmounted } from 'vue';
import { formatMoney, formatDuration } from '@/waiter/utils/formatters';
import type { Table } from '@/waiter/types';

const props = defineProps<{
  table: Table;
}>();

defineEmits<{
  select: [table: Table];
}>();

// Reactive timer update
const now = ref(Date.now());
let timerInterval: ReturnType<typeof setInterval> | null = null;

onMounted(() => {
  timerInterval = setInterval(() => {
    now.value = Date.now();
  }, 60000); // Update every minute
});

onUnmounted(() => {
  if (timerInterval) {
    clearInterval(timerInterval);
  }
});

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

// Duration in minutes since order created
const duration = computed((): number | null => {
  if (props.table.status !== 'occupied' || !props.table.current_order?.created_at) {
    return null;
  }
  const start = new Date(props.table.current_order.created_at).getTime();
  return Math.floor((now.value - start) / 60000);
});

// Timer color classes based on duration
const timerClasses = computed((): string => {
  if (duration.value === null) return '';
  if (duration.value > 60) return 'text-red-400';
  if (duration.value > 30) return 'text-yellow-400';
  return 'opacity-75';
});

// Count of ready items in order
const readyCount = computed((): number => {
  if (!props.table.current_order?.items) return 0;
  return props.table.current_order.items.filter((item: any) => item.status === 'ready').length;
});

// Has any ready items
const hasReadyItems = computed((): boolean => {
  return readyCount.value > 0;
});
</script>
