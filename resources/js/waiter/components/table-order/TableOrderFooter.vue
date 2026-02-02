<template>
  <div class="flex-shrink-0 p-4 bg-dark-800 border-t border-gray-800 safe-bottom">
    <!-- Total -->
    <div class="flex justify-between items-center mb-3">
      <span class="text-gray-400">Итого:</span>
      <span class="text-2xl font-bold text-orange-400">{{ formatMoney(total) }}</span>
    </div>

    <!-- Actions -->
    <div class="flex gap-2">
      <button
        @click="$emit('sendToKitchen')"
        :disabled="!canSend || isSaving"
        :class="[
          'flex-1 py-3 rounded-xl font-medium transition flex items-center justify-center gap-2',
          canSend && !isSaving
            ? 'bg-blue-500 text-white'
            : 'bg-gray-700 text-gray-500'
        ]"
        data-testid="send-to-kitchen-btn"
      >
        <span>🍳</span>
        <span>На кухню</span>
        <span v-if="newItemsCount > 0" class="ml-1">({{ newItemsCount }})</span>
      </button>
      <button
        @click="$emit('requestBill')"
        :disabled="!canPay || isSaving"
        :class="[
          'flex-1 py-3 rounded-xl font-medium transition flex items-center justify-center gap-2',
          canPay && !isSaving
            ? 'bg-green-500 text-white'
            : 'bg-gray-700 text-gray-500'
        ]"
        data-testid="payment-btn"
      >
        <span>💳</span>
        <span>Счёт</span>
      </button>
    </div>

    <!-- Ready items notification -->
    <button
      v-if="readyItemsCount > 0"
      @click="$emit('markAllServed')"
      class="w-full mt-2 py-2 rounded-xl bg-green-500/20 text-green-400 font-medium flex items-center justify-center gap-2"
    >
      <span>✓</span>
      <span>{{ readyItemsCount }} готово к подаче</span>
    </button>
  </div>
</template>

<script setup lang="ts">
import { formatMoney } from '@/waiter/utils/formatters';

defineProps<{
  total: number;
  newItemsCount: number;
  readyItemsCount: number;
  canSend: boolean;
  canPay: boolean;
  isSaving: boolean;
}>();

defineEmits<{
  sendToKitchen: [];
  requestBill: [];
  markAllServed: [];
}>();
</script>
