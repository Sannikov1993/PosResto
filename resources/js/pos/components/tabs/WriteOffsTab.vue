<template>
    <div class="h-full flex flex-col" data-testid="writeoffs-tab">
        <!-- Header -->
        <div class="flex items-center gap-4 px-4 py-3 border-b border-gray-800 bg-dark-900">
            <h1 class="text-lg font-semibold">Списания</h1>
            <div class="flex gap-2" data-testid="writeoffs-tabs">
                <button
                    v-for="tab in tabs"
                    :key="tab.value"
                    @click="activeTab = tab.value"
                    :class="[
                        'px-3 py-1 rounded-lg text-sm',
                        activeTab === tab.value ? 'bg-accent text-white' : 'bg-dark-800 text-gray-400'
                    ]"
                    :data-testid="`writeoffs-tab-${tab.value}`"
                >
                    {{ tab.label }}
                    <span v-if="tab.value === 'pending' && pendingCount > 0" class="ml-1 px-1.5 py-0.5 bg-red-500 rounded-full text-xs" data-testid="pending-count">
                        {{ pendingCount }}
                    </span>
                </button>
            </div>
            <button
                v-can="'inventory.write_off'"
                @click="openWriteOffModal"
                class="ml-auto px-4 py-2 bg-red-600 hover:bg-red-700 rounded-lg text-sm text-white"
                data-testid="new-writeoff-btn"
            >
                + Новое списание
            </button>
        </div>

        <div class="flex-1 overflow-y-auto p-4">
            <!-- Loading state -->
            <div v-if="loading" class="flex flex-col items-center justify-center h-full text-gray-500">
                <div class="animate-spin w-8 h-8 border-4 border-accent border-t-transparent rounded-full mb-4"></div>
                <p>Загрузка...</p>
            </div>

            <!-- Pending Cancellations -->
            <template v-else-if="activeTab === 'pending'">
                <div v-if="pendingCancellations.length === 0" class="flex flex-col items-center justify-center h-full text-gray-500">
                    <p class="text-4xl mb-4">✓</p>
                    <p>Нет заявок на отмену</p>
                    <p class="text-sm mt-2">Все заявки обработаны</p>
                </div>

                <div v-else class="space-y-3" data-testid="pending-cancellations-list">
                    <div
                        v-for="item in pendingCancellations"
                        :key="item.id"
                        class="bg-dark-800 rounded-lg p-4"
                        :data-testid="`pending-cancellation-${item.id}`"
                    >
                        <!-- Заголовок: разный для заказа и позиции -->
                        <div class="flex items-center justify-between mb-2">
                            <div class="flex items-center gap-3">
                                <span v-if="item.type === 'item'" class="px-2 py-0.5 bg-orange-500/20 text-orange-400 rounded text-xs">Позиция</span>
                                <span v-else class="px-2 py-0.5 bg-red-500/20 text-red-400 rounded text-xs">Заказ</span>
                                <span class="font-medium">
                                    {{ item.type === 'item' ? item.item?.name : `Заказ #${item.order?.order_number}` }}
                                </span>
                                <span class="text-accent text-sm">
                                    {{ item.type === 'item' ? formatPrice(item.item?.price * item.item?.quantity) : formatPrice(item.order?.total) }} ₽
                                </span>
                            </div>
                            <span class="text-sm text-gray-400">{{ formatDateTime(item.created_at) }}</span>
                        </div>

                        <!-- Информация о заказе для позиции -->
                        <p v-if="item.type === 'item'" class="text-xs text-gray-500 mb-2">
                            Заказ #{{ item.order?.order_number }} • Стол {{ item.order?.table?.number || '—' }}
                        </p>

                        <p class="text-sm text-gray-400 mb-2">
                            <span class="text-gray-500">Причина:</span> {{ item.reason || 'Не указана' }}
                        </p>
                        <p v-if="item.requested_by" class="text-xs text-gray-500 mb-3">
                            Запросил: {{ item.requested_by }}
                        </p>

                        <!-- Order items preview (только для отмены заказа) -->
                        <div v-if="item.type !== 'item' && item.order?.items?.length" class="bg-dark-900 rounded-lg p-3 mb-3">
                            <p class="text-xs text-gray-500 mb-2">Позиции заказа:</p>
                            <div class="space-y-1">
                                <div v-for="orderItem in item.order.items.slice(0, 3)" :key="orderItem.id" class="text-sm flex justify-between">
                                    <span class="text-gray-400">{{ orderItem.name }} x{{ orderItem.quantity }}</span>
                                    <span>{{ formatPrice(orderItem.price * orderItem.quantity) }} ₽</span>
                                </div>
                                <p v-if="item.order.items.length > 3" class="text-xs text-gray-500">
                                    ... и ещё {{ item.order.items.length - 3 }} позиций
                                </p>
                            </div>
                        </div>

                        <div class="flex gap-2">
                            <button
                                v-can="'orders.cancel'"
                                @click="approveCancellation(item)"
                                :disabled="processingId === item.id"
                                class="px-4 py-2 bg-green-600 hover:bg-green-700 rounded-lg text-sm text-white disabled:opacity-50"
                            >
                                {{ processingId === item.id ? 'Обработка...' : 'Подтвердить' }}
                            </button>
                            <button
                                v-can="'orders.cancel'"
                                @click="showRejectModal(item)"
                                :disabled="processingId === item.id"
                                class="px-4 py-2 bg-red-600 hover:bg-red-700 rounded-lg text-sm text-white disabled:opacity-50"
                            >
                                Отклонить
                            </button>
                        </div>
                    </div>
                </div>
            </template>

            <!-- Write-offs History -->
            <template v-else>
                <!-- Filter by date -->
                <div class="flex items-center gap-4 mb-4" data-testid="writeoffs-filter">
                    <div class="flex items-center gap-2">
                        <label class="text-sm text-gray-400">С:</label>
                        <input
                            v-model="dateFrom"
                            type="date"
                            class="bg-dark-800 border border-gray-700 rounded-lg px-3 py-1.5 text-sm"
                            data-testid="writeoffs-date-from"
                        />
                    </div>
                    <div class="flex items-center gap-2">
                        <label class="text-sm text-gray-400">По:</label>
                        <input
                            v-model="dateTo"
                            type="date"
                            class="bg-dark-800 border border-gray-700 rounded-lg px-3 py-1.5 text-sm"
                            data-testid="writeoffs-date-to"
                        />
                    </div>
                    <button
                        @click="loadWriteOffs"
                        class="px-3 py-1.5 bg-dark-800 hover:bg-dark-700 rounded-lg text-sm text-gray-400"
                        data-testid="writeoffs-apply-filter"
                    >
                        Применить
                    </button>
                </div>

                <!-- Total -->
                <div v-if="writeOffs.length > 0" class="bg-dark-800 rounded-lg p-4 mb-4">
                    <div class="flex justify-between items-center">
                        <span class="text-gray-400">Всего списаний за период:</span>
                        <span class="text-xl font-bold text-red-400">-{{ formatPrice(totalWriteOffs) }} ₽</span>
                    </div>
                </div>

                <div v-if="writeOffs.length === 0" class="flex flex-col items-center justify-center h-64 text-gray-500">
                    <p class="text-4xl mb-4">📝</p>
                    <p>Нет списаний за выбранный период</p>
                </div>

                <div v-else class="space-y-2" data-testid="writeoffs-list">
                    <div
                        v-for="item in writeOffs"
                        :key="item.id"
                        class="flex items-center gap-4 px-4 py-3 bg-dark-800 rounded-lg hover:bg-dark-700/50 cursor-pointer"
                        @click="showWriteOffDetail(item)"
                        :data-testid="`writeoff-item-${item.id}`"
                    >
                        <div class="w-10 h-10 rounded-lg flex items-center justify-center"
                             :class="getTypeClass(item.type)">
                            {{ getTypeIcon(item.type) }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="font-medium">{{ item.description || getTypeLabel(item.type) }}</p>
                            <p class="text-xs text-gray-500">
                                {{ item.user?.name || 'Система' }} • {{ formatDateTime(item.created_at) }}
                            </p>
                        </div>
                        <span class="text-red-400 font-medium">-{{ formatPrice(item.amount) }} ₽</span>
                    </div>
                </div>
            </template>
        </div>

        <!-- Reject Cancellation Modal -->
        <Teleport to="body">
            <div v-if="showRejectModalFlag" class="fixed inset-0 bg-black/70 flex items-center justify-center z-50">
                <div class="bg-dark-900 rounded-xl w-full max-w-md">
                    <div class="flex items-center justify-between p-4 border-b border-gray-800">
                        <h2 class="text-lg font-semibold">Отклонить заявку</h2>
                        <button @click="closeRejectModal" class="text-gray-400 hover:text-white">✕</button>
                    </div>
                    <div class="p-4">
                        <p class="text-sm text-gray-400 mb-4">
                            Укажите причину отклонения заявки на отмену заказа #{{ rejectingItem?.order?.order_number }}
                        </p>
                        <textarea
                            v-model="rejectReason"
                            class="w-full bg-dark-800 border border-gray-700 rounded-lg px-3 py-2 h-24"
                            placeholder="Причина отклонения..."
                        ></textarea>
                    </div>
                    <div class="flex gap-3 p-4 border-t border-gray-800">
                        <button
                            @click="closeRejectModal"
                            class="flex-1 py-2 bg-dark-800 text-gray-400 rounded-lg hover:bg-dark-700"
                        >
                            Отмена
                        </button>
                        <button
                            @click="confirmReject"
                            :disabled="!rejectReason.trim()"
                            class="flex-1 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 disabled:opacity-50"
                        >
                            Отклонить
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- New Write-off Modal (Enhanced) -->
        <WriteOffModal
            v-model="showWriteOffModalFlag"
            @created="onWriteOffCreated"
        />

        <!-- Write-off Detail Modal -->
        <Teleport to="body">
            <div v-if="selectedWriteOff" class="fixed inset-0 bg-black/70 flex items-center justify-center z-50">
                <div class="bg-dark-900 rounded-xl w-full max-w-md max-h-[90vh] overflow-y-auto">
                    <div class="flex items-center justify-between p-4 border-b border-gray-800 sticky top-0 bg-dark-900 z-10">
                        <h2 class="text-lg font-semibold">Детали списания</h2>
                        <button @click="selectedWriteOff = null" class="text-gray-400 hover:text-white">✕</button>
                    </div>
                    <div class="p-4 space-y-4">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 rounded-lg flex items-center justify-center text-2xl"
                                 :class="getTypeClass(selectedWriteOff.type)">
                                {{ getTypeIcon(selectedWriteOff.type) }}
                            </div>
                            <div>
                                <p class="font-medium">{{ getTypeLabel(selectedWriteOff.type) }}</p>
                                <p class="text-2xl font-bold text-red-400">-{{ formatPrice(selectedWriteOff.amount) }} ₽</p>
                            </div>
                        </div>

                        <div class="bg-dark-800 rounded-lg p-4 space-y-3">
                            <div>
                                <p class="text-xs text-gray-500">Причина</p>
                                <p class="text-sm">{{ selectedWriteOff.description || selectedWriteOff.reason || 'Не указана' }}</p>
                            </div>
                            <div v-if="selectedWriteOff.order">
                                <p class="text-xs text-gray-500">Связанный заказ</p>
                                <p class="text-sm">#{{ selectedWriteOff.order.order_number }}
                                    <span v-if="selectedWriteOff.order.table" class="text-gray-500">
                                        • Стол {{ selectedWriteOff.order.table.number }}
                                    </span>
                                </p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Создал</p>
                                <p class="text-sm">{{ selectedWriteOff.user?.name || selectedWriteOff.cancelled_by || 'Система' }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Дата и время</p>
                                <p class="text-sm">{{ formatFullDateTime(selectedWriteOff.created_at) }}</p>
                            </div>
                        </div>

                        <!-- Позиции заказа (для отмены заказа) -->
                        <div v-if="selectedWriteOff.order?.items?.length" class="bg-dark-800 rounded-lg p-4">
                            <p class="text-xs text-gray-500 mb-3">Позиции заказа</p>
                            <div class="space-y-2">
                                <div v-for="item in selectedWriteOff.order.items" :key="item.id"
                                     class="flex justify-between items-center py-2 border-b border-gray-700 last:border-0">
                                    <div>
                                        <p class="text-sm text-white">{{ item.name }}</p>
                                        <p class="text-xs text-gray-500">{{ item.quantity }} × {{ formatPrice(item.price) }} ₽</p>
                                    </div>
                                    <p class="text-sm font-medium">{{ formatPrice(item.total || item.price * item.quantity) }} ₽</p>
                                </div>
                            </div>
                            <div class="mt-3 pt-3 border-t border-gray-700 flex justify-between">
                                <span class="text-gray-400">Итого:</span>
                                <span class="font-bold text-red-400">{{ formatPrice(selectedWriteOff.amount) }} ₽</span>
                            </div>
                        </div>

                        <!-- Отменённая позиция (для item_cancellation) -->
                        <div v-else-if="selectedWriteOff.item" class="bg-dark-800 rounded-lg p-4">
                            <p class="text-xs text-gray-500 mb-3">Отменённая позиция</p>
                            <div class="flex justify-between items-center">
                                <div>
                                    <p class="text-sm text-white">{{ selectedWriteOff.item.name || selectedWriteOff.item_name }}</p>
                                    <p class="text-xs text-gray-500">{{ selectedWriteOff.item.quantity || selectedWriteOff.quantity }} × {{ formatPrice(selectedWriteOff.item.price) }} ₽</p>
                                </div>
                                <p class="text-sm font-medium text-red-400">{{ formatPrice(selectedWriteOff.amount) }} ₽</p>
                            </div>
                        </div>

                        <!-- Позиции списания (новая система) -->
                        <div v-else-if="selectedWriteOff.items?.length" class="bg-dark-800 rounded-lg p-4">
                            <p class="text-xs text-gray-500 mb-3">Позиции списания</p>
                            <div class="space-y-2">
                                <div v-for="item in selectedWriteOff.items" :key="item.id"
                                     class="flex justify-between items-center py-2 border-b border-gray-700 last:border-0">
                                    <div>
                                        <p class="text-sm text-white">{{ item.name }}</p>
                                        <p class="text-xs text-gray-500">{{ item.quantity }} × {{ formatPrice(item.unit_price) }} ₽</p>
                                    </div>
                                    <p class="text-sm font-medium">{{ formatPrice(item.total_price) }} ₽</p>
                                </div>
                            </div>
                            <div class="mt-3 pt-3 border-t border-gray-700 flex justify-between">
                                <span class="text-gray-400">Итого:</span>
                                <span class="font-bold text-red-400">{{ formatPrice(selectedWriteOff.amount) }} ₽</span>
                            </div>
                        </div>

                        <!-- Фото списания -->
                        <div v-if="selectedWriteOff.photo_url" class="bg-dark-800 rounded-lg p-4">
                            <p class="text-xs text-gray-500 mb-3">Фото</p>
                            <img :src="selectedWriteOff.photo_url"
                                 alt="Фото списания"
                                 class="w-full rounded-lg object-cover max-h-48" />
                        </div>
                    </div>
                    <div class="p-4 border-t border-gray-800">
                        <button
                            @click="selectedWriteOff = null"
                            class="w-full py-2 bg-dark-800 text-gray-400 rounded-lg hover:bg-dark-700"
                        >
                            Закрыть
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { usePosStore } from '../../stores/pos';
import api from '../../api';
import WriteOffModal from '../modals/WriteOffModal.vue';

const posStore = usePosStore();

// State
const activeTab = ref('pending');
const loading = ref(false);
const processingId = ref(null);

// Date filters for history
const dateFrom = ref(getDefaultDateFrom());
const dateTo = ref(getDefaultDateTo());

// Modals
const showRejectModalFlag = ref(false);
const showWriteOffModalFlag = ref(false);
const rejectingItem = ref(null);
const rejectReason = ref('');
const selectedWriteOff = ref(null);

const tabs = [
    { value: 'pending', label: 'Ожидают' },
    { value: 'history', label: 'История' }
];

// Computed
const pendingCancellations = computed(() => posStore.pendingCancellations);
const writeOffs = computed(() => posStore.writeOffs);
const pendingCount = computed(() => pendingCancellations.value.length);

const totalWriteOffs = computed(() => {
    return writeOffs.value.reduce((sum, item) => sum + Number(item.amount || 0), 0);
});

// Helper functions
function getDefaultDateFrom() {
    const date = new Date();
    date.setDate(date.getDate() - 7);
    return date.toISOString().slice(0, 10);
}

function getDefaultDateTo() {
    return new Date().toISOString().slice(0, 10);
}

const formatDateTime = (dt) => {
    if (!dt) return '';
    return new Date(dt).toLocaleString('ru-RU', {
        day: '2-digit',
        month: '2-digit',
        hour: '2-digit',
        minute: '2-digit'
    });
};

const formatFullDateTime = (dt) => {
    if (!dt) return '';
    return new Date(dt).toLocaleString('ru-RU', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit'
    });
};

const formatPrice = (price) => {
    return Number(price || 0).toLocaleString('ru-RU');
};

const getTypeIcon = (type) => {
    const icons = {
        spoilage: '🗑️',
        expired: '⏰',
        loss: '❓',
        staff_meal: '🍽️',
        promo: '🎁',
        cancellation: '❌',
        item_cancellation: '🍽️',
        order: '❌',
        item: '🍽️',
        other: '📝'
    };
    return icons[type] || '📝';
};

const getTypeLabel = (type) => {
    const labels = {
        spoilage: 'Порча продукта',
        expired: 'Истек срок годности',
        loss: 'Потеря/недостача',
        staff_meal: 'Питание персонала',
        promo: 'Промо/дегустация',
        cancellation: 'Отмена заказа',
        item_cancellation: 'Отмена позиции',
        order: 'Отмена заказа',
        item: 'Отмена позиции',
        other: 'Прочее'
    };
    return labels[type] || 'Списание';
};

const getTypeClass = (type) => {
    const classes = {
        spoilage: 'bg-red-600/20 text-red-400',
        expired: 'bg-orange-600/20 text-orange-400',
        loss: 'bg-yellow-600/20 text-yellow-400',
        staff_meal: 'bg-blue-600/20 text-blue-400',
        promo: 'bg-purple-600/20 text-purple-400',
        cancellation: 'bg-red-600/20 text-red-400',
        item_cancellation: 'bg-orange-600/20 text-orange-400',
        order: 'bg-red-600/20 text-red-400',
        item: 'bg-orange-600/20 text-orange-400',
        other: 'bg-gray-600/20 text-gray-400'
    };
    return classes[type] || 'bg-gray-600/20 text-gray-400';
};

// Methods
const loadWriteOffs = async () => {
    try {
        await posStore.loadWriteOffs(dateFrom.value, dateTo.value);
    } catch (error) {
        console.error('Error loading write-offs:', error);
        window.$toast?.('Ошибка загрузки списаний', 'error');
    }
};

const approveCancellation = async (item) => {
    const isItem = item.type === 'item';
    const confirmText = isItem
        ? `Подтвердить отмену позиции "${item.item?.name}"? Сумма ${formatPrice(item.item?.price * item.item?.quantity)} ₽ будет списана.`
        : `Подтвердить отмену заказа #${item.order?.order_number}? Сумма ${formatPrice(item.order?.total)} ₽ будет списана.`;

    if (!confirm(confirmText)) {
        return;
    }

    processingId.value = item.id;
    try {
        if (isItem) {
            // Отмена позиции
            await api.orderItems.approveCancellation(item.item.id);
        } else {
            // Отмена заказа
            await api.cancellations.approve(item.id);
        }
        window.$toast?.('Отмена подтверждена', 'success');
        await posStore.loadPendingCancellations();
        await loadWriteOffs();
    } catch (error) {
        console.error('Error approving cancellation:', error);
        const message = error.response?.data?.message || 'Ошибка при подтверждении';
        window.$toast?.(message, 'error');
    } finally {
        processingId.value = null;
    }
};

const showRejectModal = (item) => {
    rejectingItem.value = item;
    rejectReason.value = '';
    showRejectModalFlag.value = true;
};

const closeRejectModal = () => {
    showRejectModalFlag.value = false;
    rejectingItem.value = null;
    rejectReason.value = '';
};

const confirmReject = async () => {
    if (!rejectingItem.value || !rejectReason.value.trim()) return;

    const isItem = rejectingItem.value.type === 'item';
    processingId.value = rejectingItem.value.id;
    try {
        if (isItem) {
            // Отклонение отмены позиции
            await api.orderItems.rejectCancellation(rejectingItem.value.item.id, rejectReason.value);
        } else {
            // Отклонение отмены заказа
            await api.cancellations.reject(rejectingItem.value.id, rejectReason.value);
        }
        window.$toast?.('Заявка отклонена', 'success');
        closeRejectModal();
        await posStore.loadPendingCancellations();
    } catch (error) {
        console.error('Error rejecting cancellation:', error);
        const message = error.response?.data?.message || 'Ошибка при отклонении';
        window.$toast?.(message, 'error');
    } finally {
        processingId.value = null;
    }
};

const openWriteOffModal = () => {
    showWriteOffModalFlag.value = true;
};

const onWriteOffCreated = async () => {
    await loadWriteOffs();
};

const showWriteOffDetail = (item) => {
    selectedWriteOff.value = item;
};

// Lifecycle
onMounted(async () => {
    loading.value = true;
    try {
        await Promise.all([
            posStore.loadPendingCancellations(),
            posStore.loadWriteOffs(dateFrom.value, dateTo.value)
        ]);
    } finally {
        loading.value = false;
    }
});
</script>
