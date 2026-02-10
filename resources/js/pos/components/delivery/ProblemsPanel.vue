<template>
    <div class="problems-panel h-full flex flex-col">
        <!-- Заголовок со статистикой -->
        <div class="p-4 border-b border-[rgba(255,255,255,0.08)]">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-white font-semibold text-lg">Проблемы доставки</h3>
                <button
                    @click="loadProblems"
                    class="p-2 rounded-lg bg-[rgba(255,255,255,0.05)] hover:bg-[rgba(255,255,255,0.1)] text-[rgba(255,255,255,0.7)] transition-colors"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                </button>
            </div>

            <!-- Статистика -->
            <div class="flex gap-4">
                <div class="flex-1 p-3 rounded-xl bg-red-500/10 border border-red-500/20">
                    <div class="text-2xl font-bold text-red-400">{{ stats.open }}</div>
                    <div class="text-xs text-[rgba(255,255,255,0.5)]">Открытых</div>
                </div>
                <div class="flex-1 p-3 rounded-xl bg-yellow-500/10 border border-yellow-500/20">
                    <div class="text-2xl font-bold text-yellow-400">{{ stats.in_progress }}</div>
                    <div class="text-xs text-[rgba(255,255,255,0.5)]">В работе</div>
                </div>
                <div class="flex-1 p-3 rounded-xl bg-green-500/10 border border-green-500/20">
                    <div class="text-2xl font-bold text-green-400">{{ stats.resolved_today }}</div>
                    <div class="text-xs text-[rgba(255,255,255,0.5)]">Решено сегодня</div>
                </div>
            </div>
        </div>

        <!-- Фильтры -->
        <div class="p-4 border-b border-[rgba(255,255,255,0.08)] flex gap-2">
            <button
                v-for="filter in filters"
                :key="filter.value"
                @click="activeFilter = filter.value"
                class="px-4 py-2 rounded-lg text-sm font-medium transition-colors"
                :class="activeFilter === filter.value
                    ? 'bg-[#3B82F6] text-white'
                    : 'bg-[rgba(255,255,255,0.05)] text-[rgba(255,255,255,0.7)] hover:bg-[rgba(255,255,255,0.1)]'"
            >
                {{ filter.label }}
            </button>
        </div>

        <!-- Список проблем -->
        <div class="flex-1 overflow-y-auto p-4 space-y-3">
            <!-- Загрузка -->
            <div v-if="loading" class="flex items-center justify-center py-8">
                <div class="animate-spin w-8 h-8 border-4 border-[#3B82F6] border-t-transparent rounded-full"></div>
            </div>

            <!-- Проблемы -->
            <div
                v-for="problem in filteredProblems"
                :key="problem.id"
                class="p-4 rounded-xl bg-[rgba(255,255,255,0.03)] border border-[rgba(255,255,255,0.08)] hover:border-[rgba(255,255,255,0.15)] transition-colors"
            >
                <!-- Заголовок -->
                <div class="flex items-start justify-between mb-3">
                    <div class="flex items-center gap-3">
                        <div
                            class="w-10 h-10 rounded-xl flex items-center justify-center text-xl"
                            :class="getTypeIconClass(problem.type)"
                        >
                            {{ getTypeIcon(problem.type) }}
                        </div>
                        <div>
                            <p class="text-white font-medium">{{ problem.type_label }}</p>
                            <p class="text-[rgba(255,255,255,0.5)] text-sm">
                                Заказ {{ problem.delivery_order?.order_number || problem.delivery_order?.daily_number }}
                            </p>
                        </div>
                    </div>
                    <span
                        class="px-2 py-1 rounded-lg text-xs font-medium"
                        :class="getStatusClass(problem.status)"
                    >
                        {{ problem.status_label }}
                    </span>
                </div>

                <!-- Описание -->
                <p v-if="problem.description" class="text-[rgba(255,255,255,0.7)] text-sm mb-3">
                    {{ problem.description }}
                </p>

                <!-- Курьер и время -->
                <div class="flex items-center justify-between text-xs text-[rgba(255,255,255,0.5)] mb-3">
                    <span v-if="problem.courier">
                        Курьер: {{ problem.courier.name }}
                    </span>
                    <span>{{ formatTime(problem.created_at) }}</span>
                </div>

                <!-- Действия -->
                <div v-if="problem.status !== 'resolved' && problem.status !== 'cancelled'" class="flex gap-2">
                    <button
                        @click="openResolveModal(problem)"
                        class="flex-1 py-2 px-4 rounded-lg bg-green-500/20 text-green-400 text-sm font-medium hover:bg-green-500/30 transition-colors"
                    >
                        Решить
                    </button>
                    <button
                        @click="cancelProblem(problem)"
                        class="py-2 px-4 rounded-lg bg-[rgba(255,255,255,0.05)] text-[rgba(255,255,255,0.7)] text-sm hover:bg-[rgba(255,255,255,0.1)] transition-colors"
                    >
                        Отмена
                    </button>
                </div>

                <!-- Решение -->
                <div v-if="problem.status === 'resolved'" class="mt-3 p-3 rounded-lg bg-green-500/10 border border-green-500/20">
                    <p class="text-green-400 text-xs font-medium mb-1">Решено:</p>
                    <p class="text-[rgba(255,255,255,0.7)] text-sm">{{ problem.resolution }}</p>
                    <p class="text-[rgba(255,255,255,0.5)] text-xs mt-1">
                        {{ problem.resolved_by?.name }} · {{ formatTime(problem.resolved_at) }}
                    </p>
                </div>
            </div>

            <!-- Пусто -->
            <div v-if="!loading && filteredProblems.length === 0" class="flex flex-col items-center justify-center py-12">
                <div class="text-5xl mb-4">✅</div>
                <p class="text-[rgba(255,255,255,0.5)]">Нет проблем</p>
            </div>
        </div>

        <!-- Модалка решения -->
        <Teleport to="body">
            <div
                v-if="showResolveModal"
                class="fixed inset-0 bg-black/70 z-50 flex items-center justify-center p-4"
                @click.self="showResolveModal = false"
            >
                <div class="w-full max-w-md bg-[rgba(30,30,50,0.95)] backdrop-blur-xl rounded-2xl border border-[rgba(255,255,255,0.1)] overflow-hidden">
                    <div class="p-6 border-b border-[rgba(255,255,255,0.08)]">
                        <h3 class="text-white font-semibold text-lg">Решение проблемы</h3>
                        <p class="text-[rgba(255,255,255,0.5)] text-sm mt-1">
                            {{ selectedProblem?.type_label }}
                        </p>
                    </div>

                    <div class="p-6">
                        <label class="block text-[rgba(255,255,255,0.7)] text-sm mb-2">
                            Как была решена проблема?
                        </label>
                        <textarea
                            v-model="resolution"
                            class="w-full h-32 bg-[rgba(255,255,255,0.05)] border border-[rgba(255,255,255,0.1)] rounded-xl p-4 text-white placeholder-[rgba(255,255,255,0.3)] resize-none focus:outline-none focus:border-[#3B82F6]"
                            placeholder="Опишите решение..."
                        ></textarea>
                    </div>

                    <div class="p-6 border-t border-[rgba(255,255,255,0.08)] flex gap-3">
                        <button
                            @click="showResolveModal = false"
                            class="flex-1 py-3 rounded-xl bg-[rgba(255,255,255,0.05)] text-white font-medium hover:bg-[rgba(255,255,255,0.1)] transition-colors"
                        >
                            Отмена
                        </button>
                        <button
                            @click="resolveProblem"
                            :disabled="!resolution.trim() || resolving"
                            class="flex-1 py-3 rounded-xl bg-green-500 text-white font-medium hover:bg-green-600 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            {{ resolving ? 'Сохранение...' : 'Решить' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import api from '../../api';
