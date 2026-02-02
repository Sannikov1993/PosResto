<template>
  <div class="h-full flex flex-col">
    <!-- Header -->
    <TableOrderHeader
      :table="selectedTable"
      :order="currentOrder"
      @back="goBack"
    />

    <!-- Order Panel -->
    <OrderPanel
      :items="currentOrderItems"
      @increment-item="handleIncrement"
      @decrement-item="handleDecrement"
      @remove-item="handleRemove"
    />

    <!-- Menu Section -->
    <div class="flex-shrink-0 border-t border-gray-800">
      <CategoryList
        :categories="rootCategories"
        :selected-category-id="selectedCategoryId"
        @select="selectCategory"
      />
      <DishGrid
        :dishes="filteredDishes"
        @select="handleAddDish"
      />
    </div>

    <!-- Footer -->
    <TableOrderFooter
      :total="currentOrderTotal"
      :new-items-count="newItemsCount"
      :ready-items-count="readyItemsCount"
      :can-send="canSendToKitchen"
      :can-pay="canPay"
      :is-saving="isSaving"
      @send-to-kitchen="handleSendToKitchen"
      @request-bill="openPayment"
      @mark-all-served="handleMarkAllServed"
    />
  </div>
</template>

<script setup lang="ts">
import { storeToRefs } from 'pinia';
import { useOrders, useTables } from '@/waiter/composables';
import { useMenuStore } from '@/waiter/stores/menu';
import { useUiStore } from '@/waiter/stores/ui';
import type { Dish, OrderItem } from '@/waiter/types';

import TableOrderHeader from './TableOrderHeader.vue';
import OrderPanel from './OrderPanel.vue';
import CategoryList from './CategoryList.vue';
import DishGrid from './DishGrid.vue';
import TableOrderFooter from './TableOrderFooter.vue';

// Composables
const { selectedTable } = useTables();
const {
  currentOrder,
  currentOrderItems,
  currentOrderTotal,
  newItemsCount,
  readyItemsCount,
  canSendToKitchen,
  canPay,
  isSaving,
  addDish,
  incrementQuantity,
  decrementQuantity,
  removeItem,
  sendToKitchen,
  markAllServed,
  openPayment,
} = useOrders();

// Menu store
const menuStore = useMenuStore();
const { rootCategories, selectedCategoryId, filteredDishes } = storeToRefs(menuStore);

// UI store
const uiStore = useUiStore();

// Methods
function goBack(): void {
  uiStore.goToTables();
}

function selectCategory(categoryId: number): void {
  menuStore.selectCategory(categoryId);
}

async function handleAddDish(dish: Dish): Promise<void> {
  await addDish(dish);
}

async function handleIncrement(item: OrderItem): Promise<void> {
  await incrementQuantity(item.id);
}

async function handleDecrement(item: OrderItem): Promise<void> {
  await decrementQuantity(item.id);
}

async function handleRemove(item: OrderItem): Promise<void> {
  await removeItem(item.id);
}

async function handleSendToKitchen(): Promise<void> {
  await sendToKitchen();
}

async function handleMarkAllServed(): Promise<void> {
  await markAllServed();
}
</script>
