<template>
  <div class="bg-dark-800 rounded-xl p-4">
    <div class="flex items-center justify-between mb-3">
      <h3 class="font-medium">Смена</h3>
      <span :class="['px-3 py-1 rounded-full text-sm', statusClass]">
        {{ statusLabel }}
      </span>
    </div>

    <template v-if="shift">
      <div class="space-y-2 text-sm">
        <div class="flex justify-between">
          <span class="text-gray-500">Открыта</span>
          <span>{{ formatDateTime(shift.opened_at) }}</span>
        </div>
        <div class="flex justify-between">
          <span class="text-gray-500">Наличных</span>
          <span class="text-green-400">{{ formatMoney(shift.cash_total) }}</span>
        </div>
        <div class="flex justify-between">
          <span class="text-gray-500">Безнал</span>
          <span class="text-blue-400">{{ formatMoney(shift.card_total) }}</span>
        </div>
        <div class="flex justify-between font-medium">
          <span class="text-gray-400">Всего</span>
          <span class="text-orange-400">{{ formatMoney(shift.total) }}</span>
        </div>
      </div>
    </template>

    <template v-else>
      <p class="text-gray-500 text-sm">Смена не открыта</p>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { formatMoney, formatDateTime } from '@/waiter/utils/formatters';
import type { Shift } from '@/waiter/types';

const props = defineProps<{
  shift: Shift | null;
}>();

const statusLabel = computed(() => props.shift ? 'Открыта' : 'Закрыта');

const statusClass = computed(() => {
  return props.shift
    ? 'bg-green-500/20 text-green-400'
    : 'bg-gray-500/20 text-gray-400';
});
</script>
