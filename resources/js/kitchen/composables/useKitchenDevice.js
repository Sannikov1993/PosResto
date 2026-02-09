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

/**
 * Kitchen device composable
 * @param {Object} [options] - Configuration options
 * @param {boolean} [options.autoInit=true] - Auto-initialize on mount
 * @param {boolean} [options.autoPoll=true] - Auto-poll device status
 * @returns {Object} Device composable
 */
export function useKitchenDevice(options = {}) {
    const {
        autoInit = true,
        autoPoll = true,
    } = options;

    const deviceStore = useDeviceStore();

    // Refs
    const pollIntervalId = ref(null);
    const linkingCodeDigits = ref(['', '', '', '', '', '']);
    const codeInputRefs = ref([]);

    // Store refs
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

    // Computed
    const isConfigured = computed(() => deviceStore.isConfigured);
    const needsLinking = computed(() => deviceStore.needsLinking);
    const isPending = computed(() => deviceStore.isPending);
    const isDisabled = computed(() => deviceStore.isDisabled);
    const isLoading = computed(() => deviceStore.isLoading);
    const stationSound = computed(() => deviceStore.stationSound);
    const stationName = computed(() => deviceStore.stationName);
    const stationIcon = computed(() => deviceStore.stationIcon);

    /**
     * Initialize device
     */
    async function initialize() {
        await deviceStore.initialize();
    }

    /**
     * Check device status
     */
    async function checkStatus() {
        await deviceStore.checkStatus();
    }

    /**
     * Start polling device status
     */
    function startStatusPolling() {
        if (pollIntervalId.value) return;

        pollIntervalId.value = setInterval(
            checkStatus,
            POLLING_CONFIG.DEVICE_STATUS_INTERVAL
        );
    }

    /**
     * Stop polling device status
     */
    function stopStatusPolling() {
        if (pollIntervalId.value) {
            clearInterval(pollIntervalId.value);
            pollIntervalId.value = null;
        }
    }

    // ==================== Linking Code Input ====================

    /**
     * Get full linking code from digits
     * @returns {string}
     */
    function getLinkingCode() {
        return linkingCodeDigits.value.join('');
    }

    /**
     * Check if linking code is complete
     * @returns {boolean}
     */
    function isLinkingCodeComplete() {
        return getLinkingCode().length === 6;
    }

    /**
     * Clear linking code digits (preserves error message)
     */
    function clearLinkingCode() {
        linkingCodeDigits.value = ['', '', '', '', '', ''];
        // Focus first input
        codeInputRefs.value[0]?.focus();
    }

    /**
     * Handle code digit input
     * @param {number} index - Digit index (0-5)
     * @param {Event} event - Input event
     */
    function onCodeDigitInput(index, event) {
        const value = event.target.value;

        // Clear error when user starts typing new code
        if (linkingError.value) {
            deviceStore.clearLinkingError();
        }

        // Only allow digits
        if (value && !/^\d$/.test(value)) {
            linkingCodeDigits.value[index] = '';
            return;
        }

        // Move to next input if digit entered
        if (value && index < 5) {
            codeInputRefs.value[index + 1]?.focus();
        }
    }

    /**
     * Handle keydown on code input
     * @param {number} index - Digit index
     * @param {KeyboardEvent} event - Keyboard event
     */
    function onCodeDigitKeydown(index, event) {
        // Handle backspace - move to previous input
        if (event.key === 'Backspace' && !linkingCodeDigits.value[index] && index > 0) {
            codeInputRefs.value[index - 1]?.focus();
        }
    }

    /**
     * Handle paste into code inputs
     * @param {ClipboardEvent} event - Paste event
     */
    function onCodePaste(event) {
        event.preventDefault();
        const pastedText = (event.clipboardData || window.clipboardData).getData('text');
        const digits = pastedText.replace(/\D/g, '').slice(0, 6).split('');

        digits.forEach((digit, i) => {
            linkingCodeDigits.value[i] = digit;
        });

        // Focus last filled or next empty
        const focusIndex = Math.min(digits.length, 5);
        codeInputRefs.value[focusIndex]?.focus();
    }

    /**
     * Submit linking code
     * @returns {Promise<boolean>} True if successful
     */
    async function submitLinkingCode() {
        const code = getLinkingCode();
        if (code.length !== 6) return false;

        const success = await deviceStore.linkDevice(code);

        if (!success) {
            // Clear code on error
            clearLinkingCode();
        }

        return success;
    }

    // ==================== Lifecycle ====================

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
        // State
        deviceId,
        status,
        deviceData,
        currentStation,
        stationSlug,
        isLinking,
        linkingError,
        isCheckingStatus,

        // Computed
        isConfigured,
        needsLinking,
        isPending,
        isDisabled,
        isLoading,
        stationSound,
        stationName,
        stationIcon,

        // Actions
        initialize,
        checkStatus,
        startStatusPolling,
        stopStatusPolling,

        // Linking code
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
