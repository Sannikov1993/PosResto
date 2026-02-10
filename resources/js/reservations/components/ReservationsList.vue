<template>
    <div class="divide-y">
        <div v-for="res in store.filteredReservations" :key="res.id"
             @click="store.selectedReservation = res"
             class="px-6 py-4 hover:bg-gray-50 cursor-pointer flex items-center gap-4">

            <!-- Time -->
            <div class="w-24 text-center">
                <p class="text-2xl font-bold text-gray-800">{{ store.formatTime(res.time_from) }}</p>
                <p class="text-xs text-gray-400">до {{ store.formatTime(res.time_to as any) }}</p>
            </div>

            <!-- Status line -->
            <div :class="['w-3 h-12 rounded-full', store.getStatusColor(res.status)]"></div>

            <!-- Guest Info -->
            <div class="flex-1">
                <p class="font-semibold text-lg">{{ res.guest_name }}</p>
                <p class="text-gray-500">{{ res.guest_phone }}</p>
            </div>

            <!-- Table -->
            <div class="text-center">
                <p class="text-sm font-medium">Стол {{ (res.table as any)?.number }}</p>
            </div>

            <!-- Guests -->
            <div class="text-center">
                <p class="text-sm font-medium">{{ res.guests_count }} чел</p>
            </div>

            <!-- Status Badge -->
            <div :class="['px-3 py-1 rounded-full text-sm font-medium', store.getStatusBadge(res.status)]">
                {{ res.status_label }}
            </div>

            <!-- Actions -->
            <div class="flex gap-1">
                <button v-if="res.status === 'pending'"
                        @click.stop="store.updateStatus(res, 'confirm')"
                        class="p-2 hover:bg-green-100 rounded-lg text-green-600" title="Подтвердить">✓</button>
                <button v-if="res.status === 'confirmed'"
                        @click.stop="store.updateStatus(res, 'seat')"
                        class="p-2 hover:bg-blue-100 rounded-lg text-blue-600" title="Гости сели">🪑</button>
                <button @click.stop="callGuest(res)"
                        class="p-2 hover:bg-gray-100 rounded-lg" title="Позвонить">📞</button>
            </div>
        </div>

        <div v-if="store.filteredReservations.length === 0" class="px-6 py-12 text-center text-gray-400">
            <p class="text-4xl mb-2">📅</p>
            <p>Нет бронирований на эту дату</p>
        </div>
    </div>
</template>

<script setup lang="ts">
import { useReservationsStore } from '../stores/reservations';
const store = useReservationsStore();

function callGuest(res: any) {
    window.open(`tel:${res.guest_phone}`);
}
</script>
