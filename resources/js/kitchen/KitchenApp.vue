<template>
    <div class="min-h-screen bg-gray-900 text-white">
        <!-- Device States -->
        <DeviceLoading v-if="isLoading" />

        <DeviceLinking v-else-if="needsLinking" />

        <DevicePending
            v-else-if="isPending"
            :device-id="deviceId as any"
            :is-checking-status="isCheckingStatus"
            @check-status="checkStatus"
        />

        <DeviceDisabled
            v-else-if="isDisabled"
            :device-id="deviceId as any"
            @retry="checkStatus"
        />

        <!-- Main Kitchen Display -->
        <template v-else-if="isConfigured">
            <!-- Header -->
            <KitchenHeader
                :station-name="stationName"
                :station-icon="stationIcon"
                :current-time="currentTime"
                :current-date="currentDate"
                :focus-mode="focusMode"
                :compact-mode="compactMode"
                :single-column-mode="singleColumnMode"
                :sound-enabled="soundEnabled"
                :auto-responsive-enabled="autoResponsiveEnabled"
                @toggle-single-column="toggleSingleColumnMode"
                @toggle-compact="toggleCompactMode"
                @toggle-focus="toggleFocusMode"
                @toggle-sound="toggleSound"
                @toggle-fullscreen="toggleFullscreen"
                @toggle-auto-responsive="toggleAutoResponsive"
                @open-mobile-menu="showMobileDrawer = true"
            >
                <template #date-selector>
                    <DateSelector
                        :display-date="displaySelectedDate"
                        :is-selected-date-today="isSelectedDateToday"
                        :show-calendar="showCalendarPicker"
                        :calendar-month-year="calendarMonthYear"
                        :calendar-days="calendarDays"
                        :focus-mode="focusMode"
                        @prev-day="goToPreviousDay"
                        @next-day="goToNextDay"
                        @toggle-calendar="toggleCalendar"
                        @close-calendar="closeCalendar"
                        @prev-month="previousMonth"
                        @next-month="nextMonth"
                        @select-date="onSelectDate"
                        @select-today="selectToday"
                        @select-tomorrow="selectTomorrow"
                    />
                </template>

                <template #stop-list>
                    <StopListDropdown
                        :stop-list="stopList"
                        :show="showStopListDropdown"
                        :focus-mode="focusMode"
                        @toggle="toggleStopListDropdown"
                    />
                </template>
            </KitchenHeader>

            <!-- Mobile Settings Drawer -->
            <MobileSettingsDrawer
                :show="showMobileDrawer"
                :current-time="currentTime"
                :current-date="currentDate"
                :single-column-mode="singleColumnMode"
                :compact-mode="compactMode"
                :focus-mode="focusMode"
                :sound-enabled="soundEnabled"
                :auto-responsive-enabled="autoResponsiveEnabled"
                @close="showMobileDrawer = false"
                @toggle-single-column="toggleSingleColumnMode"
                @toggle-compact="toggleCompactMode"
                @toggle-focus="toggleFocusMode"
                @toggle-sound="toggleSound"
                @toggle-fullscreen="toggleFullscreen"
                @toggle-auto-responsive="toggleAutoResponsive"
            >
                <template #date-selector>
                    <DateSelector
                        :display-date="displaySelectedDate"
                        :is-selected-date-today="isSelectedDateToday"
                        :show-calendar="showCalendarPicker"
                        :calendar-month-year="calendarMonthYear"
                        :calendar-days="calendarDays"
                        :focus-mode="false"
                        :compact="true"
                        @prev-day="goToPreviousDay"
                        @next-day="goToNextDay"
                        @toggle-calendar="toggleCalendar"
                        @close-calendar="closeCalendar"
                        @prev-month="previousMonth"
                        @next-month="nextMonth"
                        @select-date="onSelectDate"
                        @select-today="selectToday"
                        @select-tomorrow="selectTomorrow"
                    />
                </template>

                <template #stop-list>
                    <StopListDropdown
                        :stop-list="stopList"
                        :show="showStopListDropdown"
                        :focus-mode="false"
                        :inline="true"
                        @toggle="toggleStopListDropdown"
                    />
                </template>
            </MobileSettingsDrawer>

            <!-- Main Content -->
            <main :class="[
                focusMode ? 'p-2 sm:p-3' : 'p-3 sm:p-4 lg:p-6',
                // Add bottom padding for mobile bottom tabs when in single column mode
                effectiveSingleColumn && effectiveMobile ? 'pb-24' : ''
            ]">
                <!-- Single Column Mode Tabs (non-mobile: top tabs) -->
                <ColumnTabs
                    v-if="effectiveSingleColumn && !effectiveMobile"
                    :active-column="activeColumn"
                    :new-count="totalNewOrders"
                    :cooking-count="cookingOrders.length"
                    :ready-count="readyOrders.length"
                    :is-mobile="false"
                    @select="setActiveColumn"
                />

                <!-- Order Columns -->
                <div
                    :class="[
                        'gap-3 md:gap-4 lg:gap-6',
                        effectiveSingleColumn
                            ? 'flex flex-col'
                            : 'grid grid-cols-1 lg:grid-cols-2 2xl:grid-cols-3',
                        effectiveSingleColumn
                            ? (effectiveMobile
                                ? (focusMode ? 'h-[calc(100vh-120px)]' : 'h-[calc(100vh-140px)]')
                                : (focusMode ? 'h-[calc(100vh-130px)]' : 'h-[calc(100vh-180px)]'))
                            : (focusMode ? 'h-[calc(100vh-70px)]' : 'h-[calc(100vh-90px)] md:h-[calc(100vh-100px)]')
                    ]"
                >
                    <!-- NEW Orders Column -->
                    <div
                        v-show="!effectiveSingleColumn || activeColumn === 'new'"
                        class="flex-1 min-w-0 flex flex-col"
                    >
                        <div class="bg-blue-500 text-white px-4 py-3 rounded-t-2xl font-bold text-xl md:text-2xl flex items-center justify-between">
                            <span>📥 НОВЫЕ</span>
                            <span class="bg-white text-blue-500 px-3 py-1 rounded-full text-lg md:text-xl font-bold">{{ totalNewOrders }}</span>
                        </div>
                        <div class="bg-gray-800 rounded-b-2xl flex-1 overflow-y-auto p-3 md:p-4 space-y-4 @container">
                            <div v-if="totalNewOrders === 0" class="flex flex-col items-center justify-center h-full text-gray-600">
                                <p class="text-6xl mb-4">📭</p>
                                <p class="text-xl">Нет новых заказов</p>
                            </div>
                            <template v-else>
                                <!-- Preorder Time Slots -->
                                <PreorderSlots
                                    :time-slots="preorderTimeSlots"
                                    @start-cooking="startCooking"
                                    @show-dish-info="openDishModal"
                                />
                                <!-- ASAP Orders -->
                                <AsapOrders
                                    :orders="newOrders"
                                    :compact="compactMode"
                                    @start-cooking="startCooking"
                                    @show-dish-info="openDishModal"
                                />
                            </template>
                        </div>
                    </div>

                    <!-- COOKING Orders Column -->
                    <OrderColumn
                        v-show="!effectiveSingleColumn || activeColumn === 'cooking'"
                        title="ГОТОВЯТСЯ"
                        icon="🔥"
                        color="orange"
                        :orders="cookingOrders"
                        empty-icon="👨‍🍳"
                        empty-text="Нет заказов в работе"
                    >
                        <template #card="{ order }">
                            <CookingOrderCard
                                :order="order"
                                :item-done-state="itemDoneState"
                                :compact="compactMode"
                                @toggle-item="toggleItemDone"
                                @mark-ready="markReady"
                                @return-to-new="returnToNew"
                                @mark-item-ready="markItemReady"
                                @show-dish-info="openDishModal"
                            />
                        </template>
                    </OrderColumn>

                    <!-- READY Orders Column -->
                    <OrderColumn
                        v-show="!effectiveSingleColumn || activeColumn === 'ready'"
                        title="ГОТОВЫ"
                        icon="✅"
                        color="green"
                        :orders="readyOrders"
                        empty-icon="✨"
                        empty-text="Нет готовых заказов"
                        :collapsible="true"
                    >
                        <template #card="{ order }">
                            <ReadyOrderCard
                                :order="order"
                                :waiter-called="waiterCalledOrders.has(order.id)"
                                :compact="compactMode"
                                @return-to-cooking="returnToCooking"
                                @call-waiter="callWaiter"
                            />
                        </template>
                    </OrderColumn>
                </div>
            </main>

            <!-- Mobile Bottom Tabs (fixed position) -->
            <ColumnTabs
                v-if="effectiveSingleColumn && effectiveMobile"
                :active-column="activeColumn"
                :new-count="totalNewOrders"
                :cooking-count="cookingOrders.length"
                :ready-count="readyOrders.length"
                :is-mobile="true"
                @select="setActiveColumn"
            />

            <!-- Alerts -->
            <NewOrderAlert
                :show="showNewOrderAlert"
                :order-number="newOrderNumber"
                @dismiss="dismissNewOrderAlert"
            />

            <CancellationAlert
                :show="showCancellationAlert"
                :data="cancellationData"
                @dismiss="dismissCancellationAlert"
            />

            <OverdueAlert
                :show="showOverdueAlert"
                :data="overdueAlertData"
                @dismiss="dismissOverdueAlert"
            />

            <OverdueBadge
                v-if="!showOverdueAlert"
                :overdue-orders="overdueOrders"
                @click="showFirstOverdue"
            />

            <WaiterCallToast
                :show="showWaiterCallSuccess"
                :data="waiterCallData"
            />

            <!-- Dish Detail Modal -->
            <DishDetailModal
                :show="showDishModal"
                :dish="selectedDish as any"
                :modifiers="selectedItemModifiers"
                :comment="selectedItemComment"
                :is-mobile="effectiveMobile"
                @close="closeDishModal"
            />
        </template>
    </div>
