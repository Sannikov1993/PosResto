<template>
    <Teleport to="body">
        <div v-if="modelValue" class="fixed inset-0 bg-black/80 flex items-center justify-center z-[9999] p-4" role="dialog" aria-modal="true" aria-labelledby="payment-modal-title" data-testid="payment-modal">
            <div class="bg-dark-800 rounded-2xl w-full max-w-md border border-gray-700 shadow-2xl overflow-hidden" data-testid="payment-modal-content">
                <!-- Header -->
                <div class="bg-gradient-to-r from-green-600 to-emerald-600 p-5">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-white/70 text-sm">{{ isDelivery ? 'Закрытие заказа' : 'Оплата заказа' }}</p>
                            <h3 id="payment-modal-title" class="text-2xl font-bold text-white">#{{ order?.order_number }}</h3>
                        </div>
                        <button @click="close" class="text-white/70 hover:text-white text-2xl" aria-label="�������">&times;</button>
                    </div>
                </div>

                <!-- Content -->
                <div class="p-5">
                    <!-- Order info -->
                    <div class="bg-dark-900 rounded-xl p-4 mb-5">
                        <div class="flex justify-between items-center mb-3">
                            <span class="text-gray-400">{{ orderLocationLabel }}</span>
                            <span class="text-white font-medium text-right max-w-[200px] truncate">{{ orderLocationValue }}</span>
                        </div>
                        <div class="flex justify-between items-center mb-3">
                            <span class="text-gray-400">Позиций</span>
                            <span class="text-white font-medium">{{ order?.items?.length || 0 }}</span>
                        </div>

                        <!-- Legal entity split info -->
                        <div v-if="paymentSplit.hasSplit" class="border-t border-gray-700 pt-3 mt-3">
                            <p class="text-gray-500 text-xs uppercase mb-2">Разбиение по юрлицам</p>
                            <div v-for="split in paymentSplit.splits" :key="split.legal_entity_id" class="flex justify-between items-center text-sm mb-1">
                                <span class="text-gray-400 flex items-center gap-2">
                                    <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                                    {{ split.legal_entity_short_name || split.legal_entity_name }}
                                    <span class="text-gray-600">({{ split.items_count }} поз.)</span>
                                </span>
                                <span class="text-white font-medium">{{ formatMoney(split.amount) }} ₽</span>
                            </div>
                        </div>
                        <!-- Applied certificate -->
                        <div v-if="appliedCertificate" class="flex justify-between items-center mb-3 text-pink-400">
                            <span class="flex items-center gap-2">
                                🎟️ Сертификат <span class="font-mono text-xs">{{ appliedCertificate.code }}</span>
                                <button @click="removeCertificate" class="text-gray-500 hover:text-red-400 text-xs">✕</button>
                            </span>
                            <span class="font-medium">-{{ formatMoney(certificateAmount) }} ₽</span>
                        </div>

                        <div class="border-t border-gray-700 pt-3 flex justify-between items-center">
                            <span class="text-lg text-white font-medium">Итого к оплате</span>
                            <span class="text-2xl text-green-400 font-bold">{{ formatMoney(amountToPay) }} ₽</span>
                        </div>
                    </div>

                    <!-- Gift certificate section -->
                    <div v-if="!appliedCertificate" class="mb-5">
                        <button v-if="!showCertificateInput" @click="showCertificateInput = true"
                                class="w-full py-3 rounded-xl border-2 border-dashed border-gray-700 hover:border-pink-500/50 text-gray-400 hover:text-pink-400 transition-all flex items-center justify-center gap-2">
                            <span>🎟️</span>
                            <span>Применить сертификат</span>
                        </button>

                        <div v-else class="bg-dark-900 rounded-xl p-4">
                            <p class="text-gray-400 text-sm mb-2">Код сертификата</p>
                            <div class="flex gap-2">
                                <input v-model="certificateCode"
                                       type="text"
                                       class="flex-1 bg-dark-700 border border-gray-600 rounded-lg px-4 py-2 text-white font-mono uppercase focus:border-pink-500 focus:outline-none"
                                       placeholder="GC-XXXX-XXXX"
                                       @keyup.enter="checkCertificate" />
                                <button @click="checkCertificate"
                                        :disabled="checkingCertificate || !certificateCode"
                                        class="px-4 py-2 bg-pink-600 hover:bg-pink-700 disabled:bg-gray-700 text-white rounded-lg font-medium transition">
                                    {{ checkingCertificate ? '...' : 'Применить' }}
                                </button>
                                <button @click="showCertificateInput = false; certificateCode = ''"
                                        class="px-3 py-2 text-gray-500 hover:text-white">
                                    ✕
                                </button>
                            </div>
                            <p v-if="certificateError" class="text-red-400 text-sm mt-2">{{ certificateError }}</p>
                        </div>
                    </div>

                    <!-- Fully covered by certificate message -->
                    <div v-if="amountToPay === 0 && appliedCertificate" class="mb-5 bg-pink-500/10 border border-pink-500/30 rounded-xl p-4 text-center">
                        <span class="text-pink-400 font-medium">🎟️ Оплата полностью покрыта сертификатом</span>
                    </div>

                    <!-- Payment method -->
                    <template v-if="amountToPay > 0">
                        <p class="text-gray-400 text-sm mb-3">Способ оплаты</p>
                        <div class="grid grid-cols-2 gap-3 mb-5">
                            <button @click="paymentMethod = 'cash'"
                                    data-testid="payment-cash-btn"
                                    :class="['p-4 rounded-xl border-2 transition-all flex flex-col items-center gap-2',
                                             paymentMethod === 'cash' ? 'border-green-500 bg-green-500/10' : 'border-gray-700 hover:border-gray-600']">
                                <span class="text-3xl">💵</span>
                                <span :class="paymentMethod === 'cash' ? 'text-green-400 font-medium' : 'text-gray-400'">Наличные</span>
                            </button>
                            <button @click="paymentMethod = 'card'"
                                    data-testid="payment-card-btn"
                                    :class="['p-4 rounded-xl border-2 transition-all flex flex-col items-center gap-2',
                                             paymentMethod === 'card' ? 'border-blue-500 bg-blue-500/10' : 'border-gray-700 hover:border-gray-600']">
                                <span class="text-3xl">💳</span>
                                <span :class="paymentMethod === 'card' ? 'text-blue-400 font-medium' : 'text-gray-400'">Картой</span>
                            </button>
                        </div>
                    </template>

                    <!-- Cash input -->
                    <div v-if="paymentMethod === 'cash' && amountToPay > 0" class="mb-5">
                        <p class="text-gray-400 text-sm mb-2">Получено от клиента</p>
                        <div class="relative">
                            <input
                                type="number"
                                v-model.number="cashReceived"
                                data-testid="cash-received-input"
                                class="w-full bg-dark-900 border border-gray-700 rounded-xl px-4 py-3 text-white text-lg font-medium focus:border-green-500 focus:outline-none"
                                placeholder="0"
                                @focus="$event.target.select()"
                            />
                            <span class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-500">₽</span>
                        </div>

                        <!-- Quick amounts -->
                        <div class="flex gap-2 mt-3">
                            <button
                                v-for="amount in quickAmounts"
                                :key="amount"
                                @click="cashReceived = amount"
                                class="flex-1 py-2 rounded-lg bg-dark-700 text-gray-300 hover:bg-dark-600 text-sm transition-colors"
                            >
                                {{ formatMoney(amount) }}
                            </button>
                        </div>

                        <!-- Change calculation -->
                        <div v-if="cashReceived && cashReceived >= amountToPay"
                             class="mt-4 bg-green-500/10 border border-green-500/30 rounded-xl p-4">
                            <div class="flex justify-between items-center">
                                <span class="text-green-400">Сдача</span>
                                <span class="text-2xl text-green-400 font-bold">{{ formatMoney(changeAmount) }} ₽</span>
                            </div>
                        </div>

                        <!-- Not enough -->
                        <div v-else-if="cashReceived && cashReceived < amountToPay && amountToPay > 0"
                             class="mt-4 bg-red-500/10 border border-red-500/30 rounded-xl p-4">
                            <div class="flex justify-between items-center">
                                <span class="text-red-400">Не хватает</span>
                                <span class="text-xl text-red-400 font-bold">{{ formatMoney(amountToPay - cashReceived) }} ₽</span>
                            </div>
                        </div>
                    </div>

                    <!-- Warning if no shift -->
                    <div v-if="!hasActiveShift" class="bg-red-500/20 border border-red-500/50 rounded-xl p-4 mb-4 text-center">
                        <p class="text-red-400 font-medium">Кассовая смена не открыта!</p>
                        <p class="text-red-400/70 text-sm mt-1">Откройте смену в разделе "Касса"</p>
                    </div>

                    <!-- Print receipt checkbox -->
                    <label class="flex items-center gap-3 mb-4 cursor-pointer">
                        <input type="checkbox" v-model="printReceipt" class="w-4 h-4 rounded border-gray-600 bg-dark-900 text-green-500 focus:ring-green-500 focus:ring-offset-0 focus:ring-offset-dark-800">
                        <span class="text-gray-400 text-sm">Напечатать чек после оплаты</span>
                    </label>

                    <!-- Actions -->
                    <div class="flex gap-3">
                        <button @click="close"
                                data-testid="payment-cancel-btn"
                                class="flex-1 py-4 rounded-xl font-medium bg-dark-900 text-gray-400 hover:bg-dark-700 transition-colors">
                            Отмена
                        </button>
                        <button @click="processPayment"
                                :disabled="!canProcess"
                                data-testid="payment-submit-btn"
                                :class="['flex-1 py-4 rounded-xl font-bold transition-all flex items-center justify-center gap-2',
                                         canProcess ? 'bg-green-600 hover:bg-green-700 text-white' : 'bg-gray-700 text-gray-500 cursor-not-allowed']">
                            <span v-if="processing" class="animate-spin">⏳</span>
                            <span v-else>✓</span>
                            {{ processing ? 'Обработка...' : 'Принять оплату' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { useAuthStore } from '../../stores/auth';
import api from '../../api';

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    order: { type: Object, default: null },
    isDelivery: { type: Boolean, default: false }
});

