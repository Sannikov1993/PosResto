<template>
    <aside class="w-20 bg-dark-900 flex flex-col items-center py-4 border-r border-gray-800 relative" data-testid="sidebar">
        <!-- Logo -->
        <div class="w-12 h-12 mb-6">
            <img src="/images/logo/menulab_icon.svg" alt="MenuLab" class="w-full h-full" />
        </div>

        <!-- Navigation Tabs -->
        <nav class="flex-1 flex flex-col gap-2" data-testid="nav-tabs">
            <button
                v-for="(tab, index) in tabs"
                :key="tab.id"
                @click="$emit('change-tab', tab.id)"
                @mouseenter="hoveredTab = tab.id"
                @mouseleave="hoveredTab = null"
                :data-testid="`tab-${tab.id}`"
                :class="[
                    'group w-14 h-14 rounded-xl flex flex-col items-center justify-center gap-1 transition-colors duration-200 relative',
                    activeTab === tab.id
                        ? 'text-accent'
                        : 'text-gray-500 hover:text-white'
                ]"
            >
                <!-- Discord-style pill indicator -->
                <span
                    :class="[
                        'absolute -left-3 w-1 rounded-r-full transition-all duration-200',
                        activeTab === tab.id
                            ? 'h-10 bg-accent'
                            : hoveredTab === tab.id
                                ? 'h-5 bg-white'
                                : 'h-0 bg-white'
                    ]"
                    style="top: 50%; transform: translateY(-50%);"
                ></span>
                <!-- Icon with animations -->
                <div :class="[
                    'transition-transform duration-200',
                    hoveredTab === tab.id && tab.hoverAnimation
                ]">
                    <component :is="tab.iconComponent" class="w-5 h-5" />
                </div>
                <span class="text-[10px] font-medium">{{ tab.label }}</span>

                <!-- Badge for pending cancellations -->
                <span
                    v-if="tab.id === 'writeoffs' && pendingCancellationsCount > 0"
                    class="absolute -top-1 -right-1 w-5 h-5 bg-red-500 text-white text-xs rounded-full flex items-center justify-center animate-pulse"
                >
                    {{ pendingCancellationsCount > 9 ? '9+' : pendingCancellationsCount }}
                </span>

                <!-- Badge for pending delivery orders -->
                <span
                    v-if="tab.id === 'delivery' && pendingDeliveryCount > 0"
                    class="absolute -top-1 -right-1 w-5 h-5 bg-orange-500 text-white text-xs rounded-full flex items-center justify-center animate-bounce"
                >
                    {{ pendingDeliveryCount > 9 ? '9+' : pendingDeliveryCount }}
                </span>
            </button>
        </nav>

        <!-- Bar Button -->
        <button
            v-if="hasBar"
            @click="$emit('open-bar')"
            @mouseenter="showBarTooltip = true"
            @mouseleave="showBarTooltip = false"
            data-testid="bar-btn"
            class="w-14 h-14 rounded-xl flex flex-col items-center justify-center gap-1 transition-colors duration-200 relative mb-2 text-amber-400 hover:text-amber-300 hover:bg-amber-500/10"
        >
            <div class="text-xl">🍸</div>
            <span class="text-[10px] font-medium">Бар</span>
            <!-- Badge for bar items -->
            <span
                v-if="barItemsCount > 0"
                class="absolute -top-1 -right-1 w-5 h-5 bg-amber-500 text-white text-xs rounded-full flex items-center justify-center animate-pulse"
            >
                {{ barItemsCount > 9 ? '9+' : barItemsCount }}
            </span>
            <!-- Tooltip -->
            <div
                v-if="showBarTooltip"
                class="absolute left-full ml-3 px-3 py-2 bg-black/70 backdrop-blur-md rounded-lg text-sm whitespace-nowrap z-50 shadow-xl top-1/2 -translate-y-1/2 pointer-events-none"
            >
                <div class="font-medium text-white">Барная стойка</div>
                <div class="text-xs text-gray-400">{{ barItemsCount > 0 ? `${barItemsCount} позиций в очереди` : 'Открыть панель бара' }}</div>
            </div>
        </button>

        <!-- Restaurant Switcher -->
        <div
            v-if="hasMultipleRestaurants"
            class="relative mb-2"
            data-testid="restaurant-switcher"
        >
            <button
                @click="showRestaurantMenu = !showRestaurantMenu"
                @mouseenter="showRestaurantTooltip = true"
                @mouseleave="showRestaurantTooltip = false"
                data-testid="restaurant-switcher-btn"
                class="w-14 h-14 rounded-xl flex flex-col items-center justify-center gap-1 transition-colors duration-200 relative text-emerald-400 bg-emerald-500/10 hover:bg-emerald-500/20"
            >
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 01.75-.75h3a.75.75 0 01.75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349m-16.5 11.65V9.35m0 0a3.001 3.001 0 003.75-.615A2.993 2.993 0 009.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 002.25 1.016c.896 0 1.7-.393 2.25-1.016a3.001 3.001 0 003.75.614m-16.5 0a3.004 3.004 0 01-.621-4.72L4.318 3.44A1.5 1.5 0 015.378 3h13.243a1.5 1.5 0 011.06.44l1.19 1.189a3 3 0 01-.621 4.72m-13.5 8.65h3.75a.75.75 0 00.75-.75V13.5a.75.75 0 00-.75-.75H6.75a.75.75 0 00-.75.75v3.75c0 .415.336.75.75.75z" />
                </svg>
                <span class="text-[10px] font-medium">Точка</span>
            </button>

            <!-- Tooltip -->
            <div
                v-if="showRestaurantTooltip && !showRestaurantMenu"
                class="absolute left-full ml-3 px-3 py-2 bg-black/70 backdrop-blur-md rounded-lg text-sm whitespace-nowrap z-50 shadow-xl top-1/2 -translate-y-1/2 pointer-events-none"
            >
                <div class="font-medium text-white">Текущая точка</div>
                <div class="text-xs text-gray-400">{{ currentRestaurant?.name || 'Не выбрана' }}</div>
            </div>

            <!-- Restaurant flyout menu -->
            <Transition name="menu">
                <div
                    v-if="showRestaurantMenu"
                    class="absolute left-full ml-3 w-64 bg-dark-800 rounded-xl shadow-xl border border-gray-700/50 overflow-hidden z-50 top-0"
                >
                    <div class="p-3 border-b border-gray-700/50">
                        <div class="font-medium text-white text-sm">Выбор точки</div>
                    </div>
                    <div class="p-1 max-h-64 overflow-y-auto">
                        <button
                            v-for="r in restaurants"
                            :key="r.id"
                            @click="selectRestaurant(r.id)"
                            :class="[
                                'w-full text-left px-3 py-2.5 rounded-lg text-sm transition-colors flex items-center gap-2',
                                currentRestaurant?.id === r.id
                                    ? 'bg-emerald-500/20 text-emerald-400'
                                    : 'text-gray-300 hover:bg-dark-700'
                            ]"
                        >
                            <span :class="['w-2 h-2 rounded-full', currentRestaurant?.id === r.id ? 'bg-emerald-400' : 'bg-gray-600']"></span>
                            <span class="flex-1 truncate">{{ r.name }}</span>
                            <span v-if="r.is_main" class="text-[10px] text-gray-500">главная</span>
                        </button>
                    </div>
                </div>
            </Transition>
        </div>

        <!-- Click outside to close restaurant menu -->
        <Teleport to="body">
            <div v-if="showRestaurantMenu" class="fixed inset-0 z-40" @click="showRestaurantMenu = false"></div>
        </Teleport>

        <!-- Price List Selector -->
        <div
            v-if="availablePriceLists.length > 0"
            class="relative mb-2"
        >
            <button
                @click="showPriceListMenu = !showPriceListMenu"
                @mouseenter="showPriceListTooltip = true"
                @mouseleave="showPriceListTooltip = false"
                :class="[
                    'w-14 h-14 rounded-xl flex flex-col items-center justify-center gap-1 transition-colors duration-200 relative',
                    selectedPriceListId
                        ? 'text-blue-400 bg-blue-500/10 hover:bg-blue-500/20'
                        : 'text-gray-500 hover:text-white hover:bg-dark-800'
                ]"
            >
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span class="text-[10px] font-medium">Прайс</span>
                <!-- Active indicator dot -->
                <span
                    v-if="selectedPriceListId"
                    class="absolute top-1 right-1 w-2 h-2 bg-blue-400 rounded-full"
                ></span>
            </button>

            <!-- Tooltip -->
            <div
                v-if="showPriceListTooltip && !showPriceListMenu"
                class="absolute left-full ml-3 px-3 py-2 bg-black/70 backdrop-blur-md rounded-lg text-sm whitespace-nowrap z-50 shadow-xl top-1/2 -translate-y-1/2 pointer-events-none"
            >
                <div class="font-medium text-white">Прайс-лист</div>
                <div class="text-xs text-gray-400">{{ selectedPriceListName || 'Базовые цены' }}</div>
            </div>

            <!-- Price list flyout menu -->
            <Transition name="menu">
                <div
                    v-if="showPriceListMenu"
                    class="absolute left-full ml-3 w-56 bg-dark-800 rounded-xl shadow-xl border border-gray-700/50 overflow-hidden z-50 top-0"
                >
                    <div class="p-3 border-b border-gray-700/50">
                        <div class="font-medium text-white text-sm">Выбор прайс-листа</div>
                    </div>
                    <div class="p-1 max-h-64 overflow-y-auto">
                        <!-- Base prices option -->
                        <button
                            @click="selectPriceList(null)"
                            :class="[
                                'w-full text-left px-3 py-2.5 rounded-lg text-sm transition-colors flex items-center gap-2',
                                !selectedPriceListId
                                    ? 'bg-accent/20 text-accent'
                                    : 'text-gray-300 hover:bg-dark-700'
                            ]"
                        >
                            <span :class="['w-2 h-2 rounded-full', !selectedPriceListId ? 'bg-accent' : 'bg-gray-600']"></span>
                            Базовые цены
                        </button>
                        <!-- Price lists -->
                        <button
                            v-for="pl in availablePriceLists"
                            :key="pl.id"
                            @click="selectPriceList(pl.id)"
                            :class="[
                                'w-full text-left px-3 py-2.5 rounded-lg text-sm transition-colors flex items-center gap-2',
                                selectedPriceListId === pl.id
                                    ? 'bg-blue-500/20 text-blue-400'
                                    : 'text-gray-300 hover:bg-dark-700'
                            ]"
                        >
                            <span :class="['w-2 h-2 rounded-full', selectedPriceListId === pl.id ? 'bg-blue-400' : 'bg-gray-600']"></span>
                            <span class="flex-1 truncate">{{ pl.name }}</span>
                            <span v-if="pl.is_default" class="text-[10px] text-gray-500">по умолч.</span>
                        </button>
                    </div>
                </div>
            </Transition>
        </div>

        <!-- Click outside to close price list menu -->
        <Teleport to="body">
            <div v-if="showPriceListMenu" class="fixed inset-0 z-40" @click="showPriceListMenu = false"></div>
        </Teleport>

        <!-- Bottom Section -->
        <div class="flex flex-col items-center gap-3 pt-4 border-t border-gray-800 w-full px-3" data-testid="sidebar-bottom">
            <!-- Shift Status -->
            <div
                @click="currentShift ? $emit('change-tab', 'cash') : $emit('open-shift')"
                @mouseenter="showShiftTooltip = true"
                @mouseleave="showShiftTooltip = false"
                data-testid="shift-status"
                :class="[
                    'w-full py-2 px-2 rounded-xl cursor-pointer transition-colors duration-200 relative',
                    currentShift
                        ? isShiftTooLong
                            ? 'bg-orange-500/10 hover:bg-orange-500/20'
                            : 'bg-green-500/10 hover:bg-green-500/20'
                        : 'bg-red-500/10 hover:bg-red-500/20'
                ]"
            >
                <div class="flex flex-col items-center gap-1">
                    <div :class="[
                        'w-8 h-8 rounded-lg flex items-center justify-center',
                        currentShift
                            ? isShiftTooLong
                                ? 'bg-orange-500/20 text-orange-400'
                                : 'bg-green-500/20 text-green-400'
                            : 'bg-red-500/20 text-red-400'
                    ]">
                        <!-- Warning icon for long shift -->
                        <svg v-if="currentShift && isShiftTooLong" class="w-4 h-4 animate-pulse" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path v-if="currentShift" stroke-linecap="round" stroke-linejoin="round" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z" />
                            <path v-else stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                    </div>
                    <span :class="[
                        'text-[9px] font-medium uppercase tracking-wide',
                        currentShift
                            ? isShiftTooLong ? 'text-orange-400' : 'text-green-400'
                            : 'text-red-400'
                    ]">
                        {{ currentShift ? 'Открыта' : 'Закрыта' }}
                    </span>
                    <span v-if="currentShift" :class="['text-[9px]', isShiftTooLong ? 'text-orange-400 font-medium' : 'text-gray-500']">
                        {{ formatShiftDuration(currentShift.opened_at) }}
                    </span>
                </div>

                <!-- Shift tooltip -->
                <div
                    v-if="showShiftTooltip"
                    class="absolute left-full ml-3 px-3 py-2 bg-black/70 backdrop-blur-md rounded-lg text-sm whitespace-nowrap z-50 shadow-xl top-1/2 -translate-y-1/2 pointer-events-none"
                >
                    <div class="font-medium text-white mb-1">{{ currentShift ? 'Смена открыта' : 'Смена закрыта' }}</div>
                    <div v-if="currentShift" class="text-xs text-gray-400">
                        <div>Открыта в {{ formatShiftTime(currentShift.opened_at) }}</div>
                        <div v-if="isShiftTooLong" class="text-orange-400 mt-1 font-medium">
                            ⚠️ Смена открыта {{ shiftHoursOpen }}ч! Закройте смену.
                        </div>
                        <div :class="isShiftTooLong ? 'text-gray-500 mt-1' : 'text-green-400 mt-1'">
                            В кассе: {{ formatMoney(currentShift.current_cash || 0) }} ₽
                        </div>
                    </div>
                    <div v-else class="text-xs text-gray-400">Нажмите чтобы открыть</div>
                </div>
            </div>

            <!-- User Avatar with Work Shift -->
            <div
                class="relative"
                @mouseenter="showUserTooltip = true"
                @mouseleave="showUserTooltip = false"
                data-testid="user-menu"
            >
                <div
                    @click="toggleWorkShiftMenu"
                    data-testid="user-avatar"
                    class="w-11 h-11 rounded-xl flex items-center justify-center text-sm font-bold cursor-pointer transition-transform duration-200 hover:scale-105 text-white relative"
                    :style="{ background: userGradient, boxShadow: `0 4px 15px ${userColor}40` }"
                >
                    {{ userInitials }}
                    <!-- Work shift indicator dot -->
                    <span
                        :class="[
                            'absolute -bottom-0.5 -right-0.5 w-3 h-3 rounded-full border-2 border-dark-900',
                            workShiftStatus.is_clocked_in ? 'bg-green-400' : 'bg-gray-500'
                        ]"
                    ></span>
                </div>

                <!-- User tooltip -->
                <div
                    v-if="showUserTooltip && !showWorkShiftMenu"
                    class="absolute left-full ml-3 px-3 py-2 bg-black/70 backdrop-blur-md rounded-lg text-sm whitespace-nowrap z-50 shadow-xl top-1/2 -translate-y-1/2 pointer-events-none"
                >
                    <div class="font-medium text-white">{{ user?.name || 'Пользователь' }}</div>
                    <div class="text-xs text-gray-400">{{ user?.role_name || 'Сотрудник' }}</div>
                    <div class="text-xs mt-1" :class="workShiftStatus.is_clocked_in ? 'text-green-400' : 'text-gray-500'">
                        {{ workShiftStatus.is_clocked_in ? 'На смене' : 'Не на смене' }}
                    </div>
                </div>

                <!-- Work shift menu -->
                <Transition name="menu">
                    <div
                        v-if="showWorkShiftMenu"
                        class="absolute left-full ml-3 w-56 bg-dark-800 rounded-xl shadow-xl border border-gray-700/50 overflow-hidden z-50 top-0"
                    >
                        <div class="p-3 border-b border-gray-700/50">
                            <div class="font-medium text-white">{{ user?.name || 'Пользователь' }}</div>
                            <div class="text-xs text-gray-400">{{ user?.role_name || 'Сотрудник' }}</div>
                        </div>
                        <div class="p-3 border-b border-gray-700/50">
                            <div class="flex items-center gap-2 mb-2">
                                <span :class="['w-2 h-2 rounded-full', workShiftStatus.is_clocked_in ? 'bg-green-400' : 'bg-gray-500']"></span>
                                <span class="text-sm" :class="workShiftStatus.is_clocked_in ? 'text-green-400' : 'text-gray-400'">
                                    {{ workShiftStatus.is_clocked_in ? 'Рабочая смена активна' : 'Рабочая смена не начата' }}
                                </span>
                            </div>
                            <div v-if="workShiftStatus.is_clocked_in && workShiftStatus.session" class="text-xs text-gray-500">
                                С {{ formatWorkShiftTime(workShiftStatus.session.clock_in) }} ({{ workShiftDuration }})
                            </div>
                        </div>
                        <div class="p-2">
                            <button
                                @click="toggleWorkShift"
                                :disabled="workShiftLoading"
                                :class="[
                                    'w-full py-2 rounded-lg font-medium text-sm transition flex items-center justify-center gap-2',
                                    workShiftStatus.is_clocked_in
                                        ? 'bg-red-500/20 text-red-400 hover:bg-red-500/30'
                                        : 'bg-green-500/20 text-green-400 hover:bg-green-500/30'
                                ]"
                            >
                                <svg v-if="workShiftLoading" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                {{ workShiftStatus.is_clocked_in ? 'Завершить смену' : 'Начать смену' }}
                            </button>
                        </div>
                    </div>
                </Transition>
            </div>

            <!-- Click outside to close work shift menu -->
            <Teleport to="body">
                <div v-if="showWorkShiftMenu" class="fixed inset-0 z-40" @click="showWorkShiftMenu = false"></div>
            </Teleport>

            <!-- Logout Button -->
            <button
                @click="$emit('logout')"
                @mouseenter="showLogoutTooltip = true"
                @mouseleave="showLogoutTooltip = false"
                data-testid="logout-btn"
                class="w-11 h-11 rounded-xl bg-dark-800 flex items-center justify-center text-gray-500 hover:text-red-400 hover:bg-red-500/10 transition-colors duration-200 relative"
            >
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                </svg>

                <!-- Logout tooltip -->
                <div
                    v-if="showLogoutTooltip"
                    class="absolute left-full ml-3 px-3 py-2 bg-black/70 backdrop-blur-md rounded-lg text-sm whitespace-nowrap z-50 shadow-xl top-1/2 -translate-y-1/2 pointer-events-none"
                >
                    <div class="font-medium text-white">Выход</div>
                    <div class="text-xs text-gray-400">Завершить сеанс</div>
                </div>
            </button>
        </div>
    </aside>