</template>

<script setup lang="ts">
/**
 * Kitchen Application
 *
 * Enterprise-grade kitchen display system with responsive design.
 * This component orchestrates the kitchen display using
 * composables for logic and child components for UI.
 *
 * @module kitchen/KitchenApp
 */

import { ref, computed, watch } from 'vue';
import { storeToRefs } from 'pinia';
import { createLogger } from '../shared/services/logger.js';

const log = createLogger('Kitchen');

// Stores
import { useSettingsStore } from './stores/settings.js';
import { useUiStore } from './stores/ui.js';

// Composables
import { useKitchenDevice } from './composables/useKitchenDevice.js';
import { useKitchenOrders } from './composables/useKitchenOrders.js';
import { useKitchenCalendar } from './composables/useKitchenCalendar.js';
import { useKitchenNotifications } from './composables/useKitchenNotifications.js';
import { useKitchenTime } from './composables/useKitchenTime.js';
import { useOverdueCheck } from './composables/useOverdueCheck.js';
import { useResponsive } from './composables/useResponsive.js';
import { useKitchenRealtime } from './composables/useKitchenRealtime.js';

// Initialize Laravel Echo for WebSocket
import '../echo.js';

import { defineAsyncComponent } from 'vue';

// Device Components
import { DeviceLoading, DeviceLinking, DevicePending, DeviceDisabled } from './components/device';

