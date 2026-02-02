<template>
  <div class="flex flex-col items-center justify-center p-8 text-center">
    <div class="text-5xl mb-4">{{ icon }}</div>
    <h3 class="text-lg font-medium text-gray-300 mb-2">{{ title }}</h3>
    <p v-if="description" class="text-sm text-gray-500 max-w-xs">{{ description }}</p>
    <slot name="action">
      <button
        v-if="actionText"
        @click="$emit('action')"
        class="mt-4 px-4 py-2 bg-orange-500 text-white rounded-xl font-medium hover:bg-orange-600 transition"
      >
        {{ actionText }}
      </button>
    </slot>
  </div>
</template>

<script setup lang="ts">
withDefaults(defineProps<{
  icon?: string;
  title: string;
  description?: string;
  actionText?: string;
}>(), {
  icon: '📭',
});

defineEmits<{
  action: [];
}>();
</script>
