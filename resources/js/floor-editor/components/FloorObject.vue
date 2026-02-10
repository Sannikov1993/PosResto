<template>
    <div class="floor-object"
         :class="{
             selected: selected,
             dragging: dragging,
             'pointer-events-none': !editMode && obj.type !== 'table'
         }"
         :style="objectStyle"
         @mousedown.stop="editMode ? $emit('start-drag', obj, $event) : null"
         @click.stop="!editMode && obj.type === 'table' ? $emit('table-click', obj) : null">

        <!-- Table -->
        <template v-if="obj.type === 'table'">
            <div class="table-item table-shadow relative w-full h-full"
                 :class="[
                     animationClass,
                     { 'selected': selected },
                     obj.shape === 'round' || obj.shape === 'oval' ? 'rounded-full' : 'rounded-xl'
                 ]">

                <!-- Chairs -->
                <template v-if="showChairs">
                    <div v-for="(chair, idx) in chairPositions" :key="'chair-' + idx"
                         class="chair"
                         :class="chairStatusClass"
                         :style="chair.style">
                    </div>
                </template>

                <!-- Table surface -->
                <div class="wood-texture flex flex-col items-center justify-center w-full h-full"
                     :class="[
                         tableStatusClass,
                         obj.shape === 'round' || obj.shape === 'oval' ? 'rounded-full' : 'rounded-xl'
                     ]">

                    <!-- Table number -->
                    <span class="text-white font-bold drop-shadow-lg relative z-10"
                          :class="obj.width > 100 ? 'text-xl' : 'text-lg'">
                        {{ obj.number }}
                    </span>

                    <!-- Amount (if occupied) -->
                    <span v-if="showAmount"
                          class="text-white/90 text-xs relative z-10 mt-0.5">
                        {{ formatAmount(obj.totalAmount || 0) }}
                    </span>

                    <!-- Reservation time -->
                    <span v-if="displayStatus === 'reserved' && obj.reservationTime"
                          class="text-blue-300 text-sm font-bold relative z-10 mt-1">
                        {{ obj.reservationTime }}
                    </span>
                </div>

                <!-- Bill icon -->
                <div v-if="obj.hasBillRequest || displayStatus === 'billing'"
                     class="bill-icon anim-bill">💳</div>
            </div>

            <!-- Seats count (edit mode) -->
            <div v-if="editMode" class="absolute -bottom-5 left-0 right-0 text-center text-xs text-gray-500 font-medium">
                {{ obj.seats }} мест
            </div>
        </template>

        <!-- Wall -->
        <template v-else-if="obj.type === 'wall'">
            <div class="w-full h-full bg-neutral-600 rounded shadow-lg"></div>
        </template>

        <!-- Column -->
        <template v-else-if="obj.type === 'column'">
            <div class="w-full h-full bg-neutral-500 rounded shadow-lg"></div>
        </template>

        <!-- Bar -->
        <template v-else-if="obj.type === 'bar'">
            <div class="w-full h-full bg-amber-800 rounded-lg shadow-lg flex items-center justify-center border border-amber-700">
                <span class="text-amber-200 text-xs font-bold tracking-wider">BAR</span>
            </div>
        </template>

        <!-- Sofa -->
        <template v-else-if="obj.type === 'sofa'">
            <div class="w-full h-full bg-purple-900/50 border-2 border-purple-600 rounded-lg shadow-lg"></div>
        </template>

        <!-- Door (top-down view / architectural) -->
        <template v-else-if="obj.type === 'door'">
            <svg class="w-full h-full" viewBox="0 0 100 80" preserveAspectRatio="none">
                <!-- Стена слева -->
                <rect x="0" y="55" width="8" height="16" fill="#4b5563"/>
                <!-- Стена справа -->
                <rect x="85" y="55" width="15" height="16" fill="#4b5563"/>
                <!-- Рамка проёма -->
                <rect x="8" y="57" width="77" height="12" fill="none" stroke="#6b7280" stroke-width="2"/>
                <!-- Дверное полотно -->
                <rect x="8" y="59" width="55" height="8" fill="#3b82f6" rx="1"/>
                <!-- Дуга открывания -->
                <path d="M 63 63 A 55 55 0 0 0 8 8" stroke="#3b82f6" stroke-width="1.5" fill="none" stroke-dasharray="4,3"/>
                <!-- Петля -->
                <circle cx="8" cy="63" r="3" fill="#1e3a8a"/>
            </svg>
        </template>

        <!-- Window -->
        <template v-else-if="obj.type === 'window'">
            <div class="w-full h-full bg-blue-500/30 border border-blue-400 shadow-lg"></div>
        </template>

        <!-- Plant -->
        <template v-else-if="obj.type === 'plant'">
            <div class="w-full h-full flex items-center justify-center text-2xl drop-shadow-lg">🌿</div>
        </template>

        <!-- Label -->
        <template v-else-if="obj.type === 'label'">
            <div class="w-full h-full flex items-center justify-center text-sm font-medium text-white bg-neutral-800/80 rounded px-2 border border-neutral-700">
                {{ obj.text || 'Надпись' }}
            </div>
        </template>

        <!-- Resize & Rotate handles (edit mode only) -->
        <template v-if="editMode && selected && obj.resizable !== false">
            <div class="resize-handle se" @mousedown.stop="$emit('start-resize', obj, 'se', $event)"></div>
            <div class="rotate-handle" @mousedown.stop="$emit('start-rotate', obj, $event)">↻</div>
        </template>
    </div>
