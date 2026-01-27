<template>
    <div class="min-h-screen bg-gray-100">
        <!-- Header -->
        <header class="bg-white shadow-sm px-4 py-3 sticky top-0 z-40">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="font-bold text-lg">{{ restaurantName }}</h1>
                    <p v-if="tableNumber" class="text-gray-500 text-sm">Стол {{ tableNumber }}</p>
                </div>
                <button @click="showWaiterCall = true" class="p-2 bg-orange-500 text-white rounded-lg">
                    🔔 Официант
                </button>
            </div>
        </header>

        <!-- Categories -->
        <div class="bg-white px-4 py-2 overflow-x-auto sticky top-14 z-30 shadow-sm">
            <div class="flex gap-2 min-w-max">
                <button v-for="cat in categories" :key="cat.id"
                        @click="scrollToCategory(cat.id)"
                        :class="['px-4 py-2 rounded-full text-sm font-medium whitespace-nowrap transition',
                                 activeCategory === cat.id ? 'bg-orange-500 text-white' : 'bg-gray-100']">
                    {{ cat.name }}
                </button>
            </div>
        </div>

        <!-- Menu -->
        <main class="p-4 pb-24">
            <div v-for="cat in categories" :key="cat.id" :id="'cat-' + cat.id" class="mb-8">
                <h2 class="text-xl font-bold mb-4">{{ cat.name }}</h2>
                <div class="space-y-3">
                    <div v-for="dish in getCategoryDishes(cat.id)" :key="dish.id"
                         @click="openDish(dish)"
                         class="bg-white rounded-xl shadow-sm p-4 flex gap-4 cursor-pointer active:bg-gray-50">
                        <div v-if="dish.image" class="w-20 h-20 bg-gray-200 rounded-lg overflow-hidden shrink-0">
                            <img :src="dish.image" :alt="dish.name" class="w-full h-full object-cover">
                        </div>
                        <div class="flex-1">
                            <h3 class="font-semibold">{{ dish.name }}</h3>
                            <p class="text-gray-500 text-sm line-clamp-2">{{ dish.description }}</p>
                            <p class="text-orange-500 font-bold mt-2">{{ formatMoney(dish.price) }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <!-- Dish Detail Modal -->
        <div v-if="selectedDish" class="fixed inset-0 bg-black/50 z-50 flex items-end">
            <div class="bg-white rounded-t-2xl w-full max-h-[80vh] overflow-y-auto">
                <div v-if="selectedDish.image" class="h-48 bg-gray-200">
                    <img :src="selectedDish.image" class="w-full h-full object-cover">
                </div>
                <div class="p-6">
                    <h2 class="text-2xl font-bold">{{ selectedDish.name }}</h2>
                    <p class="text-gray-500 mt-2">{{ selectedDish.description }}</p>
                    <p class="text-2xl text-orange-500 font-bold mt-4">{{ formatMoney(selectedDish.price) }}</p>
                    <button @click="selectedDish = null"
                            class="w-full mt-6 py-3 bg-gray-200 rounded-xl font-medium">
                        Закрыть
                    </button>
                </div>
            </div>
        </div>

        <!-- Waiter Call Modal -->
        <div v-if="showWaiterCall" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl w-full max-w-sm p-6 text-center">
                <div class="text-5xl mb-4">🔔</div>
                <h2 class="text-xl font-bold mb-2">Вызов официанта</h2>
                <p class="text-gray-500 mb-6">Официант подойдёт к вашему столу</p>
                <div class="flex gap-3">
                    <button @click="showWaiterCall = false" class="flex-1 py-3 bg-gray-200 rounded-xl">Отмена</button>
                    <button @click="callWaiter" class="flex-1 py-3 bg-orange-500 text-white rounded-xl">Позвать</button>
                </div>
            </div>
        </div>

        <!-- Toast -->
        <div v-if="toast" class="fixed bottom-20 left-4 right-4 bg-green-500 text-white px-4 py-3 rounded-xl text-center z-50">
            {{ toast }}
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';

const restaurantName = ref('PosResto');
const tableNumber = ref(null);
const tableCode = ref(null);
const categories = ref([]);
const dishes = ref([]);
const selectedDish = ref(null);
const activeCategory = ref(null);
const showWaiterCall = ref(false);
const toast = ref(null);

function getCategoryDishes(catId) {
    return dishes.value.filter(d => d.category_id === catId);
}

function scrollToCategory(catId) {
    activeCategory.value = catId;
    const el = document.getElementById('cat-' + catId);
    if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function openDish(dish) {
    selectedDish.value = dish;
}

async function callWaiter() {
    try {
        await axios.post('/api/guest/call-waiter', { table_code: tableCode.value });
        showWaiterCall.value = false;
        showToast('Официант уже идёт');
    } catch (e) {
        showToast('Ошибка');
    }
}

function formatMoney(a) {
    return new Intl.NumberFormat('ru-RU', { style: 'currency', currency: 'RUB', maximumFractionDigits: 0 }).format(a || 0);
}

function showToast(msg) {
    toast.value = msg;
    setTimeout(() => { toast.value = null; }, 3000);
}

async function loadMenu() {
    try {
        const res = await axios.get('/api/menu/public', { params: { code: tableCode.value } });
        if (res.data.success) {
            categories.value = res.data.categories || [];
            dishes.value = res.data.dishes || [];
            restaurantName.value = res.data.restaurant_name || 'PosResto';
            tableNumber.value = res.data.table_number;
            if (categories.value.length) activeCategory.value = categories.value[0].id;
        }
    } catch (e) { console.error(e); }
}

onMounted(() => {
    // Get table code from URL hash or query
    const hash = window.location.hash.slice(1);
    const urlParams = new URLSearchParams(window.location.search);
    tableCode.value = hash || urlParams.get('code') || '';
    loadMenu();
});
</script>
