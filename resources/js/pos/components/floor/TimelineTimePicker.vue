<template>
    <div class="timeline-picker-wrapper" :class="{ 'embedded-mode': embedded }">
        <!-- Trigger button - shows selected time (hidden in embedded mode) -->
        <button v-if="!embedded"
                @click="toggleOpen"
                class="w-full flex items-center justify-between px-4 py-3 bg-[#252a3a] rounded-xl border border-gray-700 hover:border-gray-600 transition-colors">
            <div class="flex items-center gap-3">
                <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span class="text-white text-lg font-medium">{{ displayTime }}</span>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-gray-500 text-sm">{{ duration }}</span>
                <svg :class="['w-4 h-4 text-gray-400 transition-transform', isOpen ? 'rotate-180' : '']" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </div>
        </button>

        <!-- Overlay panel - slides down inside parent modal (or always visible in embedded mode) -->
        <Transition name="slide-down">
            <div v-if="isOpen || embedded" class="timeline-overlay" :class="{ 'embedded': embedded }">
                <!-- Header -->
                <div class="timeline-header">
                    <div class="flex items-center gap-3">
                        <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span class="text-white font-semibold">Выбор времени</span>
                    </div>
                    <button @click="closeWithoutSave" class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-white/10 text-gray-400 hover:text-white transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <!-- Selection hint & quick actions -->
                <div class="timeline-hint">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <div :class="['w-2 h-2 rounded-full', selectionMode === 'start' ? 'bg-green-400 animate-pulse' : 'bg-blue-400 animate-pulse']"></div>
                            <span class="text-sm text-gray-300 whitespace-nowrap">
                                {{ selectionMode === 'start' ? 'Выберите начало' : 'Выберите конец' }}
                            </span>
                        </div>
                        <div class="flex gap-1.5">
                            <button v-if="selectionMode === 'end'"
                                    @click="setUntilClosing"
                                    class="px-3 py-1.5 rounded-lg text-xs font-medium bg-orange-500/20 text-orange-400 hover:bg-orange-500/30 transition-colors">
                                До закрытия
                            </button>
                            <button v-if="tempTimeFrom"
                                    @click="resetSelection"
                                    class="px-3 py-1.5 rounded-lg text-xs font-medium bg-gray-700/50 text-gray-400 hover:bg-gray-600/50 transition-colors">
                                Сбросить
                            </button>
                        </div>
                    </div>
                    <!-- Show selected range -->
                    <div v-if="tempTimeFrom" class="mt-2 flex items-center gap-2">
                        <span class="text-gray-500 text-sm">Выбрано:</span>
                        <span class="px-2 py-1 bg-green-500/20 text-green-400 rounded text-sm font-medium">{{ tempTimeFrom }}</span>
                        <template v-if="tempTimeTo">
                            <span class="text-gray-600">→</span>
                            <span class="px-2 py-1 bg-blue-500/20 text-blue-400 rounded text-sm font-medium">{{ tempTimeTo }}</span>
                            <!-- Midnight crossing indicator -->
                            <span v-if="crossesMidnight" class="px-2 py-1 bg-purple-500/20 text-purple-400 rounded text-xs font-medium">+1 день</span>
                            <span class="text-gray-500 text-sm ml-2">({{ tempDuration }})</span>
                        </template>
                    </div>
                </div>

                <!-- Timeline slots - scrollable area -->
                <div class="timeline-slots" ref="timelineRef">
                    <div v-for="slot in timeSlots" :key="slot.time"
                         @click="handleSlotClick(slot)"
                         :class="['timeline-slot', getSlotClasses(slot)]">
                        <!-- Time label -->
                        <div class="slot-time" :class="slot.disabled ? 'text-gray-600' : 'text-white'">
                            {{ slot.time }}
                            <span v-if="slot.isNextDay" class="text-purple-400 text-xs ml-1">+1</span>
                        </div>

                        <!-- Status bar -->
                        <div class="slot-bar">
                            <!-- Existing reservations -->
                            <div v-for="(res, idx) in slot.reservations" :key="idx"
                                 class="slot-reservation"
                                 :style="{ left: res.start + '%', width: res.width + '%' }"
                                 :title="res.name">
                            </div>

                            <!-- Selected range indicator -->
                            <div v-if="isInSelectedRange(slot.time)" class="slot-selected"></div>

                            <!-- Start marker -->
                            <div v-if="slot.time === tempTimeFrom" class="slot-marker-start"></div>

                            <!-- End marker -->
                            <div v-if="slot.time === tempTimeTo" class="slot-marker-end"></div>
                        </div>

                        <!-- Status text -->
                        <div class="slot-status" :class="getSlotStatusClass(slot)">
                            {{ getSlotStatusText(slot) }}
                        </div>
                    </div>
                </div>

                <!-- Legend -->
                <div class="timeline-legend">
                    <div class="legend-item">
                        <div class="w-3 h-3 rounded bg-green-400"></div>
                        <span>Начало</span>
                    </div>
                    <div class="legend-item">
                        <div class="w-3 h-3 rounded bg-blue-500/40"></div>
                        <span>Выбрано</span>
                    </div>
                    <div class="legend-item">
                        <div class="w-3 h-3 rounded bg-red-500/60"></div>
                        <span>Занято</span>
                    </div>
                    <div class="legend-item">
                        <div class="w-3 h-3 rounded bg-gray-600/60"></div>
                        <span>Прошло</span>
                    </div>
                </div>

                <!-- Confirm button -->
                <div class="timeline-footer">
                    <button @click="confirm"
                            :disabled="!canConfirm"
                            :class="[
                                'w-full py-3 rounded-xl text-sm font-medium transition-colors',
                                canConfirm
                                    ? 'bg-blue-500 hover:bg-blue-600 text-white'
                                    : 'bg-gray-700 text-gray-500 cursor-not-allowed'
                            ]">
                        {{ canConfirm ? `Подтвердить ${tempTimeFrom} — ${tempTimeTo}${crossesMidnight ? ' (+1)' : ''}` : 'Выберите время' }}
                    </button>
                </div>
            </div>
        </Transition>
    </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, nextTick, onMounted, PropType } from 'vue';

