<template>
    <!-- Mobile: Fixed bottom navigation -->
    <nav
        v-if="isMobile"
        class="fixed bottom-0 left-0 right-0 z-50 bg-gray-800 border-t border-gray-700 safe-area-bottom"
    >
        <div class="flex">
            <button
                @click="$emit('select', 'new')"
                :class="[
                    'flex-1 py-4 flex flex-col items-center justify-center gap-1 transition min-h-[64px]',
                    activeColumn === 'new' ? 'bg-blue-500/20 text-blue-400' : 'text-gray-400 active:bg-gray-700'
                ]"
            >
                <span class="text-2xl">📥</span>
                <div class="flex items-center gap-1">
                    <span class="text-xs font-medium sm:text-sm">Новые</span>
                    <span
                        :class="[
                            'px-1.5 py-0.5 rounded-full text-xs font-bold min-w-[20px] text-center',
                            activeColumn === 'new' ? 'bg-blue-500 text-white' : 'bg-gray-600 text-gray-300'
                        ]"
                    >
                        {{ newCount }}
                    </span>
                </div>
            </button>

            <button
                @click="$emit('select', 'cooking')"
                :class="[
                    'flex-1 py-4 flex flex-col items-center justify-center gap-1 transition min-h-[64px]',
                    activeColumn === 'cooking' ? 'bg-orange-500/20 text-orange-400' : 'text-gray-400 active:bg-gray-700'
                ]"
            >
                <span class="text-2xl">🔥</span>
                <div class="flex items-center gap-1">
                    <span class="text-xs font-medium sm:text-sm">Готовятся</span>
                    <span
                        :class="[
                            'px-1.5 py-0.5 rounded-full text-xs font-bold min-w-[20px] text-center',
                            activeColumn === 'cooking' ? 'bg-orange-500 text-white' : 'bg-gray-600 text-gray-300'
                        ]"
                    >
                        {{ cookingCount }}
                    </span>
                </div>
            </button>

            <button
                @click="$emit('select', 'ready')"
                :class="[
                    'flex-1 py-4 flex flex-col items-center justify-center gap-1 transition min-h-[64px]',
                    activeColumn === 'ready' ? 'bg-green-500/20 text-green-400' : 'text-gray-400 active:bg-gray-700'
                ]"
            >
                <span class="text-2xl">✅</span>
                <div class="flex items-center gap-1">
                    <span class="text-xs font-medium sm:text-sm">Готовы</span>
                    <span
                        :class="[
                            'px-1.5 py-0.5 rounded-full text-xs font-bold min-w-[20px] text-center',
                            activeColumn === 'ready' ? 'bg-green-500 text-white' : 'bg-gray-600 text-gray-300'
                        ]"
                    >
                        {{ readyCount }}
                    </span>
                </div>
            </button>
        </div>
    </nav>

    <!-- Tablet/Desktop: Relative top tabs -->
    <div v-else class="flex gap-2 mb-4">
        <button
            @click="$emit('select', 'new')"
            :class="[
                'flex-1 py-3 rounded-xl text-lg lg:text-xl font-bold transition flex items-center justify-center gap-2',
                activeColumn === 'new' ? 'bg-blue-500 text-white' : 'bg-gray-700 text-gray-400 hover:bg-gray-600'
            ]"
        >
            📥 <span class="hidden sm:inline">Новые</span>
            <span
                :class="[
                    'px-2 py-0.5 rounded-full text-sm',
                    activeColumn === 'new' ? 'bg-white text-blue-500' : 'bg-gray-600'
                ]"
            >
                {{ newCount }}
            </span>
        </button>

        <button
            @click="$emit('select', 'cooking')"
            :class="[
                'flex-1 py-3 rounded-xl text-lg lg:text-xl font-bold transition flex items-center justify-center gap-2',
                activeColumn === 'cooking' ? 'bg-orange-500 text-white' : 'bg-gray-700 text-gray-400 hover:bg-gray-600'
            ]"
        >
            🔥 <span class="hidden sm:inline">Готовятся</span>
            <span
                :class="[
                    'px-2 py-0.5 rounded-full text-sm',
                    activeColumn === 'cooking' ? 'bg-white text-orange-500' : 'bg-gray-600'
                ]"
            >
                {{ cookingCount }}
            </span>
        </button>

        <button
            @click="$emit('select', 'ready')"
            :class="[
                'flex-1 py-3 rounded-xl text-lg lg:text-xl font-bold transition flex items-center justify-center gap-2',
                activeColumn === 'ready' ? 'bg-green-500 text-white' : 'bg-gray-700 text-gray-400 hover:bg-gray-600'
            ]"
        >
            ✅ <span class="hidden sm:inline">Готовы</span>
            <span
                :class="[
                    'px-2 py-0.5 rounded-full text-sm',
                    activeColumn === 'ready' ? 'bg-white text-green-500' : 'bg-gray-600'
                ]"
            >
                {{ readyCount }}
            </span>
        </button>
    </div>
</template>

<script setup lang="ts">
/**
 * Column Tabs Component
 *
 * Tab navigation for single-column mode.
 * Mobile: Fixed to bottom with safe-area padding
 * Tablet+: Relative position as before
 */

defineProps({
    activeColumn: {
        type: String,
        default: 'new',
        validator: (value) => ['new', 'cooking', 'ready'].includes(value as any),
    },
    newCount: {
        type: Number,
        default: 0,
    },
    cookingCount: {
        type: Number,
        default: 0,
    },
    readyCount: {
        type: Number,
        default: 0,
    },
    isMobile: {
        type: Boolean,
        default: false,
    },
});

defineEmits(['select']);
</script>

<style scoped>
/* Safe area support for notched devices */
.safe-area-bottom {
    padding-bottom: env(safe-area-inset-bottom, 0);
}
</style>
