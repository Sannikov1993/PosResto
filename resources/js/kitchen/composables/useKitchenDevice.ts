/**
 * Kitchen Device Composable
 *
 * Provides device management functionality including
 * initialization, linking, and status checking.
 *
 * @module kitchen/composables/useKitchenDevice
 */

import { computed, onMounted, onUnmounted, ref } from 'vue';
import { storeToRefs } from 'pinia';
import { useDeviceStore } from '../stores/device.js';
import { POLLING_CONFIG } from '../constants/thresholds.js';

interface KitchenDeviceOptions {
    autoInit?: boolean;
    autoPoll?: boolean;
}

export function useKitchenDevice(options: KitchenDeviceOptions = {}) {
    const {
        autoInit = true,
        autoPoll = true,
    } = options;

    const deviceStore = useDeviceStore();

    const pollIntervalId = ref<ReturnType<typeof setInterval> | null>(null);
    const linkingCodeDigits = ref<string[]>(['', '', '', '', '', '']);
    const codeInputRefs = ref<HTMLInputElement[]>([]);

    const {
        deviceId,
        status,
        deviceData,
        currentStation,
        stationSlug,
        isLinking,
        linkingError,
        isCheckingStatus,
    } = storeToRefs(deviceStore);

    const isConfigured = computed(() => deviceStore.isConfigured);
    const needsLinking = computed(() => deviceStore.needsLinking);
    const isPending = computed(() => deviceStore.isPending);
    const isDisabled = computed(() => deviceStore.isDisabled);
    const isLoading = computed(() => deviceStore.isLoading);
    const stationSound = computed(() => deviceStore.stationSound);
    const stationName = computed(() => deviceStore.stationName);
    const stationIcon = computed(() => deviceStore.stationIcon);

    async function initialize(): Promise<void> {
        await deviceStore.initialize();
    }

    async function checkStatus(): Promise<void> {
        await deviceStore.checkStatus();
    }

    function startStatusPolling(): void {
        if (pollIntervalId.value) return;

        pollIntervalId.value = setInterval(
            checkStatus,
            POLLING_CONFIG.DEVICE_STATUS_INTERVAL
        );
    }

    function stopStatusPolling(): void {
        if (pollIntervalId.value) {
            clearInterval(pollIntervalId.value);
            pollIntervalId.value = null;
        }
    }

    function getLinkingCode(): string {
        return linkingCodeDigits.value.join('');
    }

    function isLinkingCodeComplete(): boolean {
        return getLinkingCode().length === 6;
    }

    function clearLinkingCode(): void {
        linkingCodeDigits.value = ['', '', '', '', '', ''];
        codeInputRefs.value[0]?.focus();
    }

    function onCodeDigitInput(index: number, event: Event): void {
        const value = (event.target as HTMLInputElement).value;

        if (linkingError.value) {
            deviceStore.clearLinkingError();
        }

        if (value && !/^\d$/.test(value)) {
            linkingCodeDigits.value[index] = '';
            return;
        }

        if (value && index < 5) {
            codeInputRefs.value[index + 1]?.focus();
        }
    }

    function onCodeDigitKeydown(index: number, event: KeyboardEvent): void {
        if (event.key === 'Backspace' && !linkingCodeDigits.value[index] && index > 0) {
            codeInputRefs.value[index - 1]?.focus();
        }
    }

    function onCodePaste(event: ClipboardEvent): void {
        event.preventDefault();
        const pastedText = (event.clipboardData || (window as any).clipboardData).getData('text');
        const digits = pastedText.replace(/\D/g, '').slice(0, 6).split('');

        digits.forEach((digit: string, i: number) => {
            linkingCodeDigits.value[i] = digit;
        });

        const focusIndex = Math.min(digits.length, 5);
        codeInputRefs.value[focusIndex]?.focus();
    }

    async function submitLinkingCode(): Promise<boolean> {
        const code = getLinkingCode();
        if (code.length !== 6) return false;

        const success = await deviceStore.linkDevice(code);

        if (!success) {
            clearLinkingCode();
        }

        return success;
    }

    onMounted(async () => {
        if (autoInit) {
            await initialize();
        }

        if (autoPoll && (isPending.value || !isConfigured.value)) {
            startStatusPolling();
        }
    });

    onUnmounted(() => {
        stopStatusPolling();
    });

    return {
        deviceId,
        status,
        deviceData,
        currentStation,
        stationSlug,
        isLinking,
        linkingError,
        isCheckingStatus,
        isConfigured,
        needsLinking,
        isPending,
        isDisabled,
        isLoading,
        stationSound,
        stationName,
        stationIcon,
        initialize,
        checkStatus,
        startStatusPolling,
        stopStatusPolling,
        linkingCodeDigits,
        codeInputRefs,
        getLinkingCode,
        isLinkingCodeComplete,
        clearLinkingCode,
        onCodeDigitInput,
        onCodeDigitKeydown,
        onCodePaste,
        submitLinkingCode,
    };
}
