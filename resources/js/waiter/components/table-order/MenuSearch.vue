<template>
  <div class="relative px-4 py-2 bg-dark-900 sticky top-0 z-10">
    <input
      v-model="searchQuery"
      type="text"
      placeholder="Поиск блюда..."
      class="w-full bg-dark-700 rounded-lg px-4 py-2 pl-10 text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-primary"
      data-testid="menu-search-input"
    >
    <svg
      class="absolute left-7 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-500"
      fill="none"
      stroke="currentColor"
      viewBox="0 0 24 24"
    >
      <path
        stroke-linecap="round"
        stroke-linejoin="round"
        stroke-width="2"
        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
      />
    </svg>
    <button
      v-if="searchQuery"
      @click="clearSearch"
      class="absolute right-7 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-300 transition"
    >
      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
      </svg>
    </button>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useMenuStore } from '@/waiter/stores/menu';
import { storeToRefs } from 'pinia';

const menuStore = useMenuStore();
const { searchQuery: storeSearchQuery } = storeToRefs(menuStore);

const searchQuery = computed({
  get: () => storeSearchQuery.value,
  set: (value: string) => menuStore.setSearchQuery(value),
});

function clearSearch(): void {
  menuStore.clearSearch();
}
</script>