</template>

<script setup>
import { computed, ref, h, onMounted, onUnmounted } from 'vue';
import api from '../api';
import { usePosStore } from '../stores/pos';
import { useNavigationStore } from '../../shared/stores/navigation.js';

const props = defineProps({
    user: Object,
    activeTab: String,
    currentShift: Object,
    authToken: { type: String, default: null },
    pendingCancellationsCount: { type: Number, default: 0 },
    pendingDeliveryCount: { type: Number, default: 0 },
    hasBar: { type: Boolean, default: false },
    barItemsCount: { type: Number, default: 0 },
    restaurants: { type: Array, default: () => [] },
    currentRestaurant: { type: Object, default: null },
    hasMultipleRestaurants: { type: Boolean, default: false }
});

const emit = defineEmits(['change-tab', 'logout', 'open-bar', 'switch-restaurant', 'open-shift']);

const posStore = usePosStore();
const navigationStore = useNavigationStore();

// Hover states
const hoveredTab = ref(null);
const showShiftTooltip = ref(false);
const showUserTooltip = ref(false);
const showLogoutTooltip = ref(false);
const showBarTooltip = ref(false);

// Price list
const showPriceListMenu = ref(false);
const showPriceListTooltip = ref(false);
const availablePriceLists = computed(() => posStore.availablePriceLists);
const selectedPriceListId = computed(() => posStore.selectedPriceListId);
const selectedPriceListName = computed(() => {
    if (!posStore.selectedPriceListId) return null;
    const pl = posStore.availablePriceLists.find(p => p.id === posStore.selectedPriceListId);
    return pl?.name || null;
});