// Helper для локальной даты (не UTC!)
const getLocalDateString = (date = new Date()) => {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
};

const props = defineProps({
    modelValue: {
        type: Object as PropType<Record<string, any>>,
        default: () => ({ time_from: '19:00', time_to: '21:00' })
    },
    existingReservations: {
        type: Array as PropType<any[]>,
        default: () => []
    },
    workingHours: {
        type: Object as PropType<Record<string, any>>,
        default: () => ({ start: '10:00', end: '23:00' })
    },
    panelWidth: {
        type: String,
        default: '384px'
    },
    embedded: {
        type: Boolean,
        default: false
    },
    selectedDate: {
        type: String,
        default: null  // Format: 'YYYY-MM-DD'
    }
});

const emit = defineEmits(['update:modelValue', 'close']);

const isOpen = ref(false);
const timelineRef = ref<any>(null);

// Selection state
const selectionMode = ref('start'); // 'start' or 'end'
const tempTimeFrom = ref<any>(null);
const tempTimeTo = ref<any>(null);

// Initialize from modelValue
watch(() => props.modelValue, (val) => {
    if (val.time_from && val.time_to && !isOpen.value) {
        tempTimeFrom.value = val.time_from;
        tempTimeTo.value = val.time_to;
    }
}, { immediate: true });

// Reset selection mode when opening
watch(isOpen, (val) => {
    if (val) {
        if (props.modelValue.time_from && props.modelValue.time_to) {
            tempTimeFrom.value = props.modelValue.time_from;
            tempTimeTo.value = props.modelValue.time_to;
            selectionMode.value = 'start';
        } else {
            resetSelection();
        }
    }
});

// Initialize in embedded mode
onMounted(() => {
    if (props.embedded) {
        tempTimeFrom.value = props.modelValue.time_from;
        tempTimeTo.value = props.modelValue.time_to;
        selectionMode.value = 'start';
        nextTick(() => scrollToSelected());
    }
});

// Check if selected date is today
const isToday = computed(() => {
    if (!props.selectedDate) return false;
    const today = getLocalDateString();
    return props.selectedDate === today;
});

// Get current time in minutes
const currentTimeMinutes = computed(() => {
    const now = new Date();
    return now.getHours() * 60 + now.getMinutes();
});

// Check if working hours cross midnight (e.g., 18:00 - 02:00)
const isOvernightSchedule = computed(() => {
    const [startH] = props.workingHours.start.split(':').map(Number);
    const [endH] = props.workingHours.end.split(':').map(Number);
    return endH < startH;
});

