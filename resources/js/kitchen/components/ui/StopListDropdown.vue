<template>
    <div v-if="!focusMode" :class="inline ? '' : 'relative'">
        <!-- Toggle Button (not shown in inline mode) -->
        <button
            v-if="!inline"
            @click="$emit('toggle')"
            :class="[
                'p-2 sm:p-3 rounded-xl text-xl sm:text-2xl transition relative',
                stopList.length > 0 ? 'bg-red-500/20 text-red-400' : 'bg-gray-700 text-gray-500'
            ]"
        >
            🚫
            <span
                v-if="stopList.length > 0"
                class="absolute -top-1 -right-1 bg-red-500 text-white text-xs font-bold w-5 h-5 rounded-full flex items-center justify-center"
            >
                {{ stopList.length }}
            </span>
        </button>

        <!-- Inline header for mobile drawer -->
        <div v-if="inline" class="flex items-center justify-between mb-2">
            <div class="flex items-center gap-2">
                <span class="text-xl">🚫</span>
                <span class="font-medium text-gray-300">Стоп-лист</span>
            </div>
            <span :class="[
                'px-2 py-0.5 rounded-full text-sm font-medium',
                stopList.length > 0 ? 'bg-red-500/20 text-red-400' : 'bg-gray-600 text-gray-400'
            ]">
                {{ stopList.length }} поз.
            </span>
        </div>

        <!-- Dropdown / Content -->
        <div
            v-if="show || inline"
            :class="[
                inline
                    ? 'bg-gray-700/30 rounded-xl overflow-hidden'
                    : 'absolute right-0 top-full mt-2 w-72 sm:w-80 bg-gray-800 rounded-xl shadow-2xl border border-gray-700 overflow-hidden z-50'
            ]"
        >
            <!-- Header (not shown in inline mode - already shown above) -->
            <div v-if="!inline" class="px-3 sm:px-4 py-2 sm:py-3 bg-red-500/20 border-b border-gray-700 flex items-center justify-between">
                <span class="font-bold text-red-400">🚫 Стоп-лист</span>
                <span class="text-sm text-gray-400">{{ stopList.length }} поз.</span>
            </div>

            <div v-if="stopList.length === 0" class="p-4 text-center text-gray-500">
                <p class="text-2xl mb-2">✨</p>
                <p class="text-sm sm:text-base">Все блюда доступны</p>
            </div>

            <div v-else :class="['overflow-y-auto divide-y divide-gray-700/50', inline ? 'max-h-48' : 'max-h-80']">
                <div
                    v-for="item in stopList"
                    :key="item.id"
                    class="px-3 sm:px-4 py-2 sm:py-3 hover:bg-gray-700/50"
                >
                    <div class="flex items-center gap-2 sm:gap-3">
                        <div :class="['rounded-lg bg-gray-700 overflow-hidden flex-shrink-0', inline ? 'w-8 h-8' : 'w-10 h-10']">
                            <img
                                v-if="item.dish?.image"
                                :src="item.dish.image"
                                :alt="item.dish?.name"
                                class="w-full h-full object-cover opacity-50"
                            />
                            <div v-else class="w-full h-full flex items-center justify-center text-sm sm:text-lg">
                                🍽️
                            </div>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="font-medium text-white truncate text-sm sm:text-base">{{ item.dish?.name }}</p>
                            <p class="text-xs text-gray-400 truncate">{{ item.reason }}</p>
                        </div>
                    </div>
                    <div v-if="item.resume_at" class="mt-1 text-xs text-yellow-400 pl-10 sm:pl-13">
                        ⏰ До {{ formatStopListTime(item.resume_at) }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
/**
 * Stop List Dropdown Component
 *
 * Shows dishes currently unavailable.
 * Supports inline mode for mobile drawer.
 */

import { formatStopListTime } from '../../utils/format.js';

defineProps({
    stopList: {
        type: Array,
        default: () => [],
    },
    show: {
        type: Boolean,
        default: false,
    },
    focusMode: {
        type: Boolean,
        default: false,
    },
    inline: {
        type: Boolean,
        default: false,
    },
});

defineEmits(['toggle']);
</script>
