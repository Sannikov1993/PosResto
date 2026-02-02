<template>
  <button
    @click="handleClick"
    :disabled="!dish.is_available || dish.in_stop_list"
    :class="[
      'p-2 rounded-xl text-left transition',
      dish.in_stop_list
        ? 'bg-red-500/10 border border-red-500/30 opacity-50'
        : dish.is_available
          ? 'bg-dark-700 active:bg-dark-600'
          : 'bg-dark-700 opacity-50'
    ]"
    :data-testid="`dish-${dish.id}`"
  >
    <p class="text-sm font-medium truncate">{{ dish.name }}</p>
    <p :class="['text-xs', dish.in_stop_list ? 'text-red-400' : 'text-orange-400']">
      {{ dish.in_stop_list ? 'Стоп-лист' : formatMoney(dish.price) }}
    </p>
    <p v-if="dish.cooking_time" class="text-xs text-gray-600">
      ~{{ dish.cooking_time }} мин
    </p>
  </button>
</template>

<script setup lang="ts">
import { formatMoney } from '@/waiter/utils/formatters';
import type { Dish } from '@/waiter/types';

const props = defineProps<{
  dish: Dish;
}>();

const emit = defineEmits<{
  select: [dish: Dish];
}>();

function handleClick(): void {
  if (props.dish.is_available && !props.dish.in_stop_list) {
    emit('select', props.dish);
  }
}
</script>
