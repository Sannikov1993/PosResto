<template>
  <div class="h-32 overflow-y-auto p-2 bg-dark-800">
    <div v-if="dishes.length > 0" class="grid grid-cols-2 gap-2">
      <DishCard
        v-for="dish in dishes"
        :key="dish.id"
        :dish="dish"
        @select="$emit('select', $event)"
      />
    </div>
    <div v-else class="h-full flex items-center justify-center text-gray-500 text-sm">
      Выберите категорию
    </div>
  </div>
</template>

<script setup lang="ts">
import type { Dish } from '@/waiter/types';
import DishCard from './DishCard.vue';

defineProps<{
  dishes: Dish[];
}>();

defineEmits<{
  select: [dish: Dish];
}>();
</script>
