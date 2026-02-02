<template>
  <div class="h-full flex flex-col bg-dark-900">
    <!-- Header -->
    <div class="p-4 border-b border-gray-800">
      <div class="flex items-center gap-3">
        <div class="w-12 h-12 rounded-full bg-orange-500/20 flex items-center justify-center text-orange-400 font-bold text-lg">
          {{ userInitials }}
        </div>
        <div class="flex-1 min-w-0">
          <p class="font-semibold truncate">{{ userName }}</p>
          <p class="text-sm text-gray-500">{{ roleLabel }}</p>
        </div>
        <button
          @click="$emit('close')"
          class="text-2xl text-gray-500 p-2 -m-2"
        >
          ✕
        </button>
      </div>
    </div>

    <!-- Shift Status -->
    <div v-if="hasOpenShift" class="p-4 border-b border-gray-800">
      <div class="flex items-center gap-2 text-green-400">
        <span>●</span>
        <span class="text-sm">Смена открыта</span>
      </div>
      <p class="text-xs text-gray-500 mt-1">
        Открыта в {{ shiftOpenTime }}
      </p>
    </div>

    <!-- Menu Items -->
    <div class="flex-1 overflow-y-auto p-2">
      <button
        v-for="item in menuItems"
        :key="item.id"
        @click="handleMenuClick(item)"
        :class="[
          'w-full flex items-center gap-3 px-4 py-3 rounded-xl transition',
          item.active ? 'bg-orange-500/10 text-orange-400' : 'text-gray-400 hover:bg-dark-800'
        ]"
      >
        <span class="text-xl">{{ item.icon }}</span>
        <span>{{ item.label }}</span>
      </button>
    </div>

    <!-- Settings -->
    <div class="p-4 border-t border-gray-800 space-y-3">
      <label class="flex items-center justify-between cursor-pointer">
        <span class="text-gray-400">Звуки</span>
        <div
          @click="$emit('toggleSound')"
          :class="[
            'w-12 h-6 rounded-full transition relative',
            soundEnabled ? 'bg-orange-500' : 'bg-gray-700'
          ]"
        >
          <div
            :class="[
              'w-5 h-5 rounded-full bg-white shadow absolute top-0.5 transition',
              soundEnabled ? 'right-0.5' : 'left-0.5'
            ]"
          ></div>
        </div>
      </label>

      <label class="flex items-center justify-between cursor-pointer">
        <span class="text-gray-400">Уведомления</span>
        <div
          @click="$emit('toggleNotifications')"
          :class="[
            'w-12 h-6 rounded-full transition relative',
            notificationsEnabled ? 'bg-orange-500' : 'bg-gray-700'
          ]"
        >
          <div
            :class="[
              'w-5 h-5 rounded-full bg-white shadow absolute top-0.5 transition',
              notificationsEnabled ? 'right-0.5' : 'left-0.5'
            ]"
          ></div>
        </div>
      </label>
    </div>

    <!-- Logout -->
    <div class="p-4 border-t border-gray-800">
      <button
        @click="$emit('logout')"
        class="w-full py-3 rounded-xl bg-red-500/10 text-red-400 font-medium"
        data-testid="logout-btn"
      >
        Выйти
      </button>
    </div>

    <!-- Version -->
    <div class="p-4 text-center text-xs text-gray-600">
      MenuLab Waiter v1.0
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { formatTime } from '@/waiter/utils/formatters';
import type { Tab } from '@/waiter/stores/ui';

interface MenuItem {
  id: Tab;
  label: string;
  icon: string;
  active?: boolean;
}

const props = defineProps<{
  userName: string;
  userRole: string;
  currentTab: Tab;
  hasOpenShift?: boolean;
  shiftOpenedAt?: string;
  soundEnabled?: boolean;
  notificationsEnabled?: boolean;
}>();

const emit = defineEmits<{
  close: [];
  navigate: [tab: Tab];
  toggleSound: [];
  toggleNotifications: [];
  logout: [];
}>();

const userInitials = computed((): string => {
  if (!props.userName) return '??';
  const parts = props.userName.split(' ');
  if (parts.length >= 2) {
    return (parts[0][0] + parts[1][0]).toUpperCase();
  }
  return props.userName.substring(0, 2).toUpperCase();
});

const roleLabel = computed((): string => {
  const labels: Record<string, string> = {
    admin: 'Администратор',
    manager: 'Менеджер',
    waiter: 'Официант',
    cashier: 'Кассир',
  };
  return labels[props.userRole] || props.userRole;
});

const shiftOpenTime = computed((): string => {
  if (!props.shiftOpenedAt) return '';
  return formatTime(props.shiftOpenedAt);
});

const menuItems = computed((): MenuItem[] => [
  { id: 'tables', label: 'Столы', icon: '🪑', active: props.currentTab === 'tables' },
  { id: 'orders', label: 'Заказы', icon: '📋', active: props.currentTab === 'orders' },
  { id: 'profile', label: 'Профиль', icon: '👤', active: props.currentTab === 'profile' },
]);

function handleMenuClick(item: MenuItem): void {
  emit('navigate', item.id);
  emit('close');
}
</script>
