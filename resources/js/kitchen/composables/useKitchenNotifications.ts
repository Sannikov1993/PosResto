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
import type { CancellationData } from '../types/index.js';

export function useKitchenNotifications() {
    const uiStore = useUiStore();
    const settingsStore = useSettingsStore();
    const deviceStore = useDeviceStore();

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

    const stationSound = computed(() => deviceStore.stationSound);

    function playNewOrderSound(): void {
        if (!soundEnabled.value) return;
        audioService.playStationNotification(stationSound.value);
    }

    function playReadySound(): void {
        if (!soundEnabled.value) return;
        audioService.playOrderReady();
    }

    function playOverdueSound(): void {
        if (!soundEnabled.value) return;
        audioService.playOverdueWarning();
    }

    function playCancellationSound(): void {
        if (!soundEnabled.value) return;
        audioService.playCancellation();
    }

    function playStopListSound(): void {
        if (!soundEnabled.value) return;
        audioService.playStopList();
    }

    function playWaiterCallSound(): void {
        if (!soundEnabled.value) return;
        audioService.playWaiterCall();
    }

    function toggleSound(): boolean {
        const enabled = settingsStore.toggleSound();
        audioService.enabled = enabled;
        return enabled;
    }

    function showNewOrder(orderNumber: string | number): void {
        uiStore.showNewOrder(orderNumber);
        playNewOrderSound();
    }

    function showCancellation(data: CancellationData): void {
        uiStore.showCancellation(data);
        playCancellationSound();
    }

    function showStopListChange(data: Record<string, any>): void {
        uiStore.showStopListChange(data);
        playStopListSound();
    }

    function showOverdue(orderData: Record<string, any>): void {
        uiStore.showOverdue(orderData);
        playOverdueSound();
    }

    function showWaiterCallToast(data: Record<string, any>): void {
        uiStore.showWaiterCallToast(data);
        playWaiterCallSound();
    }

    function dismissNewOrderAlert(): void {
        uiStore.dismissNewOrderAlert();
    }

    function dismissCancellationAlert(): void {
        uiStore.dismissCancellationAlert();
    }

    function dismissStopListAlert(): void {
        uiStore.dismissStopListAlert();
    }

    function dismissOverdueAlert(): void {
        uiStore.dismissOverdueAlert();
    }

    function dismissWaiterCallToast(): void {
        uiStore.dismissWaiterCallToast();
    }

    function dismissAllAlerts(): void {
        uiStore.dismissAllAlerts();
    }

    onMounted(() => {
        audioService.initialize();
        audioService.enabled = soundEnabled.value;
    });

    onUnmounted(() => {
        // Cleanup not needed - audioService is a singleton
    });

    return {
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
        playNewOrderSound,
        playReadySound,
        playOverdueSound,
        playCancellationSound,
        playStopListSound,
        playWaiterCallSound,
        toggleSound,
        showNewOrder,
        showCancellation,
        showStopListChange,
        showOverdue,
        showWaiterCallToast,
        dismissNewOrderAlert,
        dismissCancellationAlert,
        dismissStopListAlert,
        dismissOverdueAlert,
        dismissWaiterCallToast,
        dismissAllAlerts,
    };
}