// Generate time slots with overnight support
const timeSlots = computed(() => {
    const slots = [];
    const [startH, startM] = props.workingHours.start.split(':').map(Number);
    const [endH, endM] = props.workingHours.end.split(':').map(Number);

    // Calculate total minutes for proper iteration
    // For overnight: 22:00-02:00 means slots from 22:00 to 26:00 (next day 02:00)
    const startTotalMinutes = startH * 60 + startM;
    let endTotalMinutes = endH * 60 + endM;
    if (isOvernightSchedule.value) {
        endTotalMinutes += 24 * 60; // Add 24 hours for next day
    }

    for (let totalMin = startTotalMinutes; totalMin < endTotalMinutes; totalMin += 30) {
        // Normalize to 24-hour format for display
        const displayMinutes = totalMin % (24 * 60);
        const h = Math.floor(displayMinutes / 60);
        const m = displayMinutes % 60;

        const time = `${h.toString().padStart(2, '0')}:${m.toString().padStart(2, '0')}`;
        const slotMinutes = totalMin; // Keep original for comparison
        const isNextDay = totalMin >= 24 * 60;

        const reservations: any = [];
        let hasReservation = false;
        let reservationName = '';

        props.existingReservations.forEach((res: any) => {
            const [rh1, rm1] = (res.time_from || '').split(':').map(Number);
            let [rh2, rm2] = (res.time_to || '').split(':').map(Number);
            let resStart = rh1 * 60 + rm1;
            let resEnd = rh2 * 60 + rm2;

            // Handle midnight-crossing reservations
            // If the reservation crosses midnight (end < start), adjust for comparison
            if (res.crosses_midnight || resEnd <= resStart) {
                resEnd += 24 * 60;
            }

            // Also adjust resStart if it's in the "next day" portion of overnight schedule
            if (isOvernightSchedule.value && resStart < startTotalMinutes) {
                resStart += 24 * 60;
                resEnd += 24 * 60;
            }

            if (slotMinutes >= resStart && slotMinutes < resEnd) {
                hasReservation = true;
                reservationName = res.guest_name || 'Занято';
                const startPct = Math.max(0, ((resStart - slotMinutes) / 30) * 100);
                const endPct = Math.min(100, ((resEnd - slotMinutes) / 30) * 100);
                reservations.push({
                    start: Math.max(0, startPct),
                    width: Math.min(100, endPct) - Math.max(0, startPct),
                    name: res.guest_name
                });
            }
        });

        // Check if slot is in the past (only for today, and not for next-day slots)
        const isPast = isToday.value && !isNextDay && displayMinutes < currentTimeMinutes.value;

        slots.push({
            time,
            minutes: slotMinutes,
            displayMinutes,
            isNextDay,
            disabled: hasReservation || isPast,
            hasReservation,
            isPast,
            reservationName: isPast ? 'Прошло' : reservationName,
            reservations
        });
    }

    return slots;
});

// Check if selection crosses midnight
const crossesMidnight = computed(() => {
    if (!tempTimeFrom.value || !tempTimeTo.value) return false;
    const [h1, m1] = tempTimeFrom.value.split(':').map(Number);
    const [h2, m2] = tempTimeTo.value.split(':').map(Number);
    const startMinutes = h1 * 60 + m1;
    const endMinutes = h2 * 60 + m2;
    return endMinutes <= startMinutes;
});

// Helper to get normalized minutes for a slot (handles overnight)
const getSlotNormalizedMinutes = (slot: any) => {
    if (typeof slot === 'object' && slot.minutes !== undefined) {
        return slot.minutes;
    }
    // For time string
    const [h, m] = String(slot).split(':').map(Number);
    let minutes = h * 60 + m;
    // If overnight schedule and time is in the early hours, add 24 hours
    const [startH] = props.workingHours.start.split(':').map(Number);
    if (isOvernightSchedule.value && h < startH) {
        minutes += 24 * 60;
    }
    return minutes;
};

// Check if slot is in selected range (handles overnight)
const isInSelectedRange = (time: any) => {
    if (!tempTimeFrom.value) return false;

    // Find the slot object for this time to get proper minutes
    const slot = timeSlots.value.find((s: any) => s.time === time);
    const slotMinutes = slot ? slot.minutes : getSlotNormalizedMinutes(time);

    const startMinutes = getSlotNormalizedMinutes(tempTimeFrom.value);

    let endMinutes = startMinutes;
    if (tempTimeTo.value) {
        endMinutes = getSlotNormalizedMinutes(tempTimeTo.value);
        // Handle midnight crossing in selection
        if (endMinutes <= startMinutes) {
            endMinutes += 24 * 60;
        }
    }

    return slotMinutes >= startMinutes && slotMinutes < endMinutes;
};

