<template>
    <div>
        <h1 class="text-2xl font-bold mb-6">Дашборд</h1>
        <div class="grid grid-cols-4 gap-6">
            <div class="bg-white rounded-xl shadow-sm p-6">
                <p class="text-gray-500 text-sm">Заказов сегодня</p>
                <p class="text-3xl font-bold text-gray-800">{{ store.stats.today_orders || 0 }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-6">
                <p class="text-gray-500 text-sm">Выручка сегодня</p>
                <p class="text-3xl font-bold text-green-600">{{ store.formatMoney(store.stats.today_revenue) }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-6">
                <p class="text-gray-500 text-sm">Средний чек</p>
                <p class="text-3xl font-bold text-blue-600">{{ store.formatMoney(store.stats.avg_check) }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-6">
                <p class="text-gray-500 text-sm">Активных столов</p>
                <p class="text-3xl font-bold text-orange-600">{{ store.stats.active_tables || 0 }}</p>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { onMounted } from 'vue';
import { useAdminStore } from '../stores/admin';
const store = useAdminStore();
onMounted(() => store.loadStats());
</script>
