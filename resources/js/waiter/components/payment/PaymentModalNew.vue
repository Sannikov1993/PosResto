<template>
  <Teleport to="body">
    <div class="fixed inset-0 bg-black/60 z-50 flex items-end justify-center" @click.self="$emit('close')">
      <div class="bg-dark-800 rounded-t-3xl w-full max-w-md animate-slide-up">
        <!-- Header -->
        <div class="p-4 border-b border-gray-800 flex items-center justify-between">
          <h3 class="text-xl font-bold">Оплата</h3>
          <button @click="$emit('close')" class="text-2xl text-gray-500">✕</button>
        </div>

        <!-- Order Info -->
        <div class="p-4 border-b border-gray-800">
          <div class="flex justify-between items-center mb-2">
            <span class="text-gray-400">Стол</span>
            <span class="font-bold">{{ order?.table?.number }}</span>
          </div>
          <div class="flex justify-between items-center mb-2">
            <span class="text-gray-400">Позиций</span>
            <span>{{ order?.items?.length || 0 }}</span>
          </div>
          <div class="flex justify-between items-center text-xl">
            <span class="text-gray-400">Итого</span>
            <span class="font-bold text-orange-400">{{ formatMoney(order?.total || 0) }}</span>
          </div>
        </div>

        <!-- Payment Methods -->
        <div class="p-4">
          <p class="text-gray-500 text-sm mb-3">Способ оплаты</p>
          <div class="flex gap-3">
            <PaymentMethod
              method="cash"
              :selected="selectedMethod === 'cash'"
              @select="selectedMethod = $event"
            />
            <PaymentMethod
              method="card"
              :selected="selectedMethod === 'card'"
              @select="selectedMethod = $event"
            />
          </div>
        </div>

        <!-- Cash Amount (for cash payment) -->
        <div v-if="selectedMethod === 'cash'" class="px-4 pb-4">
          <p class="text-gray-500 text-sm mb-2">Сумма от гостя</p>
          <input
            v-model="cashAmount"
            type="number"
            inputmode="numeric"
            :min="order?.total || 0"
            class="w-full px-4 py-3 rounded-xl bg-dark-700 border border-gray-700 text-xl text-center font-bold focus:border-orange-500 focus:outline-none"
            placeholder="0"
          />
          <div class="flex gap-2 mt-2">
            <button
              v-for="amount in quickAmounts"
              :key="amount"
              @click="cashAmount = amount"
              class="flex-1 py-2 rounded-lg bg-dark-700 text-sm text-gray-400 hover:bg-dark-600"
            >
              {{ amount }}
            </button>
          </div>
          <div v-if="change > 0" class="mt-3 text-center">
            <span class="text-gray-400">Сдача: </span>
            <span class="text-green-400 font-bold text-xl">{{ formatMoney(change) }}</span>
          </div>
        </div>

        <!-- Actions -->
        <div class="p-4 border-t border-gray-800 safe-bottom">
          <button
            @click="handlePay"
            :disabled="!canPay || isLoading"
            :class="[
              'w-full py-4 rounded-xl font-bold text-lg transition',
              canPay && !isLoading
                ? 'bg-green-500 text-white'
                : 'bg-gray-700 text-gray-500'
            ]"
          >
            {{ isLoading ? 'Обработка...' : 'Оплатить' }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';
import { formatMoney } from '@/waiter/utils/formatters';
import type { Order, PaymentMethod as PaymentMethodType } from '@/waiter/types';
import PaymentMethod from './PaymentMethod.vue';

const props = defineProps<{
  order: Order | null;
  isLoading?: boolean;
}>();

const emit = defineEmits<{
  pay: [method: PaymentMethodType];
  close: [];
}>();

const selectedMethod = ref<PaymentMethodType>('cash');
const cashAmount = ref(0);

const quickAmounts = computed(() => {
  const total = props.order?.total || 0;
  const rounded = Math.ceil(total / 100) * 100;
  return [total, rounded, rounded + 100, rounded + 500].filter((v, i, arr) => arr.indexOf(v) === i);
});

const change = computed(() => {
  if (selectedMethod.value !== 'cash') return 0;
  const total = props.order?.total || 0;
  return Math.max(0, cashAmount.value - total);
});

const canPay = computed(() => {
  if (!props.order?.total) return false;
  if (selectedMethod.value === 'cash') {
    return cashAmount.value >= props.order.total;
  }
  return true;
});

function handlePay(): void {
  if (canPay.value) {
    emit('pay', selectedMethod.value);
  }
}
</script>

<style scoped>
.animate-slide-up {
  animation: slide-up 0.3s ease-out;
}

@keyframes slide-up {
  from {
    transform: translateY(100%);
  }
  to {
    transform: translateY(0);
  }
}
</style>