// Get slot classes
const getSlotClasses = (slot: any) => {
    const classes = [];

    if (slot.disabled) {
        classes.push('disabled');
        if (slot.isPast) {
            classes.push('is-past');
        }
    } else {
        classes.push('clickable');
    }

    if (isInSelectedRange(slot.time)) {
        classes.push('in-range');
    }

    if (slot.time === tempTimeFrom.value) {
        classes.push('is-start');
    }

    if (slot.time === tempTimeTo.value) {
        classes.push('is-end');
    }

    return classes.join(' ');
};

// Get slot status text
const getSlotStatusText = (slot: any) => {
    if (slot.time === tempTimeFrom.value) return 'Начало';
    if (slot.time === tempTimeTo.value) return 'Конец';
    if (slot.isPast) return 'Прошло';
    if (slot.hasReservation) return slot.reservationName;
    return '';
};

// Get slot status class
const getSlotStatusClass = (slot: any) => {
    if (slot.time === tempTimeFrom.value) return 'text-green-400';
    if (slot.time === tempTimeTo.value) return 'text-blue-400';
    if (slot.isPast) return 'text-gray-500';
    if (slot.hasReservation) return 'text-red-400';
    return 'text-gray-600';
};

// Handle slot click (supports overnight selection)
const handleSlotClick = (slot: any) => {
    if (slot.disabled) return;

    const clickedMinutes = slot.minutes;

    if (selectionMode.value === 'start') {
        tempTimeFrom.value = slot.time;
        tempTimeTo.value = null;
        selectionMode.value = 'end';
    } else {
        const startMinutes = getSlotNormalizedMinutes(tempTimeFrom.value);

        // In overnight mode, allow end time to be "earlier" (next day)
        if (clickedMinutes <= startMinutes && !isOvernightSchedule.value) {
            // Clicked before start - reset start
            tempTimeFrom.value = slot.time;
            tempTimeTo.value = null;
            selectionMode.value = 'end';
        } else {
            tempTimeTo.value = slot.time;
            selectionMode.value = 'start';
        }
    }
};

// Set until closing time
const setUntilClosing = () => {
    if (!tempTimeFrom.value) return;
    tempTimeTo.value = props.workingHours.end;
    selectionMode.value = 'start';
};

// Reset selection
const resetSelection = () => {
    tempTimeFrom.value = null;
    tempTimeTo.value = null;
    selectionMode.value = 'start';
};

// Can confirm
const canConfirm = computed(() => {
    return tempTimeFrom.value && tempTimeTo.value;
});

// Display time
const displayTime = computed(() => {
    const from = props.modelValue.time_from || '—';
    const to = props.modelValue.time_to || '—';
    return `${from} — ${to}`;
});

// Calculate duration with midnight crossing support
const calculateDuration = (timeFrom: any, timeTo: any) => {
    if (!timeFrom || !timeTo) return '';

    const startMinutes = getSlotNormalizedMinutes(timeFrom);
    let endMinutes = getSlotNormalizedMinutes(timeTo);

    // Handle midnight crossing
    if (endMinutes <= startMinutes) {
        endMinutes += 24 * 60;
    }

    const diff = endMinutes - startMinutes;
    if (diff <= 0) return '';

    const hours = Math.floor(diff / 60);
    const mins = diff % 60;

    if (hours === 0) return `${mins}м`;
    if (mins === 0) return `${hours}ч`;
    return `${hours}ч ${mins}м`;
};

// Duration text
const duration = computed(() => {
    return calculateDuration(props.modelValue.time_from, props.modelValue.time_to);
});

// Temp duration text (for selection preview)
const tempDuration = computed(() => {
    return calculateDuration(tempTimeFrom.value, tempTimeTo.value);
});

// Toggle open
const toggleOpen = () => {
    isOpen.value = !isOpen.value;
    if (isOpen.value) {
        nextTick(() => {
            scrollToSelected();
        });
    }
};

// Close without saving
const closeWithoutSave = () => {
    // Restore original values
    tempTimeFrom.value = props.modelValue.time_from;
    tempTimeTo.value = props.modelValue.time_to;
    if (props.embedded) {
        emit('close');
    } else {
        isOpen.value = false;
    }
};

// Scroll to selected time
const scrollToSelected = () => {
    if (!timelineRef.value) return;

    const targetTime = tempTimeFrom.value || props.modelValue.time_from;
    if (!targetTime) return;

    const [h] = targetTime.split(':').map(Number);
    const [startH] = props.workingHours.start.split(':').map(Number);
    const slotIndex = (h - startH) * 2;

    const slotHeight = 48;
    timelineRef.value.scrollTop = Math.max(0, (slotIndex - 2) * slotHeight);
};

