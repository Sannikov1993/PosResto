<template>
  <Transition name="slide-down">
    <div
      v-if="hasReadyItems"
      class="fixed top-0 left-0 right-0 z-50 bg-green-500 text-white px-4 py-3 flex items-center justify-between shadow-lg cursor-pointer"
      @click="goToReadyOrders"
    >
      <div class="flex items-center gap-2">
        <span class="text-xl animate-bounce">🔔</span>
        <span class="font-medium">{{ readyItemsText }}</span>
      </div>
      <button class="text-sm underline hover:no-underline">Посмотреть</button>
    </div>
  </Transition>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { storeToRefs } from 'pinia';
import { useOrdersStore } from '@/waiter/stores/orders';
import { useUiStore } from '@/waiter/stores/ui';
import { pluralize } from '@/waiter/utils/formatters';

const ordersStore = useOrdersStore();
const uiStore = useUiStore();

const { ordersWithReadyItems } = storeToRefs(ordersStore);

const hasReadyItems = computed((): boolean => {
  return ordersWithReadyItems.value.length > 0;
});

const totalReadyItems = computed((): number => {
  return ordersWithReadyItems.value.reduce((sum: any, order: any) => {
    return sum + order.items.filter((item: any) => item.status === 'ready').length;
  }, 0);
});

const readyItemsText = computed((): string => {
  const count = totalReadyItems.value;
  const word = pluralize(count, ['блюдо', 'блюда', 'блюд']);
  return `${count} ${word} готово к подаче`;
});

function goToReadyOrders(): void {
  uiStore.goToOrders();
}
</script>

<style scoped>
.slide-down-enter-active,
.slide-down-leave-active {
  transition: transform 0.3s ease, opacity 0.3s ease;
}

.slide-down-enter-from,
.slide-down-leave-to {
  transform: translateY(-100%);
  opacity: 0;
}
</style>
