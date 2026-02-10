<template>
    <div class="p-6">
        <div class="relative">
            <!-- Time slots header -->
            <div class="flex">
                <div class="w-16 shrink-0"></div>
                <div class="flex-1 flex">
                    <div v-for="hour in store.workHours" :key="hour" class="flex-1 text-center text-xs text-gray-400 border-l">
                        {{ hour }}:00
                    </div>
                </div>
            </div>

            <!-- Tables rows -->
            <div v-for="table in store.tables" :key="table.id" class="flex items-center mt-2">
                <div class="w-16 shrink-0 text-sm font-medium text-gray-600">
                    Стол {{ table.number }}
                </div>
                <div class="flex-1 h-10 bg-gray-100 rounded relative flex">
                    <div v-for="res in getTableReservations(table.id)" :key="res.id"
                         @click="store.selectedReservation = res"
                         :class="['absolute h-full rounded cursor-pointer flex items-center px-2 text-white text-xs font-medium truncate', getStatusBg(res.status)]"
                         :style="getTimelineStyle(res)"
                         :title="res.guest_name + ' (' + res.guests_count + ' чел)'">
                        {{ res.guest_name }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { useReservationsStore } from '../stores/reservations';
const store = useReservationsStore();

function getTableReservations(tableId: any) {
    return store.filteredReservations.filter((r: any) => r.table_id === tableId);
}

function getStatusBg(s: any) {
    return ({ pending: 'bg-yellow-500', confirmed: 'bg-green-500', seated: 'bg-blue-500', completed: 'bg-gray-500', cancelled: 'bg-red-400' } as Record<string, string>)[s] || 'bg-gray-400';
}

function getTimelineStyle(res: any) {
    const startHour = parseInt(res.time_from.split(':')[0]);
    const startMin = parseInt(res.time_from.split(':')[1]);
    const endHour = parseInt(res.time_to.split(':')[0]);
    const endMin = parseInt(res.time_to.split(':')[1]);

    const totalMinutes = (22 - 10) * 60;
    const startOffset = (startHour - 10) * 60 + startMin;
    const duration = (endHour - startHour) * 60 + (endMin - startMin);

    return {
        left: (startOffset / totalMinutes * 100) + '%',
        width: (duration / totalMinutes * 100) + '%',
    };
}
</script>
