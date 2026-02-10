<template>
    <div class="relative">
        <!-- Filter Button -->
        <button
            @click="isOpen = !isOpen"
            :class="[
                'flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition-colors',
                hasActiveFilters
                    ? 'bg-accent text-white'
                    : 'bg-dark-800 text-gray-400 hover:text-white'
            ]"
        >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
            </svg>
            Фильтры
            <span v-if="activeFiltersCount > 0" class="px-1.5 py-0.5 bg-white/20 rounded text-xs">
                {{ activeFiltersCount }}
            </span>
        </button>

        <!-- Dropdown Panel -->
        <Teleport to="body">
            <transition
                enter-active-class="transition ease-out duration-200"
                enter-from-class="opacity-0 translate-y-1"
                enter-to-class="opacity-100 translate-y-0"
                leave-active-class="transition ease-in duration-150"
                leave-from-class="opacity-100 translate-y-0"
                leave-to-class="opacity-0 translate-y-1"
            >
                <div
                    v-if="isOpen"
                    class="fixed z-50"
                    :style="dropdownStyle"
                >
                    <!-- Backdrop -->
                    <div class="fixed inset-0" @click="isOpen = false"></div>

                    <!-- Panel -->
                    <div class="relative bg-dark-900 border border-dark-700 rounded-xl shadow-2xl w-80 overflow-hidden">
                        <!-- Header -->
                        <div class="flex items-center justify-between px-4 py-3 border-b border-dark-700">
                            <span class="font-medium text-white">Фильтры</span>
                            <button
                                v-if="hasActiveFilters"
                                @click="resetFilters"
                                class="text-sm text-accent hover:text-blue-400 transition-colors"
                            >
                                Сбросить
                            </button>
                        </div>

                        <!-- Content -->
                        <div class="p-4 space-y-4 max-h-96 overflow-y-auto">
                            <!-- Status Filter -->
                            <div>
                                <label class="block text-xs text-gray-500 uppercase tracking-wider mb-2">
                                    Статус
                                </label>
                                <div class="space-y-1">
                                    <button
                                        v-for="status in statuses"
                                        :key="status.value"
                                        @click="toggleStatus(status.value)"
                                        :class="[
                                            'w-full flex items-center justify-between px-3 py-2 rounded-lg text-sm transition-colors',
                                            filters.statuses.includes(status.value)
                                                ? 'bg-accent/20 text-accent'
                                                : 'text-gray-400 hover:bg-dark-800 hover:text-white'
                                        ]"
                                    >
                                        <span class="flex items-center gap-2">
                                            <span :class="['w-2 h-2 rounded-full', status.color]"></span>
                                            {{ status.label }}
                                        </span>
                                        <svg
                                            v-if="filters.statuses.includes(status.value)"
                                            class="w-4 h-4"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            <!-- Payment Filter -->
                            <div>
                                <label class="block text-xs text-gray-500 uppercase tracking-wider mb-2">
                                    Оплата
                                </label>
                                <div class="grid grid-cols-2 gap-2">
                                    <button
                                        @click="filters.paymentStatus = filters.paymentStatus === 'paid' ? null : 'paid'"
                                        :class="[
                                            'px-3 py-2 rounded-lg text-sm transition-colors',
                                            filters.paymentStatus === 'paid'
                                                ? 'bg-green-500/20 text-green-400'
                                                : 'bg-dark-800 text-gray-400 hover:text-white'
                                        ]"
                                    >
                                        Оплачен
                                    </button>
                                    <button
                                        @click="filters.paymentStatus = filters.paymentStatus === 'unpaid' ? null : 'unpaid'"
                                        :class="[
                                            'px-3 py-2 rounded-lg text-sm transition-colors',
                                            filters.paymentStatus === 'unpaid'
                                                ? 'bg-red-500/20 text-red-400'
                                                : 'bg-dark-800 text-gray-400 hover:text-white'
                                        ]"
                                    >
                                        Не оплачен
                                    </button>
                                </div>
                            </div>

                            <!-- Type Filter -->
                            <div>
                                <label class="block text-xs text-gray-500 uppercase tracking-wider mb-2">
                                    Тип заказа
                                </label>
                                <div class="grid grid-cols-2 gap-2">
                                    <button
                                        @click="filters.type = filters.type === 'delivery' ? null : 'delivery'"
                                        :class="[
                                            'px-3 py-2 rounded-lg text-sm transition-colors',
                                            filters.type === 'delivery'
                                                ? 'bg-accent/20 text-accent'
                                                : 'bg-dark-800 text-gray-400 hover:text-white'
                                        ]"
                                    >
                                        Доставка
                                    </button>
                                    <button
                                        @click="filters.type = filters.type === 'pickup' ? null : 'pickup'"
                                        :class="[
                                            'px-3 py-2 rounded-lg text-sm transition-colors',
                                            filters.type === 'pickup'
                                                ? 'bg-accent/20 text-accent'
                                                : 'bg-dark-800 text-gray-400 hover:text-white'
                                        ]"
                                    >
                                        Самовывоз
                                    </button>
                                </div>
                            </div>

                            <!-- Courier Filter -->
                            <div v-if="couriers.length > 0">
                                <label class="block text-xs text-gray-500 uppercase tracking-wider mb-2">
                                    Курьер
                                </label>
                                <select
                                    v-model="filters.courierId"
                                    class="w-full bg-dark-800 border border-dark-700 rounded-lg px-3 py-2 text-sm text-white focus:border-accent focus:outline-none"
                                >
                                    <option :value="null">Все курьеры</option>
                                    <option v-for="courier in couriers" :key="courier.id" :value="courier.id">
                                        {{ courier.name }}
                                    </option>
                                </select>
                            </div>

                            <!-- Time Filter -->
                            <div>
                                <label class="block text-xs text-gray-500 uppercase tracking-wider mb-2">
                                    Период
                                </label>
                                <div class="grid grid-cols-3 gap-2">
                                    <button
                                        v-for="period in periods"
                                        :key="period.value"
                                        @click="filters.period = filters.period === period.value ? null : period.value"
                                        :class="[
                                            'px-2 py-2 rounded-lg text-xs transition-colors',
                                            filters.period === period.value
                                                ? 'bg-accent/20 text-accent'
                                                : 'bg-dark-800 text-gray-400 hover:text-white'
                                        ]"
                                    >
                                        {{ period.label }}
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Footer -->
                        <div class="px-4 py-3 border-t border-dark-700">
                            <button
                                @click="applyFilters"
                                class="w-full py-2 bg-accent hover:bg-blue-600 rounded-lg text-white text-sm font-medium transition-colors"
                            >
                                Применить
                            </button>
                        </div>
                    </div>
                </div>
            </transition>
        </Teleport>
    </div>
