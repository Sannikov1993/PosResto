<template>
    <Teleport to="body">
        <div v-if="modelValue" class="fixed inset-0 bg-black/80 z-50 flex items-center justify-center p-4" @click.self="close">
            <div data-testid="cancel-order-modal" class="bg-gray-900 rounded-2xl w-[420px] max-h-[90vh] overflow-y-auto">
                <!-- Header -->
                <div class="p-4 border-b border-gray-800 flex items-center justify-between sticky top-0 bg-gray-900 z-10">
                    <div class="flex items-center gap-3">
                        <button v-if="mode && !canCancelOrders" @click="mode = null" class="text-gray-400 hover:text-white">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                            </svg>
                        </button>
                        <div class="w-10 h-10 bg-red-500/20 rounded-xl flex items-center justify-center">
                            <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                        </div>
                        <h3 class="text-white font-semibold">Удаление заказа</h3>
                    </div>
                    <button @click="close" class="text-gray-500 hover:text-white text-xl">&times;</button>
                </div>

                <!-- Order info -->
                <div class="p-4 bg-red-500/10 border-b border-red-500/30">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-white font-semibold text-lg">
                            <template v-if="ordersCount > 1">
                                {{ ordersCount }} заказа на столе
                            </template>
                            <template v-else>
                                Заказ #{{ firstOrder?.order_number }}
                            </template>
                        </span>
                        <span class="text-blue-500 font-bold">{{ formatPrice(orderTotal) }}</span>
                    </div>
                    <div class="flex items-center gap-4 text-sm">
                        <span class="text-gray-400">Позиций: <span class="text-white">{{ itemsCount }}</span></span>
                        <span class="text-gray-400">Стол: <span class="text-white">{{ table?.number }}</span></span>
                        <span v-if="ordersCount > 1" class="text-orange-400">Все заказы будут отменены</span>
                    </div>
                    <p v-if="hasSentItems" class="text-red-400 text-xs mt-2">
                        ⚠ Есть блюда на кухне! Продукты будут списаны.
                    </p>
                </div>

                <!-- Mode selection (for non-managers) -->
                <div v-if="mode === null && !canCancelOrders" class="p-4 space-y-3">
                    <p class="text-gray-400 text-sm">Выберите способ удаления:</p>
                    <button @click="mode = 'pin'"
                        class="w-full p-4 bg-gray-800 hover:bg-gray-700 rounded-xl text-left transition-colors border border-gray-700 hover:border-orange-500">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-lg bg-orange-500/20 flex items-center justify-center">
                                <span class="text-xl">&#x1F512;</span>
                            </div>
                            <div>
                                <div class="text-white font-medium">Ввести PIN менеджера</div>
                                <div class="text-gray-500 text-sm">Удаление будет выполнено сразу</div>
                            </div>
                        </div>
                    </button>
                    <button @click="mode = 'request'"
                        class="w-full p-4 bg-gray-800 hover:bg-gray-700 rounded-xl text-left transition-colors border border-gray-700 hover:border-blue-500">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-lg bg-blue-500/20 flex items-center justify-center">
                                <span class="text-xl">&#x1F4DD;</span>
                            </div>
                            <div>
                                <div class="text-white font-medium">Отправить на списание</div>
                                <div class="text-gray-500 text-sm">После одобрения менеджером</div>
                            </div>
                        </div>
                    </button>
                </div>

                <!-- PIN input -->
                <template v-if="mode === 'pin'">
                    <div class="p-4">
                        <label class="text-gray-400 text-sm mb-2 block">PIN менеджера</label>
                        <input v-model="managerPin"
                               type="password"
                               maxlength="6"
                               placeholder="Введите PIN"
                               class="w-full bg-gray-800 border border-gray-700 rounded-xl px-4 py-3 text-white text-center text-2xl tracking-widest placeholder-gray-500 focus:border-orange-500 focus:outline-none"
                               @keyup.enter="submit">
                        <p v-if="pinError" class="text-red-400 text-sm mt-2">{{ pinError }}</p>
                    </div>
                </template>

                <!-- Reason selection -->
                <div v-if="mode" class="p-4">
                    <label class="text-gray-400 text-sm mb-2 block">Причина удаления</label>
                    <div class="space-y-2">
                        <label v-for="r in cancelReasons" :key="r.value"
                               class="flex items-center gap-3 p-3 bg-gray-800 rounded-xl cursor-pointer hover:bg-gray-700 transition-colors"
                               :class="{ 'border border-red-500 bg-red-500/10': reason === r.value }">
                            <input type="radio" v-model="reason" :value="r.value" class="hidden">
                            <span class="w-5 h-5 rounded-full border-2 flex items-center justify-center"
                                  :class="reason === r.value ? 'border-red-500 bg-red-500' : 'border-gray-600'">
                                <svg v-if="reason === r.value" class="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                </svg>
                            </span>
                            <span class="text-white">{{ r.label }}</span>
                        </label>
                    </div>
                    <textarea v-model="comment"
                              placeholder="Дополнительный комментарий (необязательно)"
                              class="w-full mt-3 bg-gray-800 border border-gray-700 rounded-xl px-4 py-3 text-white placeholder-gray-500 focus:border-gray-600 focus:outline-none resize-none"
                              rows="2"></textarea>
                </div>

                <!-- Actions -->
                <div v-if="mode" class="px-4 pb-4">
                    <button @click="submit"
                        :disabled="!reason || loading || (mode === 'pin' && managerPin.length < 4)"
                        :class="reason && !loading && (mode !== 'pin' || managerPin.length >= 4) ? 'bg-red-600 hover:bg-red-700' : 'bg-gray-700 cursor-not-allowed'"
                        class="w-full py-3.5 text-white rounded-xl font-medium transition-colors">
                        <span v-if="loading" class="flex items-center justify-center gap-2">
                            <svg class="animate-spin h-5 w-5" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Обработка...
                        </span>
                        <span v-else-if="mode === 'request'">Отправить на списание</span>
                        <span v-else>Удалить заказ</span>
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import { createLogger } from '../../shared/services/logger.js';
import api from '../../pos/api';

