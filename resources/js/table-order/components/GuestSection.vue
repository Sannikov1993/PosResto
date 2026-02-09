<template>
    <div class="guest-section border-b border-white/10" :class="{ collapsed: guest.collapsed }">
        <!-- Guest header -->
        <div class="px-3 py-2 flex items-center gap-2 cursor-pointer hover:bg-gray-800/30 transition-colors group"
             :class="{ 'bg-blue-500/10 border-l-2 border-blue-500': isSelected }"
             @click="actions.selectGuest(guest.number)">
            <span class="collapse-icon text-gray-600 text-xs transition-transform duration-200 w-3"
                  :class="{ 'rotate-[-90deg]': guest.collapsed }"
                  @click.stop="actions.toggleGuestCollapse(guest)">▼</span>
            <span :class="['text-base font-medium', guest.isPaid ? 'text-gray-500' : 'text-gray-200']">Гость {{ guest.number }}</span>

            <!-- Paid badge -->
            <span v-if="guest.isPaid"
                  class="bg-green-600/20 text-green-400 text-[10px] px-2 py-0.5 rounded font-medium border border-green-600/30">
                ✓ Оплачено
            </span>

            <!-- Pending items badge -->
            <span v-if="pendingCount > 0 && !guest.isPaid"
                  class="bg-blue-500 text-white text-[10px] px-2 py-0.5 rounded font-medium">
                новые {{ pendingCount }}
            </span>

            <!-- Ready items badge -->
            <span v-if="readyCount > 0 && !guest.isPaid"
                  class="bg-green-500 text-white text-[10px] px-2 py-0.5 rounded font-medium">
                🍽️ подать {{ readyCount }}
            </span>

            <!-- Select mode button -->
            <button v-if="!selectMode && guest.items.length > 0 && guestsCount > 1 && !guest.isPaid"
                    @click.stop="actions.startSelectMode(guest.number)"
                    class="opacity-0 group-hover:opacity-100 px-2 py-0.5 text-gray-500 hover:text-blue-400 text-xs transition-all"
                    title="Выбрать для переноса">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                </svg>
            </button>

            <span :class="['text-base ml-auto font-bold', guest.isPaid ? 'text-green-400/70 line-through' : 'text-white']">{{ formatPrice(guest.total) }}</span>
        </div>

        <!-- Multi-select panel -->
        <div v-if="selectMode && selectModeGuest === guest.number"
             class="px-3 py-2 bg-blue-500/10 border-b border-blue-500/30 flex items-center gap-2">
            <button @click="actions.selectAllGuestItems(guest)"
                    class="px-2 py-1 text-xs text-blue-400 hover:bg-blue-500/20 rounded transition-colors">
                Все
            </button>
            <button @click="actions.deselectAllItems()"
                    class="px-2 py-1 text-xs text-gray-400 hover:bg-gray-500/20 rounded transition-colors">
                Сбросить
            </button>
            <span class="text-gray-500 text-xs">|</span>
            <span class="text-gray-400 text-xs">Выбрано: {{ selectedItems.length }}</span>
            <div class="ml-auto flex items-center gap-2">
                <button v-if="selectedItems.length > 0"
                        @click="actions.openBulkMoveModal()"
                        class="px-3 py-1 bg-blue-500 text-white text-xs rounded hover:bg-blue-600 transition-colors">
                    Перенести
                </button>
                <button @click="actions.cancelSelectMode()"
                        class="px-2 py-1 text-gray-500 hover:text-white text-xs transition-colors">
                    ✕
                </button>
            </div>
        </div>

        <!-- Guest items -->
        <div v-if="!guest.collapsed" :class="['guest-items', guest.isPaid ? 'opacity-50 pointer-events-none' : '']">
            <div v-if="guest.items.length === 0" class="px-4 py-3 text-center">
                <p class="text-gray-600 text-sm">Нет позиций</p>
            </div>

            <OrderItem
                v-for="item in guest.items"
                :key="item.id"
                :item="item"
                :guest="guest"
                :guestsCount="guestsCount"
                :selectMode="selectMode && selectModeGuest === guest.number"
                :isSelectedForMove="selectedItems.some(i => i.id === item.id)"
                :hasModifiers="itemHasModifiers(item)"
                @updateQuantity="actions.updateItemQuantity(item, $event)"
                @remove="actions.removeItem(item)"
                @sendToKitchen="actions.sendItemToKitchen(item)"
                @openComment="actions.openCommentModal(item)"
                @openMove="actions.openMoveModal(item, guest)"
                @markServed="actions.markItemServed(item)"
                @toggleSelection="actions.toggleItemSelection(item)"
                @openModifiers="actions.openModifiersModal(item)"
            />
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import OrderItem from './OrderItem.vue';
import { useOrderActions, useOrderState } from '../composables/useOrderContext';

const actions = useOrderActions();
const { selectMode, selectModeGuest, selectedItems, categories, roundAmounts } = useOrderState();

const props = defineProps({
    guest: Object,
    isSelected: Boolean,
    guestsCount: Number,
});

// Check if item has available modifiers
const itemHasModifiers = (item) => {
    if (item.modifiers?.length) return true;

    const dishId = item.dish_id || item.dish?.id;
    if (!dishId) return false;

    for (const cat of categories.value) {
        const dish = cat.products?.find(p => p.id === dishId);
        if (dish?.modifiers?.length) return true;

        for (const product of (cat.products || [])) {
            if (product.variants?.some(v => v.id === dishId)) {
                if (product.modifiers?.length) return true;
            }
        }
    }

    return false;
};

const pendingCount = computed(() => props.guest.items.filter(i => i.status === 'pending').length);
const readyCount = computed(() => props.guest.items.filter(i => i.status === 'ready').length);

const formatPrice = (price) => {
    let num = parseFloat(price) || 0;
    if (roundAmounts.value) {
        num = Math.floor(num);
    }
    return new Intl.NumberFormat('ru-RU').format(num) + ' ₽';
};
</script>