</template>

<script setup lang="ts">
import { ref, computed, reactive, watch, onMounted, onUnmounted, PropType } from 'vue';

const props = defineProps({
    modelValue: {
        type: Object as PropType<Record<string, any>>,
        default: () => ({})
    },
    couriers: {
        type: Array as PropType<any[]>,
        default: () => []
    }
});

const emit = defineEmits(['update:modelValue']);

const isOpen = ref(false);
const buttonRef = ref<any>(null);

// Фильтры
const filters = reactive<Record<string, any>>({
    statuses: [] as any[],
    paymentStatus: null as any,
    type: null as any,
    courierId: null as any,
    period: null as any
});

// Конфигурации
const statuses = [
    { value: 'pending', label: 'Новый', color: 'bg-blue-400' },
    { value: 'preparing', label: 'Готовится', color: 'bg-orange-400' },
    { value: 'ready', label: 'Готов', color: 'bg-cyan-400' },
    { value: 'in_transit', label: 'В пути', color: 'bg-purple-400' },
    { value: 'delivered', label: 'Доставлен', color: 'bg-green-400' }
];

const periods = [
    { value: 'today', label: 'Сегодня' },
    { value: 'yesterday', label: 'Вчера' },
    { value: 'week', label: 'Неделя' }
];

// Dropdown positioning
const dropdownStyle = computed(() => {
    return {
        top: '60px',
        right: '24px'
    };
});

// Computed
const hasActiveFilters = computed(() => {
    return filters.statuses.length > 0 ||
           filters.paymentStatus !== null ||
           filters.type !== null ||
           filters.courierId !== null ||
           filters.period !== null;
});

const activeFiltersCount = computed(() => {
    let count = 0;
    if (filters.statuses.length > 0) count += filters.statuses.length;
    if (filters.paymentStatus) count++;
    if (filters.type) count++;
    if (filters.courierId) count++;
    if (filters.period) count++;
    return count;
});

// Methods
const toggleStatus = (status: any) => {
    const idx = filters.statuses.indexOf(status);
    if (idx > -1) {
        filters.statuses.splice(idx, 1);
    } else {
        filters.statuses.push(status);
    }
};

const resetFilters = () => {
    filters.statuses = [];
    filters.paymentStatus = null;
    filters.type = null;
    filters.courierId = null;
    filters.period = null;
    applyFilters();
};

const applyFilters = () => {
    emit('update:modelValue', { ...filters });
    isOpen.value = false;
};

// Initialize from props
watch(() => props.modelValue, (val) => {
    if (val) {
        filters.statuses = val.statuses || [];
        filters.paymentStatus = val.paymentStatus || null;
        filters.type = val.type || null;
        filters.courierId = val.courierId || null;
        filters.period = val.period || null;
    }
}, { immediate: true });

// Close on escape
const handleKeydown = (e: any) => {
    if (e.key === 'Escape') {
        isOpen.value = false;
    }
};

onMounted(() => {
    document.addEventListener('keydown', handleKeydown);
});

onUnmounted(() => {
    document.removeEventListener('keydown', handleKeydown);
});
</script>
