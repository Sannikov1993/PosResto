<template>
  <nav class="flex-shrink-0 bg-dark-800 border-t border-gray-800 safe-bottom">
    <div class="flex justify-around py-2">
      <button
        v-for="tab in tabs"
        :key="tab.id"
        @click="$emit('navigate', tab.id)"
        :class="[
          'flex flex-col items-center py-2 px-4 rounded-lg transition relative',
          currentTab === tab.id ? 'text-orange-500' : 'text-gray-500'
        ]"
        :data-testid="`nav-${tab.id}`"
      >
        <span class="text-xl">{{ tab.icon }}</span>
        <span class="text-xs mt-1">{{ tab.label }}</span>

        <!-- Badge for pending items -->
        <span
          v-if="tab.id === 'orders' && pendingCount > 0"
          class="absolute -top-1 right-1 w-5 h-5 bg-orange-500 rounded-full text-xs flex items-center justify-center text-white font-bold"
        >
          {{ pendingCount > 9 ? '9+' : pendingCount }}
        </span>
      </button>
    </div>
  </nav>
</template>

<script setup lang="ts">
import type { Tab } from '@/waiter/stores/ui';

interface NavTab {
  id: Tab;
  label: string;
  icon: string;
}

withDefaults(defineProps<{
  currentTab: Tab;
  pendingCount?: number;
}>(), {
  pendingCount: 0,
});

defineEmits<{
  navigate: [tab: Tab];
}>();

const tabs: NavTab[] = [
  { id: 'tables', label: 'Столы', icon: '🪑' },
  { id: 'orders', label: 'Заказы', icon: '📋' },
  { id: 'profile', label: 'Профиль', icon: '👤' },
];
</script>
