<template>
  <div class="h-screen flex flex-col bg-dark-900 text-white overflow-hidden">
    <!-- Login Screen -->
    <LoginScreen v-if="!isAuthenticated" />

    <!-- Main App -->
    <template v-else>
      <!-- Header -->
      <AppHeader
        :title="headerTitle"
        :is-online="isOnline"
        :notifications-count="0"
        @menu-click="toggleSideMenu"
        @notifications-click="handleNotifications"
      />

      <!-- Main Content -->
      <main class="flex-1 overflow-hidden">
        <TablesTab v-show="currentTab === 'tables'" />
        <OrdersTab v-show="currentTab === 'orders'" />
        <TableOrderTab v-show="currentTab === 'table-order'" />
        <ProfileTab v-show="currentTab === 'profile'" />
      </main>

      <!-- Bottom Navigation -->
      <BottomNav
        :current-tab="currentTab"
        :pending-count="newItemsCount"
        @navigate="setTab"
      />

      <!-- Side Menu Overlay -->
      <Transition name="fade">
        <div
          v-if="isSideMenuOpen"
          class="fixed inset-0 bg-black/60 z-40"
          @click="closeSideMenu"
        ></div>
      </Transition>

      <!-- Side Menu -->
      <Transition name="slide">
        <div v-if="isSideMenuOpen" class="fixed inset-y-0 left-0 w-80 z-50">
          <SideMenu
            :user-name="userName"
            :user-role="userRole"
            :current-tab="currentTab"
            :has-open-shift="hasOpenShift"
            :shift-opened-at="currentShift?.opened_at"
            @close="closeSideMenu"
            @navigate="handleNavigate"
            @logout="handleLogout"
          />
        </div>
      </Transition>

      <!-- Payment Modal -->
      <PaymentModal
        v-if="isPaymentModalOpen"
        :order="currentOrder"
        :is-loading="isSaving"
        @pay="handlePay"
        @close="closePaymentModal"
      />

      <!-- Toast Notifications -->
      <AppToast :toasts="toasts" @remove="removeToast" />
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, onUnmounted } from 'vue';
import { storeToRefs } from 'pinia';

// Stores
import { useAuthStore } from '@/waiter/stores/auth';
import { useTablesStore } from '@/waiter/stores/tables';
import { useOrdersStore } from '@/waiter/stores/orders';
import { useMenuStore } from '@/waiter/stores/menu';
import { useUiStore } from '@/waiter/stores/ui';

// Composables
import { useAuth } from '@/waiter/composables';

// Components
import { AppHeader, AppToast } from '@/waiter/components/common';
import { LoginScreen } from '@/waiter/components/auth';
import { BottomNav, SideMenu } from '@/waiter/components/layout';
import { TablesTab } from '@/waiter/components/tables';
import { OrdersTab } from '@/waiter/components/orders';
import { TableOrderTab } from '@/waiter/components/table-order';
import { ProfileTab } from '@/waiter/components/profile';
import { PaymentModal } from '@/waiter/components/payment';

import type { Tab } from '@/waiter/stores/ui';
import type { PaymentMethod } from '@/waiter/types';

// === Stores ===
const authStore = useAuthStore();
const tablesStore = useTablesStore();
const ordersStore = useOrdersStore();
const menuStore = useMenuStore();
const uiStore = useUiStore();

// === Auth ===
const { userName, userRole, hasOpenShift, logout } = useAuth();
const { isAuthenticated, currentShift } = storeToRefs(authStore);

// === Orders ===
const { currentOrder, newItemsCount, isSaving } = storeToRefs(ordersStore);

// === UI ===
const {
  currentTab,
  isSideMenuOpen,
  isPaymentModalOpen,
  toasts,
  isOnline,
} = storeToRefs(uiStore);

// === Computed ===
const headerTitle = computed(() => {
  if (currentTab.value === 'table-order') {
    const table = tablesStore.selectedTable;
    return table ? `Стол ${table.number}` : 'Заказ';
  }
  const titles: Record<Tab, string> = {
    tables: 'Столы',
    orders: 'Заказы',
    'table-order': 'Заказ',
    profile: 'Профиль',
  };
  return titles[currentTab.value] || 'MenuLab';
});

// === Methods ===
function setTab(tab: Tab): void {
  uiStore.setTab(tab);
}

function toggleSideMenu(): void {
  uiStore.toggleSideMenu();
}

function closeSideMenu(): void {
  uiStore.closeSideMenu();
}

function handleNavigate(tab: Tab): void {
  uiStore.setTab(tab);
}

function handleNotifications(): void {
  // TODO: Show notifications panel
}

async function handleLogout(): Promise<void> {
  await logout();
}

function closePaymentModal(): void {
  uiStore.closePaymentModal();
}

async function handlePay(method: PaymentMethod): Promise<void> {
  if (!currentOrder.value) return;

  const success = await ordersStore.payOrder(currentOrder.value.id, {
    payment_method: method,
  });

  if (success) {
    uiStore.showSuccess('Заказ оплачен');
    uiStore.closePaymentModal();
    uiStore.goToTables();
  }
}

function removeToast(id: number): void {
  uiStore.removeToast(id);
}

// === Lifecycle ===
let refreshInterval: ReturnType<typeof setInterval>;

onMounted(async () => {
  // Initialize UI store
  uiStore.init();

  // Check auth
  const isAuth = await authStore.checkAuth();

  if (isAuth) {
    // Load initial data
    await Promise.all([
      tablesStore.fetchAll(),
      ordersStore.fetchOrders(),
      menuStore.fetchAll(),
    ]);

    // Auto-refresh data
    refreshInterval = setInterval(() => {
      if (isAuthenticated.value && !document.hidden) {
        tablesStore.fetchAll();
        ordersStore.fetchOrders();
      }
    }, 30000);
  }
});

onUnmounted(() => {
  if (refreshInterval) {
    clearInterval(refreshInterval);
  }
});
</script>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

.slide-enter-active,
.slide-leave-active {
  transition: transform 0.3s ease;
}

.slide-enter-from,
.slide-leave-to {
  transform: translateX(-100%);
}
</style>
