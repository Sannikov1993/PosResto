<template>
    <Teleport to="body">
        <div v-if="modelValue" class="fixed inset-0 bg-black/80 z-50 flex items-center justify-center p-4" @click.self="close">
            <div class="bg-gray-900 rounded-2xl w-[420px] max-h-[90vh] overflow-y-auto">
                <!-- Header -->
                <div class="p-4 border-b border-gray-800 flex items-center justify-between sticky top-0 bg-gray-900 z-10">
                    <div class="flex items-center gap-3">
                        <button v-if="mode && !canCancelItems" @click="mode = null" class="text-gray-400 hover:text-white">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                            </svg>
                        </button>
                        <div class="w-10 h-10 bg-red-500/20 rounded-xl flex items-center justify-center">
                            <span class="text-xl">&#x26D4;</span>
                        </div>
                        <h3 class="text-white font-semibold">Отмена позиции</h3>
                    </div>
                    <button @click="close" class="text-gray-500 hover:text-white text-xl">&#x2715;</button>
                </div>

                <!-- Item info -->
                <div class="p-4 bg-red-500/10 border-b border-red-500/30">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-white font-semibold text-lg">{{ item?.name }}</span>
                        <span class="text-blue-500 font-bold">{{ formatPrice(item?.price * item?.quantity) }}</span>
                    </div>
                    <div class="flex items-center gap-4 text-sm">
                        <span class="text-gray-400">Кол-во: <span class="text-white">{{ item?.quantity }}</span></span>
                        <span class="px-2 py-0.5 rounded text-xs"
                            :class="item?.status === 'cooking' ? 'bg-yellow-500/20 text-yellow-500' : 'bg-green-500/20 text-green-500'">
                            {{ item?.status === 'cooking' ? 'Готовится' : 'Готово' }}
                        </span>
                    </div>
                    <p class="text-red-400 text-xs mt-2">
                        &#x26A0; Блюдо уже на кухне! Продукты будут списаны.
                    </p>
                </div>

                <!-- Mode selection (for non-managers) -->
                <div v-if="mode === null && !canCancelItems" class="p-4 space-y-3">
                    <p class="text-gray-400 text-sm">Выберите способ отмены:</p>
                    <button @click="mode = 'pin'"
                        class="w-full p-4 bg-gray-800 hover:bg-gray-700 rounded-xl text-left transition-colors border border-gray-700 hover:border-orange-500">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-lg bg-orange-500/20 flex items-center justify-center">
                                <span class="text-xl">&#x1F512;</span>
                            </div>
                            <div>
                                <div class="text-white font-medium">Ввести PIN менеджера</div>
                                <div class="text-gray-500 text-sm">Отмена будет выполнена сразу</div>
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
                                <div class="text-white font-medium">Отправить заявку</div>
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

                <!-- Reason selection (for PIN and direct modes, and request) -->
                <div v-if="mode" class="p-4">
                    <label class="text-gray-400 text-sm mb-2 block">Причина отмены</label>
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
                        <span v-else-if="mode === 'request'">Отправить заявку</span>
                        <span v-else>Отменить позицию</span>
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<script setup>
import { ref, watch, computed } from 'vue';

const props = defineProps({
    modelValue: Boolean,
    item: Object,
    orderId: Number,
    canCancelItems: Boolean
});

const emit = defineEmits(['update:modelValue', 'cancelled', 'requestSent']);

const mode = ref(null);
const managerPin = ref('');
const pinError = ref('');
const reason = ref('');
const comment = ref('');
const loading = ref(false);

const cancelReasons = [
    { value: 'guest_refused', label: 'Гость отказался' },
    { value: 'wrong_order', label: 'Ошибка в заказе' },
    { value: 'kitchen_error', label: 'Ошибка кухни' },
    { value: 'quality_issue', label: 'Проблема с качеством' },
    { value: 'long_wait', label: 'Долгое ожидание' },
    { value: 'other', label: 'Другая причина' }
];

// Reset when modal opens
watch(() => props.modelValue, (val) => {
    if (val) {
        mode.value = props.canCancelItems ? 'direct' : null;
        managerPin.value = '';
        pinError.value = '';
        reason.value = '';
        comment.value = '';
        loading.value = false;
    }
});

const formatPrice = (price) => {
    return new Intl.NumberFormat('ru-RU').format(price || 0) + ' P';
};

const close = () => {
    emit('update:modelValue', false);
};

const submit = async () => {
    if (!reason.value) return;

    // Request mode - send for approval (item cancellation request)
    if (mode.value === 'request') {
        loading.value = true;
        try {
            const reasonLabel = cancelReasons.find(r => r.value === reason.value)?.label || reason.value;
            const response = await fetch(`/api/order-items/${props.item?.id}/request-cancellation`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
                },
                body: JSON.stringify({
                    reason: `${reasonLabel}${comment.value ? ': ' + comment.value : ''}`
                })
            });
            const data = await response.json();
            if (data.success) {
                emit('requestSent', data.new_status);
                close();
            }
        } catch (e) {
            console.error('Error sending request:', e);
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
            const authResponse = await fetch('/api/auth/login-pin', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
                },
                body: JSON.stringify({ pin: managerPin.value })
            });
            const authData = await authResponse.json();
            const managerRoles = ['super_admin', 'owner', 'admin', 'manager'];
            const userRole = authData.data?.user?.role;
            if (!authData.success || !managerRoles.includes(userRole)) {
                pinError.value = 'Неверный PIN или недостаточно прав';
                loading.value = false;
                return;
            }
        } catch (e) {
            pinError.value = 'Ошибка проверки PIN';
            loading.value = false;
            return;
        }
    }

    // Direct or after PIN - cancel the item
    loading.value = true;
    try {
        const response = await fetch(`/api/order-items/${props.item?.id}/cancel`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
            },
            body: JSON.stringify({
                reason_type: reason.value,
                reason_comment: comment.value || null
            })
        });
        const data = await response.json();
        if (data.success) {
            emit('cancelled', data.new_status || 'cancelled');
            close();
        }
    } catch (e) {
        console.error('Error cancelling item:', e);
    } finally {
        loading.value = false;
    }
};
</script>
