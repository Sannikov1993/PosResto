<template>
    <div class="border-b border-white/5 transition-all duration-300"
         :data-testid="`order-item-${item.id || item.local_id}`"
         :class="{
             'opacity-50': isCancelled,
             'bg-blue-500/10': selectMode && isSelectedForMove,
             'cursor-pointer': selectMode,
             'kitchen-flash': isFlashing
         }">
        <!-- Item row (hover area) -->
        <div class="px-3 py-2 hover:bg-gray-800/20 transition-colors"
             @click="selectMode ? $emit('toggleSelection') : null"
             @mouseenter="isHovered = true"
             @mouseleave="isHovered = false">
            <!-- Main row: name, price, quantity, total/buttons -->
            <div class="flex items-center gap-2">
                <!-- Checkbox in select mode -->
                <label v-if="selectMode" class="flex items-center cursor-pointer" @click.stop>
                    <input type="checkbox"
                           :checked="isSelectedForMove"
                           @change="$emit('toggleSelection')"
                           class="w-4 h-4 rounded border-gray-600 bg-gray-700 text-blue-500 focus:ring-blue-500 focus:ring-offset-0 cursor-pointer">
                </label>

                <!-- Status dot -->
                <span class="w-2 h-2 rounded-full flex-shrink-0" :class="statusDotClass"></span>

                <!-- Name -->
                <span class="text-gray-200 text-base flex-1 truncate"
                      :class="{ 'line-through text-gray-500': isCancelled }">
                    {{ item.name || item.dish?.name }}
                </span>

                <!-- Price/buttons container - no layout shift -->
                <div class="relative flex-shrink-0">
                    <!-- Price info (always rendered, hidden on hover when editable) -->
                    <div class="flex items-center gap-2"
                         :class="(isHovered && (canEdit || canCancel) && !selectMode) ? 'invisible' : 'visible'">
                        <span class="text-gray-500 text-sm">{{ formatPrice(item.price) }}</span>
                        <span class="text-gray-500 text-sm">×</span>
                        <span class="text-gray-400 text-sm">{{ item.quantity }} шт</span>
                        <span class="text-gray-300 text-[14px] font-semibold w-20 text-right">{{ formatPrice(item.price * item.quantity) }}</span>
                    </div>

                    <!-- Inline action buttons for pending/saved (on hover) -->
                    <div v-if="canEdit && !selectMode"
                         class="absolute inset-y-0 right-0 flex items-center gap-1"
                         :class="isHovered ? 'visible' : 'invisible'">
                        <div class="flex items-center gap-0.5">
                            <button @click.stop="$emit('updateQuantity', -1)"
                                    data-testid="item-qty-minus"
                                    class="w-7 h-7 bg-gray-700/50 text-gray-300 rounded text-base hover:bg-gray-600 flex items-center justify-center">−</button>
                            <span class="text-gray-300 text-sm w-6 text-center" data-testid="item-qty-display">{{ item.quantity }}</span>
                            <button @click.stop="$emit('updateQuantity', 1)"
                                    data-testid="item-qty-plus"
                                    class="w-7 h-7 bg-gray-700/50 text-gray-300 rounded text-base hover:bg-gray-600 flex items-center justify-center">+</button>
                        </div>

                        <button @click.stop="$emit('sendToKitchen')"
                                class="w-7 h-7 text-gray-400 hover:text-blue-500 rounded flex items-center justify-center"
                                title="На кухню">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 18.657A8 8 0 016.343 7.343S7 9 9 10c0-2 .5-5 2.986-7C14 5 16.09 5.777 17.656 7.343A7.975 7.975 0 0120 13a7.975 7.975 0 01-2.343 5.657z"/>
                            </svg>
                        </button>

                        <button v-if="hasModifiers"
                                @click.stop="$emit('openModifiers')"
                                :class="item.modifiers?.length ? 'text-green-400' : 'text-gray-400 hover:text-green-400'"
                                class="w-7 h-7 rounded flex items-center justify-center"
                                title="Модификаторы">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                            </svg>
                        </button>

                        <button @click.stop="$emit('openComment')"
                                :class="item.comment ? 'text-yellow-500' : 'text-gray-400 hover:text-yellow-500'"
                                class="w-7 h-7 rounded flex items-center justify-center"
                                title="Комментарий">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                            </svg>
                        </button>

                        <button v-if="guestsCount > 1" @click.stop="$emit('openMove')"
                                class="w-7 h-7 text-gray-400 hover:text-blue-500 rounded flex items-center justify-center"
                                title="Перенести">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                            </svg>
                        </button>

                        <button @click.stop="$emit('remove')"
                                data-testid="item-remove-btn"
                                class="w-7 h-7 text-gray-400 hover:text-red-500 rounded flex items-center justify-center"
                                title="Удалить">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                        </button>
                    </div>

                    <!-- Inline action buttons for cooking/ready/served (on hover) -->
                    <div v-if="canCancel && !selectMode"
                         class="absolute inset-y-0 right-0 flex items-center gap-1"
                         :class="isHovered ? 'visible' : 'invisible'">
                        <span class="text-gray-500 text-sm">{{ formatPrice(item.price) }}</span>
                        <span class="text-gray-500 text-sm">×</span>
                        <span class="text-gray-400 text-sm">{{ item.quantity }}</span>

                        <button v-if="guestsCount > 1" @click.stop="$emit('openMove')"
                                class="w-7 h-7 text-gray-400 hover:text-blue-500 rounded flex items-center justify-center"
                                title="Перенести">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                            </svg>
                        </button>

                        <button @click.stop="$emit('remove')"
                                class="w-7 h-7 text-gray-400 hover:text-red-500 rounded flex items-center justify-center"
                                title="Удалить">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Modifiers display as sub-items -->
            <div v-if="item.modifiers?.length" class="mt-0.5">
                <div
                    v-for="mod in item.modifiers"
                    :key="mod.id || mod.option_id"
                    class="flex items-center gap-2 text-[12px] text-gray-500 pl-4"
                >
                    <span class="flex-1 truncate">+ {{ mod.option_name || mod.name }}</span>
                    <span class="text-gray-600">× 1</span>
                    <span class="w-14 text-right text-gray-600">{{ mod.price > 0 ? formatPrice(mod.price) : '0 ₽' }}</span>
                </div>
            </div>

            <!-- Comment -->
            <div v-if="item.comment" class="text-yellow-500 text-xs mt-0.5 italic">
                💬 {{ item.comment }}
            </div>
        </div>

        <!-- Serve button for ready items (outside hover area) -->
        <div v-if="item.status === 'ready'" class="flex items-center px-3 pb-2">
            <button @click.stop="$emit('markServed')"
                    class="flex-1 py-2 bg-gradient-to-r from-green-500/10 to-green-400/5 border border-green-500/30 text-green-400 rounded-lg text-sm font-medium hover:from-green-500/20 hover:to-green-400/10 hover:border-green-400/50 transition-all duration-200 flex items-center justify-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                Подать гостю
            </button>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { FLASH_DURATION } from '../../shared/config/uiConfig.js';

