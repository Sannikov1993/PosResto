/**
 * Kitchen Notifications Composable
 *
 * Provides notification functionality including
 * audio alerts and visual notifications.
 *
 * @module kitchen/composables/useKitchenNotifications
 */

import { computed, onMounted, onUnmounted } from 'vue';
import { storeToRefs } from 'pinia';
import { useUiStore } from '../stores/ui.js';
import { useSettingsStore } from '../stores/settings.js';
import { useDeviceStore } from '../stores/device.js';
import { audioService } from '../services/audio/AudioService.js';

/**
 * Kitchen notifications composable
 * @returns {Object} Notifications composable
 */
export function useKitchenNotifications() {
    const uiStore = useUiStore();
    const settingsStore = useSettingsStore();
    const deviceStore = useDeviceStore();

    // Store refs
    const { soundEnabled } = storeToRefs(settingsStore);
    const {
        showNewOrderAlert,
        newOrderNumber,
        showCancellationAlert,
        cancellationData,
        showStopListAlert,
        stopListData,
        showOverdueAlert,
        overdueAlertData,
        showWaiterCallSuccess,
        waiterCallData,
    } = storeToRefs(uiStore);

    // Computed
    const stationSound = computed(() => deviceStore.stationSound);

    // ==================== Audio ====================

    /**
     * Play new order notification sound
     */
    function playNewOrderSound() {
        if (!soundEnabled.value) return;
        audioService.playStationNotification(stationSound.value);
    }

    /**
     * Play order ready sound
     */
    function playReadySound() {
        if (!soundEnabled.value) return;
        audioService.playOrderReady();
    }

    /**
     * Play overdue warning sound
     */
    function playOverdueSound() {
        if (!soundEnabled.value) return;
        audioService.playOverdueWarning();
    }

    /**
     * Play cancellation alert sound
     */
    function playCancellationSound() {
        if (!soundEnabled.value) return;
        audioService.playCancellation();
    }

    /**
     * Play stop list notification sound
     */
    function playStopListSound() {
        if (!soundEnabled.value) return;
        audioService.playStopList();
    }

    /**
     * Play waiter call confirmation sound
     */
    function playWaiterCallSound() {
        if (!soundEnabled.value) return;
        audioService.playWaiterCall();
    }

    /**
     * Toggle sound enabled
     * @returns {boolean} New state
     */
    function toggleSound() {
        const enabled = settingsStore.toggleSound();
        audioService.enabled = enabled;
        return enabled;
    }

    // ==================== Alerts ====================

    /**
     * Show new order notification
     * @param {string} orderNumber
     */
    function showNewOrder(orderNumber) {
        uiStore.showNewOrder(orderNumber);
        playNewOrderSound();
    }

    /**
     * Show cancellation alert
     * @param {Object} data - Cancellation data
     */
    function showCancellation(data) {
        uiStore.showCancellation(data);
        playCancellationSound();
    }

    /**
     * Show stop list change alert
     * @param {Object} data - Stop list data
     */
    function showStopListChange(data) {
        uiStore.showStopListChange(data);
        playStopListSound();
    }

    /**
     * Show overdue order alert
     * @param {Object} orderData
     */
    function showOverdue(orderData) {
        uiStore.showOverdue(orderData);
        playOverdueSound();
    }

    /**
     * Show waiter call success toast
     * @param {Object} data
     */
    function showWaiterCallToast(data) {
        uiStore.showWaiterCallToast(data);
        playWaiterCallSound();
    }

    // ==================== Dismiss ====================

    /**
     * Dismiss new order alert
     */
    function dismissNewOrderAlert() {
        uiStore.dismissNewOrderAlert();
    }

    /**
     * Dismiss cancellation alert
     */
    function dismissCancellationAlert() {
        uiStore.dismissCancellationAlert();
    }

    /**
     * Dismiss stop list alert
     */
    function dismissStopListAlert() {
        uiStore.dismissStopListAlert();
    }

    /**
     * Dismiss overdue alert
     */
    function dismissOverdueAlert() {
        uiStore.dismissOverdueAlert();
    }

    /**
     * Dismiss waiter call toast
     */
    function dismissWaiterCallToast() {
        uiStore.dismissWaiterCallToast();
    }

    /**
     * Dismiss all alerts
     */
    function dismissAllAlerts() {
        uiStore.dismissAllAlerts();
    }

    // ==================== Lifecycle ====================

    onMounted(() => {
        // Initialize audio service
        audioService.initialize();
        audioService.enabled = soundEnabled.value;
    });

    onUnmounted(() => {
        // Cleanup not needed - audioService is a singleton
    });

    return {
        // State
        soundEnabled,
        showNewOrderAlert,
        newOrderNumber,
        showCancellationAlert,
        cancellationData,
        showStopListAlert,
        stopListData,
        showOverdueAlert,
        overdueAlertData,
        showWaiterCallSuccess,
        waiterCallData,

        // Audio
        playNewOrderSound,
        playReadySound,
        playOverdueSound,
        playCancellationSound,
        playStopListSound,
        playWaiterCallSound,
        toggleSound,

        // Show alerts
        showNewOrder,
        showCancellation,
        showStopListChange,
        showOverdue,
        showWaiterCallToast,

        // Dismiss
        dismissNewOrderAlert,
        dismissCancellationAlert,
        dismissStopListAlert,
        dismissOverdueAlert,
        dismissWaiterCallToast,
        dismissAllAlerts,
    };
}
