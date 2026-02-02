<template>
  <Teleport to="body">
    <TransitionGroup
      name="toast"
      tag="div"
      class="fixed bottom-20 left-4 right-4 z-50 flex flex-col gap-2 pointer-events-none"
    >
      <div
        v-for="toast in toasts"
        :key="toast.id"
        :class="['p-4 rounded-xl text-center font-medium shadow-lg pointer-events-auto', toastClass(toast.type)]"
        @click="onRemove(toast.id)"
      >
        <div class="flex items-center justify-center gap-2">
          <span>{{ getIcon(toast.type) }}</span>
          <span>{{ toast.message }}</span>
        </div>
      </div>
    </TransitionGroup>
  </Teleport>
</template>

<script setup lang="ts">
import type { Toast, ToastType } from '@/waiter/stores/ui';

defineProps<{
  toasts: Toast[];
}>();

const emit = defineEmits<{
  remove: [id: number];
}>();

function onRemove(id: number): void {
  emit('remove', id);
}

function toastClass(type: ToastType): string {
  const classes: Record<ToastType, string> = {
    success: 'bg-green-500 text-white',
    error: 'bg-red-500 text-white',
    warning: 'bg-yellow-500 text-white',
    info: 'bg-blue-500 text-white',
  };
  return classes[type] || classes.info;
}

function getIcon(type: ToastType): string {
  const icons: Record<ToastType, string> = {
    success: '✓',
    error: '✕',
    warning: '⚠',
    info: 'ℹ',
  };
  return icons[type] || icons.info;
}
</script>

<style scoped>
.toast-enter-active,
.toast-leave-active {
  transition: all 0.3s ease;
}

.toast-enter-from {
  opacity: 0;
  transform: translateY(20px);
}

.toast-leave-to {
  opacity: 0;
  transform: translateX(100%);
}
</style>
