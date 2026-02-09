<template>
    <Teleport to="body">
        <Transition name="fade">
            <div v-if="modelValue" class="fixed inset-0 bg-black/80 z-[10000] flex items-center justify-center p-4" @click.self="close">
                <Transition name="scale">
                    <div v-if="modelValue" class="bg-gray-900 rounded-2xl w-full max-w-md shadow-2xl flex flex-col max-h-[90vh]">
                        <!-- Header -->
                        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-800">
                            <h3 class="text-lg font-semibold text-white">Предпросмотр счёта</h3>
                            <button @click="close" class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-gray-800 text-gray-400 hover:text-white transition-colors">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>

                        <!-- Receipt Preview -->
                        <div class="flex-1 overflow-auto p-4">
                            <div v-if="loading" class="flex items-center justify-center py-12">
                                <div class="animate-spin w-8 h-8 border-4 border-accent border-t-transparent rounded-full"></div>
                            </div>

                            <div v-else-if="data" class="receipt-paper bg-white text-gray-900 rounded-lg p-4 font-mono text-sm">
                                <!-- Title -->
                                <div class="text-center border-b-2 border-dashed border-gray-300 pb-3 mb-3">
                                    <div class="text-lg font-bold">{{ data.title }}</div>
                                    <div class="text-xs text-gray-500">{{ data.subtitle }}</div>
                                </div>

                                <!-- Order Info -->
                                <div class="space-y-1 text-xs border-b-2 border-dashed border-gray-300 pb-3 mb-3">
                                    <div v-if="data.table" class="flex justify-between">
                                        <span>Стол №:</span>
                                        <span class="font-medium">{{ data.table }}</span>
                                    </div>
                                    <div v-if="data.date" class="flex justify-between">
                                        <span>Дата:</span>
                                        <span>{{ data.date }}</span>
                                    </div>
                                    <div v-if="data.waiter" class="flex justify-between">
                                        <span>Официант:</span>
                                        <span>{{ data.waiter }}</span>
                                    </div>
                                    <div v-if="data.guests" class="flex justify-between">
                                        <span>Гостей:</span>
                                        <span>{{ data.guests }}</span>
                                    </div>
                                </div>

                                <!-- Items Header -->
                                <div class="flex text-xs font-bold border-b border-gray-300 pb-1 mb-2">
                                    <div class="w-8">Кол</div>
                                    <div class="flex-1">Наименование</div>
                                    <div class="w-20 text-right">Сумма</div>
                                </div>

                                <!-- Items -->
                                <div class="space-y-1 border-b-2 border-dashed border-gray-300 pb-3 mb-3">
                                    <div v-for="(item, index) in data.items" :key="index" class="flex text-xs">
                                        <div class="w-8">{{ item.quantity }}x</div>
                                        <div class="flex-1">
                                            <div>{{ item.name }}</div>
                                            <div v-if="item.comment" class="text-gray-500 text-[10px] italic">{{ item.comment }}</div>
                                        </div>
                                        <div class="w-20 text-right">{{ formatPrice(item.total) }}</div>
                                    </div>
                                </div>

                                <!-- Totals -->
                                <div class="space-y-1 text-xs">
                                    <div v-if="data.discount > 0" class="flex justify-between text-green-600">
                                        <span>Скидка{{ data.discount_percent ? ` (${data.discount_percent}%)` : '' }}:</span>
                                        <span>-{{ formatPrice(data.discount) }}</span>
                                    </div>
                                    <div class="flex justify-between text-base font-bold pt-2 border-t border-gray-300">
                                        <span>К ОПЛАТЕ:</span>
                                        <span>{{ formatPrice(data.total) }}</span>
                                    </div>
                                </div>

                                <!-- Footer -->
                                <div v-if="data.footer" class="text-center text-xs text-gray-500 mt-4 pt-3 border-t-2 border-dashed border-gray-300">
                                    {{ data.footer }}
                                </div>
                            </div>

                            <div v-else class="text-center text-gray-400 py-12">
                                Не удалось загрузить данные
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="flex gap-2 p-4 border-t border-gray-800">
                            <button
                                @click="close"
                                class="flex-1 py-3 bg-gray-800 hover:bg-gray-700 text-white rounded-xl font-medium transition-colors"
                            >
                                Закрыть
                            </button>
                            <button
                                @click="print"
                                :disabled="printing || !data"
                                class="flex-1 py-3 bg-accent hover:bg-accent/90 text-white rounded-xl font-medium transition-colors flex items-center justify-center gap-2 disabled:opacity-50"
                            >
                                <svg v-if="printing" class="animate-spin h-5 w-5" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                                </svg>
                                <span>{{ printing ? 'Печать...' : 'Печатать' }}</span>
                            </button>
                        </div>
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup>
import { ref, watch } from 'vue';
import { createLogger } from '../../shared/services/logger.js';

const log = createLogger('Receipt');

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    orderId: { type: [Number, String], required: true },
    type: { type: String, default: 'precheck' }, // precheck or receipt
});

const emit = defineEmits(['update:modelValue', 'print']);

const loading = ref(false);
const printing = ref(false);
const data = ref(null);

const formatPrice = (value) => {
    return new Intl.NumberFormat('ru-RU', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
    }).format(value || 0) + ' ₽';
};

const loadPreview = async () => {
    if (!props.orderId) return;

    loading.value = true;
    data.value = null;

    try {
        const endpoint = props.type === 'receipt'
            ? `/api/orders/${props.orderId}/preview/receipt`
            : `/api/orders/${props.orderId}/preview/precheck`;

        const response = await fetch(endpoint);
        const result = await response.json();

        if (result.success) {
            data.value = result.data;
        }
    } catch (error) {
        log.error('Failed to load preview:', error);
    } finally {
        loading.value = false;
    }
};

const close = () => {
    emit('update:modelValue', false);
};

const print = async () => {
    printing.value = true;
    try {
        emit('print', props.type);
    } finally {
        // Небольшая задержка для отображения анимации
        setTimeout(() => {
            printing.value = false;
        }, 500);
    }
};

watch(() => props.modelValue, (newVal) => {
    if (newVal) {
        loadPreview();
    }
});
</script>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.2s ease;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

.scale-enter-active,
.scale-leave-active {
    transition: all 0.2s ease;
}
.scale-enter-from,
.scale-leave-to {
    opacity: 0;
    transform: scale(0.95);
}

.receipt-paper {
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    background: linear-gradient(to bottom, #fff 0%, #f9f9f9 100%);
}
</style>
