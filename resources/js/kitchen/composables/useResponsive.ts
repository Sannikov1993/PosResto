/**
 * Responsive Composable
 *
 * Auto-detect viewport size and device capabilities.
 *
 * @module kitchen/composables/useResponsive
 */

import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { useSettingsStore } from '../stores/settings.js';
import type { WatchStopHandle } from 'vue';

const BREAKPOINTS = {
    sm: 640,
    md: 768,
    lg: 1024,
    xl: 1280,
} as const;

const viewportWidth = ref(typeof window !== 'undefined' ? window.innerWidth : 1024);
const viewportHeight = ref(typeof window !== 'undefined' ? window.innerHeight : 768);
let resizeHandler: (() => void) | null = null;
let initialized = false;

function detectTouchDevice(): boolean {
    if (typeof window === 'undefined') return false;
    return (
        'ontouchstart' in window ||
        navigator.maxTouchPoints > 0 ||
        (window.matchMedia && window.matchMedia('(hover: none)').matches)
    );
}

const isTouchDevice = ref(detectTouchDevice());

function initializeListeners(): void {
    if (initialized || typeof window === 'undefined') return;

    let resizeTimeout: ReturnType<typeof setTimeout> | null;
    resizeHandler = () => {
        if (resizeTimeout) return;
        resizeTimeout = setTimeout(() => {
            viewportWidth.value = window.innerWidth;
            viewportHeight.value = window.innerHeight;
            resizeTimeout = null;
        }, 100);
    };

    window.addEventListener('resize', resizeHandler, { passive: true });

    if (window.matchMedia) {
        const mediaQueryTouch = window.matchMedia('(hover: none)');
        const touchHandler = (e: MediaQueryListEvent) => {
            isTouchDevice.value = e.matches || detectTouchDevice();
        };
        if (mediaQueryTouch.addEventListener) {
            mediaQueryTouch.addEventListener('change', touchHandler);
        }
    }

    initialized = true;
}

export function useResponsive() {
    const settingsStore = useSettingsStore();

    onMounted(() => {
        initializeListeners();
        if (typeof window !== 'undefined') {
            viewportWidth.value = window.innerWidth;
            viewportHeight.value = window.innerHeight;
        }
    });

    const isMobile = computed(() => viewportWidth.value < BREAKPOINTS.sm);
    const isSmallMobile = computed(() => viewportWidth.value < 375);
    const isTablet = computed(() =>
        viewportWidth.value >= BREAKPOINTS.sm && viewportWidth.value < BREAKPOINTS.lg
    );
    const isDesktop = computed(() => viewportWidth.value >= BREAKPOINTS.lg);
    const isLargeDesktop = computed(() => viewportWidth.value >= BREAKPOINTS.xl);

    const isPortrait = computed(() => viewportHeight.value > viewportWidth.value);
    const isLandscape = computed(() => viewportWidth.value > viewportHeight.value);

    const shouldUseSingleColumn = computed(() => viewportWidth.value < BREAKPOINTS.md);

    const autoResponsiveActive = computed(() => {
        return settingsStore.autoResponsiveEnabled && settingsStore.layoutSource === 'auto';
    });

    function applyAutoResponsive(): void {
        if (!settingsStore.autoResponsiveEnabled) return;

        if (settingsStore.layoutSource === 'auto') {
            const shouldBeSingleColumn = viewportWidth.value < BREAKPOINTS.md;
            if (settingsStore.singleColumnMode !== shouldBeSingleColumn) {
                settingsStore.setSingleColumnModeAuto(shouldBeSingleColumn);
            }
        }
    }

    function initAutoResponsive(): void {
        if (settingsStore.autoResponsiveEnabled && settingsStore.layoutSource === 'auto') {
            applyAutoResponsive();
        }
    }

    function setManualLayout(singleColumn: boolean): void {
        settingsStore.setSingleColumnModeManual(singleColumn);
    }

    function resetToAutoResponsive(): void {
        settingsStore.resetLayoutToAuto();
        applyAutoResponsive();
    }

    let unwatchWidth: WatchStopHandle | null = null;
    onMounted(() => {
        initAutoResponsive();

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
        viewportWidth,
        viewportHeight,
        isMobile,
        isSmallMobile,
        isTablet,
        isDesktop,
        isLargeDesktop,
        isTouchDevice,
        isPortrait,
        isLandscape,
        shouldUseSingleColumn,
        autoResponsiveActive,
        applyAutoResponsive,
        initAutoResponsive,
        setManualLayout,
        resetToAutoResponsive,
        BREAKPOINTS,
    };
}
