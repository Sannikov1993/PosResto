<template>
    <Teleport to="body">
        <!-- Overlay -->
        <Transition name="fade">
            <div v-if="isOpen" class="fixed inset-0 bg-black/50 z-40" @click="close"></div>
        </Transition>

        <!-- Panel -->
        <Transition name="slide">
            <div v-if="isOpen" class="fixed top-0 right-0 h-full w-[380px] bg-dark-900 shadow-2xl z-50 flex flex-col border-l border-gray-800">
                <!-- Header -->
                <div class="flex items-center justify-between px-4 py-3 bg-dark-800 border-b border-gray-700">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-purple-600/20 flex items-center justify-center">
                            <span class="text-xl">🍸</span>
                        </div>
                        <div>
                            <h2 class="font-semibold text-white">Бар</h2>
                            <p class="text-xs text-gray-500">{{ barStation?.name || 'Напитки' }}</p>
                        </div>
                    </div>
                    <button @click="close" class="w-10 h-10 flex items-center justify-center text-gray-400 hover:text-white hover:bg-dark-700 rounded-xl transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <!-- Counts -->
                <div class="flex bg-dark-800/50 border-b border-gray-800">
                    <div class="flex-1 text-center py-3 border-r border-gray-800">
                        <div class="text-xl font-bold text-orange-400">{{ counts.new }}</div>
                        <div class="text-xs text-gray-500">Новые</div>
                    </div>
                    <div class="flex-1 text-center py-3 border-r border-gray-800">
                        <div class="text-xl font-bold text-blue-400">{{ counts.in_progress }}</div>
                        <div class="text-xs text-gray-500">В работе</div>
                    </div>
                    <div class="flex-1 text-center py-3">
                        <div class="text-xl font-bold text-green-400">{{ counts.ready }}</div>
                        <div class="text-xs text-gray-500">Готово</div>
                    </div>
                </div>

                <!-- Items List -->
                <div class="flex-1 overflow-y-auto p-3 space-y-2">
                    <div v-if="loading" class="flex items-center justify-center py-12">
                        <div class="animate-spin text-3xl">⏳</div>
                    </div>

                    <div v-else-if="items.length === 0" class="text-center py-12 text-gray-500">
                        <div class="text-4xl mb-2">🍸</div>
                        <p>Нет позиций</p>
                    </div>

                    <template v-else>
                        <!-- New items -->
                        <div v-for="item in newItems" :key="item.id"
                             class="bg-dark-800 border border-orange-500/30 rounded-xl p-3">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 text-xs text-gray-400 mb-1">
                                        <span class="font-medium text-orange-400">#{{ item.order_number }}</span>
                                        <span v-if="item.table" class="text-gray-500">{{ item.table.name || 'Стол ' + item.table.number }}</span>
                                        <span v-else-if="item.order_type === 'delivery'" class="text-blue-400">Доставка</span>
                                        <span v-else-if="item.order_type === 'takeaway'" class="text-green-400">С собой</span>
                                    </div>
                                    <div class="text-white font-medium truncate">
                                        <span class="text-orange-400 font-bold">{{ item.quantity }}×</span>
                                        {{ item.dish_name }}
                                    </div>
                                    <div v-if="item.notes" class="text-xs text-gray-500 mt-1 italic truncate">{{ item.notes }}</div>
                                </div>
                                <button @click="startItem(item)"
                                        class="px-4 py-2 bg-orange-500 text-white text-sm font-medium rounded-lg hover:bg-orange-400 transition shrink-0">
                                    В работу
                                </button>
                            </div>
                        </div>

                        <!-- In progress items -->
                        <div v-for="item in inProgressItems" :key="item.id"
                             class="bg-dark-800 border border-blue-500/30 rounded-xl p-3">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 text-xs text-gray-400 mb-1">
                                        <span class="font-medium text-blue-400">#{{ item.order_number }}</span>
                                        <span v-if="item.table" class="text-gray-500">{{ item.table.name || 'Стол ' + item.table.number }}</span>
                                        <span v-else-if="item.order_type === 'delivery'" class="text-blue-400">Доставка</span>
                                        <span v-else-if="item.order_type === 'takeaway'" class="text-green-400">С собой</span>
                                    </div>
                                    <div class="text-white font-medium truncate">
                                        <span class="text-blue-400 font-bold">{{ item.quantity }}×</span>
                                        {{ item.dish_name }}
                                    </div>
                                    <div v-if="item.notes" class="text-xs text-gray-500 mt-1 italic truncate">{{ item.notes }}</div>
                                </div>
                                <button @click="readyItem(item)"
                                        class="px-4 py-2 bg-green-500 text-white text-sm font-medium rounded-lg hover:bg-green-400 transition shrink-0">
                                    Готово
                                </button>
                            </div>
                        </div>

                        <!-- Ready items -->
                        <div v-for="item in readyItems" :key="item.id"
                             class="bg-dark-800/50 border border-green-500/20 rounded-xl p-3 opacity-60">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 text-xs text-gray-500 mb-1">
                                        <span class="font-medium text-green-400">#{{ item.order_number }}</span>
                                        <span v-if="item.table">{{ item.table.name || 'Стол ' + item.table.number }}</span>
                                    </div>
                                    <div class="text-gray-300 font-medium truncate">
                                        <span class="text-green-400 font-bold">{{ item.quantity }}×</span>
                                        {{ item.dish_name }}
                                    </div>
                                </div>
                                <span class="px-3 py-1.5 bg-green-500/20 text-green-400 text-sm rounded-lg shrink-0">
                                    ✓ Готово
                                </span>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- Footer -->
                <div class="border-t border-gray-800 p-3 bg-dark-800">
                    <button @click="fetchItems" class="w-full py-2.5 bg-dark-700 hover:bg-dark-600 text-gray-300 rounded-xl transition text-sm flex items-center justify-center gap-2">
                        <span>🔄</span> Обновить
                    </button>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import axios from 'axios';

