<template>
  <div class="bg-dark-800 rounded-xl p-4">
    <h3 class="font-medium mb-3">Статистика за сегодня</h3>
    <div class="grid grid-cols-3 gap-4 text-center">
      <div>
        <p class="text-2xl font-bold text-white">{{ stats.ordersCount }}</p>
        <p class="text-xs text-gray-500">Заказов</p>
      </div>
      <div>
        <p class="text-2xl font-bold text-green-400">{{ formatMoneyShort(stats.totalSales) }}</p>
        <p class="text-xs text-gray-500">Выручка</p>
      </div>
      <div>
        <p class="text-2xl font-bold text-orange-400">{{ formatMoney(stats.avgCheck) }}</p>
        <p class="text-xs text-gray-500">Ср. чек</p>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { formatMoney, formatMoneyShort } from '@/waiter/utils/formatters';

interface Stats {
  ordersCount: number;
  totalSales: number;
  avgCheck: number;
}

defineProps<{
  stats: Stats;
}>();
</script>
