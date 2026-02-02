<template>
  <div class="h-full overflow-y-auto p-4 space-y-4">
    <!-- User Card -->
    <div class="bg-dark-800 rounded-xl p-4 flex items-center gap-4">
      <div class="w-16 h-16 rounded-full bg-orange-500/20 flex items-center justify-center text-orange-400 font-bold text-2xl">
        {{ userInitials }}
      </div>
      <div class="flex-1">
        <p class="font-bold text-lg">{{ userName }}</p>
        <p class="text-gray-500">{{ roleLabel }}</p>
        <p v-if="restaurant" class="text-xs text-gray-600">{{ restaurant.name }}</p>
      </div>
    </div>

    <!-- Shift Status -->
    <ShiftStatus :shift="currentShift" />

    <!-- Stats -->
    <ProfileStats :stats="todayStats" />

    <!-- Actions -->
    <div class="space-y-2">
      <button
        @click="handleRefresh"
        :disabled="isRefreshing"
        class="w-full py-3 rounded-xl bg-dark-800 text-gray-400 font-medium flex items-center justify-center gap-2"
      >
        <span :class="{ 'animate-spin': isRefreshing }">🔄</span>
        <span>{{ isRefreshing ? 'Обновление...' : 'Обновить данные' }}</span>
      </button>
    </div>

    <!-- Logout -->
    <button
      @click="handleLogout"
      class="w-full py-3 rounded-xl bg-red-500/10 text-red-400 font-medium"
      data-testid="profile-logout-btn"
    >
      Выйти
    </button>

    <!-- Version -->
    <p class="text-center text-xs text-gray-600 py-4">
      MenuLab Waiter v1.0
    </p>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';
import { storeToRefs } from 'pinia';
import { useAuth, useOrders } from '@/waiter/composables';
import { useTablesStore } from '@/waiter/stores/tables';
import { useMenuStore } from '@/waiter/stores/menu';
import ShiftStatus from './ShiftStatus.vue';
import ProfileStats from './ProfileStats.vue';

const {
  user,
  restaurant,
  currentShift,
  userName,
  userRole,
  userInitials,
  logout,
} = useAuth();

const { todayStats, fetchOrders } = useOrders();

const tablesStore = useTablesStore();
const menuStore = useMenuStore();

const isRefreshing = ref(false);

const roleLabel = computed(() => {
  const labels: Record<string, string> = {
    admin: 'Администратор',
    manager: 'Менеджер',
    waiter: 'Официант',
    cashier: 'Кассир',
  };
  return labels[userRole.value] || userRole.value;
});

async function handleRefresh(): Promise<void> {
  isRefreshing.value = true;
  try {
    await Promise.all([
      tablesStore.fetchAll(true),
      fetchOrders(true),
      menuStore.fetchAll(true),
    ]);
  } finally {
    isRefreshing.value = false;
  }
}

async function handleLogout(): Promise<void> {
  await logout();
}
</script>