</template>

<script setup lang="ts">
import { computed, PropType } from 'vue';
import { useFloorEditorStore } from '../stores/floorEditor';

const props = defineProps({
    obj: { type: Object as PropType<Record<string, any>>, required: true },
    selected: Boolean,
    dragging: Boolean,
    editMode: Boolean,
    showChairs: Boolean
});

defineEmits(['start-drag', 'start-resize', 'start-rotate', 'table-click']);

const store = useFloorEditorStore();

// Object style
const objectStyle = computed(() => ({
    left: props.obj.x + 'px',
    top: props.obj.y + 'px',
    width: props.obj.width + 'px',
    height: props.obj.height + 'px',
    transform: `rotate(${props.obj.rotation || 0}deg)`
}));

// Display status
const displayStatus = computed(() => {
    if (props.editMode) return 'free';
    return props.obj.status || 'free';
});

// Table status class
const tableStatusClass = computed(() => {
    const status = displayStatus.value;
    const map = {
        'free': 'table-free',
        'occupied': 'table-occupied',
        'reserved': 'table-reserved',
        'billing': 'table-occupied',
        'bill': 'table-occupied',
        'alert': 'table-alert',
        'ready': 'table-ready'
    };
    return (map as Record<string, any>)[status] || 'table-free';
});

// Chair status class
const chairStatusClass = computed(() => {
    const status = displayStatus.value;
    const map = {
        'free': 'chair-free',
        'occupied': 'chair-occupied',
        'reserved': 'chair-reserved',
        'billing': 'chair-occupied',
        'bill': 'chair-occupied',
        'alert': 'chair-alert',
        'ready': 'chair-ready'
    };
    return (map as Record<string, any>)[status] || 'chair-free';
});

// Animation class
const animationClass = computed(() => {
    if (props.editMode) return '';
    const status = displayStatus.value;
    const map = {
        'free': 'anim-free',
        'reserved': 'anim-reserved',
        'alert': 'anim-alert'
    };
    return (map as Record<string, any>)[status] || '';
});

// Show amount
const showAmount = computed(() => {
    if (props.editMode) return false;
    const status = displayStatus.value;
    return ['occupied', 'billing', 'bill', 'alert', 'ready'].includes(status) && (props.obj.totalAmount || 0) > 0;
});

// Format amount
function formatAmount(amount: any) {
    return new Intl.NumberFormat('ru-RU').format(amount || 0) + ' ₽';
}

