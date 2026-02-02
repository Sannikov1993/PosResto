<template>
  <div class="flex-shrink-0 px-4 py-2 flex gap-2 overflow-x-auto bg-dark-900 scrollbar-hide">
    <button
      v-for="zone in zones"
      :key="zone.id"
      @click="$emit('select', zone.id)"
      :class="[
        'px-4 py-2 rounded-xl text-sm font-medium whitespace-nowrap transition',
        selectedZoneId === zone.id
          ? 'bg-orange-500 text-white'
          : 'bg-dark-800 text-gray-400 hover:bg-dark-700'
      ]"
      :data-testid="`zone-${zone.id}`"
    >
      {{ zone.name }}
      <span v-if="showCount" class="ml-1 opacity-60">
        ({{ getZoneTableCount(zone.id) }})
      </span>
    </button>
  </div>
</template>

<script setup lang="ts">
import type { Zone } from '@/waiter/types';

const props = defineProps<{
  zones: Zone[];
  selectedZoneId: number | null;
  tablesByZone?: Record<number, number>;
  showCount?: boolean;
}>();

defineEmits<{
  select: [zoneId: number];
}>();

function getZoneTableCount(zoneId: number): number {
  return props.tablesByZone?.[zoneId] || 0;
}
</script>

<style scoped>
.scrollbar-hide::-webkit-scrollbar {
  display: none;
}
.scrollbar-hide {
  -ms-overflow-style: none;
  scrollbar-width: none;
}
</style>