const props = defineProps({
    item: {
        type: Object as any,
        default: () => ({})
    },
    guest: Object,
    guestsCount: {
        type: Number,
        default: 0
    },
    selectMode: Boolean,
    isSelectedForMove: Boolean,
    hasModifiers: Boolean
});

defineEmits([
    'updateQuantity',
    'remove',
    'sendToKitchen',
    'openComment',
    'openMove',
    'markServed',
    'toggleSelection',
    'openModifiers'
]);

const isHovered = ref(false);
const isFlashing = ref(false);
const prevStatus = ref(props.item?.status);

// Watch for status change to trigger flash animation
watch(() => props.item?.status, (newStatus, oldStatus) => {
    if (oldStatus === 'pending' && newStatus === 'cooking') {
        isFlashing.value = true;
        setTimeout(() => { isFlashing.value = false; }, FLASH_DURATION);
    }
    if (oldStatus === 'cooking' && newStatus === 'ready') {
        isFlashing.value = true;
        setTimeout(() => { isFlashing.value = false; }, FLASH_DURATION);
    }
    prevStatus.value = newStatus;
});

const isCancelled = computed(() => ['cancelled', 'voided'].includes(props.item.status));
const canEdit = computed(() => ['pending', 'saved'].includes(props.item.status));
const canCancel = computed(() => ['cooking', 'ready', 'served'].includes(props.item.status));

const statusDotClass = computed(() => {
    const classes: Record<string, string> = {
        pending: 'bg-blue-500',
        cooking: 'bg-orange-500',
        ready: 'bg-green-500',
        served: 'bg-purple-500',
        cancelled: 'bg-gray-500',
        voided: 'bg-gray-500',
        saved: 'bg-indigo-500'
    };
    return classes[props.item.status] || 'bg-gray-500';
});

const formatPrice = (price: any) => {
    return new Intl.NumberFormat('ru-RU').format(price || 0) + ' ₽';
};
</script>

<style scoped>
.kitchen-flash {
    animation: kitchenFlash 0.6s ease-out;
}

@keyframes kitchenFlash {
    0% { background-color: rgba(249, 115, 22, 0.3); }
    50% { background-color: rgba(249, 115, 22, 0.15); }
    100% { background-color: transparent; }
}
</style>