// Chair positions
const chairPositions = computed(() => {
    const chairs = [];
    const seats = props.obj.seats || 4;
    const w = props.obj.width || 80;
    const h = props.obj.height || 80;
    const chairWidth = 28;
    const chairHeight = 12;
    const gap = 4;

    if (props.obj.shape === 'round' || props.obj.shape === 'oval') {
        const centerX = w / 2;
        const centerY = h / 2;
        const radiusX = (w / 2) + gap + chairHeight / 2;
        const radiusY = (h / 2) + gap + chairHeight / 2;

        for (let i = 0; i < seats; i++) {
            const angle = (i / seats) * 2 * Math.PI - Math.PI / 2;
            const x = centerX + radiusX * Math.cos(angle) - chairWidth / 2;
            const y = centerY + radiusY * Math.sin(angle) - chairHeight / 2;
            const rotation = (angle * 180 / Math.PI) + 90;

            chairs.push({
                style: {
                    left: x + 'px',
                    top: y + 'px',
                    width: chairWidth + 'px',
                    height: chairHeight + 'px',
                    borderRadius: '6px',
                    '--chair-direction': rotation + 'deg',
                    transform: `rotate(${rotation}deg)`
                }
            });
        }
    } else {
        const sides = { top: 0, bottom: 0, left: 0, right: 0 };

        if (props.obj.shape === 'rectangle' && w > h * 1.3) {
            sides.top = Math.ceil(seats / 2);
            sides.bottom = seats - sides.top;
        } else if (props.obj.shape === 'rectangle' && h > w * 1.3) {
            sides.left = Math.ceil(seats / 2);
            sides.right = seats - sides.left;
        } else {
            const perSide = Math.ceil(seats / 4);
            let remaining = seats;
            sides.top = Math.min(perSide, remaining); remaining -= sides.top;
            sides.bottom = Math.min(perSide, remaining); remaining -= sides.bottom;
            sides.left = Math.min(perSide, remaining); remaining -= sides.left;
            sides.right = remaining;
        }

        // Top chairs
        for (let i = 0; i < sides.top; i++) {
            const spacing = w / (sides.top + 1);
            chairs.push({
                style: {
                    left: (spacing * (i + 1) - chairWidth / 2) + 'px',
                    top: (-chairHeight - gap) + 'px',
                    width: chairWidth + 'px',
                    height: chairHeight + 'px',
                    borderRadius: '6px 6px 0 0',
                    '--chair-direction': '180deg'
                }
            });
        }

        // Bottom chairs
        for (let i = 0; i < sides.bottom; i++) {
            const spacing = w / (sides.bottom + 1);
            chairs.push({
                style: {
                    left: (spacing * (i + 1) - chairWidth / 2) + 'px',
                    top: (h + gap) + 'px',
                    width: chairWidth + 'px',
                    height: chairHeight + 'px',
                    borderRadius: '0 0 6px 6px',
                    '--chair-direction': '0deg'
                }
            });
        }

        // Left chairs
        for (let i = 0; i < sides.left; i++) {
            const spacing = h / (sides.left + 1);
            chairs.push({
                style: {
                    left: (-chairHeight - gap) + 'px',
                    top: (spacing * (i + 1) - chairWidth / 2) + 'px',
                    width: chairHeight + 'px',
                    height: chairWidth + 'px',
                    borderRadius: '6px 0 0 6px',
                    '--chair-direction': '90deg'
                }
            });
        }

        // Right chairs
        for (let i = 0; i < sides.right; i++) {
            const spacing = h / (sides.right + 1);
            chairs.push({
                style: {
                    left: (w + gap) + 'px',
                    top: (spacing * (i + 1) - chairWidth / 2) + 'px',
                    width: chairHeight + 'px',
                    height: chairWidth + 'px',
                    borderRadius: '0 6px 6px 0',
                    '--chair-direction': '-90deg'
                }
            });
        }
    }

    return chairs;
});
</script>

<style scoped>
.floor-object {
    position: absolute;
    cursor: move;
    user-select: none;
    transition: box-shadow 0.2s, transform 0.1s;
}
.floor-object:hover { z-index: 100; }
.floor-object.selected { z-index: 101; }
.floor-object.dragging { opacity: 0.8; z-index: 1000; }

/* Table styles */
.table-shadow {
    filter: drop-shadow(0 8px 16px rgba(0,0,0,0.5));
}