const selectPriceList = async (id) => {
    showPriceListMenu.value = false;
    await posStore.setPriceList(id);
};

// Restaurant switcher
const showRestaurantMenu = ref(false);
const showRestaurantTooltip = ref(false);
const restaurantSwitchLoading = ref(false);

const selectRestaurant = async (restaurantId) => {
    if (restaurantSwitchLoading.value) return;
    if (props.currentRestaurant?.id === restaurantId) {
        showRestaurantMenu.value = false;
        return;
    }
    restaurantSwitchLoading.value = true;
    showRestaurantMenu.value = false;
    emit('switch-restaurant', restaurantId);
    restaurantSwitchLoading.value = false;
};

// Work shift states
const showWorkShiftMenu = ref(false);
const workShiftStatus = ref({ is_clocked_in: false, session: null });
const workShiftLoading = ref(false);
const workShiftElapsed = ref(0);
let workShiftTimer = null;
let workShiftRefreshTimer = null;

const workShiftDuration = computed(() => {
    const hours = Math.floor(workShiftElapsed.value / 3600);
    const minutes = Math.floor((workShiftElapsed.value % 3600) / 60);
    return hours > 0 ? `${hours}ч ${minutes}м` : `${minutes}м`;
});

const formatWorkShiftTime = (datetime) => {
    if (!datetime) return '';
    return new Date(datetime).toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' });
};

