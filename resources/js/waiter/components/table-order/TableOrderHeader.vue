<template>
  <div class="flex-shrink-0 px-4 py-3 bg-dark-800 flex items-center gap-3 border-b border-gray-800">
    <button @click="$emit('back')" class="text-2xl">←</button>
    <div class="flex-1">
      <h2 class="font-bold">Стол {{ table?.number }}</h2>
      <p class="text-xs text-gray-500">
        {{ table?.seats }} мест
        <span v-if="guestsCount"> · {{ guestsCount }} гостей</span>
      </p>
    </div>
    <OrderStatusBadge v-if="order" :status="order.status" />
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import type { Table, Order } from '@/waiter/types';
import { OrderStatusBadge } from '@/waiter/components/orders';

const props = defineProps<{
  table: Table | null;
  order: Order | null;
}>();

defineEmits<{
  back: [];
}>();

const guestsCount = computed(() => props.order?.guests_count || 0);
</script>