const emit = defineEmits(['update:modelValue', 'paid']);

const authStore = useAuthStore();

// State
const paymentMethod = ref('cash');
const cashReceived = ref(0);
const processing = ref(false);
const printReceipt = ref(false);

// Certificate state
const showCertificateInput = ref(false);
const certificateCode = ref('');
const checkingCertificate = ref(false);
const certificateError = ref('');
const appliedCertificate = ref(null);

// Legal entity split state
const paymentSplit = ref({ hasSplit: false, splits: [] });

// Computed
const hasActiveShift = computed(() => {
    return !!authStore.currentShift;
});

const orderTotal = computed(() => props.order?.total || 0);

// Certificate amount: min of certificate balance and order total
const certificateAmount = computed(() => {
    if (!appliedCertificate.value) return 0;
    return Math.min(appliedCertificate.value.balance, orderTotal.value);
});

// Amount still to pay after certificate
const amountToPay = computed(() => {
    return Math.max(0, orderTotal.value - certificateAmount.value);
});

const changeAmount = computed(() => {
    if (paymentMethod.value !== 'cash') return 0;
    return Math.max(0, (cashReceived.value || 0) - amountToPay.value);
});

const quickAmounts = computed(() => {
    const total = orderTotal.value;
    if (total <= 0) return [500, 1000, 2000, 5000];

    // Generate quick amounts based on order total
    const amounts = [];
    const roundUp = (n, base) => Math.ceil(n / base) * base;

    amounts.push(roundUp(total, 100));
    amounts.push(roundUp(total, 500));
    amounts.push(roundUp(total, 1000));
    if (total > 1000) amounts.push(roundUp(total, 5000));

    // Remove duplicates and limit to 4
    return [...new Set(amounts)].slice(0, 4);
});

