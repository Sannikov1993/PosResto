<template>
    <Teleport to="body">
        <div v-if="modelValue" class="fixed inset-0 bg-black/70 z-50 flex items-center justify-center p-4" @click.self="$emit('update:modelValue', false)">
            <div class="bg-gray-900 rounded-2xl w-full max-w-md overflow-hidden">
                <div class="p-4 border-b border-gray-800 flex items-center justify-between">
                    <h3 class="text-white text-lg font-semibold">💰 Оплата заказа</h3>
                    <button @click="$emit('update:modelValue', false)" class="text-gray-500 hover:text-white text-xl">✕</button>
                </div>
                <div class="p-4">
                    <!-- Amount -->
                    <div class="bg-gray-800 rounded-xl p-4 mb-4 text-center">
                        <p class="text-gray-400 text-sm mb-1">Итого к оплате</p>
                        <p class="text-3xl font-bold text-blue-500">{{ formatPrice(orderTotal) }}</p>
                    </div>

                    <!-- Payment method -->
                    <p class="text-gray-400 text-sm mb-3">Способ оплаты:</p>
                    <div class="grid grid-cols-2 gap-3 mb-4">
                        <button @click="selectedMethod = 'cash'"
                            :class="selectedMethod === 'cash' ? 'border-green-500 bg-green-500/20' : 'border-gray-700 bg-gray-800'"
                            class="p-4 rounded-xl border-2 flex flex-col items-center gap-2 transition-all">
                            <span class="text-3xl">💵</span>
                            <span class="text-white font-medium">Наличные</span>
                        </button>
                        <button @click="selectedMethod = 'card'"
                            :class="selectedMethod === 'card' ? 'border-blue-500 bg-blue-500/20' : 'border-gray-700 bg-gray-800'"
                            class="p-4 rounded-xl border-2 flex flex-col items-center gap-2 transition-all">
                            <span class="text-3xl">💳</span>
                            <span class="text-white font-medium">Картой</span>
                        </button>
                    </div>

                    <!-- Buttons -->
                    <div class="flex gap-3">
                        <button @click="$emit('update:modelValue', false)" class="flex-1 py-3 bg-gray-700 text-gray-300 rounded-xl font-medium hover:bg-gray-600">
                            Отмена
                        </button>
                        <button @click="$emit('pay', selectedMethod)" class="flex-1 py-3 bg-green-500 text-white rounded-xl font-bold hover:bg-green-600">
                            ✓ Принять оплату
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<script setup>
import { ref } from 'vue';

defineProps({
    modelValue: Boolean,
    orderTotal: Number
});

defineEmits(['update:modelValue', 'pay']);

const selectedMethod = ref('cash');

const formatPrice = (price) => {
    return new Intl.NumberFormat('ru-RU').format(price || 0) + ' ₽';
};
</script>
