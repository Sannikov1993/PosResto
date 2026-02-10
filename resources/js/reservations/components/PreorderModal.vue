<template>
    <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
        <div class="bg-white rounded-2xl w-[960px] max-h-[90vh] overflow-hidden flex flex-col shadow-2xl">
            <!-- Header -->
            <div class="bg-gradient-to-r from-purple-600 to-purple-500 text-white">
                <div class="px-6 py-3 flex justify-between items-center">
                    <h3 class="text-lg font-semibold">Предзаказ</h3>
                    <button @click="close" class="w-8 h-8 rounded-full bg-white/20 hover:bg-white/30 flex items-center justify-center">&times;</button>
                </div>
                <div class="px-6 pb-4">
                    <div class="bg-white/10 backdrop-blur rounded-xl p-3 flex items-center gap-5">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-white/20 rounded-full flex items-center justify-center font-bold">
                                {{ (store.preorderReservation?.guest_name as any)?.charAt(0) }}
                            </div>
                            <div>
                                <p class="font-medium">{{ store.preorderReservation?.guest_name }}</p>
                                <p class="text-white/70 text-xs">{{ store.preorderReservation?.guest_phone }}</p>
                            </div>
                        </div>
                        <div class="h-8 w-px bg-white/20"></div>
                        <div class="flex gap-5 text-xs">
                            <div><p class="text-white/60">Дата</p><p class="font-medium">{{ store.formatDateShort(store.preorderReservation?.date as any) }}</p></div>
                            <div><p class="text-white/60">Время</p><p class="font-medium">{{ store.formatTime(store.preorderReservation?.time_from as any) }}</p></div>
                            <div><p class="text-white/60">Стол</p><p class="font-medium">{{ (store.preorderReservation?.table as any)?.number }}</p></div>
                            <div><p class="text-white/60">Гостей</p><p class="font-medium">{{ store.preorderReservation?.guests_count }}</p></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex-1 flex overflow-hidden">
                <!-- Categories -->
                <div class="w-52 border-r overflow-y-auto bg-gray-50/50 p-3">
                    <p class="text-xs text-gray-400 uppercase tracking-wide mb-2 px-2">Категории</p>
                    <div class="space-y-1">
                        <button v-for="cat in store.menuCategories" :key="cat.id"
                                @click="selectCategory(cat)"
                                :class="['w-full text-left px-3 py-2 rounded-lg text-sm transition',
                                         (store.selectedCategory as any)?.id === (cat as any).id ? 'bg-purple-100 text-purple-700' : 'hover:bg-gray-100']">
                            {{ cat.name }}
                        </button>
                    </div>
                </div>

                <!-- Dishes -->
                <div class="flex-1 overflow-y-auto p-4">
                    <div class="grid grid-cols-2 gap-3">
                        <div v-for="dish in store.categoryDishes" :key="dish.id"
                             @click="addToCart(dish)"
                             class="bg-white border rounded-xl p-3 cursor-pointer hover:shadow-md transition">
                            <p class="font-medium">{{ dish.name }}</p>
                            <p class="text-purple-600 font-bold">{{ store.formatMoney(dish.price) }}</p>
                        </div>
                    </div>
                    <div v-if="store.categoryDishes.length === 0" class="text-center text-gray-400 py-8">
                        Выберите категорию слева
                    </div>
                </div>

                <!-- Cart -->
                <div class="w-72 border-l flex flex-col bg-white">
                    <div class="p-3 border-b flex items-center justify-between">
                        <p class="font-semibold text-gray-800 text-sm">Корзина</p>
                        <span v-if="store.preorderCart.length" class="bg-purple-100 text-purple-600 text-xs font-bold px-2 py-0.5 rounded-full">
                            {{ store.preorderCart.length }}
                        </span>
                    </div>
                    <div class="flex-1 overflow-y-auto p-3">
                        <div v-if="store.preorderCart.length === 0" class="h-full flex items-center justify-center text-gray-400 text-center">
                            <div><p class="text-2xl mb-1">🛒</p><p class="text-xs">Добавьте блюда</p></div>
                        </div>
                        <div v-else class="space-y-2">
                            <div v-for="(item, idx) in store.preorderCart" :key="idx" class="bg-gray-50 rounded-lg p-2 flex items-center gap-2">
                                <div class="flex-1 min-w-0">
                                    <p class="font-medium text-xs text-gray-800 truncate">{{ item.name }}</p>
                                    <p class="text-purple-600 text-xs font-semibold">{{ store.formatMoney(item.price * item.quantity) }}</p>
                                </div>
                                <div class="flex items-center gap-0.5 bg-white rounded p-0.5">
                                    <button @click="updateQty(item, -1)" class="w-6 h-6 rounded bg-gray-100 hover:bg-gray-200 text-xs font-bold">−</button>
                                    <span class="w-6 text-center text-xs font-semibold">{{ item.quantity }}</span>
                                    <button @click="updateQty(item, 1)" class="w-6 h-6 rounded bg-gray-100 hover:bg-gray-200 text-xs font-bold">+</button>
                                </div>
                                <button @click="removeItem(item)" class="w-6 h-6 rounded hover:bg-red-100 text-gray-400 hover:text-red-500 text-sm">×</button>
                            </div>
                        </div>
                    </div>
                    <div class="p-3 border-t bg-gray-50/50">
                        <div class="flex justify-between items-center mb-3">
                            <span class="text-gray-500 text-sm">Итого:</span>
                            <span class="text-xl font-bold text-gray-800">{{ store.formatMoney(store.preorderCartTotal) }}</span>
                        </div>
                        <button @click="save" :disabled="store.preorderCart.length === 0"
                                :class="['w-full py-3 rounded-xl font-semibold text-white transition',
                                         store.preorderCart.length ? 'bg-purple-500 hover:bg-purple-600' : 'bg-gray-300 cursor-not-allowed']">
                            Сохранить предзаказ
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { useReservationsStore } from '../stores/reservations';

const store = useReservationsStore();

function close() {
    store.showPreorderModal = false;
    store.preorderReservation = null;
    store.preorderCart = [];
}

async function selectCategory(cat: any) {
    store.selectedCategory = cat;
    await store.loadCategoryDishes(cat.id);
}

function addToCart(dish: any) {
    const existing = store.preorderCart.find((i: any) => i.dish_id === dish.id);
    if (existing) {
        existing.quantity++;
    } else {
        store.preorderCart.push({
            dish_id: dish.id,
            name: dish.name,
            price: dish.price,
            quantity: 1,
            isExisting: false
        });
    }
}

function updateQty(item: any, delta: any) {
    item.quantity += delta;
    if (item.quantity <= 0) removeItem(item);
}

function removeItem(item: any) {
    const idx = store.preorderCart.indexOf(item);
    if (idx >= 0) store.preorderCart.splice(idx, 1);
}

async function save() {
    await store.savePreorder();
}
</script>
