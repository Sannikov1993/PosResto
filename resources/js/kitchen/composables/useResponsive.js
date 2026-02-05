/**
 * Responsive Composable
 *
 * Auto-detect viewport size and device capabilities.
 * Provides reactive breakpoint states for responsive UI adaptation.
 *
 * Breakpoints:
 * - Mobile: < 640px (default Tailwind)
 * - Tablet: 640-1023px (sm:, md:)
 * - Desktop: 1024px+ (lg:, xl:)
 *
 * @module kitchen/composables/useResponsive
 */

import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { useSettingsStore } from '../stores/settings.js';

// Breakpoint constants matching Tailwind
const BREAKPOINTS = {
    sm: 640,
    md: 768,
    lg: 1024,
    xl: 1280,
};

// Shared state across all instances
const viewportWidth = ref(typeof window !== 'undefined' ? window.innerWidth : 1024);
const viewportHeight = ref(typeof window !== 'undefined' ? window.innerHeight : 768);
let resizeHandler = null;
let mediaQueryTouch = null;
let initialized = false;

/**
 * Detect if device is touch-capable
 * @returns {boolean}
 */
function detectTouchDevice() {
    if (typeof window === 'undefined') return false;
    return (
        'ontouchstart' in window ||
        navigator.maxTouchPoints > 0 ||
        (window.matchMedia && window.matchMedia('(hover: none)').matches)
    );
}

const isTouchDevice = ref(detectTouchDevice());

/**
 * Initialize global resize listener (once)
 */
function initializeListeners() {
    if (initialized || typeof window === 'undefined') return;

    // Throttled resize handler
    let resizeTimeout;
    resizeHandler = () => {
        if (resizeTimeout) return;
        resizeTimeout = setTimeout(() => {
            viewportWidth.value = window.innerWidth;
            viewportHeight.value = window.innerHeight;
            resizeTimeout = null;
        }, 100);
    };

    window.addEventListener('resize', resizeHandler, { passive: true });

    // Touch media query listener
    if (window.matchMedia) {
        mediaQueryTouch = window.matchMedia('(hover: none)');
        const touchHandler = (e) => {
            isTouchDevice.value = e.matches || detectTouchDevice();
        };
        if (mediaQueryTouch.addEventListener) {
            mediaQueryTouch.addEventListener('change', touchHandler);
        } else if (mediaQueryTouch.addListener) {
            mediaQueryTouch.addListener(touchHandler);
        }
    }

    initialized = true;
}

/**
 * Responsive composable for viewport detection
 *
 * @returns {Object} Reactive responsive state and utilities
 */
export function useResponsive() {
    const settingsStore = useSettingsStore();

    // Initialize listeners on first use
    onMounted(() => {
        initializeListeners();
        // Update initial values
        if (typeof window !== 'undefined') {
            viewportWidth.value = window.innerWidth;
            viewportHeight.value = window.innerHeight;
        }
    });

    // Breakpoint computed values
    const isMobile = computed(() => viewportWidth.value < BREAKPOINTS.sm);
    const isSmallMobile = computed(() => viewportWidth.value < 375);
    const isTablet = computed(() =>
        viewportWidth.value >= BREAKPOINTS.sm && viewportWidth.value < BREAKPOINTS.lg
    );
    const isDesktop = computed(() => viewportWidth.value >= BREAKPOINTS.lg);
    const isLargeDesktop = computed(() => viewportWidth.value >= BREAKPOINTS.xl);

    // More specific breakpoints
    const isPortrait = computed(() => viewportHeight.value > viewportWidth.value);
    const isLandscape = computed(() => viewportWidth.value > viewportHeight.value);

    // Responsive mode for single column
    const shouldUseSingleColumn = computed(() => viewportWidth.value < BREAKPOINTS.md);

    /**
     * Check if auto-responsive is enabled and should apply
     */
    const autoResponsiveActive = computed(() => {
        return settingsStore.autoResponsiveEnabled && settingsStore.layoutSource === 'auto';
    });

    /**
     * Apply auto-responsive settings based on viewport
     * Only applies if autoResponsiveEnabled and not manually overridden
     */
    function applyAutoResponsive() {
        if (!settingsStore.autoResponsiveEnabled) return;

        // Only auto-apply if not manually overridden
        if (settingsStore.layoutSource === 'auto') {
            const shouldBeSingleColumn = viewportWidth.value < BREAKPOINTS.md;
            if (settingsStore.singleColumnMode !== shouldBeSingleColumn) {
                settingsStore.setSingleColumnModeAuto(shouldBeSingleColumn);
            }
        }
    }

    /**
     * Initialize auto-responsive on mount
     */
    function initAutoResponsive() {
        if (settingsStore.autoResponsiveEnabled && settingsStore.layoutSource === 'auto') {
            applyAutoResponsive();
        }
    }

    /**
     * Manually override layout (disables auto for this setting)
     */
    function setManualLayout(singleColumn) {
        settingsStore.setSingleColumnModeManual(singleColumn);
    }

    /**
     * Reset to auto-responsive mode
     */
    function resetToAutoResponsive() {
        settingsStore.resetLayoutToAuto();
        applyAutoResponsive();
    }

    // Watch viewport and auto-apply
    let unwatchWidth = null;
    onMounted(() => {
        initAutoResponsive();

        // Watch for resize and auto-apply
        unwatchWidth = watch(viewportWidth, () => {
            if (settingsStore.autoResponsiveEnabled && settingsStore.layoutSource === 'auto') {
                applyAutoResponsive();
            }
        });
    });

    onUnmounted(() => {
        if (unwatchWidth) unwatchWidth();
    });

    return {
        // Viewport dimensions
        viewportWidth,
        viewportHeight,

        // Device type flags
        isMobile,
        isSmallMobile,
        isTablet,
        isDesktop,
        isLargeDesktop,
        isTouchDevice,

        // Orientation
        isPortrait,
        isLandscape,

        // Auto-responsive
        shouldUseSingleColumn,
        autoResponsiveActive,
        applyAutoResponsive,
        initAutoResponsive,
        setManualLayout,
        resetToAutoResponsive,

        // Breakpoint constants
        BREAKPOINTS,
    };
}
