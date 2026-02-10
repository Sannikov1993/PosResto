<template>
    <div class="fixed inset-0 z-50 bg-black/50" @click.self="close">
        <div class="absolute inset-x-0 bottom-0 bg-white rounded-t-2xl max-h-[90vh] overflow-y-auto animate-slide-up">
            <!-- Header -->
            <div class="sticky top-0 bg-white border-b border-gray-100 px-4 py-3 flex items-center justify-between">
                <div>
                    <h2 class="font-semibold text-lg">{{ order.order_number }}</h2>
                    <span :class="['px-2 py-0.5 text-xs rounded-full text-white', store.getStatusClass(order.delivery_status)]">
                        {{ store.getStatusLabel(order.delivery_status) }}
                    </span>
                </div>
                <button @click="close" class="p-2 hover:bg-gray-100 rounded-full">
                    <svg class="w-6 h-6 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <!-- Content -->
            <div class="p-4 space-y-4">
                <!-- Customer -->
                <div class="bg-gray-50 rounded-xl p-4">
                    <h3 class="font-medium text-gray-800 mb-2">Клиент</h3>
                    <p class="text-gray-600">{{ order.customer_name }}</p>
                    <a :href="'tel:' + order.customer_phone" class="text-purple-600 font-medium">
                        {{ order.customer_phone }}
                    </a>
                </div>

                <!-- Address -->
                <div class="bg-gray-50 rounded-xl p-4">
                    <h3 class="font-medium text-gray-800 mb-2">Адрес доставки</h3>
                    <p class="text-gray-600 mb-2">{{ store.formatFullAddress(order as any) }}</p>
                    <div v-if="order.address_comment" class="text-sm text-gray-500 italic">
                        {{ order.address_comment }}
                    </div>
                    <button @click="navigateTo"
                            class="mt-3 w-full py-2.5 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700 transition-colors flex items-center justify-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
                        </svg>
                        Открыть в картах
                    </button>
                </div>

                <!-- Items -->
                <div class="bg-gray-50 rounded-xl p-4">
                    <h3 class="font-medium text-gray-800 mb-2">Состав заказа</h3>
                    <div class="space-y-2">
                        <div v-for="item in order.items" :key="item.id" class="flex justify-between">
                            <div>
                                <span class="text-gray-800">{{ item.product_name }}</span>
                                <span class="text-gray-500 text-sm"> x{{ item.quantity }}</span>
                                <div v-if="item.modifiers?.length" class="text-xs text-gray-400">
                                    {{ item.modifiers.map((m: any) => m.name).join(', ') }}
                                </div>
                            </div>
                            <span class="text-gray-600">{{ store.formatMoney(item.total) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Payment -->
                <div class="bg-gray-50 rounded-xl p-4">
                    <h3 class="font-medium text-gray-800 mb-2">Оплата</h3>
                    <div class="flex justify-between mb-1">
                        <span class="text-gray-600">Способ оплаты</span>
                        <span class="text-gray-800">{{ store.formatPaymentMethod(order.payment_method as any) }}</span>
                    </div>
                    <div v-if="order.payment_method === 'cash' && order.change_from" class="flex justify-between mb-1">
                        <span class="text-gray-600">Сдача с</span>
                        <span class="text-gray-800">{{ store.formatMoney(order.change_from as any) }}</span>
                    </div>
                    <div class="flex justify-between pt-2 border-t border-gray-200 mt-2">
                        <span class="font-medium text-gray-800">Итого</span>
                        <span class="font-bold text-lg text-gray-800">{{ store.formatMoney(order.total as any) }}</span>
                    </div>
                </div>

                <!-- Customer comment -->
                <div v-if="order.customer_comment" class="bg-yellow-50 rounded-xl p-4">
                    <h3 class="font-medium text-yellow-800 mb-1">Комментарий клиента</h3>
                    <p class="text-yellow-700">{{ order.customer_comment }}</p>
                </div>
            </div>

            <!-- Actions -->
            <div class="sticky bottom-0 bg-white border-t border-gray-100 p-4 safe-bottom">
                <!-- Status actions for assigned orders -->
                <div v-if="order.courier_id === store.courierId" class="space-y-2">
                    <button v-if="order.delivery_status === 'ready'"
                            @click="updateStatus('picked_up')"
                            :disabled="store.isLoading"
                            class="w-full py-3 bg-yellow-500 text-white rounded-xl font-semibold hover:bg-yellow-600 disabled:opacity-50 transition-colors">
                        Забрал заказ
                    </button>
                    <button v-if="order.delivery_status === 'picked_up'"
                            @click="updateStatus('in_transit')"
                            :disabled="store.isLoading"
                            class="w-full py-3 bg-purple-600 text-white rounded-xl font-semibold hover:bg-purple-700 disabled:opacity-50 transition-colors">
                        В пути к клиенту
                    </button>
                    <button v-if="['picked_up', 'in_transit', 'delivering'].includes(order.delivery_status)"
                            @click="updateStatus('completed')"
                            :disabled="store.isLoading"
                            class="w-full py-3 bg-green-600 text-white rounded-xl font-semibold hover:bg-green-700 disabled:opacity-50 transition-colors">
                        Доставлено
                    </button>
                    <button v-if="['picked_up', 'in_transit', 'delivering'].includes(order.delivery_status)"
                            @click="openProblemModal"
                            class="w-full py-3 bg-orange-100 text-orange-600 rounded-xl font-medium hover:bg-orange-200 transition-colors flex items-center justify-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        Проблема с доставкой
                    </button>
                    <button v-if="!['completed', 'cancelled'].includes(order.delivery_status)"
                            @click="showCancelModal = true"
                            class="w-full py-3 bg-gray-100 text-red-600 rounded-xl font-medium hover:bg-gray-200 transition-colors">
                        Отменить заказ
                    </button>
                </div>

                <!-- Accept order -->
                <div v-else-if="!order.courier_id && order.delivery_status === 'ready'">
                    <button @click="acceptOrder"
                            :disabled="store.isLoading"
                            class="w-full py-3 bg-purple-600 text-white rounded-xl font-semibold hover:bg-purple-700 disabled:opacity-50 transition-colors">
                        Взять заказ
                    </button>
                </div>
            </div>
        </div>

        <!-- Cancel Modal -->
        <div v-if="showCancelModal" class="fixed inset-0 z-[60] bg-black/50 flex items-center justify-center p-4" @click.self="showCancelModal = false">
            <div class="bg-white rounded-2xl w-full max-w-sm p-4">
                <h3 class="font-semibold text-lg text-gray-800 mb-4">Отмена заказа</h3>
                <textarea v-model="cancelReason" rows="3"
                          placeholder="Укажите причину отмены..."
                          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent resize-none"></textarea>
                <div class="flex gap-3 mt-4">
                    <button @click="showCancelModal = false"
                            class="flex-1 py-2.5 bg-gray-100 text-gray-700 rounded-lg font-medium hover:bg-gray-200 transition-colors">
                        Назад
                    </button>
                    <button @click="cancelOrder" :disabled="store.isLoading"
                            class="flex-1 py-2.5 bg-red-600 text-white rounded-lg font-medium hover:bg-red-700 disabled:opacity-50 transition-colors">
                        Отменить
                    </button>
                </div>
            </div>
        </div>

        <!-- Problem Report Modal -->
        <ProblemReportModal
            v-if="showProblemModal"
            :order="order"
            @close="showProblemModal = false"
            @submitted="handleProblemSubmitted"
        />
    </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';
import { useCourierStore } from '../stores/courier';
import ProblemReportModal from './ProblemReportModal.vue';

const store = useCourierStore();

const showCancelModal = ref(false);
const cancelReason = ref('');
const showProblemModal = ref(false);

const order = computed(() => store.selectedOrder as any);

function close() {
    store.selectedOrder = null;
    showCancelModal.value = false;
    cancelReason.value = '';
    showProblemModal.value = false;
}

function openProblemModal() {
    showProblemModal.value = true;
}

function handleProblemSubmitted(problem: any) {
    showProblemModal.value = false;
    window.$toast?.('Проблема отправлена', 'success');
}

function navigateTo() {
    const address = store.formatFullAddress(order.value as any);
    const lat = order.value!.delivery_lat;
    const lng = order.value!.delivery_lng;

    let url;
    if (lat && lng) {
        url = `https://yandex.ru/maps/?pt=${lng},${lat}&z=17&l=map`;
    } else {
        url = `https://yandex.ru/maps/?text=${encodeURIComponent(address)}`;
    }

    window.open(url, '_blank');
}

async function acceptOrder() {
    const result = await store.acceptOrder(order.value as any);
    if (result.success) {
        close();
    }
}

async function updateStatus(status: any) {
    await store.updateOrderStatus(order.value as any, status);
    if (status === 'completed') {
        close();
    }
}

async function cancelOrder() {
    const result = await store.cancelOrder(order.value as any, cancelReason.value);
    if (result.success) {
        close();
    }
}
</script>

<style scoped>
@keyframes slide-up {
    from { transform: translateY(100%); }
    to { transform: translateY(0); }
}
.animate-slide-up {
    animation: slide-up 0.3s ease-out;
}
.safe-bottom { padding-bottom: env(safe-area-inset-bottom, 16px); }
</style>
