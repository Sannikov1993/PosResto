<template>
    <div class="min-h-screen bg-gray-100">
        <header class="bg-white shadow-sm px-6 py-4 flex items-center justify-between">
            <h1 class="text-2xl font-bold text-gray-800">Гостевой сервис</h1>
            <a href="/backoffice-vue" class="text-gray-500 hover:text-orange-500">← Назад</a>
        </header>

        <main class="p-6">
            <!-- Tabs -->
            <div class="flex gap-2 mb-6">
                <button v-for="tab in tabs" :key="tab.key"
                        @click="activeTab = tab.key"
                        :class="['px-4 py-2 rounded-lg font-medium transition',
                                 activeTab === tab.key ? 'bg-orange-500 text-white' : 'bg-white text-gray-600']">
                    {{ tab.icon }} {{ tab.label }}
                </button>
            </div>

            <!-- Calls Tab -->
            <div v-if="activeTab === 'calls'" class="bg-white rounded-xl shadow-sm p-6">
                <h2 class="text-lg font-semibold mb-4">Вызовы официанта</h2>
                <div class="space-y-3">
                    <div v-for="call in calls" :key="call.id"
                         class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                        <div>
                            <p class="font-medium">Стол {{ call.table_number }}</p>
                            <p class="text-gray-500 text-sm">{{ call.created_at }}</p>
                        </div>
                        <button @click="resolveCall(call)" class="px-4 py-2 bg-green-500 text-white rounded-lg">
                            Выполнено
                        </button>
                    </div>
                    <div v-if="calls.length === 0" class="text-center py-8 text-gray-400">
                        Нет активных вызовов
                    </div>
                </div>
            </div>

            <!-- QR Codes Tab -->
            <div v-if="activeTab === 'qr'" class="bg-white rounded-xl shadow-sm p-6">
                <h2 class="text-lg font-semibold mb-4">QR-коды столов</h2>
                <div class="grid grid-cols-4 gap-4">
                    <div v-for="table in tables" :key="table.id"
                         class="p-4 border rounded-xl text-center">
                        <div class="w-32 h-32 bg-gray-100 mx-auto mb-2 flex items-center justify-center">
                            <span class="text-4xl">📱</span>
                        </div>
                        <p class="font-medium">Стол {{ table.number }}</p>
                        <button @click="printQR(table)" class="mt-2 text-orange-500 text-sm">Печать</button>
                    </div>
                </div>
            </div>

            <!-- Reviews Tab -->
            <div v-if="activeTab === 'reviews'" class="bg-white rounded-xl shadow-sm p-6">
                <h2 class="text-lg font-semibold mb-4">Отзывы</h2>
                <div class="space-y-4">
                    <div v-for="review in reviews" :key="review.id"
                         class="p-4 border rounded-xl">
                        <div class="flex items-center gap-2 mb-2">
                            <div class="flex text-yellow-500">
                                <span v-for="i in 5" :key="i">{{ i <= review.rating ? '★' : '☆' }}</span>
                            </div>
                            <span class="text-gray-500 text-sm">{{ review.created_at }}</span>
                        </div>
                        <p class="text-gray-600">{{ review.comment }}</p>
                        <p class="text-gray-400 text-sm mt-2">Заказ #{{ review.order_number }}</p>
                    </div>
                    <div v-if="reviews.length === 0" class="text-center py-8 text-gray-400">
                        Нет отзывов
                    </div>
                </div>
            </div>

            <!-- Settings Tab -->
            <div v-if="activeTab === 'settings'" class="bg-white rounded-xl shadow-sm p-6 max-w-xl">
                <h2 class="text-lg font-semibold mb-4">Настройки гостевого сервиса</h2>
                <div class="space-y-4">
                    <label class="flex items-center justify-between">
                        <span>Показывать вызов официанта</span>
                        <input type="checkbox" v-model="settings.show_waiter_call" class="w-5 h-5">
                    </label>
                    <label class="flex items-center justify-between">
                        <span>Показывать WiFi</span>
                        <input type="checkbox" v-model="settings.show_wifi" class="w-5 h-5">
                    </label>
                    <div>
                        <label class="block text-sm font-medium mb-1">WiFi пароль</label>
                        <input v-model="settings.wifi_password" class="w-full border rounded-lg px-3 py-2">
                    </div>
                    <button @click="saveSettings" class="w-full py-2 bg-orange-500 text-white rounded-lg">
                        Сохранить
                    </button>
                </div>
            </div>
        </main>

        <!-- Toast -->
        <div v-if="toast" class="fixed bottom-6 right-6 px-6 py-3 rounded-xl shadow-lg z-50 bg-green-500 text-white">
            {{ toast }}
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';

const activeTab = ref('calls');
const tabs = [
    { key: 'calls', label: 'Вызовы', icon: '🔔' },
    { key: 'qr', label: 'QR-коды', icon: '📱' },
    { key: 'reviews', label: 'Отзывы', icon: '⭐' },
    { key: 'settings', label: 'Настройки', icon: '⚙️' },
];

const calls = ref([]);
const tables = ref([]);
const reviews = ref([]);
const settings = ref({ show_waiter_call: true, show_wifi: true, wifi_password: '' });
const toast = ref(null);

async function loadCalls() {
    try {
        const res = await axios.get('/api/guest/calls?status=pending');
        if (res.data.success) calls.value = res.data.data;
    } catch (e) { console.error(e); }
}

async function loadTables() {
    try {
        const res = await axios.get('/api/tables');
        if (res.data.success) tables.value = res.data.data;
    } catch (e) { console.error(e); }
}

async function loadReviews() {
    try {
        const res = await axios.get('/api/reviews');
        if (res.data.success) reviews.value = res.data.data;
    } catch (e) { console.error(e); }
}

async function resolveCall(call) {
    try {
        await axios.post(`/api/guest/calls/${call.id}/resolve`);
        calls.value = calls.value.filter(c => c.id !== call.id);
        showToast('Вызов выполнен');
    } catch (e) { console.error(e); }
}

function printQR(table) {
    window.open(`/api/tables/${table.id}/qr?print=1`, '_blank');
}

async function saveSettings() {
    try {
        await axios.put('/api/settings/guest', settings.value);
        showToast('Настройки сохранены');
    } catch (e) { console.error(e); }
}

function showToast(msg) {
    toast.value = msg;
    setTimeout(() => { toast.value = null; }, 3000);
}

onMounted(() => {
    loadCalls();
    loadTables();
    loadReviews();
});
</script>
