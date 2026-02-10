<template>
    <Teleport to="body">
        <div v-if="modelValue" class="fixed inset-0 bg-black/70 z-50 flex items-center justify-center p-4" @click.self="$emit('update:modelValue', false)">
            <div class="bg-gray-900 rounded-2xl w-full max-w-md overflow-hidden shadow-2xl">
                <div class="p-4 border-b border-gray-800 flex items-center justify-between bg-gradient-to-r from-emerald-900/50 to-teal-900/50">
                    <h3 class="text-white text-lg font-semibold flex items-center gap-2">
                        <svg class="w-6 h-6 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                        Предоплата за бронь
                    </h3>
                    <button @click="$emit('update:modelValue', false)" class="text-gray-500 hover:text-white text-xl">&times;</button>
                </div>

                <div class="p-6 space-y-6">
                    <!-- Order info -->
                    <div class="bg-gray-800/50 rounded-xl p-4">
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-gray-400">Сумма предзаказа:</span>
                            <span class="text-white font-bold">{{ formatPrice(orderTotal) }}</span>
                        </div>
                        <div v-if="currentPrepayment! > 0" class="flex justify-between items-center">
                            <span class="text-emerald-400">Уже внесено:</span>
                            <span class="text-emerald-300 font-bold">{{ formatPrice(currentPrepayment) }}</span>
                        </div>
                    </div>

                    <!-- Amount input -->
                    <div>
                        <label class="block text-gray-400 text-sm mb-2">Сумма предоплаты</label>
                        <div class="relative">
                            <input type="number" v-model="amount"
                                   class="w-full bg-gray-800 text-white text-2xl font-bold rounded-xl px-4 py-4 border-2 border-gray-700 focus:border-emerald-500 focus:outline-none transition-colors"
                                   placeholder="0">
                            <span class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-500 text-lg">₽</span>
                        </div>
                    </div>

                    <!-- Quick amounts -->
                    <div class="grid grid-cols-4 gap-2">
                        <button @click="amount = 500" class="py-2 bg-gray-800 hover:bg-gray-700 text-gray-300 rounded-lg text-sm font-medium transition-colors">500</button>
                        <button @click="amount = 1000" class="py-2 bg-gray-800 hover:bg-gray-700 text-gray-300 rounded-lg text-sm font-medium transition-colors">1000</button>
                        <button @click="amount = 2000" class="py-2 bg-gray-800 hover:bg-gray-700 text-gray-300 rounded-lg text-sm font-medium transition-colors">2000</button>
                        <button @click="amount = orderTotal" class="py-2 bg-emerald-600/30 hover:bg-emerald-600/50 text-emerald-400 rounded-lg text-sm font-medium transition-colors">100%</button>
                    </div>

                    <!-- Payment method -->
                    <div>
                        <label class="block text-gray-400 text-sm mb-2">Способ оплаты</label>
                        <div class="grid grid-cols-2 gap-2">
                            <button @click="method = 'cash'"
                                    :class="method === 'cash' ? 'bg-emerald-600 text-white border-emerald-500' : 'bg-gray-800 text-gray-400 border-gray-700 hover:border-gray-600'"
                                    class="py-3 rounded-xl border-2 font-medium flex items-center justify-center gap-2 transition-all">
                                <span class="text-xl">💵</span> Наличные
                            </button>
                            <button @click="method = 'card'"
                                    :class="method === 'card' ? 'bg-emerald-600 text-white border-emerald-500' : 'bg-gray-800 text-gray-400 border-gray-700 hover:border-gray-600'"
                                    class="py-3 rounded-xl border-2 font-medium flex items-center justify-center gap-2 transition-all">
                                <span class="text-xl">💳</span> Карта
                            </button>
                        </div>
                    </div>
                </div>

                <div class="p-4 border-t border-gray-800 flex gap-3">
                    <button @click="$emit('update:modelValue', false)"
                            class="flex-1 py-3 bg-gray-700 text-gray-300 rounded-xl font-medium hover:bg-gray-600 transition-colors">
                        Отмена
                    </button>
                    <button @click="$emit('pay', { amount: Number(amount), method })"
                            class="flex-1 py-3 bg-gradient-to-r from-emerald-600 to-teal-600 text-white rounded-xl font-bold hover:from-emerald-500 hover:to-teal-500 transition-all shadow-lg shadow-emerald-500/25">
                        Принять предоплату
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue';

const props = defineProps({
    modelValue: Boolean,
    orderTotal: Number,
    currentPrepayment: Number
});

defineEmits(['update:modelValue', 'pay']);

const amount = ref<any>('');
const method = ref('cash');

watch(() => props.modelValue, (val) => {
    if (val) {
        amount.value = '';
        method.value = 'cash';
    }
});

const formatPrice = (price: any) => {
    return new Intl.NumberFormat('ru-RU').format(price || 0) + ' ₽';
};
</script>
