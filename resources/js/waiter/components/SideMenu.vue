<template>
    <div class="h-full flex flex-col">
        <!-- Header -->
        <div class="p-4 border-b border-gray-700 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-orange-500 flex items-center justify-center font-bold">
                    {{ initials }}
                </div>
                <div>
                    <p class="font-medium">{{ user?.name || 'Официант' }}</p>
                    <p class="text-xs text-gray-500">{{ user?.role || 'Сотрудник' }}</p>
                </div>
            </div>
            <button @click="$emit('close')" class="text-2xl text-gray-500">✕</button>
        </div>

        <!-- Menu Items -->
        <div class="flex-1 p-4 space-y-2">
            <button class="w-full p-4 bg-dark-700 rounded-xl text-left flex items-center gap-3">
                <span class="text-xl">🪑</span>
                <span>Столы</span>
            </button>
            <button class="w-full p-4 bg-dark-700 rounded-xl text-left flex items-center gap-3">
                <span class="text-xl">📋</span>
                <span>Мои заказы</span>
            </button>
            <button class="w-full p-4 bg-dark-700 rounded-xl text-left flex items-center gap-3">
                <span class="text-xl">📊</span>
                <span>Статистика</span>
            </button>
        </div>

        <!-- Work Shift -->
        <div class="p-4 border-t border-gray-700">
            <div class="p-4 rounded-xl" :class="workShiftStatus.is_clocked_in ? 'bg-green-500/10' : 'bg-gray-700'">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-2">
                        <span :class="['w-2 h-2 rounded-full', workShiftStatus.is_clocked_in ? 'bg-green-400' : 'bg-gray-500']"></span>
                        <span class="text-sm font-medium" :class="workShiftStatus.is_clocked_in ? 'text-green-400' : 'text-gray-400'">
                            {{ workShiftStatus.is_clocked_in ? 'Рабочая смена' : 'Смена не начата' }}
                        </span>
                    </div>
                    <span v-if="workShiftStatus.is_clocked_in" class="text-xs text-gray-500">
                        {{ workShiftDuration }}
                    </span>
                </div>
                <button
                    @click="toggleWorkShift"
                    :disabled="workShiftLoading"
                    :class="[
                        'w-full py-2.5 rounded-lg font-medium text-sm transition flex items-center justify-center gap-2',
                        workShiftStatus.is_clocked_in
                            ? 'bg-red-500/20 text-red-400 hover:bg-red-500/30'
                            : 'bg-green-500/20 text-green-400 hover:bg-green-500/30'
                    ]"
                >
                    <svg v-if="workShiftLoading" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    {{ workShiftStatus.is_clocked_in ? 'Завершить смену' : 'Начать смену' }}
                </button>
            </div>
        </div>

        <!-- Settings -->
        <div class="p-4 border-t border-gray-700 space-y-3">
            <div class="flex items-center justify-between">
                <span class="text-gray-400">Звук</span>
                <button @click="$emit('toggleSound')"
                        :class="['w-12 h-7 rounded-full transition p-1',
                                 soundEnabled ? 'bg-orange-500' : 'bg-gray-600']">
                    <div :class="['w-5 h-5 rounded-full bg-white transition',
                                  soundEnabled ? 'translate-x-5' : 'translate-x-0']"></div>
                </button>
            </div>
            <div class="flex items-center justify-between">
                <span class="text-gray-400">Уведомления</span>
                <button @click="$emit('toggleNotifications')"
                        :class="['w-12 h-7 rounded-full transition p-1',
                                 notificationsEnabled ? 'bg-orange-500' : 'bg-gray-600']">
                    <div :class="['w-5 h-5 rounded-full bg-white transition',
                                  notificationsEnabled ? 'translate-x-5' : 'translate-x-0']"></div>
                </button>
            </div>
        </div>

        <!-- Logout -->
        <div class="p-4">
            <button @click="$emit('logout')"
                    class="w-full py-3 bg-red-500/20 text-red-400 rounded-xl font-medium">
                Выйти
            </button>
        </div>
    </div>
</template>

<script setup>
import { computed, ref, onMounted, onUnmounted } from 'vue';
import axios from 'axios';

const props = defineProps({
    user: { type: Object, default: null },
    soundEnabled: { type: Boolean, default: true },
    notificationsEnabled: { type: Boolean, default: true }
});

defineEmits(['close', 'toggleSound', 'toggleNotifications', 'logout']);

const initials = computed(() => {
    if (!props.user?.name) return '👤';
    const parts = props.user.name.split(' ');
    if (parts.length >= 2) return (parts[0][0] + parts[1][0]).toUpperCase();
    return props.user.name.substring(0, 2).toUpperCase();
});

// Work shift states
const workShiftStatus = ref({ is_clocked_in: false, session: null });
const workShiftLoading = ref(false);
const workShiftElapsed = ref(0);
let workShiftTimer = null;
let workShiftRefreshTimer = null;

const workShiftDuration = computed(() => {
    const hours = Math.floor(workShiftElapsed.value / 3600);
    const minutes = Math.floor((workShiftElapsed.value % 3600) / 60);
    return hours > 0 ? `${hours}ч ${minutes}м` : `${minutes}м`;
});

const calculateWorkShiftElapsed = () => {
    if (workShiftStatus.value.session?.clock_in) {
        const start = new Date(workShiftStatus.value.session.clock_in);
        workShiftElapsed.value = Math.floor((Date.now() - start.getTime()) / 1000);
    } else {
        workShiftElapsed.value = 0;
    }
};

const loadWorkShiftStatus = async () => {
    const token = localStorage.getItem('token') || localStorage.getItem('staff_token');
    if (!token) return;

    try {
        const res = await axios.get('/api/payroll/my-status', {
            headers: { Authorization: `Bearer ${token}` }
        });
        workShiftStatus.value = res.data;
        calculateWorkShiftElapsed();
    } catch (e) {
        console.error('Failed to load work shift status:', e);
    }
};

const toggleWorkShift = async () => {
    const token = localStorage.getItem('token') || localStorage.getItem('staff_token');
    if (!token) return;

    workShiftLoading.value = true;
    try {
        const endpoint = workShiftStatus.value.is_clocked_in ? 'my-clock-out' : 'my-clock-in';
        const res = await axios.post(`/api/payroll/${endpoint}`, {}, {
            headers: { Authorization: `Bearer ${token}` }
        });

        if (res.data.success) {
            await loadWorkShiftStatus();
        }
    } catch (e) {
        console.error('Failed to toggle work shift:', e);
    } finally {
        workShiftLoading.value = false;
    }
};

onMounted(() => {
    loadWorkShiftStatus();
    workShiftRefreshTimer = setInterval(loadWorkShiftStatus, 60000);
    workShiftTimer = setInterval(calculateWorkShiftElapsed, 1000);
});

onUnmounted(() => {
    if (workShiftTimer) clearInterval(workShiftTimer);
    if (workShiftRefreshTimer) clearInterval(workShiftRefreshTimer);
});
</script>