const orderLocationLabel = computed(() => {
    if (props.isDelivery) {
        return props.order?.type === 'pickup' ? 'Самовывоз' : 'Адрес';
    }
    return 'Стол';
});

const orderLocationValue = computed(() => {
    if (props.isDelivery) {
        if (props.order?.type === 'pickup') {
            return props.order?.customer?.name || 'Клиент';
        }
        return props.order?.delivery_address || props.order?.customer?.address || '—';
    }
    return props.order?.table?.name || `Стол ${props.order?.table?.number}`;
});

const canProcess = computed(() => {
    if (!hasActiveShift.value) return false;
    if (processing.value) return false;
    // If certificate covers everything, no cash needed
    if (amountToPay.value === 0 && appliedCertificate.value) return true;
    // If cash payment, check sufficient amount
    if (paymentMethod.value === 'cash' && cashReceived.value < amountToPay.value) return false;
    return true;
});

// Watch for modal open to reset state
watch(() => props.modelValue, async (isOpen) => {
    if (isOpen) {
        paymentMethod.value = props.order?.payment_method || 'cash';
        cashReceived.value = props.order?.total || 0;
        processing.value = false;
        // Reset certificate state
        showCertificateInput.value = false;
        certificateCode.value = '';
        certificateError.value = '';
        appliedCertificate.value = null;
        // Reset split state
        paymentSplit.value = { hasSplit: false, splits: [] };

        // Load payment split preview
        if (props.order?.id) {
            try {
                const data = await api.orders.getPaymentSplitPreview(props.order.id);
                if (data?.has_split) {
                    paymentSplit.value = {
                        hasSplit: true,
                        splits: data.splits
                    };
                }
            } catch (e) {
                console.warn('Failed to load payment split preview:', e);
            }
        }
    }
});

