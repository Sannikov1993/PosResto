<template>
  <button
    @click="handleClick"
    :disabled="!dish.is_available || dish.in_stop_list"
    :class="[
      'p-2 rounded-xl text-left transition flex flex-col',
      dish.in_stop_list
        ? 'bg-red-500/10 border border-red-500/30 opacity-50'
        : dish.is_available
          ? 'bg-dark-700 active:bg-dark-600'
          : 'bg-dark-700 opacity-50'
    ]"
    :data-testid="`dish-${dish.id}`"
  >
    <!-- Dish image -->
    <div class="relative aspect-square w-full rounded-lg overflow-hidden bg-dark-600 mb-2">
      <img
        v-if="dish.image_url && !imageError"
        :src="dish.image_url"
        :alt="dish.name"
        class="w-full h-full object-cover"
        loading="lazy"
        @error="handleImageError"
      >
      <div v-else class="w-full h-full flex items-center justify-center text-gray-500">
        <span class="text-3xl">🍽️</span>
      </div>

      <!-- Stop-list overlay -->
      <div
        v-if="dish.in_stop_list"
        class="absolute inset-0 bg-black/60 flex items-center justify-center"
      >
        <span class="text-red-400 font-bold text-sm">СТОП</span>
      </div>
    </div>

    <!-- Dish name -->
    <p class="text-sm font-medium truncate">{{ dish.name }}</p>

    <!-- Price and cooking time -->
    <div class="flex justify-between items-center mt-1">
      <span :class="['text-xs font-medium', dish.in_stop_list ? 'text-red-400' : 'text-orange-400']">
        {{ dish.in_stop_list ? 'Стоп-лист' : formatMoney(dish.price) }}
      </span>
      <span v-if="dish.cooking_time" class="text-xs text-gray-500">
        ~{{ dish.cooking_time }} мин
      </span>
    </div>
  </button>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { formatMoney } from '@/waiter/utils/formatters';
import type { Dish } from '@/waiter/types';

const props = defineProps<{
  dish: Dish;
}>();

const emit = defineEmits<{
  select: [dish: Dish];
}>();

const imageError = ref(false);

function handleClick(): void {
  if (props.dish.is_available && !props.dish.in_stop_list) {
    emit('select', props.dish);
  }
}

function handleImageError(): void {
  imageError.value = true;
}
</script>