.wood-texture {
    position: relative;
}
.wood-texture::before {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: inherit;
    opacity: 0.12;
    background: repeating-linear-gradient(
        90deg,
        transparent 0px,
        transparent 4px,
        rgba(0,0,0,0.3) 4px,
        rgba(0,0,0,0.3) 6px
    );
    pointer-events: none;
}

/* Table status colors */
.table-free { background: linear-gradient(135deg, #8B5A2B 0%, #6B4423 50%, #5D3A1A 100%); }
.table-occupied { background: linear-gradient(135deg, #D97706 0%, #B45309 50%, #92400E 100%); }
.table-reserved { background: linear-gradient(135deg, #5D6D7E 0%, #4A5568 50%, #3D4852 100%); }
.table-alert { background: linear-gradient(135deg, #A04040 0%, #8B3232 50%, #6B2828 100%); }
.table-ready { background: linear-gradient(135deg, #4A7C59 0%, #3D6B4A 50%, #2D5A3A 100%); }

/* Chair status colors */
.chair-free { background: linear-gradient(var(--chair-direction, 180deg), #5D4E37 0%, #4A3F2E 100%); }
.chair-occupied { background: linear-gradient(var(--chair-direction, 180deg), #B45309 0%, #92400E 100%); }
.chair-reserved { background: linear-gradient(var(--chair-direction, 180deg), #4A5568 0%, #3D4852 100%); }
.chair-alert { background: linear-gradient(var(--chair-direction, 180deg), #8B3232 0%, #6B2828 100%); }
.chair-ready { background: linear-gradient(var(--chair-direction, 180deg), #3D6B4A 0%, #2D5A3A 100%); }

.chair {
    position: absolute;
    border-radius: 4px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.4);
    transition: background 0.3s ease;
}

/* Table item */
.table-item {
    cursor: move;
    transition: box-shadow 0.15s ease, border-radius 0.15s ease;
}
.table-item:hover {
    box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.5);
}
.table-item.selected {
    box-shadow: 0 0 0 3px #f97316, 0 0 20px rgba(249, 115, 22, 0.3);
}

/* Bill icon */
.bill-icon {
    position: absolute;
    top: -8px;
    right: -8px;
    width: 24px;
    height: 24px;
    background: linear-gradient(135deg, #8B5CF6 0%, #7C3AED 100%);
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid #1a1a1a;
    box-shadow: 0 4px 12px rgba(139, 92, 246, 0.4);
    z-index: 10;
    font-size: 12px;
}

/* Animations */
@keyframes pulse-free {
    0%, 100% { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0); }
    50% { box-shadow: 0 0 15px 3px rgba(34, 197, 94, 0.3); }
}
.anim-free { animation: pulse-free 3s ease-in-out infinite; }

@keyframes pulse-reserved {
    0%, 100% { box-shadow: 0 0 0 0 rgba(59, 130, 246, 0); }
    50% { box-shadow: 0 0 20px 5px rgba(59, 130, 246, 0.4); }
}
.anim-reserved { animation: pulse-reserved 2s ease-in-out infinite; }

@keyframes pulse-alert {
    0%, 100% { box-shadow: 0 8px 16px rgba(0,0,0,0.5); }
    50% { box-shadow: 0 8px 16px rgba(0,0,0,0.5), 0 0 30px rgba(239, 68, 68, 0.5); }
}
.anim-alert { animation: pulse-alert 1s ease-in-out infinite; }

@keyframes bill-bounce {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.15); }
}
.anim-bill { animation: bill-bounce 1.5s ease-in-out infinite; }

/* Resize/Rotate handles */
.resize-handle {
    position: absolute;
    width: 10px;
    height: 10px;
    background: #f97316;
    border: 2px solid white;
    border-radius: 2px;
}
.resize-handle.se { bottom: -5px; right: -5px; cursor: se-resize; }

.rotate-handle {
    position: absolute;
    top: -24px;
    left: 50%;
    transform: translateX(-50%);
    width: 16px;
    height: 16px;
    background: #3b82f6;
    border: 2px solid white;
    border-radius: 50%;
    cursor: grab;
    z-index: 20;
    font-size: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
}
</style>
