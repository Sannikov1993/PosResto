<template>
  <div class="flex-shrink-0 px-4 py-3 bg-dark-800 flex justify-around text-center border-t border-gray-800">
    <div>
      <p class="text-xl font-bold text-green-400">{{ freeCount }}</p>
      <p class="text-xs text-gray-500">Свободно</p>
    </div>
    <div>
      <p class="text-xl font-bold text-orange-400">{{ occupiedCount }}</p>
      <p class="text-xs text-gray-500">Занято</p>
    </div>
    <div>
      <p class="text-xl font-bold text-blue-400">{{ reservedCount }}</p>
      <p class="text-xs text-gray-500">Бронь</p>
    </div>
    <div v-if="billCount > 0">
      <p class="text-xl font-bold text-purple-400">{{ billCount }}</p>
      <p class="text-xs text-gray-500">Счёт</p>
    </div>
  </div>
</template>

<script setup lang="ts">
defineProps<{
  freeCount: number;
  occupiedCount: number;
  reservedCount: number;
  billCount?: number;
}>();
</script>
