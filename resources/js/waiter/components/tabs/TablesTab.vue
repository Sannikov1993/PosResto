<template>
    <div class="h-full flex flex-col">
        <!-- Zone Tabs -->
        <div class="flex-shrink-0 px-4 py-2 flex gap-2 overflow-x-auto bg-dark-900">
            <button v-for="zone in zones" :key="zone.id"
                    @click="$emit('selectZone', zone.id)"
                    :class="['px-4 py-2 rounded-xl text-sm font-medium whitespace-nowrap transition',
                             selectedZone === zone.id ? 'bg-orange-500 text-white' : 'bg-dark-800 text-gray-400']">
                {{ zone.name }}
            </button>
        </div>

        <!-- Tables Grid -->
        <div class="flex-1 p-4 overflow-y-auto">
            <div class="grid grid-cols-3 gap-3">
                <button v-for="table in zoneTables" :key="table.id"
                        @click="$emit('selectTable', table)"
                        :class="['aspect-square rounded-2xl flex flex-col items-center justify-center transition',
                                 tableClass(table)]">
                    <span class="text-2xl font-bold">{{ table.number }}</span>
                    <span class="text-xs mt-1 opacity-75">{{ table.seats }} мест</span>
                    <span v-if="table.active_order" class="text-xs mt-1">
                        {{ formatMoney(table.active_order.total) }} ₽
                    </span>
                </button>
            </div>

            <div v-if="!zoneTables.length" class="flex flex-col items-center justify-center h-full text-gray-500">
                <p class="text-4xl mb-4">🪑</p>
                <p>Нет столов в этой зоне</p>
            </div>
        </div>

        <!-- Stats -->
        <div class="flex-shrink-0 px-4 py-3 bg-dark-800 flex justify-around text-center border-t border-gray-800">
            <div>
                <p class="text-xl font-bold text-green-400">{{ freeCount }}</p>
                <p class="text-xs text-gray-500">Свободно</p>
            </div>
            <div>
                <p class="text-xl font-bold text-orange-400">{{ occupiedCount }}</p>
                <p class="text-xs text-gray-500">Занято</p>
            </div>
            <div>
                <p class="text-xl font-bold text-blue-400">{{ reservedCount }}</p>
                <p class="text-xs text-gray-500">Бронь</p>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    zones: { type: Array, default: () => [] },
    tables: { type: Array, default: () => [] },
    selectedZone: { type: [Number, String], default: null }
});

defineEmits(['selectZone', 'selectTable']);

const zoneTables = computed(() => {
    if (!props.selectedZone) return props.tables;
    return props.tables.filter(t => t.zone_id === props.selectedZone);
});

const freeCount = computed(() => zoneTables.value.filter(t => t.status === 'free' || !t.status).length);
const occupiedCount = computed(() => zoneTables.value.filter(t => t.status === 'occupied').length);
const reservedCount = computed(() => zoneTables.value.filter(t => t.status === 'reserved').length);

const tableClass = (table) => {
    if (table.status === 'occupied') return 'bg-orange-500/20 border-2 border-orange-500';
    if (table.status === 'reserved') return 'bg-blue-500/20 border-2 border-blue-500';
    if (table.status === 'bill') return 'bg-purple-500/20 border-2 border-purple-500';
    return 'bg-dark-800 border-2 border-transparent';
};

const formatMoney = (n) => Math.floor(n || 0).toLocaleString('ru-RU');
</script>
