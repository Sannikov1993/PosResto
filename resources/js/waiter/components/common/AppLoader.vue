<template>
  <div :class="['flex items-center justify-center', containerClass]">
    <div :class="['animate-spin rounded-full border-2 border-transparent', spinnerClass]">
      <div :class="['rounded-full', innerClass]"></div>
    </div>
    <span v-if="text" class="ml-3 text-gray-400">{{ text }}</span>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(defineProps<{
  size?: 'sm' | 'md' | 'lg';
  text?: string;
  fullscreen?: boolean;
}>(), {
  size: 'md',
  fullscreen: false,
});

const containerClass = computed(() => {
  if (props.fullscreen) {
    return 'fixed inset-0 bg-dark-900/80 z-50';
  }
  return 'p-4';
});

const spinnerClass = computed(() => {
  const sizes = {
    sm: 'w-5 h-5 border-orange-500 border-t-transparent',
    md: 'w-8 h-8 border-orange-500 border-t-transparent',
    lg: 'w-12 h-12 border-orange-500 border-t-transparent',
  };
  return sizes[props.size];
});

const innerClass = computed(() => {
  const sizes = {
    sm: 'w-3 h-3',
    md: 'w-5 h-5',
    lg: 'w-8 h-8',
  };
  return sizes[props.size];
});
</script>
