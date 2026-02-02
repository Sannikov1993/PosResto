<template>
  <span :class="['px-3 py-1 rounded-full text-xs font-medium', statusClass]">
    {{ statusLabel }}
  </span>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { getOrderStatusLabel, getOrderStatusColor } from '@/waiter/utils/formatters';
import type { OrderStatus } from '@/waiter/types';

const props = defineProps<{
  status: OrderStatus;
}>();

const statusLabel = computed(() => getOrderStatusLabel(props.status));
const statusClass = computed(() => getOrderStatusColor(props.status));
</script>
