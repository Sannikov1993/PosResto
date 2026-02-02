<template>
  <div class="flex-1 p-4 overflow-y-auto">
    <div v-if="tables.length > 0" class="grid grid-cols-3 gap-3">
      <TableCard
        v-for="table in tables"
        :key="table.id"
        :table="table"
        @select="$emit('select', $event)"
      />
    </div>

    <AppEmpty
      v-else
      icon="🪑"
      title="Нет столов"
      description="В этой зоне пока нет столов"
    />
  </div>
</template>

<script setup lang="ts">
import type { Table } from '@/waiter/types';
import TableCard from './TableCard.vue';
import { AppEmpty } from '@/waiter/components/common';

defineProps<{
  tables: Table[];
}>();

defineEmits<{
  select: [table: Table];
}>();
</script>
