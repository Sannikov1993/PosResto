<template>
  <Teleport to="body">
    <div class="fixed inset-0 bg-black/60 z-50 flex items-end justify-center" @click.self="$emit('close')">
      <div class="bg-dark-800 rounded-t-3xl w-full max-w-md p-6 animate-slide-up">
        <div class="text-center mb-6">
          <h3 class="text-xl font-bold">Стол {{ table?.number }}</h3>
          <p class="text-gray-500 text-sm">Укажите количество гостей</p>
        </div>

        <!-- Counter -->
        <div class="flex items-center justify-center gap-6 mb-8">
          <button
            @click="decrease"
            :disabled="count <= 1"
            class="w-14 h-14 rounded-full bg-dark-700 text-2xl font-bold disabled:opacity-30 active:bg-dark-600 transition"
          >
            -
          </button>
          <span class="text-5xl font-bold w-20 text-center">{{ count }}</span>
          <button
            @click="increase"
            :disabled="count >= maxGuests"
            class="w-14 h-14 rounded-full bg-dark-700 text-2xl font-bold disabled:opacity-30 active:bg-dark-600 transition"
          >
            +
          </button>
        </div>

        <!-- Quick Select -->
        <div class="flex justify-center gap-2 mb-6">
          <button
            v-for="n in quickOptions"
            :key="n"
            @click="count = n"
            :class="[
              'px-4 py-2 rounded-xl transition',
              count === n ? 'bg-orange-500 text-white' : 'bg-dark-700 text-gray-400'
            ]"
          >
            {{ n }}
          </button>
        </div>

        <!-- Actions -->
        <div class="flex gap-3">
          <button
            @click="$emit('close')"
            class="flex-1 py-3 rounded-xl bg-dark-700 text-gray-400 font-medium"
          >
            Отмена
          </button>
          <button
            @click="$emit('confirm', count)"
            class="flex-1 py-3 rounded-xl bg-orange-500 text-white font-medium"
            data-testid="guest-count-confirm"
          >
            Открыть стол
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import type { Table } from '@/waiter/types';

const props = withDefaults(defineProps<{
  table: Table | null;
  initialCount?: number;
  maxGuests?: number;
}>(), {
  initialCount: 2,
  maxGuests: 20,
});

defineEmits<{
  confirm: [count: number];
  close: [];
}>();

const count = ref(props.initialCount);
const quickOptions = [1, 2, 3, 4, 6, 8];

function increase(): void {
  if (count.value < props.maxGuests) {
    count.value++;
  }
}

function decrease(): void {
  if (count.value > 1) {
    count.value--;
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
