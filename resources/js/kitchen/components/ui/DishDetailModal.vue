<template>
    <Teleport to="body">
        <!-- Backdrop -->
        <Transition name="fade">
            <div
                v-if="show"
                class="fixed inset-0 bg-black/80 z-[60]"
                @click.self="$emit('close')"
            ></div>
        </Transition>

        <!-- Modal / Bottom Sheet -->
        <Transition :name="isMobile ? 'slide-up' : 'scale'">
            <div
                v-if="show"
                :class="[
                    'fixed z-[60] bg-gray-800 shadow-2xl overflow-hidden',
                    isMobile
                        ? 'inset-x-0 bottom-0 rounded-t-3xl max-h-[90vh] safe-area-bottom'
                        : 'inset-4 sm:inset-auto sm:top-1/2 sm:left-1/2 sm:-translate-x-1/2 sm:-translate-y-1/2 rounded-3xl sm:max-w-2xl sm:w-full sm:max-h-[90vh]'
                ]"
            >
                <!-- Mobile drag handle -->
                <div v-if="isMobile" class="flex justify-center pt-3 pb-1">
                    <div class="w-12 h-1.5 bg-gray-600 rounded-full"></div>
                </div>

                <!-- Header with close button -->
                <div class="relative">
                    <!-- Dish Image -->
                    <div :class="['bg-gray-700 relative overflow-hidden', isMobile ? 'h-48' : 'h-56 sm:h-64']">
                        <img
                            v-if="dish?.image"
                            :src="dish.image"
                            :alt="dish.name"
                            class="w-full h-full object-cover"
                        />
                        <div v-else class="w-full h-full flex items-center justify-center">
                            <span :class="['opacity-50', isMobile ? 'text-6xl' : 'text-7xl sm:text-8xl']">🍽️</span>
                        </div>
                        <!-- Gradient overlay -->
                        <div class="absolute inset-0 bg-gradient-to-t from-gray-800 via-transparent to-transparent"></div>
                    </div>

                    <!-- Close button -->
                    <button
                        @click="$emit('close')"
                        :class="[
                            'absolute top-3 right-3 sm:top-4 sm:right-4 bg-black/50 hover:bg-black/70 active:bg-black/90 rounded-full flex items-center justify-center text-white transition',
                            isMobile ? 'w-12 h-12 text-2xl' : 'w-10 h-10 sm:w-12 sm:h-12 text-xl sm:text-2xl'
                        ]"
                    >
                        ×
                    </button>

                    <!-- Dish name overlay -->
                    <div class="absolute bottom-0 left-0 right-0 p-4 sm:p-6">
                        <h2 :class="['font-bold text-white drop-shadow-lg', isMobile ? 'text-2xl' : 'text-2xl sm:text-3xl']">{{ dish?.name }}</h2>
                        <div class="flex items-center gap-3 sm:gap-4 mt-2 text-gray-300 text-sm sm:text-base">
                            <span v-if="dish?.cooking_time" class="flex items-center gap-1">
                                <span>⏱️</span> {{ dish.cooking_time }} мин
                            </span>
                            <span v-if="dish?.weight" class="flex items-center gap-1">
                                <span>⚖️</span> {{ dish.weight }} г
                            </span>
                            <span v-if="dish?.calories" class="flex items-center gap-1">
                                <span>🔥</span> {{ dish.calories }} ккал
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Content -->
                <div :class="['overflow-y-auto', isMobile ? 'p-4 max-h-[50vh]' : 'p-4 sm:p-6 max-h-[40vh]']">
                    <!-- Tags -->
                    <div v-if="dish?.is_spicy || dish?.is_vegetarian || dish?.is_vegan" class="flex flex-wrap gap-2 mb-4">
                        <span v-if="dish.is_spicy" class="px-2 sm:px-3 py-1 bg-red-500/20 text-red-400 rounded-full text-xs sm:text-sm">
                            🌶️ Острое
                        </span>
                        <span v-if="dish.is_vegetarian" class="px-2 sm:px-3 py-1 bg-green-500/20 text-green-400 rounded-full text-xs sm:text-sm">
                            🌱 Вегетарианское
                        </span>
                        <span v-if="dish.is_vegan" class="px-2 sm:px-3 py-1 bg-teal-500/20 text-teal-400 rounded-full text-xs sm:text-sm">
                            🥗 Веганское
                        </span>
                    </div>

                    <!-- Description / Recipe -->
                    <div v-if="dish?.description" class="mb-4 sm:mb-6">
                        <h3 class="text-base sm:text-lg font-semibold text-gray-300 mb-2 flex items-center gap-2">
                            <span>📝</span> Описание / Рецепт
                        </h3>
                        <p class="text-gray-400 whitespace-pre-line leading-relaxed text-sm sm:text-base">{{ dish.description }}</p>
                    </div>

                    <!-- Nutritional info -->
                    <div v-if="dish?.proteins || dish?.fats || dish?.carbs" class="mb-4">
                        <h3 class="text-base sm:text-lg font-semibold text-gray-300 mb-2 sm:mb-3 flex items-center gap-2">
                            <span>🧪</span> Пищевая ценность (на 100г)
                        </h3>
                        <div class="grid grid-cols-3 gap-2 sm:gap-3">
                            <div class="bg-gray-700/50 rounded-lg sm:rounded-xl p-2 sm:p-3 text-center">
                                <p class="text-xl sm:text-2xl font-bold text-blue-400">{{ dish.proteins || 0 }}</p>
                                <p class="text-xs text-gray-500">Белки, г</p>
                            </div>
                            <div class="bg-gray-700/50 rounded-lg sm:rounded-xl p-2 sm:p-3 text-center">
                                <p class="text-xl sm:text-2xl font-bold text-yellow-400">{{ dish.fats || 0 }}</p>
                                <p class="text-xs text-gray-500">Жиры, г</p>
                            </div>
                            <div class="bg-gray-700/50 rounded-lg sm:rounded-xl p-2 sm:p-3 text-center">
                                <p class="text-xl sm:text-2xl font-bold text-green-400">{{ dish.carbs || 0 }}</p>
                                <p class="text-xs text-gray-500">Углеводы, г</p>
                            </div>
                        </div>
                    </div>

                    <!-- Modifiers if present -->
                    <div v-if="modifiers?.length" class="mb-4">
                        <h3 class="text-base sm:text-lg font-semibold text-gray-300 mb-2 flex items-center gap-2">
                            <span>➕</span> Модификаторы в заказе
                        </h3>
                        <div class="space-y-1">
                            <p v-for="mod in modifiers" :key="mod.id" class="text-blue-300 text-sm sm:text-base">
                                + {{ mod.option_name || mod.name }}
                            </p>
                        </div>
                    </div>

                    <!-- Item comment if present -->
                    <div v-if="comment" class="bg-yellow-500/10 border border-yellow-500/30 rounded-lg sm:rounded-xl p-3 sm:p-4">
                        <h3 class="text-base sm:text-lg font-semibold text-yellow-400 mb-1 flex items-center gap-2">
                            <span>💬</span> Комментарий к заказу
                        </h3>
                        <p class="text-yellow-300 text-sm sm:text-base">{{ comment }}</p>
                    </div>

                    <!-- No description placeholder -->
                    <div v-if="!dish?.description && !dish?.proteins" class="text-center py-6 sm:py-8 text-gray-500">
                        <p class="text-3xl sm:text-4xl mb-2">📋</p>
                        <p class="text-sm sm:text-base">Описание не добавлено</p>
                    </div>
                </div>

                <!-- Footer -->
                <div :class="['border-t border-gray-700 flex justify-end', isMobile ? 'p-3' : 'p-4']">
                    <button
                        @click="$emit('close')"
                        :class="[
                            'bg-gray-700 hover:bg-gray-600 active:bg-gray-500 rounded-xl font-medium transition',
                            isMobile ? 'w-full py-4 text-lg' : 'px-6 py-3'
                        ]"
                    >
                        Закрыть
                    </button>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup lang="ts">