const calculateWorkShiftElapsed = () => {
    if (workShiftStatus.value.session?.clock_in) {
        const start = new Date(workShiftStatus.value.session.clock_in);
        workShiftElapsed.value = Math.floor((Date.now() - start.getTime()) / 1000);
    } else {
        workShiftElapsed.value = 0;
    }
};

const loadWorkShiftStatus = async () => {
    try {
        const res = await api.payroll.getMyStatus();
        workShiftStatus.value = res;
        calculateWorkShiftElapsed();
    } catch (e) {
        console.error('Failed to load work shift status:', e);
    }
};

const toggleWorkShiftMenu = () => {
    showWorkShiftMenu.value = !showWorkShiftMenu.value;
};

const toggleWorkShift = async () => {
    workShiftLoading.value = true;
    try {
        const res = workShiftStatus.value.is_clocked_in
            ? await api.payroll.clockOut()
            : await api.payroll.clockIn();

        if (res.success) {
            await loadWorkShiftStatus();
        }
    } catch (e) {
        console.error('Failed to toggle work shift:', e);
    } finally {
        workShiftLoading.value = false;
        showWorkShiftMenu.value = false;
    }
};

onMounted(() => {
    loadWorkShiftStatus();
    // Refresh status every minute
    workShiftRefreshTimer = setInterval(loadWorkShiftStatus, 60000);
    // Update elapsed time every second
    workShiftTimer = setInterval(calculateWorkShiftElapsed, 1000);
});