const props = defineProps({
    isOpen: Boolean
});

const emit = defineEmits(['close', 'update:count']);

// State
const loading = ref(false);
const items = ref([]);
const barStation = ref(null);
const counts = ref({ new: 0, in_progress: 0, ready: 0 });

// Computed
const newItems = computed(() => items.value.filter(i => !i.cooking_started_at && i.status === 'cooking'));
const inProgressItems = computed(() => items.value.filter(i => i.cooking_started_at && i.status === 'cooking'));
const readyItems = computed(() => items.value.filter(i => i.status === 'ready'));

// Methods
const close = () => emit('close');

const fetchItems = async () => {
    loading.value = true;
    try {
        const res = await axios.get('/api/bar/orders');
        if (res.data.success) {
            items.value = res.data.data || [];
            barStation.value = res.data.station;
            counts.value = res.data.counts || { new: 0, in_progress: 0, ready: 0 };
            emit('update:count', counts.value.new + counts.value.in_progress);
        }
    } catch (e) {
        console.error('Failed to fetch bar items:', e);
    } finally {
        loading.value = false;
    }
};

const startItem = async (item) => {
    try {
        await axios.post('/api/bar/item-status', {
            item_id: item.id,
            status: 'cooking'
        });
        fetchItems();
    } catch (e) {
        console.error('Failed to start item:', e);
    }
};

const readyItem = async (item) => {
    try {
        await axios.post('/api/bar/item-status', {
            item_id: item.id,
            status: 'ready'
        });
        fetchItems();
    } catch (e) {
        console.error('Failed to mark item ready:', e);
    }
};

// Auto-refresh when open
let refreshInterval = null;

watch(() => props.isOpen, (open) => {
    if (open) {
        fetchItems();
        refreshInterval = setInterval(fetchItems, 15000);
    } else {
        if (refreshInterval) {
            clearInterval(refreshInterval);
            refreshInterval = null;
        }
    }
}, { immediate: true });

// Слушаем событие storage для мгновенного обновления при подаче блюд
const handleStorageChange = (e) => {
    if (e.key === 'bar_refresh' && props.isOpen) {
        fetchItems();
    }
};

onMounted(() => {
    window.addEventListener('storage', handleStorageChange);
});

onUnmounted(() => {
    if (refreshInterval) {
        clearInterval(refreshInterval);
    }
    window.removeEventListener('storage', handleStorageChange);
});
</script>

<style scoped>
.fade-enter-active, .fade-leave-active {
    transition: opacity 0.2s ease;
}
.fade-enter-from, .fade-leave-to {
    opacity: 0;
}

.slide-enter-active, .slide-leave-active {
    transition: transform 0.3s ease;
}
.slide-enter-from, .slide-leave-to {
    transform: translateX(100%);
}
</style>
