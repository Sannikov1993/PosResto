<template>
    <div class="h-14 bg-[#1e2430] border-b border-gray-800/50 flex items-center px-4 gap-4 flex-shrink-0">
        <!-- Back button + Table number -->
        <div class="flex items-center gap-2">
            <button v-if="useEmitBack" @click="$emit('back')" class="px-3 py-2 bg-[#2a3142] text-gray-300 hover:bg-gray-600 rounded-lg text-sm font-medium">
                ← Назад
            </button>
            <a v-else href="/pos#hall" class="px-3 py-2 bg-[#2a3142] text-gray-300 hover:bg-gray-600 rounded-lg text-sm font-medium">
                ← Заказ
            </a>
            <div class="flex items-center gap-1.5 px-3 py-2 bg-[#2a3142] border border-blue-500/50 text-white rounded-lg text-sm font-medium">
                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                </svg>
                <span>{{ linkedTableNumbers }}</span>
            </div>
        </div>

        <!-- Price List Selector -->
        <div v-if="availablePriceLists && availablePriceLists.length > 0" class="relative">
            <button @click="showPriceListMenu = !showPriceListMenu"
                :class="[
                    'flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium transition-all',
                    selectedPriceListId
                        ? 'bg-blue-500/20 text-blue-400 border border-blue-500/50'
                        : 'bg-[#2a3142] text-gray-400 hover:bg-gray-600'
                ]">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                </svg>
                <span class="max-w-[120px] truncate">{{ currentPriceListName }}</span>
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>
            <div v-if="showPriceListMenu"
                 class="absolute top-10 left-0 bg-[#2a3142] border border-gray-700 rounded-lg shadow-xl z-50 py-1 min-w-[180px]">
                <button @click="selectPriceList(null)"
                    :class="[
                        'w-full px-3 py-2 text-sm text-left flex items-center gap-2',
                        !selectedPriceListId ? 'bg-blue-500/20 text-blue-400' : 'text-gray-300 hover:bg-gray-700'
                    ]">
                    <span>Базовые цены</span>
                </button>
                <button v-for="pl in availablePriceLists" :key="pl.id"
                    @click="selectPriceList(pl.id)"
                    :class="[
                        'w-full px-3 py-2 text-sm text-left flex items-center gap-2',
                        selectedPriceListId === pl.id ? 'bg-blue-500/20 text-blue-400' : 'text-gray-300 hover:bg-gray-700'
                    ]">
                    <span>{{ pl.name }}</span>
                </button>
            </div>
            <div v-if="showPriceListMenu" @click="showPriceListMenu = false" class="fixed inset-0 z-40"></div>
        </div>

        <!-- Order tabs -->
        <div class="flex items-center gap-1.5 relative">
            <template v-for="(order, index) in orders!.slice(0, 4)" :key="(order as any).id">
                <button @click="$emit('update:currentOrderIndex', index)"
                    :class="currentOrderIndex === index ? 'bg-blue-500 text-white' : 'bg-[#2a3142] text-gray-400 hover:bg-gray-600'"
                    class="w-8 h-8 rounded-lg text-sm font-bold transition-all">
                    {{ index + 1 }}
                </button>
            </template>

            <div v-if="orders!.length > 4" class="relative">
                <button @click="showDropdown = !showDropdown"
                    :class="currentOrderIndex! >= 4 ? 'bg-blue-500 text-white' : 'bg-[#2a3142] text-gray-400 hover:bg-gray-600'"
                    class="w-8 h-8 rounded-lg text-sm font-bold transition-all">
                    <span v-if="currentOrderIndex! >= 4">{{ currentOrderIndex! + 1 }}</span>
                    <span v-else>...</span>
                </button>
                <div v-if="showDropdown"
                     class="absolute top-10 left-0 bg-[#2a3142] border border-gray-700 rounded-lg shadow-xl z-50 py-1 min-w-[140px]">
                    <button v-for="(order, index) in orders" :key="'drop-' + (order as any).id"
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

<script setup lang="ts">
import { ref, computed, PropType } from 'vue';

const props = defineProps({
    table: Object,
    linkedTableNumbers: [String, Number],
    reservation: Object,
    orders: Array,
    currentOrderIndex: Number,
    useEmitBack: { type: Boolean, default: false },
    availablePriceLists: { type: Array as PropType<any[]>, default: () => [] },
    selectedPriceListId: { type: [Number, null], default: null },
});

const emit = defineEmits(['update:currentOrderIndex', 'createNewOrder', 'back', 'changePriceList']);

const showDropdown = ref(false);
const showPriceListMenu = ref(false);

const currentPriceListName = computed(() => {
    if (!props.selectedPriceListId) return 'Прайс';
    const pl = props.availablePriceLists.find((p: any) => p.id === props.selectedPriceListId);
    return pl ? pl.name : 'Прайс';
});

const selectPriceList = (id: any) => {
    showPriceListMenu.value = false;
    emit('changePriceList', id);
};
</script>