// Methods
const close = () => {
    emit('update:modelValue', false);
};

// Certificate methods
const checkCertificate = async () => {
    if (!certificateCode.value || checkingCertificate.value) return;

    checkingCertificate.value = true;
    certificateError.value = '';

    try {
        const result = await api.giftCertificates.check(certificateCode.value);
        if (result.success) {
            appliedCertificate.value = result.data;
            showCertificateInput.value = false;
            certificateCode.value = '';
            // Update cash received to remaining amount
            cashReceived.value = Math.max(0, orderTotal.value - result.data.balance);
        } else {
            certificateError.value = result.message || 'Сертификат не найден';
        }
    } catch (e) {
        certificateError.value = e.response?.data?.message || 'Ошибка проверки сертификата';
    } finally {
        checkingCertificate.value = false;
    }
};

const removeCertificate = () => {
    appliedCertificate.value = null;
    cashReceived.value = orderTotal.value;
};

const processPayment = async () => {
    if (!props.order || processing.value || !canProcess.value) return;

    processing.value = true;

    try {
        // If certificate is applied, use it first
        if (appliedCertificate.value && certificateAmount.value > 0) {
            try {
                await api.giftCertificates.use(
                    appliedCertificate.value.id,
                    certificateAmount.value,
                    props.order.id,
                    props.order.customer_id
                );
            } catch (certError) {
                console.error('Certificate usage error:', certError);
                alert('Ошибка применения сертификата: ' + (certError.response?.data?.message || certError.message));
                processing.value = false;
                return;
            }
        }

        // Determine payment method
        const finalMethod = amountToPay.value === 0 ? 'certificate' : paymentMethod.value;

        // Interceptor бросит исключение при success: false
        await api.orders.payV1(props.order.id, {
            method: finalMethod,
            amount: orderTotal.value,
            cash_received: paymentMethod.value === 'cash' ? cashReceived.value : null,
            certificate_amount: certificateAmount.value || null,
            certificate_code: appliedCertificate.value?.code || null,
            fiscalize: false
        });

        // Print receipt if checkbox was checked
        if (printReceipt.value) {
            try {
                await api.orders.printReceiptV1(props.order.id);
            } catch (printError) {
                console.error('Print error:', printError);
            }
        }

        emit('paid', {
            order: props.order,
            method: finalMethod,
            isDelivery: props.isDelivery,
            certificateUsed: appliedCertificate.value?.code
        });
        close();
    } catch (e) {
        console.error('Payment error:', e);
        alert('Ошибка оплаты: ' + (e.response?.data?.message || e.message));
    } finally {
        processing.value = false;
    }
};

// Format money
const formatMoney = (amount) => {
    return new Intl.NumberFormat('ru-RU').format(amount || 0);
};
</script>
