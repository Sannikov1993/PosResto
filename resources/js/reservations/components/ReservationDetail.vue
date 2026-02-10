<template>
    <div v-if="res" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
        <div class="bg-white rounded-2xl w-[500px] overflow-hidden">
            <div :class="['px-6 py-4 text-white', statusHeaderBg]">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-sm opacity-80">{{ store.formatDateFull(res.date) }}</p>
                        <h3 class="text-2xl font-bold">{{ store.formatTime(res.time_from) }} - {{ store.formatTime(res.time_to) }}</h3>
                    </div>
                    <button @click="close" class="text-white/80 hover:text-white text-2xl">&times;</button>
                </div>
            </div>

            <div class="p-6 space-y-4">
                <!-- Guest -->
                <div class="flex items-center gap-4">
                    <div class="w-14 h-14 bg-orange-100 rounded-full flex items-center justify-center text-2xl font-bold text-orange-500">
                        {{ res.guest_name.charAt(0) }}
                    </div>
                    <div>
                        <p class="font-bold text-lg">{{ res.guest_name }}</p>
                        <p class="text-gray-500">{{ res.guest_phone }}</p>
                        <p v-if="res.guest_email" class="text-gray-500">{{ res.guest_email }}</p>
                    </div>
                </div>

                <!-- Details -->
                <div class="grid grid-cols-2 gap-4">
                    <div class="bg-gray-50 rounded-xl p-4 text-center">
                        <p class="font-bold">Стол {{ res.table?.number }}</p>
                    </div>
                    <div class="bg-gray-50 rounded-xl p-4 text-center">
                        <p class="font-bold">{{ res.guests_count }} гостей</p>
                    </div>
                </div>

                <!-- Notes -->
                <div v-if="res.notes" class="bg-yellow-50 rounded-xl p-4">
                    <p class="text-sm text-yellow-600 mb-1">Комментарий</p>
                    <p>{{ res.notes }}</p>
                </div>

                <div v-if="res.special_requests" class="bg-blue-50 rounded-xl p-4">
                    <p class="text-sm text-blue-600 mb-1">Особые пожелания</p>
                    <p>{{ res.special_requests }}</p>
                </div>

                <!-- Deposit -->
                <div v-if="res.deposit" class="bg-green-50 rounded-xl p-4 flex justify-between items-center">
                    <div>
                        <p class="text-sm text-green-600">Депозит</p>
                        <p class="font-bold text-lg">{{ store.formatMoney(res.deposit) }}</p>
                    </div>
                    <span :class="['px-3 py-1 rounded-full text-sm', res.deposit_paid ? 'bg-green-500 text-white' : 'bg-red-100 text-red-600']">
                        {{ res.deposit_paid ? '✓ Оплачен' : 'Не оплачен' }}
                    </span>
                </div>
            </div>

            <div class="px-6 py-4 border-t bg-gray-50 flex gap-2">
                <button v-if="res.status === 'pending'"
                        @click="store.updateStatus(res as any, 'confirm')"
                        class="flex-1 py-2 rounded-xl bg-green-500 text-white font-medium">✓ Подтвердить</button>

                <button v-if="res.status === 'confirmed'"
                        @click="store.updateStatus(res as any, 'seat')"
                        class="flex-1 py-2 rounded-xl bg-blue-500 text-white font-medium">Гости сели</button>

                <button v-if="['pending', 'confirmed'].includes(res.status)"
                        @click="openPreorder"
                        class="flex-1 py-2 rounded-xl bg-purple-500 text-white font-medium">Предзаказ</button>

                <button v-if="res.status === 'seated'"
                        @click="store.updateStatus(res as any, 'complete')"
                        class="flex-1 py-2 rounded-xl bg-gray-700 text-white font-medium">✓ Завершить</button>

                <button @click="edit" class="px-4 py-2 rounded-xl bg-gray-200">✏️</button>
                <button @click="call" class="px-4 py-2 rounded-xl bg-gray-200">📞</button>

                <button v-if="!['completed', 'cancelled'].includes(res.status)"
                        @click="cancel"
                        class="px-4 py-2 rounded-xl bg-red-100 text-red-600">✗</button>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useReservationsStore } from '../stores/reservations';

const store = useReservationsStore();
const res = computed(() => store.selectedReservation as Record<string, any> | null);

const statusHeaderBg = computed(() => {
    return ({ pending: 'bg-yellow-500', confirmed: 'bg-green-500', seated: 'bg-blue-500', completed: 'bg-gray-600', cancelled: 'bg-red-500' } as Record<string, string>)[res.value!.status] || 'bg-gray-500';
});

function close() {
    store.selectedReservation = null;
}

function edit() {
    store.editingReservation = res.value as any;
    store.selectedReservation = null;
    store.showModal = true;
}

function call() {
    window.open(`tel:${res.value!.guest_phone}`);
}

async function cancel() {
    if (confirm('Отменить бронирование?')) {
        await store.updateStatus(res.value as any, 'cancel');
    }
}

async function openPreorder() {
    store.preorderReservation = res.value as any;
    store.preorderCart = [];
    await store.loadPreorderItems(res.value!.id);
    store.preorderCart = store.preorderItems.map((item: any) => ({
        id: item.id,
        dish_id: item.dish_id,
        name: item.name,
        price: item.price,
        quantity: item.quantity,
        isExisting: true
    }));
    await store.loadMenuCategories();
    store.showPreorderModal = true;
}
</script>