onUnmounted(() => {
    if (workShiftTimer) clearInterval(workShiftTimer);
    if (workShiftRefreshTimer) clearInterval(workShiftRefreshTimer);
});

// Two-tone SVG Icons
const IconCash = {
    render() {
        return h('svg', { fill: 'none', viewBox: '0 0 24 24', class: 'w-5 h-5' }, [
            h('rect', { x: '3', y: '6', width: '18', height: '12', rx: '2', fill: 'currentColor', opacity: '0.2' }),
            h('path', { stroke: 'currentColor', 'stroke-width': '1.5', 'stroke-linecap': 'round', d: 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2z' }),
            h('circle', { cx: '14', cy: '13', r: '2', fill: 'currentColor' })
        ]);
    }
};

const IconOrders = {
    render() {
        return h('svg', { fill: 'none', viewBox: '0 0 24 24', class: 'w-5 h-5' }, [
            h('rect', { x: '5', y: '3', width: '14', height: '18', rx: '2', fill: 'currentColor', opacity: '0.2' }),
            h('path', { stroke: 'currentColor', 'stroke-width': '1.5', 'stroke-linecap': 'round', d: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2' }),
            h('path', { stroke: 'currentColor', 'stroke-width': '2', 'stroke-linecap': 'round', d: 'M9 12h3m-3 4h3' })
        ]);
    }
};

const IconDelivery = {
    render() {
        return h('svg', { fill: 'none', viewBox: '0 0 24 24', class: 'w-5 h-5' }, [
            h('rect', { x: '3', y: '6', width: '9', height: '10', rx: '1', fill: 'currentColor', opacity: '0.2' }),
            h('path', { stroke: 'currentColor', 'stroke-width': '1.5', 'stroke-linecap': 'round', d: 'M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1' }),
            h('circle', { cx: '7', cy: '17', r: '2', stroke: 'currentColor', 'stroke-width': '1.5', fill: 'currentColor', opacity: '0.3' }),
            h('circle', { cx: '17', cy: '17', r: '2', stroke: 'currentColor', 'stroke-width': '1.5', fill: 'currentColor', opacity: '0.3' })
        ]);
    }
};

const IconCustomers = {
    render() {
        return h('svg', { fill: 'none', viewBox: '0 0 24 24', class: 'w-5 h-5' }, [
            h('circle', { cx: '9', cy: '7', r: '4', fill: 'currentColor', opacity: '0.2' }),
            h('circle', { cx: '17', cy: '9', r: '3', fill: 'currentColor', opacity: '0.15' }),
            h('path', { stroke: 'currentColor', 'stroke-width': '1.5', 'stroke-linecap': 'round', d: 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z' })
        ]);
    }
};

const IconStopList = {
    render() {
        return h('svg', { fill: 'none', viewBox: '0 0 24 24', class: 'w-5 h-5' }, [
            h('circle', { cx: '12', cy: '12', r: '9', fill: 'currentColor', opacity: '0.1' }),
            h('circle', { cx: '12', cy: '12', r: '9', stroke: 'currentColor', 'stroke-width': '1.5' }),
            h('path', { stroke: 'currentColor', 'stroke-width': '2', 'stroke-linecap': 'round', d: 'M5.636 5.636l12.728 12.728' })
        ]);
    }
};

const IconWriteoffs = {
    render() {
        return h('svg', { fill: 'none', viewBox: '0 0 24 24', class: 'w-5 h-5' }, [
            h('path', { d: 'M7 3h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2H7a2 2 0 01-2-2V5a2 2 0 012-2z', fill: 'currentColor', opacity: '0.2' }),
            h('path', { stroke: 'currentColor', 'stroke-width': '1.5', 'stroke-linecap': 'round', d: 'M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m6.75 12H9m1.5-12H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z' }),
            h('path', { stroke: 'currentColor', 'stroke-width': '2', 'stroke-linecap': 'round', d: 'M9 14h6m-6 3h6' })
        ]);
    }
};

const IconSettings = {
    render() {
        return h('svg', { fill: 'none', viewBox: '0 0 24 24', class: 'w-5 h-5' }, [
            h('circle', { cx: '12', cy: '12', r: '8', fill: 'currentColor', opacity: '0.1' }),
            h('path', { stroke: 'currentColor', 'stroke-width': '1.5', 'stroke-linecap': 'round', d: 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z' }),
            h('circle', { cx: '12', cy: '12', r: '3', stroke: 'currentColor', 'stroke-width': '1.5', fill: 'currentColor', opacity: '0.3' })
        ]);
    }
};

const IconWarehouse = {
    render() {
        return h('svg', { fill: 'none', viewBox: '0 0 24 24', class: 'w-5 h-5' }, [
            h('rect', { x: '4', y: '10', width: '16', height: '10', rx: '1', fill: 'currentColor', opacity: '0.2' }),
            h('path', { stroke: 'currentColor', 'stroke-width': '1.5', 'stroke-linecap': 'round', 'stroke-linejoin': 'round', d: 'M4 10l8-6 8 6' }),
            h('path', { stroke: 'currentColor', 'stroke-width': '1.5', d: 'M4 10v10h16V10' }),
            h('rect', { x: '8', y: '14', width: '8', height: '6', stroke: 'currentColor', 'stroke-width': '1.5' }),
            h('path', { stroke: 'currentColor', 'stroke-width': '1.5', d: 'M12 14v6' })
        ]);
    }
};

// Tab definitions with icons (static)
const TAB_DEFINITIONS = {
    cash: { id: 'cash', label: 'Касса', iconComponent: IconCash, hoverAnimation: '' },
    orders: { id: 'orders', label: 'Заказы', iconComponent: IconOrders, hoverAnimation: 'animate-bounce-subtle' },
    delivery: { id: 'delivery', label: 'Доставка', iconComponent: IconDelivery, hoverAnimation: 'animate-shake' },
    customers: { id: 'customers', label: 'Клиенты', iconComponent: IconCustomers, hoverAnimation: '' },
    warehouse: { id: 'warehouse', label: 'Склад', iconComponent: IconWarehouse, hoverAnimation: '' },
    stoplist: { id: 'stoplist', label: 'Стоп-лист', iconComponent: IconStopList, hoverAnimation: 'animate-pulse' },
    writeoffs: { id: 'writeoffs', label: 'Списания', iconComponent: IconWriteoffs, hoverAnimation: '' },
    settings: { id: 'settings', label: 'Настройки', iconComponent: IconSettings, hoverAnimation: 'animate-spin-slow' },
};

// Filtered tabs based on user permissions (computed)
const tabs = computed(() => {
    const availableIds = new Set(navigationStore.availableTabs.map(t => t.id));
    // Maintain order from TAB_DEFINITIONS
    return Object.values(TAB_DEFINITIONS).filter(tab => availableIds.has(tab.id));
});

// Tab preview info
const getTabPreview = (tabId) => {
    switch (tabId) {
        case 'cash':
            return props.currentShift ? `В кассе: ${formatMoney(props.currentShift.current_cash || 0)} ₽` : 'Смена закрыта';
        case 'orders':
            return 'Активные заказы';
        case 'delivery':
            return props.pendingDeliveryCount > 0 ? `${props.pendingDeliveryCount} новых заказов` : 'Нет новых заказов';
        case 'customers':
            return 'База клиентов';
        case 'warehouse':
            return 'Накладные и инвентаризация';
        case 'stoplist':
            return 'Недоступные блюда';
        case 'writeoffs':
            return props.pendingCancellationsCount > 0 ? `${props.pendingCancellationsCount} на подтверждение` : 'Списания и отмены';
        case 'settings':
            return 'Настройки системы';
        default:
            return '';
    }
};

// User initials
const userInitials = computed(() => {
    if (!props.user?.name) return '?';
    const parts = props.user.name.split(' ');
    if (parts.length >= 2) {
        return (parts[0][0] + parts[1][0]).toUpperCase();
    }
    return props.user.name.substring(0, 2).toUpperCase();
});

// User gradient by first letter
const userColors = {
    'А': ['#ef4444', '#f97316'], 'Б': ['#f97316', '#f59e0b'], 'В': ['#f59e0b', '#eab308'],
    'Г': ['#eab308', '#84cc16'], 'Д': ['#84cc16', '#22c55e'], 'Е': ['#22c55e', '#10b981'],
    'Ж': ['#10b981', '#14b8a6'], 'З': ['#14b8a6', '#06b6d4'], 'И': ['#06b6d4', '#0ea5e9'],
    'К': ['#0ea5e9', '#3b82f6'], 'Л': ['#3b82f6', '#6366f1'], 'М': ['#6366f1', '#8b5cf6'],
    'Н': ['#8b5cf6', '#a855f7'], 'О': ['#a855f7', '#d946ef'], 'П': ['#d946ef', '#ec4899'],
    'Р': ['#ec4899', '#f43f5e'], 'С': ['#f43f5e', '#ef4444'], 'Т': ['#2563eb', '#4f46e5'],
    'У': ['#16a34a', '#0d9488'], 'Ф': ['#9333ea', '#7c3aed'], 'Х': ['#ea580c', '#dc2626'],
    'Ц': ['#0891b2', '#0284c7'], 'Ч': ['#059669', '#10b981'], 'Ш': ['#d97706', '#ea580c'],
    'Щ': ['#4f46e5', '#7c3aed'], 'Э': ['#0d9488', '#06b6d4'], 'Ю': ['#db2777', '#e11d48'],
    'Я': ['#7c3aed', '#a855f7']
};

const userGradient = computed(() => {
    const letter = props.user?.name?.[0]?.toUpperCase() || '';
    const [c1, c2] = userColors[letter] || ['#6b7280', '#4b5563'];
    return `linear-gradient(135deg, ${c1}, ${c2})`;
});

const userColor = computed(() => {
    const letter = props.user?.name?.[0]?.toUpperCase() || '';
    return (userColors[letter] || ['#6b7280'])[0];
});

// Formatters
const formatShiftTime = (dateStr) => {
    if (!dateStr) return '';
    return new Date(dateStr).toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' });
};

const formatShiftDuration = (openedAt) => {
    if (!openedAt) return '';
    const diffMs = new Date() - new Date(openedAt);
    const hours = Math.floor(diffMs / (1000 * 60 * 60));
    const minutes = Math.floor((diffMs % (1000 * 60 * 60)) / (1000 * 60));
    return hours > 0 ? `${hours}ч ${minutes}м` : `${minutes} мин`;
};

// Проверка: смена открыта слишком долго (> 18 часов)
const isShiftTooLong = computed(() => {
    if (!props.currentShift?.opened_at) return false;
    const diffMs = new Date() - new Date(props.currentShift.opened_at);
    const hours = diffMs / (1000 * 60 * 60);
    return hours > 18;
});

const shiftHoursOpen = computed(() => {
    if (!props.currentShift?.opened_at) return 0;
    const diffMs = new Date() - new Date(props.currentShift.opened_at);
    return Math.floor(diffMs / (1000 * 60 * 60));
});

const formatMoney = (n) => Math.floor(n || 0).toLocaleString('ru-RU');
</script>

<style scoped>
/* Custom animations */
@keyframes fadeIn {
    from { opacity: 0; transform: translateX(-5px); }
    to { opacity: 1; transform: translateX(0); }
}

@keyframes shake {
    0%, 100% { transform: translateX(0); }
    25% { transform: translateX(-2px); }
    75% { transform: translateX(2px); }
}

@keyframes bounce-subtle {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-2px); }
}

@keyframes spin-slow {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.animate-fadeIn {
    animation: fadeIn 0.15s ease-out;
}

.animate-shake {
    animation: shake 0.3s ease-in-out;
}

.animate-bounce-subtle {
    animation: bounce-subtle 0.4s ease-in-out;
}

.animate-spin-slow {
    animation: spin-slow 2s linear infinite;
}

/* Menu transition */
.menu-enter-active,
.menu-leave-active {
    transition: opacity 0.15s ease, transform 0.15s ease;
}

.menu-enter-from,
.menu-leave-to {
    opacity: 0;
    transform: translateX(-8px);
}
</style>
