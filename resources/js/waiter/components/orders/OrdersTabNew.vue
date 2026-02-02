<template>
  <div class="h-full flex flex-col">
    <!-- Filters -->
    <div class="flex-shrink-0 px-4 py-2 flex gap-2 overflow-x-auto bg-dark-900">
      <button
        v-for="filter in filters"
        :key="filter.id"
        @click="currentFilter = filter.id"
        :class="[
          'px-4 py-2 rounded-xl text-sm font-medium whitespace-nowrap transition',
          currentFilter === filter.id
            ? 'bg-orange-500 text-white'
            : 'bg-dark-800 text-gray-400'
        ]"
      >
        {{ filter.label }}
        <span v-if="filter.count > 0" class="ml-1 opacity-70">({{ filter.count }})</span>
      </button>
    </div>

    <!-- Loading -->
    <AppLoader v-if="isLoading" class="flex-1" text="Загрузка заказов..." />

    <!-- Error -->
    <AppError
      v-else-if="error"
      :message="error"
      class="flex-1"
      @retry="fetchOrders(true)"
    />

    <!-- Orders List -->
    <div v-else-if="displayedOrders.length > 0" class="flex-1 p-4 overflow-y-auto space-y-3">
      <OrderCard
        v-for="order in displayedOrders"
        :key="order.id"
        :order="order"
        @select="handleOrderSelect"
      />
    </div>

    <!-- Empty -->
    <AppEmpty
      v-else
      icon="📋"
      title="Нет заказов"
      description="У вас пока нет активных заказов"
      class="flex-1"
    />

    <!-- Stats -->
    <div class="flex-shrink-0 px-4 py-3 bg-dark-800 border-t border-gray-800">
      <div class="flex justify-around text-center">
        <div>
          <p class="text-lg font-bold text-white">{{ todayStats.ordersCount }}</p>
          <p class="text-xs text-gray-500">Заказов</p>
        </div>
        <div>
          <p class="text-lg font-bold text-green-400">{{ formatMoney(todayStats.totalSales) }}</p>
          <p class="text-xs text-gray-500">Выручка</p>
        </div>
        <div>
          <p class="text-lg font-bold text-orange-400">{{ formatMoney(todayStats.avgCheck) }}</p>
          <p class="text-xs text-gray-500">Ср. чек</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';
import { useOrders, useTables } from '@/waiter/composables';
import { formatMoney } from '@/waiter/utils/formatters';
import type { Order } from '@/waiter/types';
import { AppLoader, AppError, AppEmpty } from '@/waiter/components/common';
import OrderCard from './OrderCard.vue';

type FilterId = 'active' | 'ready' | 'all';

interface FilterOption {
  id: FilterId;
  label: string;
  count: number;
}

const {
  orders,
  activeOrders,
  ordersWithReadyItems,
  todayStats,
  isLoading,
  error,
  fetchOrders,
} = useOrders();

const { selectTable, getTableById } = useTables();

const currentFilter = ref<FilterId>('active');

const filters = computed((): FilterOption[] => [
  { id: 'active', label: 'Активные', count: activeOrders.value.length },
  { id: 'ready', label: 'Готовы', count: ordersWithReadyItems.value.length },
  { id: 'all', label: 'Все', count: orders.value.length },
]);

const displayedOrders = computed((): Order[] => {
  switch (currentFilter.value) {
    case 'ready':
      return ordersWithReadyItems.value;
    case 'all':
      return orders.value;
    default:
      return activeOrders.value;
  }
});

function handleOrderSelect(order: Order): void {
  const table = getTableById(order.table_id);
  if (table) {
    selectTable(table);
  }
}
</script>
