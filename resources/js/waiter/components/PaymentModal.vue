<template>
    <Teleport to="body">
        <div v-if="modelValue" class="fixed inset-0 bg-black/80 flex items-end justify-center z-50">
            <div class="bg-dark-800 rounded-t-3xl w-full max-w-lg p-6 safe-bottom">
                <!-- Header -->
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-xl font-bold">Оплата</h2>
                    <button @click="close" class="text-2xl text-gray-500">✕</button>
                </div>

                <!-- Order Info -->
                <div class="bg-dark-900 rounded-xl p-4 mb-6">
                    <div class="flex justify-between mb-2">
                        <span class="text-gray-400">Заказ</span>
                        <span class="font-medium">#{{ order?.order_number }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-400">Сумма</span>
                        <span class="text-2xl font-bold text-orange-400">{{ formatMoney(order?.total || 0) }} ₽</span>
                    </div>
                </div>

                <!-- Payment Method -->
                <p class="text-gray-400 text-sm mb-3">Способ оплаты</p>
                <div class="grid grid-cols-2 gap-3 mb-6">
                    <button @click="method = 'cash'"
                            :class="['p-4 rounded-xl border-2 transition flex flex-col items-center gap-2',
                                     method === 'cash' ? 'border-green-500 bg-green-500/10' : 'border-gray-700']">
                        <span class="text-3xl">💵</span>
                        <span :class="method === 'cash' ? 'text-green-400' : 'text-gray-400'">Наличные</span>
                    </button>
                    <button @click="method = 'card'"
                            :class="['p-4 rounded-xl border-2 transition flex flex-col items-center gap-2',
                                     method === 'card' ? 'border-blue-500 bg-blue-500/10' : 'border-gray-700']">
                        <span class="text-3xl">💳</span>
                        <span :class="method === 'card' ? 'text-blue-400' : 'text-gray-400'">Картой</span>
                    </button>
                </div>

                <!-- Action -->
                <button @click="submit"
                        :disabled="loading"
                        class="w-full py-4 bg-green-500 text-white rounded-xl font-bold text-lg">
                    {{ loading ? 'Обработка...' : 'Принять оплату' }}
                </button>
            </div>
        </div>
    </Teleport>
</template>

<script setup>
import { ref, watch } from 'vue';

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    order: { type: Object, default: null }
});

const emit = defineEmits(['update:modelValue', 'paid']);

const method = ref('cash');
const loading = ref(false);

const close = () => {
    emit('update:modelValue', false);
};

const submit = () => {
    loading.value = true;
    emit('paid', { method: method.value, order: props.order });
    setTimeout(() => {
        loading.value = false;
        close();
    }, 500);
};

watch(() => props.modelValue, (val) => {
    if (val) {
        method.value = 'cash';
        loading.value = false;
    }
});

const formatMoney = (n) => Math.floor(n || 0).toLocaleString('ru-RU');
</script>