import type { PropType } from 'vue';
/**
 * Dish Detail Modal Component
 *
 * Shows dish details including image, recipe, and nutrition.
 * Mobile: Bottom sheet style (slide up, rounded top)
 * Desktop: Centered modal
 */

defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    dish: {
        type: Object as PropType<Record<string, any>>,
        default: null as any,
    },
    modifiers: {
        type: Array as PropType<any[]>,
        default: () => [],
    },
    comment: {
        type: String,
        default: '',
    },
    isMobile: {
        type: Boolean,
        default: false,
    },
});

defineEmits(['close']);
</script>

<style scoped>
/* Scale animation for desktop */
.scale-enter-active,
.scale-leave-active {
    transition: transform 0.2s ease-out, opacity 0.2s ease-out;
}

.scale-enter-from,
.scale-leave-to {
    transform: scale(0.95);
    opacity: 0;
}

/* Slide up animation for mobile */
.slide-up-enter-active,
.slide-up-leave-active {
    transition: transform 0.3s ease-out;
}

.slide-up-enter-from,
.slide-up-leave-to {
    transform: translateY(100%);
}

/* Fade animation for backdrop */
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

/* Safe area support for notched devices */
.safe-area-bottom {
    padding-bottom: env(safe-area-inset-bottom, 0);
}
</style>
