<template>
  <div class="h-full flex flex-col">
    <!-- Zone Tabs -->
    <ZoneSelector
      :zones="zones"
      :selected-zone-id="selectedZoneId"
      @select="selectZone"
    />

    <!-- Loading -->
    <AppLoader v-if="isLoading" class="flex-1" text="Загрузка столов..." />

    <!-- Error -->
    <AppError
      v-else-if="error"
      :message="error"
      class="flex-1"
      @retry="fetchAll(true)"
    />

    <!-- Tables Grid -->
    <TableGrid
      v-else
      :tables="filteredTables"
      @select="handleTableSelect"
    />

    <!-- Stats -->
    <TableStats
      :free-count="freeCount"
      :occupied-count="occupiedCount"
      :reserved-count="reservedCount"
      :bill-count="billRequestedCount"
    />

    <!-- Guest Count Modal -->
    <GuestCountModal
      v-if="showGuestModal"
      :table="selectedTableForOpen"
      @confirm="handleOpenTable"
      @close="showGuestModal = false"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';
import { useTables } from '@/waiter/composables';
import type { Table } from '@/waiter/types';
import { AppLoader, AppError } from '@/waiter/components/common';
import ZoneSelector from './ZoneSelector.vue';
import TableGrid from './TableGrid.vue';
import TableStats from './TableStats.vue';
import GuestCountModal from './GuestCountModal.vue';

const {
  zones,
  filteredTables,
  selectedZoneId,
  isLoading,
  error,
  selectZone,
  selectTable,
  openTable,
  fetchAll,
} = useTables();

// Guest modal state
const showGuestModal = ref(false);
const selectedTableForOpen = ref<Table | null>(null);

// Stats computed
const freeCount = computed(() => filteredTables.value.filter((t: any) => t.status === 'free').length);
const occupiedCount = computed(() => filteredTables.value.filter((t: any) => t.status === 'occupied').length);
const reservedCount = computed(() => filteredTables.value.filter((t: any) => t.status === 'reserved').length);
const billRequestedCount = computed(() => filteredTables.value.filter((t: any) => t.status === 'bill_requested').length);

function handleTableSelect(table: Table): void {
  if (table.status === 'free') {
    // Show guest count modal for free tables
    selectedTableForOpen.value = table;
    showGuestModal.value = true;
  } else {
    // Navigate directly to occupied table
    selectTable(table);
  }
}

async function handleOpenTable(guestsCount: number): Promise<void> {
  if (!selectedTableForOpen.value) return;

  await openTable(selectedTableForOpen.value.id, guestsCount);
  showGuestModal.value = false;
  selectedTableForOpen.value = null;
}
</script>
