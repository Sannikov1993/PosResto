<template>
    <div>
        <h1 class="text-2xl font-bold mb-6">Управление залом</h1>

        <!-- Zones -->
        <div class="mb-6">
            <h2 class="text-lg font-semibold mb-3">Зоны</h2>
            <div class="flex gap-4 flex-wrap">
                <div v-for="zone in store.zones" :key="zone.id"
                     class="bg-white rounded-xl shadow-sm p-4 w-48">
                    <h3 class="font-medium">{{ zone.name }}</h3>
                    <p class="text-gray-500 text-sm">{{ zone.tables_count }} столов</p>
                </div>
            </div>
        </div>

        <!-- Tables -->
        <div>
            <h2 class="text-lg font-semibold mb-3">Столы</h2>
            <div class="grid grid-cols-6 gap-4">
                <div v-for="table in store.tables" :key="table.id"
                     :class="['bg-white rounded-xl shadow-sm p-4 text-center', getStatusClass(table.status)]">
                    <p class="text-2xl font-bold">{{ table.number }}</p>
                    <p class="text-gray-500 text-sm">{{ table.seats }} мест</p>
                    <p class="text-xs mt-1">{{ table.zone_name }}</p>
                </div>
            </div>
        </div>

        <div class="mt-6">
            <a href="/floor-editor-vue" class="text-orange-500 hover:underline">Открыть визуальный редактор →</a>
        </div>
    </div>
</template>

<script setup>
import { onMounted } from 'vue';
import { useAdminStore } from '../stores/admin';

const store = useAdminStore();

function getStatusClass(status) {
    return {
        'free': 'border-l-4 border-green-500',
        'occupied': 'border-l-4 border-orange-500',
        'reserved': 'border-l-4 border-blue-500'
    }[status] || '';
}

onMounted(() => {
    store.loadZones();
    store.loadTables();
});
</script>
