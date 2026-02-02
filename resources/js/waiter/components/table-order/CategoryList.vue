<template>
  <div class="flex gap-2 overflow-x-auto py-2 px-4 bg-dark-900 scrollbar-hide">
    <button
      v-for="category in categories"
      :key="category.id"
      @click="$emit('select', category.id)"
      :class="[
        'px-3 py-2 rounded-xl text-sm whitespace-nowrap transition flex-shrink-0',
        selectedCategoryId === category.id
          ? 'bg-orange-500 text-white'
          : 'bg-dark-800 text-gray-400'
      ]"
    >
      <span v-if="category.icon">{{ category.icon }} </span>{{ category.name }}
    </button>
  </div>
</template>

<script setup lang="ts">
import type { Category } from '@/waiter/types';

defineProps<{
  categories: Category[];
  selectedCategoryId: number | null;
}>();

defineEmits<{
  select: [categoryId: number];
}>();
</script>

<style scoped>
.scrollbar-hide::-webkit-scrollbar {
  display: none;
}
.scrollbar-hide {
  -ms-overflow-style: none;
  scrollbar-width: none;
}
</style>