// Confirm and close
const confirm = () => {
    if (!canConfirm.value) return;

    emit('update:modelValue', {
        time_from: tempTimeFrom.value,
        time_to: tempTimeTo.value,
        crosses_midnight: crossesMidnight.value
    });

    if (props.embedded) {
        emit('close');
    } else {
        isOpen.value = false;
    }
};
</script>

<style scoped>
.timeline-picker-wrapper {
    position: static;
}

.timeline-picker-wrapper.embedded-mode {
    height: 100%;
    display: flex;
    flex-direction: column;
}

.timeline-picker-wrapper.embedded-mode .timeline-overlay {
    position: relative;
    top: auto;
    right: auto;
    bottom: auto;
    width: 100% !important;
    height: 100%;
    box-shadow: none;
}

/* Overlay - fills the parent modal completely */
.timeline-overlay {
    position: fixed;
    top: 0;
    right: 0;
    bottom: 0;
    z-index: 10000;
    background: #1a1f2e;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

/* Header */
.timeline-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 20px;
    border-bottom: 1px solid rgba(55, 65, 81, 0.5);
    flex-shrink: 0;
}

/* Hint section */
.timeline-hint {
    padding: 12px 20px;
    background: rgba(37, 42, 58, 0.5);
    border-bottom: 1px solid rgba(55, 65, 81, 0.5);
    flex-shrink: 0;
}

/* Timeline slots - takes remaining space */
.timeline-slots {
    flex: 1;
    overflow-y: auto;
    min-height: 0;
}

.timeline-slot {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 20px;
    border-bottom: 1px solid rgba(55, 65, 81, 0.3);
    transition: background 0.15s;
}

.timeline-slot.clickable {
    cursor: pointer;
}

.timeline-slot.clickable:hover {
    background: rgba(55, 65, 81, 0.3);
}

.timeline-slot.disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.timeline-slot.is-past {
    opacity: 0.35;
    background: rgba(107, 114, 128, 0.1);
}

.timeline-slot.is-past .slot-bar {
    background: rgba(107, 114, 128, 0.2);
}

.timeline-slot.in-range {
    background: rgba(59, 130, 246, 0.15);
}

.timeline-slot.is-start {
    background: rgba(34, 197, 94, 0.15);
    border-left: 3px solid #22c55e;
}

.timeline-slot.is-end {
    background: rgba(59, 130, 246, 0.15);
    border-left: 3px solid #3b82f6;
}

.slot-time {
    width: 50px;
    font-size: 14px;
    font-weight: 600;
}

.slot-bar {
    flex: 1;
    height: 28px;
    border-radius: 6px;
    position: relative;
    overflow: hidden;
    background: rgba(55, 65, 81, 0.3);
}

.slot-reservation {
    position: absolute;
    top: 0;
    bottom: 0;
    background: rgba(239, 68, 68, 0.5);
    border-radius: 4px;
}

.slot-selected {
    position: absolute;
    top: 0;
    bottom: 0;
    left: 0;
    right: 0;
    background: rgba(59, 130, 246, 0.3);
    border-radius: 4px;
}

.slot-marker-start {
    position: absolute;
    top: 0;
    bottom: 0;
    left: 0;
    width: 4px;
    background: #22c55e;
    border-radius: 4px 0 0 4px;
}

.slot-marker-end {
    position: absolute;
    top: 0;
    bottom: 0;
    right: 0;
    width: 4px;
    background: #3b82f6;
    border-radius: 0 4px 4px 0;
}

.slot-status {
    width: 70px;
    font-size: 11px;
    text-align: right;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* Legend */
.timeline-legend {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 10px 20px;
    border-top: 1px solid rgba(55, 65, 81, 0.5);
    background: rgba(37, 42, 58, 0.3);
    flex-shrink: 0;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 10px;
    color: #9ca3af;
}

/* Footer */
.timeline-footer {
    padding: 16px 20px;
    border-top: 1px solid rgba(55, 65, 81, 0.5);
    flex-shrink: 0;
}

/* Scrollbar */
.timeline-slots::-webkit-scrollbar {
    width: 6px;
}
.timeline-slots::-webkit-scrollbar-track {
    background: transparent;
}
.timeline-slots::-webkit-scrollbar-thumb {
    background: #4b5563;
    border-radius: 3px;
}

/* Slide down transition */
.slide-down-enter-active,
.slide-down-leave-active {
    transition: transform 0.25s ease, opacity 0.25s ease;
}

.slide-down-enter-from {
    transform: translateY(-20px);
    opacity: 0;
}

.slide-down-leave-to {
    transform: translateY(-20px);
    opacity: 0;
}
</style>
