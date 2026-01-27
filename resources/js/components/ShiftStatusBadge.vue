<template>
    <div class="relative">
        <!-- Compact badge for header -->
        <button
            @click="handleClick"
            :disabled="loading"
            :class="[
                'flex items-center gap-2 px-3 py-2 rounded-xl transition-all',
                isClockedIn
                    ? 'bg-green-500/20 text-green-400 hover:bg-green-500/30'
                    : 'bg-gray-600/50 text-gray-400 hover:bg-gray-600/70'
            ]"
            :title="isClockedIn ? 'Смена активна' : 'Смена не начата'"
        >
            <!-- Clock icon -->
            <svg v-if="!loading" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <!-- Loading spinner -->
            <svg v-else class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>

            <!-- Status text -->
            <span class="text-sm font-medium whitespace-nowrap">
                <template v-if="isClockedIn && session">
                    {{ formatDuration }}
                </template>
                <template v-else>
                    {{ isClockedIn ? 'На смене' : 'Не на смене' }}
                </template>
            </span>

            <!-- Status dot -->
            <span :class="['w-2 h-2 rounded-full', isClockedIn ? 'bg-green-400' : 'bg-gray-500']"></span>
        </button>

        <!-- Dropdown menu -->
        <Transition name="dropdown">
            <div
                v-if="showDropdown"
                class="absolute top-full right-0 mt-2 w-64 bg-[#1e2235] rounded-xl shadow-xl border border-gray-700/50 overflow-hidden z-50"
            >
                <!-- Status info -->
                <div class="p-4 border-b border-gray-700/50">
                    <div class="flex items-center gap-3">
                        <div :class="['w-10 h-10 rounded-xl flex items-center justify-center', isClockedIn ? 'bg-green-500/20' : 'bg-gray-600/50']">
                            <svg class="w-5 h-5" :class="isClockedIn ? 'text-green-400' : 'text-gray-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <div>
                            <div class="text-sm font-medium" :class="isClockedIn ? 'text-green-400' : 'text-gray-400'">
                                {{ isClockedIn ? 'Смена активна' : 'Смена не начата' }}
                            </div>
                            <div v-if="isClockedIn && session" class="text-xs text-gray-500">
                                С {{ formatTime(session.clock_in) }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Duration (if clocked in) -->
                <div v-if="isClockedIn && session" class="px-4 py-3 bg-[#161927]">
                    <div class="text-xs text-gray-500 mb-1">Время на смене</div>
                    <div class="text-lg font-semibold text-white">{{ session.duration_formatted || formatDuration }}</div>
                </div>

                <!-- Toggle button -->
                <div class="p-3">
                    <button
                        @click="toggleShift"
                        :disabled="loading"
                        :class="[
                            'w-full py-2.5 rounded-lg font-medium text-sm transition flex items-center justify-center gap-2',
                            isClockedIn
                                ? 'bg-red-500/20 text-red-400 hover:bg-red-500/30'
                                : 'bg-green-500/20 text-green-400 hover:bg-green-500/30'
                        ]"
                    >
                        <svg v-if="loading" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <svg v-else-if="isClockedIn" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 10a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1v-4z"/>
                        </svg>
                        <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        {{ isClockedIn ? 'Завершить смену' : 'Начать смену' }}
                    </button>
                </div>
            </div>
        </Transition>

        <!-- Click outside overlay -->
        <div v-if="showDropdown" class="fixed inset-0 z-40" @click="showDropdown = false"></div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import axios from 'axios';

const props = defineProps({
    token: {
        type: String,
        default: null
    },
    autoRefresh: {
        type: Boolean,
        default: true
    },
    refreshInterval: {
        type: Number,
        default: 60000 // 1 minute
    }
});

const emit = defineEmits(['clock-in', 'clock-out', 'error']);

const loading = ref(false);
const showDropdown = ref(false);
const isClockedIn = ref(false);
const session = ref(null);
const elapsedSeconds = ref(0);

let refreshTimer = null;
let elapsedTimer = null;

const authToken = computed(() => {
    return props.token || localStorage.getItem('staff_token') || localStorage.getItem('token');
});

const formatDuration = computed(() => {
    const hours = Math.floor(elapsedSeconds.value / 3600);
    const minutes = Math.floor((elapsedSeconds.value % 3600) / 60);
    if (hours > 0) {
        return `${hours}ч ${minutes}м`;
    }
    return `${minutes}м`;
});

const formatTime = (datetime) => {
    if (!datetime) return '';
    const date = new Date(datetime);
    return date.toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' });
};

const calculateElapsed = () => {
    if (session.value?.clock_in) {
        const start = new Date(session.value.clock_in);
        elapsedSeconds.value = Math.floor((Date.now() - start.getTime()) / 1000);
    } else {
        elapsedSeconds.value = 0;
    }
};

const loadStatus = async () => {
    if (!authToken.value) return;

    try {
        const res = await axios.get('/api/payroll/my-status', {
            headers: { Authorization: `Bearer ${authToken.value}` }
        });
        isClockedIn.value = res.data.is_clocked_in;
        session.value = res.data.session;
        calculateElapsed();
    } catch (e) {
        console.error('Failed to load shift status:', e);
    }
};

const toggleShift = async () => {
    if (!authToken.value) return;

    loading.value = true;
    try {
        const endpoint = isClockedIn.value ? 'my-clock-out' : 'my-clock-in';
        const res = await axios.post(`/api/payroll/${endpoint}`, {}, {
            headers: { Authorization: `Bearer ${authToken.value}` }
        });

        if (res.data.success) {
            await loadStatus();
            emit(isClockedIn.value ? 'clock-in' : 'clock-out', res.data);
        }
    } catch (e) {
        console.error('Failed to toggle shift:', e);
        emit('error', e.response?.data?.message || 'Error toggling shift');
    } finally {
        loading.value = false;
        showDropdown.value = false;
    }
};

const handleClick = () => {
    showDropdown.value = !showDropdown.value;
};

onMounted(() => {
    loadStatus();

    // Auto refresh status
    if (props.autoRefresh) {
        refreshTimer = setInterval(loadStatus, props.refreshInterval);
    }

    // Update elapsed time every second
    elapsedTimer = setInterval(calculateElapsed, 1000);
});

onUnmounted(() => {
    if (refreshTimer) clearInterval(refreshTimer);
    if (elapsedTimer) clearInterval(elapsedTimer);
});

// Expose for parent components
defineExpose({
    loadStatus,
    toggleShift,
    isClockedIn,
    session
});
</script>

<style scoped>
.dropdown-enter-active,
.dropdown-leave-active {
    transition: opacity 0.15s ease, transform 0.15s ease;
}

.dropdown-enter-from,
.dropdown-leave-to {
    opacity: 0;
    transform: translateY(-8px);
}
</style>
