<template>
  <div class="flex-1 overflow-y-auto">
    <!-- Order Items -->
    <div v-if="items.length > 0" class="p-4 space-y-2">
      <OrderItemRow
        v-for="item in items"
        :key="item.id"
        :item="item"
        @increment="$emit('incrementItem', $event)"
        @decrement="$emit('decrementItem', $event)"
        @remove="$emit('removeItem', $event)"
      />
    </div>

    <!-- Empty -->
    <div v-else class="flex flex-col items-center justify-center h-full text-gray-500 p-4">
      <p class="text-4xl mb-4">📝</p>
      <p>Заказ пуст</p>
      <p class="text-sm mt-2">Добавьте блюда из меню</p>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { OrderItem } from '@/waiter/types';
import OrderItemRow from './OrderItemRow.vue';

defineProps<{
  items: OrderItem[];
}>();

defineEmits<{
  incrementItem: [item: OrderItem];
  decrementItem: [item: OrderItem];
  removeItem: [item: OrderItem];
}>();
</script>
