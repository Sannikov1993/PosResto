<template>
    <div class="h-full flex flex-col">
        <!-- Header -->
        <div class="flex items-center gap-4 px-4 py-3 border-b border-gray-800 bg-dark-900">
            <h1 class="text-lg font-semibold">Стоп-лист</h1>
            <span class="text-sm text-gray-400">{{ stopList.length }} позиций</span>
            <input
                v-model="search"
                type="text"
                placeholder="Поиск..."
                class="ml-auto bg-dark-800 border border-gray-700 rounded-lg px-3 py-2 text-sm w-48"
            />
            <button
                @click="openAddModal"
                class="px-4 py-2 bg-red-600 hover:bg-red-700 rounded-lg text-sm text-white"
            >
                + Добавить в стоп
            </button>
        </div>

        <!-- Stop List -->
        <div class="flex-1 overflow-y-auto">
            <!-- Loading state -->
            <div v-if="loading" class="flex flex-col items-center justify-center h-full text-gray-500">
                <div class="animate-spin w-8 h-8 border-4 border-accent border-t-transparent rounded-full mb-4"></div>
                <p>Загрузка...</p>
            </div>

            <!-- Empty state -->
            <div v-else-if="filteredStopList.length === 0" class="flex flex-col items-center justify-center h-full text-gray-500">
                <p class="text-4xl mb-4">🚫</p>
                <p v-if="search">Ничего не найдено</p>
                <p v-else>Стоп-лист пуст</p>
                <p v-if="!search" class="text-sm mt-2">Все блюда доступны для заказа</p>
            </div>

            <!-- Stop list items -->
            <div v-else class="divide-y divide-gray-800">
                <div
                    v-for="item in filteredStopList"
                    :key="item.id"
                    class="flex items-center gap-4 px-4 py-3 hover:bg-dark-900/50"
                >
                    <div class="w-12 h-12 rounded-lg bg-dark-800 flex items-center justify-center overflow-hidden">
                        <img
                            v-if="item.dish?.image"
                            :src="item.dish.image"
                            :alt="item.dish?.name"
                            class="w-full h-full object-cover"
                        />
                        <span v-else class="text-2xl">🍽️</span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="font-medium">{{ item.dish?.name || 'Неизвестное блюдо' }}</p>
                        <p class="text-sm text-gray-400">{{ item.reason || 'Причина не указана' }}</p>
                        <p v-if="item.stopped_by" class="text-xs text-gray-500">
                            Добавил: {{ item.stopped_by.name }}
                        </p>
                    </div>
                    <div class="text-right">
                        <p v-if="item.resume_at" class="text-sm text-yellow-400">
                            До {{ formatDateTime(item.resume_at) }}
                        </p>
                        <p v-else class="text-sm text-red-400">Бессрочно</p>
                        <p class="text-xs text-gray-500 mt-1">
                            {{ formatDateTime(item.stopped_at) }}
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button
                            @click="editItem(item)"
                            class="px-3 py-1 bg-dark-800 hover:bg-dark-700 rounded text-sm text-gray-400"
                        >
                            ✏️
                        </button>
                        <button
                            @click="removeFromStopList(item)"
                            class="px-3 py-1 bg-green-600 hover:bg-green-700 rounded text-sm text-white"
                        >
                            Вернуть
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add to Stop List Modal -->
        <Teleport to="body">
            <div v-if="showAddModal" class="fixed inset-0 bg-black/80 flex items-center justify-center z-50 p-4">
                <div class="bg-dark-900 rounded-2xl w-full max-w-2xl max-h-[90vh] overflow-hidden flex flex-col shadow-2xl">
                    <!-- Header -->
                    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-800 bg-dark-950">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-red-600/20 rounded-xl flex items-center justify-center">
                                <span class="text-red-500 text-xl">🚫</span>
                            </div>
                            <div>
                                <h2 class="text-lg font-semibold text-white">
                                    {{ editingItem ? 'Редактировать стоп' : 'Добавить в стоп-лист' }}
                                </h2>
                                <p class="text-xs text-gray-500">Блюдо будет недоступно для заказа</p>
                            </div>
                        </div>
                        <button @click="closeAddModal" class="w-8 h-8 flex items-center justify-center rounded-lg bg-dark-800 text-gray-400 hover:text-white hover:bg-dark-700 transition-colors">✕</button>
                    </div>

                    <div class="flex-1 overflow-y-auto p-6 space-y-6">
                        <!-- STEP 1: Dish Selection -->
                        <div v-if="!editingItem">
                            <label class="flex items-center gap-2 text-sm font-medium text-gray-300 mb-3">
                                <span class="w-6 h-6 bg-accent/20 rounded-full flex items-center justify-center text-accent text-xs font-bold">1</span>
                                Выберите блюдо
                            </label>

                            <!-- Search input -->
                            <div class="relative mb-3">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500">🔍</span>
                                <input
                                    v-model="dishSearch"
                                    type="text"
                                    class="w-full bg-dark-800 border border-gray-700 rounded-xl pl-10 pr-4 py-3 text-white placeholder-gray-500 focus:border-accent focus:outline-none transition-colors"
                                    placeholder="Поиск по названию блюда..."
                                    @input="searchDishes"
                                />
                            </div>

                            <!-- Selected dish card -->
                            <div v-if="selectedDish" class="bg-gradient-to-r from-dark-800 to-dark-800/50 rounded-xl p-4 border border-green-600/30">
                                <div class="flex items-center gap-4">
                                    <div class="w-16 h-16 rounded-xl bg-dark-700 overflow-hidden flex-shrink-0">
                                        <img v-if="selectedDish.image" :src="selectedDish.image" :alt="selectedDish.name" class="w-full h-full object-cover" />
                                        <div v-else class="w-full h-full flex items-center justify-center text-2xl">🍽️</div>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2 mb-1">
                                            <span class="text-green-500">✓</span>
                                            <p class="font-medium text-white truncate">{{ selectedDish.name }}</p>
                                        </div>
                                        <p class="text-sm text-gray-400">{{ selectedDish.category?.name || 'Без категории' }}</p>
                                        <p class="text-accent font-semibold">{{ selectedDish.price }} ₽</p>
                                    </div>
                                    <button @click="selectedDish = null" class="w-8 h-8 flex items-center justify-center rounded-lg bg-dark-700 text-gray-400 hover:text-red-400 hover:bg-red-600/20 transition-colors">✕</button>
                                </div>
                            </div>

                            <!-- Search results dropdown -->
                            <div v-if="dishSearch && !selectedDish" class="bg-dark-800 border border-gray-700 rounded-xl overflow-hidden">
                                <div v-if="searching" class="p-4 text-center text-gray-500">
                                    <div class="animate-spin w-5 h-5 border-2 border-accent border-t-transparent rounded-full mx-auto mb-2"></div>
                                    Поиск...
                                </div>
                                <div v-else-if="searchResults.length > 0" class="max-h-64 overflow-y-auto divide-y divide-gray-700/50">
                                    <div
                                        v-for="dish in searchResults"
                                        :key="dish.id"
                                        @click="selectDish(dish)"
                                        class="flex items-center gap-3 p-3 hover:bg-dark-700 cursor-pointer transition-colors"
                                    >
                                        <div class="w-12 h-12 rounded-lg bg-dark-700 overflow-hidden flex-shrink-0">
                                            <img v-if="dish.image" :src="dish.image" :alt="dish.name" class="w-full h-full object-cover" />
                                            <div v-else class="w-full h-full flex items-center justify-center text-lg">🍽️</div>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="font-medium text-white truncate">{{ dish.name }}</p>
                                            <p class="text-xs text-gray-500">{{ dish.category?.name || 'Без категории' }}</p>
                                        </div>
                                        <span class="text-accent font-medium">{{ dish.price }} ₽</span>
                                    </div>
                                </div>
                                <div v-else class="p-4 text-center text-gray-500">
                                    <span class="text-2xl mb-2 block">🔍</span>
                                    Блюда не найдены
                                </div>
                            </div>
                        </div>

                        <!-- Editing: Show selected dish -->
                        <div v-if="editingItem" class="bg-dark-800 rounded-xl p-4">
                            <div class="flex items-center gap-4">
                                <div class="w-16 h-16 rounded-xl bg-dark-700 overflow-hidden flex-shrink-0">
                                    <img v-if="editingItem.dish?.image" :src="editingItem.dish.image" :alt="editingItem.dish?.name" class="w-full h-full object-cover" />
                                    <div v-else class="w-full h-full flex items-center justify-center text-2xl">🍽️</div>
                                </div>
                                <div class="flex-1">
                                    <p class="font-medium text-white">{{ editingItem.dish?.name }}</p>
                                    <p class="text-sm text-gray-400">{{ editingItem.dish?.category?.name || 'Без категории' }}</p>
                                    <p class="text-accent font-semibold">{{ editingItem.dish?.price }} ₽</p>
                                </div>
                            </div>
                        </div>

                        <!-- STEP 2: Reason Selection -->
                        <div>
                            <label class="flex items-center gap-2 text-sm font-medium text-gray-300 mb-3">
                                <span class="w-6 h-6 bg-accent/20 rounded-full flex items-center justify-center text-accent text-xs font-bold">2</span>
                                Укажите причину
                            </label>

                            <div class="grid grid-cols-2 gap-2">
                                <button
                                    v-for="reason in reasonOptions"
                                    :key="reason.value"
                                    @click="selectReason(reason.value)"
                                    :class="[
                                        'flex items-center gap-3 p-3 rounded-xl border transition-all text-left',
                                        form.reason === reason.value
                                            ? 'bg-accent/10 border-accent text-white'
                                            : 'bg-dark-800 border-gray-700 text-gray-400 hover:border-gray-600 hover:text-white'
                                    ]"
                                >
                                    <span class="text-xl">{{ reason.icon }}</span>
                                    <span class="text-sm font-medium">{{ reason.label }}</span>
                                </button>
                            </div>

                            <!-- Custom reason input -->
                            <input
                                v-if="form.reason === 'other'"
                                v-model="form.customReason"
                                type="text"
                                class="w-full bg-dark-800 border border-gray-700 rounded-xl px-4 py-3 mt-3 text-white placeholder-gray-500 focus:border-accent focus:outline-none"
                                placeholder="Укажите свою причину..."
                            />
                        </div>

                        <!-- STEP 3: Resume Time -->
                        <div>
                            <label class="flex items-center gap-2 text-sm font-medium text-gray-300 mb-3">
                                <span class="w-6 h-6 bg-accent/20 rounded-full flex items-center justify-center text-accent text-xs font-bold">3</span>
                                Когда вернуть в продажу?
                            </label>

                            <div class="grid grid-cols-3 gap-2 mb-2">
                                <button
                                    v-for="time in timeOptions"
                                    :key="time.value"
                                    @click="form.resumeType = time.value"
                                    :class="[
                                        'flex flex-col items-center gap-1 p-3 rounded-xl border transition-all',
                                        form.resumeType === time.value
                                            ? (time.value === 'never' ? 'bg-red-600/20 border-red-600 text-red-400' : 'bg-accent/10 border-accent text-accent')
                                            : 'bg-dark-800 border-gray-700 text-gray-400 hover:border-gray-600 hover:text-white'
                                    ]"
                                >
                                    <span class="text-lg">{{ time.icon }}</span>
                                    <span class="text-xs font-medium">{{ time.label }}</span>
                                </button>
                            </div>

                            <!-- Custom datetime picker -->
                            <input
                                v-if="form.resumeType === 'custom'"
                                v-model="form.resumeAt"
                                type="datetime-local"
                                class="w-full bg-dark-800 border border-gray-700 rounded-xl px-4 py-3 mt-2 text-white focus:border-accent focus:outline-none"
                            />

                            <!-- Time preview -->
                            <div v-if="resumeTimePreview" class="mt-3 px-4 py-2 bg-dark-800 rounded-lg text-sm text-gray-400 flex items-center gap-2">
                                <span>⏰</span>
                                <span>{{ resumeTimePreview }}</span>
                            </div>
                        </div>

                        <!-- STEP 4: Notify Kitchen -->
                        <div>
                            <label class="flex items-center gap-2 text-sm font-medium text-gray-300 mb-3">
                                <span class="w-6 h-6 bg-accent/20 rounded-full flex items-center justify-center text-accent text-xs font-bold">4</span>
                                Уведомление
                            </label>

                            <button
                                @click="form.notifyKitchen = !form.notifyKitchen"
                                :class="[
                                    'w-full flex items-center gap-4 p-4 rounded-xl border transition-all',
                                    form.notifyKitchen
                                        ? 'bg-green-600/10 border-green-600 text-white'
                                        : 'bg-dark-800 border-gray-700 text-gray-400 hover:border-gray-600'
                                ]"
                            >
                                <div :class="[
                                    'w-12 h-12 rounded-xl flex items-center justify-center text-2xl',
                                    form.notifyKitchen ? 'bg-green-600/20' : 'bg-dark-700'
                                ]">
                                    🍳
                                </div>
                                <div class="flex-1 text-left">
                                    <p class="font-medium">Уведомить кухню</p>
                                    <p class="text-xs text-gray-500">Отправить сообщение на кухонный дисплей</p>
                                </div>
                                <div :class="[
                                    'w-6 h-6 rounded-full border-2 flex items-center justify-center transition-colors',
                                    form.notifyKitchen ? 'bg-green-600 border-green-600 text-white' : 'border-gray-600'
                                ]">
                                    <span v-if="form.notifyKitchen" class="text-sm">✓</span>
                                </div>
                            </button>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="flex gap-3 px-6 py-4 border-t border-gray-800 bg-dark-950">
                        <button
                            @click="closeAddModal"
                            class="flex-1 py-3 bg-dark-800 text-gray-400 rounded-xl hover:bg-dark-700 transition-colors font-medium"
                        >
                            Отмена
                        </button>
                        <button
                            @click="saveToStopList"
                            :disabled="!canSave || saving"
                            class="flex-1 py-3 bg-red-600 text-white rounded-xl hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors font-medium flex items-center justify-center gap-2"
                        >
                            <span v-if="saving" class="animate-spin">⏳</span>
                            <span>{{ saving ? 'Сохранение...' : (editingItem ? 'Сохранить изменения' : '🚫 Добавить в стоп') }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { usePosStore } from '../../stores/pos';
import api from '../../api';

const posStore = usePosStore();

// State
const search = ref('');
const loading = ref(false);
const saving = ref(false);
const showAddModal = ref(false);
const editingItem = ref(null);

// Dish search
const dishSearch = ref('');
const searchResults = ref([]);
const selectedDish = ref(null);
const searching = ref(false);
let searchTimeout = null;

// Form state
const form = reactive({
    reason: '',
    customReason: '',
    resumeType: 'never',
    resumeAt: '',
    notifyKitchen: true
});

// Reason options with icons
const reasonOptions = [
    { value: 'Закончились ингредиенты', label: 'Закончились ингредиенты', icon: '📦' },
    { value: 'Не прошло контроль качества', label: 'Контроль качества', icon: '⚠️' },
    { value: 'Поломка оборудования', label: 'Поломка оборудования', icon: '🔧' },
    { value: 'Сезонное блюдо', label: 'Сезонное блюдо', icon: '🍂' },
    { value: 'Временно недоступно', label: 'Временно недоступно', icon: '⏸️' },
    { value: 'Ожидается поставка', label: 'Ожидается поставка', icon: '🚚' },
    { value: 'По решению шефа', label: 'По решению шефа', icon: '👨‍🍳' },
    { value: 'other', label: 'Другое...', icon: '✏️' }
];

// Time options
const timeOptions = [
    { value: 'never', label: 'Бессрочно', icon: '🚫' },
    { value: '1hour', label: 'Через 1 час', icon: '⏱️' },
    { value: '2hours', label: 'Через 2 часа', icon: '⏱️' },
    { value: 'today', label: 'До конца дня', icon: '🌙' },
    { value: 'tomorrow', label: 'Завтра', icon: '📅' },
    { value: 'custom', label: 'Указать', icon: '🗓️' }
];

// Select reason helper
const selectReason = (value) => {
    form.reason = value;
    if (value !== 'other') {
        form.customReason = '';
    }
};

// Computed
const stopList = computed(() => posStore.stopList);

const filteredStopList = computed(() => {
    if (!search.value) return stopList.value;
    const q = search.value.toLowerCase();
    return stopList.value.filter(item =>
        item.dish?.name?.toLowerCase().includes(q) ||
        item.reason?.toLowerCase().includes(q)
    );
});

const canSave = computed(() => {
    const hasReason = form.reason && (form.reason !== 'other' || form.customReason);
    if (editingItem.value) {
        return hasReason;
    }
    return selectedDish.value && hasReason;
});

// Methods
const formatDateTime = (dt) => {
    if (!dt) return '';
    return new Date(dt).toLocaleString('ru-RU', {
        day: '2-digit',
        month: '2-digit',
        hour: '2-digit',
        minute: '2-digit'
    });
};

const searchDishes = () => {
    if (searchTimeout) clearTimeout(searchTimeout);

    if (!dishSearch.value || dishSearch.value.length < 2) {
        searchResults.value = [];
        return;
    }

    searching.value = true;
    searchTimeout = setTimeout(async () => {
        try {
            const results = await api.stopList.searchDishes(dishSearch.value);
            searchResults.value = Array.isArray(results) ? results : (results.data || []);
        } catch (error) {
            console.error('Error searching dishes:', error);
            searchResults.value = [];
        } finally {
            searching.value = false;
        }
    }, 300);
};

const selectDish = (dish) => {
    selectedDish.value = dish;
    dishSearch.value = '';
    searchResults.value = [];
};

const openAddModal = () => {
    editingItem.value = null;
    selectedDish.value = null;
    dishSearch.value = '';
    searchResults.value = [];
    form.reason = '';
    form.customReason = '';
    form.resumeType = 'never';
    form.resumeAt = '';
    form.notifyKitchen = true;
    showAddModal.value = true;
};

// Предустановленные причины для проверки (values from reasonOptions)
const predefinedReasons = reasonOptions.filter(r => r.value !== 'other').map(r => r.value);

const editItem = (item) => {
    editingItem.value = item;
    selectedDish.value = item.dish;

    // Проверяем, является ли причина предустановленной или кастомной
    const reason = item.reason || '';
    if (predefinedReasons.includes(reason)) {
        form.reason = reason;
        form.customReason = '';
    } else if (reason) {
        // Кастомная причина
        form.reason = 'other';
        form.customReason = reason;
    } else {
        form.reason = '';
        form.customReason = '';
    }

    form.resumeType = item.resume_at ? 'custom' : 'never';
    form.resumeAt = item.resume_at ? new Date(item.resume_at).toISOString().slice(0, 16) : '';
    showAddModal.value = true;
};

const closeAddModal = () => {
    showAddModal.value = false;
    editingItem.value = null;
    selectedDish.value = null;
};

const getResumeAt = () => {
    const now = new Date();

    switch (form.resumeType) {
        case 'never':
            return null;
        case '1hour':
            return new Date(now.getTime() + 60 * 60 * 1000).toISOString();
        case '2hours':
            return new Date(now.getTime() + 2 * 60 * 60 * 1000).toISOString();
        case 'today':
            const today = new Date();
            today.setHours(23, 59, 59, 999);
            return today.toISOString();
        case 'tomorrow':
            const tomorrow = new Date();
            tomorrow.setDate(tomorrow.getDate() + 1);
            tomorrow.setHours(9, 0, 0, 0);
            return tomorrow.toISOString();
        case 'custom':
            return form.resumeAt ? new Date(form.resumeAt).toISOString() : null;
        default:
            return null;
    }
};

// Resume time preview text
const resumeTimePreview = computed(() => {
    const resumeAt = getResumeAt();
    if (!resumeAt) return 'Блюдо будет в стопе бессрочно';

    const date = new Date(resumeAt);
    const now = new Date();
    const diffMs = date.getTime() - now.getTime();
    const diffHours = Math.round(diffMs / (1000 * 60 * 60));

    if (diffHours < 1) {
        return 'Вернётся в продажу менее чем через час';
    } else if (diffHours === 1) {
        return 'Вернётся в продажу через 1 час';
    } else if (diffHours < 24) {
        return `Вернётся в продажу через ${diffHours} ч.`;
    } else {
        return `Вернётся в продажу: ${date.toLocaleString('ru-RU', {
            day: 'numeric',
            month: 'short',
            hour: '2-digit',
            minute: '2-digit'
        })}`;
    }
});

const saveToStopList = async () => {
    if (!canSave.value) return;

    saving.value = true;
    try {
        const reason = form.reason === 'other' ? form.customReason : form.reason;
        const resumeAt = getResumeAt();
        const dishName = editingItem.value ? editingItem.value.dish?.name : selectedDish.value?.name;

        if (editingItem.value) {
            await api.stopList.update(editingItem.value.dish_id, reason, resumeAt);
            window.$toast?.('Стоп-лист обновлён', 'success');
        } else {
            await api.stopList.add(selectedDish.value.id, reason, resumeAt);
            window.$toast?.('Блюдо добавлено в стоп-лист', 'success');

            // Отправляем уведомление на кухню если включено
            if (form.notifyKitchen) {
                await sendKitchenNotification(dishName, reason, resumeAt);
            }
        }

        closeAddModal();
        await posStore.loadStopList();
    } catch (error) {
        console.error('Error saving to stop list:', error);
        const message = error.response?.data?.message || 'Ошибка сохранения';
        window.$toast?.(message, 'error');
    } finally {
        saving.value = false;
    }
};

// Отправка уведомления на кухню
const sendKitchenNotification = async (dishName, reason, resumeAt) => {
    try {
        const message = resumeAt
            ? `🚫 СТОП: "${dishName}" — ${reason}. Вернётся: ${formatDateTime(resumeAt)}`
            : `🚫 СТОП: "${dishName}" — ${reason}. Бессрочно.`;

        // Отправляем через realtime API
        await api.realtime.sendKitchenNotification(message, {
            title: 'Стоп-лист',
            dish_name: dishName,
            reason: reason,
            resume_at: resumeAt,
            type: 'stop_list_added'
        });
    } catch (error) {
        console.warn('Failed to send kitchen notification:', error);
        // Не показываем ошибку пользователю - уведомление необязательное
    }
};

const removeFromStopList = async (item) => {
    if (!confirm(`Вернуть "${item.dish?.name}" в продажу?`)) return;

    try {
        await api.stopList.remove(item.dish_id);
        window.$toast?.('Блюдо возвращено в продажу', 'success');
        await posStore.loadStopList();
    } catch (error) {
        console.error('Error removing from stop list:', error);
        window.$toast?.('Ошибка', 'error');
    }
};

// Lifecycle
onMounted(async () => {
    loading.value = true;
    try {
        await posStore.loadStopList();
    } finally {
        loading.value = false;
    }
});
</script>
