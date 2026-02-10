<template>
    <div :class="['flex flex-col transition-all duration-300', collapsed ? 'w-12 flex-none' : 'flex-1 min-w-0']">
        <!-- Collapsed state -->
        <div
            v-if="collapsed"
            :class="[`bg-${color}-500`, 'rounded-2xl flex-1 flex flex-col items-center py-4 cursor-pointer hover:opacity-90 transition']"
            @click="toggleCollapse()"
        >
            <span class="text-2xl mb-2">{{ icon }}</span>
            <div class="flex-1 flex items-center justify-center">
                <span class="text-white font-bold text-sm whitespace-nowrap vertical-text">{{ title }}</span>
            </div>
            <span :class="[`bg-white text-${color}-500`, 'w-8 h-8 rounded-full text-sm font-bold flex items-center justify-center mt-2']">
                {{ orders.length }}
            </span>
        </div>

        <!-- Expanded state -->
        <template v-else>
            <div
                :class="[
                    `bg-${color}-500`,
                    'text-white px-4 py-3 rounded-t-2xl font-bold text-xl md:text-2xl flex items-center justify-between',
                    collapsible ? 'cursor-pointer hover:opacity-90 transition' : ''
                ]"
                @click="collapsible && toggleCollapse()"
            >
                <span>{{ icon }} {{ title }}</span>
                <div class="flex items-center gap-2">
                    <span :class="[`bg-white text-${color}-500`, 'px-3 py-1 rounded-full text-lg md:text-xl font-bold']">{{ orders.length }}</span>
                    <span v-if="collapsible" class="text-white/70 ml-1 text-sm">▶</span>
                </div>
            </div>
            <div class="bg-gray-800 rounded-b-2xl flex-1 overflow-y-auto p-3 md:p-4 space-y-4 @container">
                <template v-if="orders.length > 0">
                    <slot name="card" v-for="order in orders" :key="order.id" :order="order"></slot>
                </template>
                <div v-else class="flex flex-col items-center justify-center h-full text-gray-600">
                    <p class="text-6xl mb-4">{{ emptyIcon }}</p>
                    <p class="text-xl">{{ emptyText }}</p>
                </div>
            </div>
        </template>
    </div>
</template>

<script setup lang="ts">
import { ref, PropType } from 'vue';

const props = defineProps({
    title: {
        type: String,
        required: true,
        validator: (v: any) => v && v.length > 0,
    },
    icon: {
        type: String,
        required: true,
    },
    color: {
        type: String,
        required: true,
        validator: (v: any) => ['blue', 'green', 'orange', 'red', 'yellow', 'purple', 'gray', 'amber'].includes(v),
    },
    orders: {
        type: Array as PropType<any[]>,
        default: () => [],
        validator: (arr: any) => Array.isArray(arr),
    },
    emptyIcon: {
        type: String,
        default: '📭',
    },
    emptyText: {
        type: String,
        default: 'Нет заказов',
    },
    collapsible: {
        type: Boolean,
        default: false,
    },
});

const collapsed = ref(false);

const toggleCollapse = () => {
    collapsed.value = !collapsed.value;
};
</script>

<style scoped>
.vertical-text {
    writing-mode: vertical-rl;
    text-orientation: mixed;
    transform: rotate(180deg);
}
</style>