const log = createLogger('CancelOrder');

const props = defineProps({
    modelValue: Boolean,
    order: [Object, Array], // Может быть один заказ или массив заказов
    table: Object,
    canCancelOrders: Boolean
});

const emit = defineEmits(['update:modelValue', 'cancelled', 'requestSent']);

const mode = ref<any>(null);
const managerPin = ref('');
const pinError = ref('');
const reason = ref('');
const comment = ref('');
const loading = ref(false);
const verifiedManagerId = ref<any>(null);

const cancelReasons = [
    { value: 'guest_left', label: 'Гость ушёл' },
    { value: 'guest_refused', label: 'Гость отказался' },
    { value: 'wrong_table', label: 'Ошибка стола' },
    { value: 'duplicate', label: 'Дубль заказа' },
    { value: 'test_order', label: 'Тестовый заказ' },
    { value: 'other', label: 'Другая причина' }
];

// Нормализуем заказы в массив
const orders = computed(() => {
    if (!props.order) return [];
    return Array.isArray(props.order) ? props.order : [props.order];
});

// Количество заказов
const ordersCount = computed(() => orders.value.length);

// Первый заказ (для отображения номера)
const firstOrder = computed(() => orders.value[0]);

// Все items из всех заказов
const allItems = computed(() => {
    return orders.value.flatMap((o: any) => o.items || []);
});

const itemsCount = computed(() => {
    return allItems.value.filter((i: any) => !['cancelled', 'voided'].includes(i.status)).length;
});

const orderTotal = computed(() => {
    return allItems.value.reduce((sum: any, item: any) => {
        if (['cancelled', 'voided'].includes(item.status)) return sum;
        return sum + (item.price * item.quantity);
    }, 0);
});

const hasSentItems = computed(() => {
    return allItems.value.some((i: any) => ['cooking', 'ready', 'served'].includes(i.status));
});

// Reset when modal opens
watch(() => props.modelValue, (val) => {
    if (val) {
        mode.value = props.canCancelOrders ? 'direct' : null;
        managerPin.value = '';
        pinError.value = '';
        reason.value = '';
        comment.value = '';
        loading.value = false;
        verifiedManagerId.value = null;
    }
});

const formatPrice = (price: any) => {
    return new Intl.NumberFormat('ru-RU').format(price || 0) + ' ₽';
};

const close = () => {
    emit('update:modelValue', false);
};

const submit = async () => {
    if (!reason.value) return;

    const csrfToken = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content;
    const reasonLabel = cancelReasons.find((r: any) => r.value === reason.value)?.label || reason.value;
    const fullReason = `${reasonLabel}${comment.value ? ': ' + comment.value : ''}`;

    // Request mode - send for approval (for all orders)
    if (mode.value === 'request') {
        loading.value = true;
        try {
            // Отправляем запросы для всех заказов через централизованный API
            const promises = orders.value.map((order: any) =>
                api.orders.requestCancellation(order.id, fullReason)
            );
            await Promise.all(promises);
            emit('requestSent');
            close();
        } catch (e: any) {
            log.error('Error sending request:', e);
            pinError.value = e.message || 'Ошибка отправки заявки';
        } finally {
            loading.value = false;
        }
        return;
    }

    // PIN mode - verify PIN first
    if (mode.value === 'pin') {
        if (managerPin.value.length < 4) {
            pinError.value = 'Введите PIN менеджера';
            return;
        }

        loading.value = true;
        try {
            const authData = await api.auth.loginWithPin(managerPin.value);
            const managerRoles = ['super_admin', 'owner', 'admin', 'manager'];
            const userRole = (authData.data as any)?.user?.role;
            if (!managerRoles.includes(userRole)) {
                pinError.value = 'Неверный PIN или недостаточно прав';
                loading.value = false;
                return;
            }
            // Сохраняем ID менеджера для отмены
            verifiedManagerId.value = (authData.data as any)?.user?.id;
        } catch (e: any) {
            pinError.value = e.message || 'Ошибка проверки PIN';
            loading.value = false;
            return;
        }
    }

    // Получаем ID менеджера - либо из PIN верификации, либо текущий пользователь
    const managerId = verifiedManagerId.value || parseInt(localStorage.getItem('pos_user_id') as any) || 1;

    // Direct or after PIN - cancel ALL orders with write-off
    loading.value = true;
    try {
        // Отменяем все заказы через централизованный API
        const promises = orders.value.map((order: any) =>
            api.orders.cancel(order.id, fullReason, managerId, true)
        );
        await Promise.all(promises);
        emit('cancelled');
        close();
    } catch (e: any) {
        log.error('Error cancelling order:', e);
        pinError.value = e.message || 'Ошибка удаления заказа';
    } finally {
        loading.value = false;
    }
};
</script>
