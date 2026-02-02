<template>
  <div class="flex flex-col items-center justify-center p-8 text-center">
    <div class="text-5xl mb-4">{{ icon }}</div>
    <h3 class="text-lg font-medium text-red-400 mb-2">{{ title }}</h3>
    <p v-if="message" class="text-sm text-gray-500 max-w-xs mb-4">{{ message }}</p>
    <button
      v-if="showRetry"
      @click="$emit('retry')"
      class="px-4 py-2 bg-orange-500 text-white rounded-xl font-medium hover:bg-orange-600 transition"
    >
      {{ retryText }}
    </button>
  </div>
</template>

<script setup lang="ts">
withDefaults(defineProps<{
  icon?: string;
  title?: string;
  message?: string;
  showRetry?: boolean;
  retryText?: string;
}>(), {
  icon: '⚠️',
  title: 'Произошла ошибка',
  showRetry: true,
  retryText: 'Повторить',
});

defineEmits<{
  retry: [];
}>();
</script>
