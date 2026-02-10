<template>
  <header class="flex-shrink-0 bg-dark-800 px-4 py-3 flex items-center justify-between safe-top">
    <div class="flex items-center gap-3">
      <button
        @click="$emit('menuClick')"
        class="text-2xl p-1 -m-1 active:opacity-70 transition"
        data-testid="menu-btn"
      >
        ☰
      </button>
      <img src="/images/logo/menulab_icon.svg" alt="MenuLab" class="w-8 h-8" />
      <div>
        <h1 class="font-semibold">{{ title }}</h1>
        <p :class="['text-xs', isOnline ? 'text-green-500' : 'text-red-500']">
          {{ isOnline ? 'Онлайн' : 'Оффлайн' }}
        </p>
      </div>
    </div>
    <div class="flex items-center gap-3">
      <span class="text-sm text-gray-400">{{ time }}</span>
      <div class="relative">
        <button class="text-xl p-1 -m-1" @click="$emit('notificationsClick')">
          🔔
        </button>
        <span
          v-if="notificationsCount > 0"
          class="absolute -top-1 -right-1 w-4 h-4 bg-red-500 rounded-full text-xs flex items-center justify-center"
        >
          {{ notificationsCount > 9 ? '9+' : notificationsCount }}
        </span>
      </div>
    </div>
  </header>
</template>

<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue';

withDefaults(defineProps<{
  title: string;
  isOnline?: boolean;
  notificationsCount?: number;
}>(), {
  notificationsCount: 0,
});

defineEmits<{
  menuClick: [];
  notificationsClick: [];
}>();

const time = ref('');

function updateTime(): void {
  const now = new Date();
  time.value = now.toLocaleTimeString('ru-RU', {
    hour: '2-digit',
    minute: '2-digit',
  });
}

let interval: ReturnType<typeof setInterval>;

onMounted(() => {
  updateTime();
  interval = setInterval(updateTime, 1000);
});

onUnmounted(() => {
  clearInterval(interval);
});
</script>