import { createLogger } from '../../../shared/services/logger.js';

const log = createLogger('POS:Problems');

const emit = defineEmits(['problem-resolved']);

// State
const problems = ref<any[]>([]);
const stats = ref({ open: 0, in_progress: 0, resolved_today: 0 });
const loading = ref(false);
const activeFilter = ref('all');

// Resolve modal
const showResolveModal = ref(false);
const selectedProblem = ref<any>(null);
const resolution = ref('');
const resolving = ref(false);

// Filters
const filters = [
    { value: 'all', label: 'Все' },
    { value: 'open', label: 'Открытые' },
    { value: 'in_progress', label: 'В работе' },
    { value: 'resolved', label: 'Решённые' },
];

// Computed
const filteredProblems = computed(() => {
    if (activeFilter.value === 'all') {
        return problems.value;
    }
    return problems.value.filter((p: any) => p.status === activeFilter.value);
});

// Load
onMounted(() => {
    loadProblems();
});

async function loadProblems() {
    loading.value = true;
    try {
        // Interceptor бросит исключение при success: false
        const result = await api.delivery.getProblems({ today: 1 });
        problems.value = (result as any)?.data || [];
        stats.value = (result as any)?.stats || {};
    } catch (error: any) {
        log.error('Error loading problems:', error);
    } finally {
        loading.value = false;
    }
}

// Actions
function openResolveModal(problem: any) {
    selectedProblem.value = problem;
    resolution.value = '';
    showResolveModal.value = true;
}

async function resolveProblem() {
    if (!selectedProblem.value || !resolution.value.trim()) return;

    resolving.value = true;
    try {
        // Interceptor бросит исключение при success: false
        const result = await api.delivery.resolveProblem(selectedProblem.value.id, resolution.value.trim());
        showResolveModal.value = false;
        loadProblems();
        emit('problem-resolved', (result as any)?.data);
    } catch (error: any) {
        log.error('Error resolving problem:', error);
    } finally {
        resolving.value = false;
    }
}

async function cancelProblem(problem: any) {
    if (!confirm('Отменить проблему?')) return;

    try {
        await api.delivery.deleteProblem(problem.id);
        loadProblems();
    } catch (error: any) {
        log.error('Error cancelling problem:', error);
    }
}

// Helpers
function getTypeIcon(type: any) {
    const icons = {
        'customer_unavailable': '📵',
        'wrong_address': '📍',
        'door_locked': '🔒',
        'payment_issue': '💳',
        'damaged_item': '📦',
        'other': '❓',
    };
    return (icons as Record<string, any>)[type] || '❓';
}

function getTypeIconClass(type: any) {
    const classes = {
        'customer_unavailable': 'bg-red-500/20',
        'wrong_address': 'bg-yellow-500/20',
        'door_locked': 'bg-purple-500/20',
        'payment_issue': 'bg-blue-500/20',
        'damaged_item': 'bg-orange-500/20',
        'other': 'bg-gray-500/20',
    };
    return (classes as Record<string, any>)[type] || 'bg-gray-500/20';
}

function getStatusClass(status: any) {
    const classes = {
        'open': 'bg-red-500/20 text-red-400',
        'in_progress': 'bg-yellow-500/20 text-yellow-400',
        'resolved': 'bg-green-500/20 text-green-400',
        'cancelled': 'bg-gray-500/20 text-gray-400',
    };
    return (classes as Record<string, any>)[status] || 'bg-gray-500/20 text-gray-400';
}

function formatTime(dateString: any) {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' });
}
</script>
