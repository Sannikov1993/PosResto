<template>
    <div class="min-h-screen bg-gray-100">
        <!-- Header -->
        <header class="bg-white shadow-sm px-6 py-4 flex items-center justify-between sticky top-0 z-50">
            <div class="flex items-center gap-4">
                <h1 class="text-2xl font-bold text-gray-800">Bронирование столов</h1>
                <a href="/backoffice-vue" class="text-gray-500 hover:text-orange-500 text-sm">← Назад</a>
            </div>
            <div class="flex items-center gap-4">
                <div class="flex gap-3">
                    <div class="bg-yellow-100 px-4 py-2 rounded-xl">
                        <p class="text-xs text-yellow-600">Ожидают</p>
                        <p class="text-xl font-bold text-yellow-600">{{ store.stats.today?.pending || 0 }}</p>
                    </div>
                    <div class="bg-green-100 px-4 py-2 rounded-xl">
                        <p class="text-xs text-green-600">Подтверждено</p>
                        <p class="text-xl font-bold text-green-600">{{ store.stats.today?.confirmed || 0 }}</p>
                    </div>
                    <div class="bg-blue-100 px-4 py-2 rounded-xl">
                        <p class="text-xs text-blue-600">Гостей сегодня</p>
                        <p class="text-xl font-bold text-blue-600">{{ store.stats.today?.total_guests || 0 }}</p>
                    </div>
                </div>
                <button @click="openNewReservation" class="bg-orange-500 text-white px-4 py-2 rounded-lg font-medium hover:bg-orange-600">
                    + Новое бронирование
                </button>
            </div>
        </header>

        <main class="p-6">
            <div class="flex gap-6">
                <!-- Calendar Sidebar -->
                <CalendarPanel />

                <!-- Reservations List -->
                <div class="flex-1">
                    <div class="bg-white rounded-xl shadow-sm">
                        <div class="px-6 py-4 border-b flex justify-between items-center">
                            <h3 class="font-bold text-lg">{{ store.formatDateFull(store.selectedDate) }}</h3>
                            <div class="flex gap-2">
                                <button @click="store.viewMode = 'list'"
                                        :class="['px-3 py-1 rounded-lg text-sm', store.viewMode === 'list' ? 'bg-gray-200' : 'hover:bg-gray-100']">
                                    Список
                                </button>
                                <button @click="store.viewMode = 'timeline'"
                                        :class="['px-3 py-1 rounded-lg text-sm', store.viewMode === 'timeline' ? 'bg-gray-200' : 'hover:bg-gray-100']">
                                    Таймлайн
                                </button>
                            </div>
                        </div>

                        <!-- List View -->
                        <ReservationsList v-if="store.viewMode === 'list'" />

                        <!-- Timeline View -->
                        <TimelineView v-else />
                    </div>
                </div>
            </div>
        </main>

        <!-- Modals -->
        <ReservationModal v-if="store.showModal" />
        <ReservationDetail v-if="store.selectedReservation" />
        <PreorderModal v-if="store.showPreorderModal" />

        <!-- Toast -->
        <div v-if="store.toast"
             :class="['fixed bottom-6 right-6 px-6 py-3 rounded-xl shadow-lg z-50',
                      store.toast.type === 'success' ? 'bg-green-500 text-white' : 'bg-red-500 text-white']">
            {{ store.toast.message }}
        </div>
    </div>
</template>

<script setup>
import { onMounted } from 'vue';
import { useReservationsStore } from './stores/reservations';
import CalendarPanel from './components/CalendarPanel.vue';
import ReservationsList from './components/ReservationsList.vue';
import TimelineView from './components/TimelineView.vue';
import ReservationModal from './components/ReservationModal.vue';
import ReservationDetail from './components/ReservationDetail.vue';
import PreorderModal from './components/PreorderModal.vue';

const store = useReservationsStore();

function openNewReservation() {
    store.editingReservation = null;
    store.showModal = true;
}

onMounted(async () => {
    // Сначала загружаем "рабочую дату" (учитывает работу после полуночи)
    await store.loadBusinessDate();

    store.loadCalendar();
    store.loadReservations();
    store.loadTables();
    store.loadStats();
});
</script>
