<template>
    <Teleport to="body">
        <!-- Backdrop -->
        <Transition name="fade">
            <div
                v-if="show"
                class="fixed inset-0 bg-black/60 z-[70]"
                @click="$emit('close')"
            ></div>
        </Transition>

        <!-- Drawer -->
        <Transition name="slide-right">
            <div
                v-if="show"
                class="fixed top-0 right-0 bottom-0 w-[85vw] max-w-[320px] bg-gray-800 z-[80] flex flex-col shadow-2xl safe-area-right"
            >
                <!-- Header -->
                <div class="flex items-center justify-between p-4 border-b border-gray-700">
                    <h2 class="text-xl font-bold text-white">Настройки</h2>
                    <button
                        @click="$emit('close')"
                        class="w-12 h-12 flex items-center justify-center rounded-xl bg-gray-700 active:bg-gray-600 text-2xl text-white"
                    >
                        ×
                    </button>
                </div>

                <!-- Content -->
                <div class="flex-1 overflow-y-auto p-4 space-y-4">
                    <!-- Current Time -->
                    <div class="bg-gray-700/50 rounded-2xl p-4 text-center">
                        <p class="text-4xl font-bold text-white">{{ currentTime }}</p>
                        <p class="text-lg text-gray-400 mt-1">{{ currentDate }}</p>
                    </div>

                    <!-- Date Selector Slot -->
                    <div class="bg-gray-700/50 rounded-2xl p-4">
                        <p class="text-sm text-gray-400 mb-3">Дата заказов</p>
                        <slot name="date-selector"></slot>
                    </div>

                    <!-- Stop List Slot -->
                    <div class="bg-gray-700/50 rounded-2xl p-4">
                        <p class="text-sm text-gray-400 mb-3">Стоп-лист</p>
                        <slot name="stop-list"></slot>
                    </div>

                    <!-- Settings Toggles -->
                    <div class="space-y-2">
                        <p class="text-sm text-gray-400 px-1">Настройки отображения</p>

                        <!-- Single Column Mode -->
                        <button
                            @click="$emit('toggle-single-column')"
                            class="w-full min-h-[56px] px-4 py-3 flex items-center justify-between bg-gray-700/50 hover:bg-gray-700 active:bg-gray-600 rounded-xl transition"
                        >
                            <div class="flex items-center gap-3">
                                <span class="text-2xl">📱</span>
                                <span class="text-white text-lg">Одна колонка</span>
                            </div>
                            <ToggleSwitch :enabled="singleColumnMode" color="cyan" />
                        </button>

                        <!-- Compact Mode -->
                        <button
                            @click="$emit('toggle-compact')"
                            class="w-full min-h-[56px] px-4 py-3 flex items-center justify-between bg-gray-700/50 hover:bg-gray-700 active:bg-gray-600 rounded-xl transition"
                        >
                            <div class="flex items-center gap-3">
                                <span class="text-2xl">📐</span>
                                <span class="text-white text-lg">Компактный вид</span>
                            </div>
                            <ToggleSwitch :enabled="compactMode" color="purple" />
                        </button>

                        <!-- Focus Mode -->
                        <button
                            @click="$emit('toggle-focus')"
                            class="w-full min-h-[56px] px-4 py-3 flex items-center justify-between bg-gray-700/50 hover:bg-gray-700 active:bg-gray-600 rounded-xl transition"
                        >
                            <div class="flex items-center gap-3">
                                <span class="text-2xl">🎯</span>
                                <span class="text-white text-lg">Режим фокуса</span>
                            </div>
                            <ToggleSwitch :enabled="focusMode" color="orange" />
                        </button>

                        <!-- Sound -->
                        <button
                            @click="$emit('toggle-sound')"
                            class="w-full min-h-[56px] px-4 py-3 flex items-center justify-between bg-gray-700/50 hover:bg-gray-700 active:bg-gray-600 rounded-xl transition"
                        >
                            <div class="flex items-center gap-3">
                                <span class="text-2xl">{{ soundEnabled ? '🔔' : '🔕' }}</span>
                                <span class="text-white text-lg">Звук уведомлений</span>
                            </div>
                            <ToggleSwitch :enabled="soundEnabled" color="green" />
                        </button>

                        <!-- Auto Responsive -->
                        <button
                            @click="$emit('toggle-auto-responsive')"
                            class="w-full min-h-[56px] px-4 py-3 flex items-center justify-between bg-gray-700/50 hover:bg-gray-700 active:bg-gray-600 rounded-xl transition"
                        >
                            <div class="flex items-center gap-3">
                                <span class="text-2xl">📲</span>
                                <div class="text-left">
                                    <span class="text-white text-lg block">Авто-адаптация</span>
                                    <span class="text-gray-400 text-sm">Подстраиваться под экран</span>
                                </div>
                            </div>
                            <ToggleSwitch :enabled="autoResponsiveEnabled" color="blue" />
                        </button>
                    </div>
                </div>

                <!-- Footer Actions -->
                <div class="p-4 border-t border-gray-700 space-y-2 safe-area-bottom">
                    <!-- Fullscreen -->
                    <button
                        @click="$emit('toggle-fullscreen'); $emit('close')"
                        class="w-full min-h-[56px] px-4 py-3 flex items-center justify-center gap-3 bg-gray-700 hover:bg-gray-600 active:bg-gray-500 rounded-xl transition text-white text-lg font-medium"
                    >
                        <span class="text-2xl">⛶</span>
                        Полный экран
                    </button>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup>
/**
 * Mobile Settings Drawer Component
 *
 * Slide-in drawer for mobile devices containing all settings.
 * Touch-friendly with 48px+ tap targets.
 */

import ToggleSwitch from './ToggleSwitch.vue';

defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    currentTime: {
        type: String,
        default: '',
    },
    currentDate: {
        type: String,
        default: '',
    },
    singleColumnMode: {
        type: Boolean,
        default: false,
    },
    compactMode: {
        type: Boolean,
        default: false,
    },
    focusMode: {
        type: Boolean,
        default: false,
    },
    soundEnabled: {
        type: Boolean,
        default: true,
    },
    autoResponsiveEnabled: {
        type: Boolean,
        default: true,
    },
});

defineEmits([
    'close',
    'toggle-single-column',
    'toggle-compact',
    'toggle-focus',
    'toggle-sound',
    'toggle-fullscreen',
    'toggle-auto-responsive',
]);
</script>

<style scoped>
/* Slide from right animation */
.slide-right-enter-active,
.slide-right-leave-active {
    transition: transform 0.3s ease;
}

.slide-right-enter-from,
.slide-right-leave-to {
    transform: translateX(100%);
}

/* Fade animation for backdrop */
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

/* Safe area support for notched devices */
.safe-area-right {
    padding-right: env(safe-area-inset-right, 0);
}

.safe-area-bottom {
    padding-bottom: env(safe-area-inset-bottom, 0);
}
</style>