// Layout Components
import { KitchenHeader, ColumnTabs } from './components/layout';
const MobileSettingsDrawer = defineAsyncComponent(() => import('./components/layout/MobileSettingsDrawer.vue'));

// Calendar Components
import { DateSelector } from './components/calendar';

// Alert Components — CancellationAlert lazy (modal)
import { NewOrderAlert, OverdueAlert, OverdueBadge, WaiterCallToast } from './components/alerts';
const CancellationAlert = defineAsyncComponent(() => import('./components/alerts/CancellationAlert.vue'));

// UI Components — DishDetailModal lazy (modal)
import { StopListDropdown } from './components/ui';
const DishDetailModal = defineAsyncComponent(() => import('./components/ui/DishDetailModal.vue'));

// Order Components (existing)
import OrderColumn from './components/OrderColumn.vue';
import NewOrderCard from './components/NewOrderCard.vue';
import CookingOrderCard from './components/CookingOrderCard.vue';
import ReadyOrderCard from './components/ReadyOrderCard.vue';

// Temporary components until fully refactored
import PreorderSlots from './components/PreorderSlots.vue';
import AsapOrders from './components/AsapOrders.vue';

// ==================== Responsive ====================
const { isMobile, isTablet, isDesktop, isTouchDevice, viewportWidth, BREAKPOINTS } = useResponsive();

