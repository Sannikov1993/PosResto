<template>
    <header
        :class="[
            'bg-gray-800 sticky top-0 z-50 shadow-lg transition-all',
            'px-3 sm:px-4 lg:px-6',
            focusMode ? 'py-2' : 'py-2 sm:py-3 lg:py-4'
        ]"
    >
        <div class="flex items-center justify-between">
            <!-- Left side - Logo and Station -->
            <div class="flex items-center gap-2 sm:gap-3 lg:gap-4 min-w-0">
                <!-- Logo - hidden on mobile and tablet, visible on desktop -->
                <img
                    v-if="!focusMode"
                    src="/images/logo/menulab_logo_dark_bg.svg"
                    alt="MenuLab"
                    class="h-8 lg:h-10 hidden lg:block"
                />
                <div v-if="!focusMode" class="w-px h-8 bg-gray-600 hidden lg:block"></div>

                <!-- Station name -->
                <h1 :class="[
                    'font-bold flex items-center gap-1.5 sm:gap-2 min-w-0',
                    focusMode ? 'text-lg sm:text-xl' : 'text-lg sm:text-xl lg:text-2xl'
                ]">
                    <span :class="focusMode ? 'text-xl sm:text-2xl' : 'text-2xl sm:text-2xl lg:text-3xl'">{{ stationIcon }}</span>
                    <span class="truncate max-w-[100px] sm:max-w-[150px] lg:max-w-none">{{ stationName }}</span>
                </h1>
            </div>

            <!-- Right side - Controls -->
            <div class="flex items-center gap-2 sm:gap-3 lg:gap-6">
                <!-- Date Selector - hidden on mobile, visible on tablet+ -->
                <div class="hidden sm:block">
                    <slot name="date-selector" />
                </div>

                <!-- Time - simplified on mobile -->
                <div class="text-right hidden sm:block">
                    <p :class="['font-bold', focusMode ? 'text-xl lg:text-2xl' : 'text-2xl lg:text-3xl']">{{ currentTime }}</p>
                    <p v-if="!focusMode" class="text-xs lg:text-sm text-gray-400">{{ currentDate }}</p>
                </div>

                <!-- Mobile time (compact) -->
                <div class="sm:hidden text-right">
                    <p class="font-bold text-xl">{{ currentTime }}</p>
                </div>

                <!-- Stop List Indicator - hidden on mobile -->
                <div class="hidden md:block">
                    <slot name="stop-list" />
                </div>

                <!-- Desktop Settings Menu -->
                <div class="relative hidden md:block">
                    <button
                        @click="showSettingsMenu = !showSettingsMenu"
                        class="p-2 lg:p-3 rounded-xl bg-gray-700 text-xl lg:text-2xl hover:bg-gray-600 transition"
                        title="Настройки"
                    >
                        ⚙️
                    </button>

                    <!-- Dropdown Menu -->
                    <div
                        v-if="showSettingsMenu"
                        class="absolute right-0 top-full mt-2 bg-gray-800 rounded-xl shadow-2xl border border-gray-700 py-2 min-w-[220px] z-50"
                    >
                        <!-- Single Column Mode -->
                        <button
                            @click="$emit('toggle-single-column')"
                            class="w-full px-4 py-3 flex items-center justify-between hover:bg-gray-700 transition"
                        >
                            <span class="text-gray-300">Одна колонка</span>
                            <ToggleSwitch :enabled="singleColumnMode" color="cyan" />
                        </button>

                        <!-- Compact Mode -->
                        <button
                            @click="$emit('toggle-compact')"
                            class="w-full px-4 py-3 flex items-center justify-between hover:bg-gray-700 transition"
                        >
                            <span class="text-gray-300">Компактный вид</span>
                            <ToggleSwitch :enabled="compactMode" color="purple" />
                        </button>

                        <!-- Focus Mode -->
                        <button
                            @click="$emit('toggle-focus')"
                            class="w-full px-4 py-3 flex items-center justify-between hover:bg-gray-700 transition"
                        >
                            <span class="text-gray-300">Режим фокуса</span>
                            <ToggleSwitch :enabled="focusMode" color="orange" />
                        </button>

                        <!-- Sound -->
                        <button
                            @click="$emit('toggle-sound')"
                            class="w-full px-4 py-3 flex items-center justify-between hover:bg-gray-700 transition"
                        >
                            <span class="text-gray-300">Звук уведомлений</span>
                            <ToggleSwitch :enabled="soundEnabled" color="green" />
                        </button>

                        <!-- Auto Responsive -->
                        <button
                            @click="$emit('toggle-auto-responsive')"
                            class="w-full px-4 py-3 flex items-center justify-between hover:bg-gray-700 transition"
                        >
                            <span class="text-gray-300">Авто-адаптация</span>
                            <ToggleSwitch :enabled="autoResponsiveEnabled" color="blue" />
                        </button>

                        <div class="border-t border-gray-700 my-2"></div>

                        <!-- Fullscreen -->
                        <button
                            @click="$emit('toggle-fullscreen'); showSettingsMenu = false"
                            class="w-full px-4 py-3 flex items-center gap-3 hover:bg-gray-700 transition text-gray-300"
                        >
                            <span>⛶</span>
                            <span>Полный экран</span>
                        </button>
                    </div>

                    <!-- Backdrop to close menu -->
                    <div
                        v-if="showSettingsMenu"
                        class="fixed inset-0 z-40"
                        @click="showSettingsMenu = false"
                    ></div>
                </div>

                <!-- Mobile Menu Button (hamburger) -->
                <button
                    @click="$emit('open-mobile-menu')"
                    class="md:hidden w-12 h-12 flex items-center justify-center rounded-xl bg-gray-700 active:bg-gray-600 text-2xl"
                >
                    ☰
                </button>
            </div>
        </div>
    </header>
</template>

<script setup lang="ts">
/**
 * Kitchen Header Component
 *
 * Responsive header with station info, stats, time, and controls.
 * Mobile: Icon + truncated name, time, hamburger menu
 * Tablet: Hide logo, compact date selector
 * Desktop: Full layout with all controls
 */

import { ref } from 'vue';
import ToggleSwitch from './ToggleSwitch.vue';

const showSettingsMenu = ref(false);

defineProps({
    stationName: {
        type: String,
        default: 'Кухня',
    },
    stationIcon: {
        type: String,
        default: '🍳',
    },
    currentTime: {
        type: String,
        default: '',
    },
    currentDate: {
        type: String,
        default: '',
    },
    focusMode: {
        type: Boolean,
        default: false,
    },
    compactMode: {
        type: Boolean,
        default: false,
    },
    singleColumnMode: {
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
    'toggle-single-column',
    'toggle-compact',
    'toggle-focus',
    'toggle-sound',
    'toggle-fullscreen',
    'toggle-auto-responsive',
    'open-mobile-menu',
]);
</script>
