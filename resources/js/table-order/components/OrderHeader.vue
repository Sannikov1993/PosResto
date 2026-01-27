<template>
    <div class="h-14 bg-[#1e2430] border-b border-gray-800/50 flex items-center px-4 gap-4 flex-shrink-0">
        <!-- Back button + Table number -->
        <div class="flex items-center gap-2">
            <button v-if="useEmitBack" @click="$emit('back')" class="px-3 py-2 bg-[#2a3142] text-gray-300 hover:bg-gray-600 rounded-lg text-sm font-medium">
                ← Назад
            </button>
            <a v-else href="/pos-vue#hall" class="px-3 py-2 bg-[#2a3142] text-gray-300 hover:bg-gray-600 rounded-lg text-sm font-medium">
                ← Заказ
            </a>
            <div class="flex items-center gap-1.5 px-3 py-2 bg-[#2a3142] border border-blue-500/50 text-white rounded-lg text-sm font-medium">
                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                </svg>
                <span>{{ linkedTableNumbers }}</span>
            </div>
        </div>

        <!-- Order tabs -->
        <div class="flex items-center gap-1.5 relative">
            <template v-for="(order, index) in orders.slice(0, 4)" :key="order.id">
                <button @click="$emit('update:currentOrderIndex', index)"
                    :class="currentOrderIndex === index ? 'bg-blue-500 text-white' : 'bg-[#2a3142] text-gray-400 hover:bg-gray-600'"
                    class="w-8 h-8 rounded-lg text-sm font-bold transition-all">
                    {{ index + 1 }}
                </button>
            </template>

            <div v-if="orders.length > 4" class="relative">
                <button @click="showDropdown = !showDropdown"
                    :class="currentOrderIndex >= 4 ? 'bg-blue-500 text-white' : 'bg-[#2a3142] text-gray-400 hover:bg-gray-600'"
                    class="w-8 h-8 rounded-lg text-sm font-bold transition-all">
                    <span v-if="currentOrderIndex >= 4">{{ currentOrderIndex + 1 }}</span>
                    <span v-else>...</span>
                </button>
                <div v-if="showDropdown"
                     class="absolute top-10 left-0 bg-[#2a3142] border border-gray-700 rounded-lg shadow-xl z-50 py-1 min-w-[140px]">
                    <button v-for="(order, index) in orders" :key="'drop-' + order.id"
                        @click="$emit('update:currentOrderIndex', index); showDropdown = false"
                        :class="currentOrderIndex === index ? 'bg-blue-500/20 text-blue-400' : 'text-gray-300 hover:bg-gray-700'"
                        class="w-full px-3 py-2 text-sm text-left flex items-center gap-2">
                        <span class="w-6 h-6 rounded flex items-center justify-center text-xs font-bold"
                            :class="currentOrderIndex === index ? 'bg-blue-500 text-white' : 'bg-gray-600'">{{ index + 1 }}</span>
                        <span>Заказ {{ index + 1 }}</span>
                    </button>
                </div>
            </div>
            <div v-if="showDropdown" @click="showDropdown = false" class="fixed inset-0 z-40"></div>

            <button @click="$emit('createNewOrder')" class="w-8 h-8 rounded-lg bg-[#2a3142] text-gray-400 hover:bg-gray-600 hover:text-white text-sm font-bold">+</button>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';

const props = defineProps({
    table: Object,
    linkedTableNumbers: [String, Number],
    reservation: Object,
    orders: Array,
    currentOrderIndex: Number,
    useEmitBack: { type: Boolean, default: false }
});

defineEmits(['update:currentOrderIndex', 'createNewOrder', 'back']);

const showDropdown = ref(false);
</script>