// Mobile settings drawer state
const showMobileDrawer = ref(false);

// ==================== Device ====================
const {
    deviceId,
    isConfigured,
    needsLinking,
    isPending,
    isDisabled,
    isLoading,
    isCheckingStatus,
    stationName,
    stationIcon,
    checkStatus,
} = useKitchenDevice();

// ==================== Orders ====================
const {
    newOrders,
    cookingOrders,
    readyOrders,
    totalNewOrders,
    preorderTimeSlots,
    overdueOrders,
    itemDoneState,
    waiterCalledOrders,
    startCooking,
    markReady,
    returnToNew,
    returnToCooking,
    markItemReady,
    toggleItemDone,
    callWaiter,
    goToPreviousDay,
    goToNextDay,
    fetchOrders: fetchOrdersAction,
    startPolling,
    stopPolling,
} = useKitchenOrders({ autoFetch: false, autoPoll: false });

// ==================== Real-time (Reverb) ====================
const { connected: realtimeConnected } = useKitchenRealtime({
    autoConnect: true,
    onConnected: () => {
        log.debug('Real-time connected, stopping aggressive polling');
        // When real-time is connected, stop frequent polling
        // Keep slow fallback polling for sync
        stopPolling();
    },
    onDisconnected: () => {
        log.debug('Real-time disconnected, resuming polling');
        // Resume polling when disconnected as fallback
        if (isConfigured.value) {
            startPolling();
        }
    },
});

// Start fetching when device becomes configured
watch(isConfigured, (configured) => {
    if (configured) {
        // Initial fetch
        fetchOrdersAction();
        // Start polling as fallback until real-time connects
        startPolling();
    }
}, { immediate: true });

// ==================== Calendar ====================
const {
    selectedDate,
    isSelectedDateToday,
    showCalendarPicker,
    calendarMonthYear,
    calendarDays,
    toggleCalendar,
    closeCalendar,
    previousMonth,
    nextMonth,
    selectDate,
    selectToday,
    selectTomorrow,
} = useKitchenCalendar();

import { formatDisplayDate } from './utils/format.js';
const displaySelectedDate = computed(() => formatDisplayDate(selectedDate.value));

function onSelectDate(date: any) {
    selectDate(date);
    useKitchenOrders().fetchOrders();
}

// ==================== Notifications ====================
const {
    showNewOrderAlert,
    newOrderNumber,
    showCancellationAlert,
    cancellationData,
    showOverdueAlert,
    overdueAlertData,
    showWaiterCallSuccess,
    waiterCallData,
    toggleSound,
    dismissNewOrderAlert,
    dismissCancellationAlert,
    dismissOverdueAlert,
} = useKitchenNotifications();

// ==================== Time ====================
const { currentTime, currentDate } = useKitchenTime();

// ==================== Overdue Check ====================
useOverdueCheck();

function showFirstOverdue() {
    if (overdueOrders.value.length > 0) {
        useUiStore().showOverdue(overdueOrders.value[0]);
    }
}

// ==================== Settings ====================
const settingsStore = useSettingsStore();
const {
    soundEnabled,
    compactMode,
    focusMode,
    singleColumnMode,
    activeColumn,
    autoResponsiveEnabled,
} = storeToRefs(settingsStore);

const {
    toggleCompactMode,
    toggleFocusMode,
    toggleSingleColumnMode,
    setActiveColumn,
    toggleFullscreen,
    toggleAutoResponsive,
} = settingsStore;

// Force single column on tablets and smaller (< 1024px) regardless of setting
// This provides better UX for touch devices in kitchen environment
const effectiveSingleColumn = computed(() => {
    if (viewportWidth.value < BREAKPOINTS.lg) {
        return true;
    }
    return singleColumnMode.value;
});

// Is effectively mobile (for bottom tabs - < 768px)
// On phones show bottom tabs, on tablets show top tabs
const effectiveMobile = computed(() => viewportWidth.value < BREAKPOINTS.md);

// ==================== UI Store ====================
const uiStore = useUiStore();
const {
    showStopListDropdown,
    stopList,
    showDishModal,
    selectedDish,
    selectedItemModifiers,
    selectedItemComment,
} = storeToRefs(uiStore);

const { toggleStopListDropdown, openDishModal, closeDishModal } = uiStore;
</script>
