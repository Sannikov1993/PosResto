<template>
    <div class="h-full flex" data-testid="settings-tab">
        <!-- Sidebar -->
        <div class="w-56 border-r border-gray-800 p-4">
            <h2 class="text-lg font-semibold mb-4">Настройки</h2>
            <nav class="space-y-1">
                <button
                    v-for="section in sections"
                    :key="section.id"
                    @click="activeSection = section.id"
                    :class="[
                        'w-full text-left px-3 py-2 rounded-lg text-sm flex items-center gap-2',
                        activeSection === section.id ? 'bg-accent text-white' : 'text-gray-400 hover:text-white hover:bg-dark-800'
                    ]"
                >
                    <span>{{ section.icon }}</span>
                    <span>{{ section.label }}</span>
                </button>
            </nav>

            <!-- Unsaved changes indicator -->
            <div v-if="hasUnsavedChanges" class="mt-6 p-3 bg-yellow-600/20 border border-yellow-600/50 rounded-lg">
                <p class="text-sm text-yellow-400">Есть несохраненные изменения</p>
            </div>
        </div>

        <!-- Content -->
        <div class="flex-1 p-6 overflow-y-auto">
            <!-- Loading state -->
            <div v-if="loading" class="flex flex-col items-center justify-center h-full text-gray-500">
                <div class="animate-spin w-8 h-8 border-4 border-accent border-t-transparent rounded-full mb-4"></div>
                <p>Загрузка настроек...</p>
            </div>

            <template v-else>
                <!-- Interface -->
                <template v-if="activeSection === 'interface'">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-medium">Интерфейс</h3>
                        <button
                            v-if="sectionHasChanges('interface')"
                            @click="resetSection('interface')"
                            class="text-sm text-gray-400 hover:text-white"
                        >
                            Сбросить
                        </button>
                    </div>
                    <div class="space-y-4 max-w-md">
                        <div>
                            <label class="block text-sm text-gray-400 mb-2">Тема оформления</label>
                            <div class="grid grid-cols-2 gap-2">
                                <button
                                    @click="settings.theme = 'dark'"
                                    :class="[
                                        'py-3 rounded-lg text-sm flex items-center justify-center gap-2',
                                        settings.theme === 'dark' ? 'bg-accent text-white' : 'bg-dark-800 text-gray-400'
                                    ]"
                                >
                                    <span>🌙</span> Темная
                                </button>
                                <button
                                    @click="settings.theme = 'light'"
                                    :class="[
                                        'py-3 rounded-lg text-sm flex items-center justify-center gap-2',
                                        settings.theme === 'light' ? 'bg-accent text-white' : 'bg-dark-800 text-gray-400'
                                    ]"
                                >
                                    <span>☀️</span> Светлая
                                </button>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm text-gray-400 mb-2">Размер шрифта</label>
                            <div class="grid grid-cols-3 gap-2">
                                <button
                                    v-for="size in fontSizes"
                                    :key="size.value"
                                    @click="settings.fontSize = size.value"
                                    :class="[
                                        'py-2 rounded-lg',
                                        settings.fontSize === size.value ? 'bg-accent text-white' : 'bg-dark-800 text-gray-400'
                                    ]"
                                    :style="{ fontSize: size.preview }"
                                >
                                    {{ size.label }}
                                </button>
                            </div>
                        </div>
                        <div class="bg-dark-800 rounded-lg p-4 space-y-3">
                            <label class="flex items-center justify-between cursor-pointer">
                                <span class="text-sm">Показывать фото блюд</span>
                                <input
                                    type="checkbox"
                                    v-model="settings.showDishPhotos"
                                    class="w-5 h-5 accent-accent rounded"
                                />
                            </label>
                            <label class="flex items-center justify-between cursor-pointer">
                                <span class="text-sm">Компактный режим меню</span>
                                <input
                                    type="checkbox"
                                    v-model="settings.compactMenu"
                                    class="w-5 h-5 accent-accent rounded"
                                />
                            </label>
                            <label class="flex items-center justify-between cursor-pointer">
                                <span class="text-sm">Показывать подсказки</span>
                                <input
                                    type="checkbox"
                                    v-model="settings.showTooltips"
                                    class="w-5 h-5 accent-accent rounded"
                                />
                            </label>
                        </div>
                    </div>
                </template>

                <!-- Cash -->
                <template v-else-if="activeSection === 'cash'">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-medium">Касса</h3>
                        <button
                            v-if="sectionHasChanges('cash')"
                            @click="resetSection('cash')"
                            class="text-sm text-gray-400 hover:text-white"
                        >
                            Сбросить
                        </button>
                    </div>
                    <div class="space-y-4 max-w-md">
                        <div class="bg-dark-800 rounded-lg p-4 space-y-3">
                            <label class="flex items-center justify-between cursor-pointer">
                                <div>
                                    <span class="text-sm">Автоматически открывать смену</span>
                                    <p class="text-xs text-gray-500">При входе в систему</p>
                                </div>
                                <input
                                    type="checkbox"
                                    v-model="settings.autoOpenShift"
                                    class="w-5 h-5 accent-accent rounded"
                                />
                            </label>
                            <label class="flex items-center justify-between cursor-pointer">
                                <div>
                                    <span class="text-sm">Подтверждение при закрытии смены</span>
                                    <p class="text-xs text-gray-500">Показывать диалог с итогами</p>
                                </div>
                                <input
                                    type="checkbox"
                                    v-model="settings.confirmCloseShift"
                                    class="w-5 h-5 accent-accent rounded"
                                />
                            </label>
                        </div>
                        <div>
                            <label class="block text-sm text-gray-400 mb-2">Способ оплаты по умолчанию</label>
                            <div class="grid grid-cols-2 gap-2">
                                <button
                                    @click="settings.defaultPaymentMethod = 'cash'"
                                    :class="[
                                        'py-3 rounded-lg text-sm flex items-center justify-center gap-2',
                                        settings.defaultPaymentMethod === 'cash' ? 'bg-accent text-white' : 'bg-dark-800 text-gray-400'
                                    ]"
                                >
                                    <span>💵</span> Наличные
                                </button>
                                <button
                                    @click="settings.defaultPaymentMethod = 'card'"
                                    :class="[
                                        'py-3 rounded-lg text-sm flex items-center justify-center gap-2',
                                        settings.defaultPaymentMethod === 'card' ? 'bg-accent text-white' : 'bg-dark-800 text-gray-400'
                                    ]"
                                >
                                    <span>💳</span> Карта
                                </button>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm text-gray-400 mb-2">Быстрые суммы для сдачи</label>
                            <div class="flex flex-wrap gap-2">
                                <span
                                    v-for="amount in settings.quickAmounts"
                                    :key="amount"
                                    class="px-3 py-1 bg-dark-800 rounded text-sm"
                                >
                                    {{ amount }} ₽
                                </span>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">Настраивается в административной панели</p>
                        </div>
                    </div>
                </template>

                <!-- Printing -->
                <template v-else-if="activeSection === 'printing'">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-medium">Печать</h3>
                        <button
                            v-if="sectionHasChanges('printing')"
                            @click="resetSection('printing')"
                            class="text-sm text-gray-400 hover:text-white"
                        >
                            Сбросить
                        </button>
                    </div>
                    <div class="space-y-4 max-w-md">
                        <div class="bg-dark-800 rounded-lg p-4 space-y-3">
                            <label class="flex items-center justify-between cursor-pointer">
                                <div>
                                    <span class="text-sm">Автопечать чека клиенту</span>
                                    <p class="text-xs text-gray-500">После оплаты заказа</p>
                                </div>
                                <input
                                    type="checkbox"
                                    v-model="settings.autoPrintReceipt"
                                    class="w-5 h-5 accent-accent rounded"
                                />
                            </label>
                            <label class="flex items-center justify-between cursor-pointer">
                                <div>
                                    <span class="text-sm">Автопечать на кухню</span>
                                    <p class="text-xs text-gray-500">При создании заказа</p>
                                </div>
                                <input
                                    type="checkbox"
                                    v-model="settings.autoPrintKitchen"
                                    class="w-5 h-5 accent-accent rounded"
                                />
                            </label>
                            <label class="flex items-center justify-between cursor-pointer">
                                <div>
                                    <span class="text-sm">Печать пречека</span>
                                    <p class="text-xs text-gray-500">Перед оплатой</p>
                                </div>
                                <input
                                    type="checkbox"
                                    v-model="settings.autoPrintPrecheck"
                                    class="w-5 h-5 accent-accent rounded"
                                />
                            </label>
                        </div>
                        <div>
                            <label class="block text-sm text-gray-400 mb-2">Количество копий чека</label>
                            <div class="flex items-center gap-3">
                                <button
                                    @click="settings.receiptCopies = Math.max(1, settings.receiptCopies - 1)"
                                    class="w-10 h-10 bg-dark-800 rounded-lg text-lg hover:bg-dark-700"
                                >
                                    -
                                </button>
                                <span class="w-12 text-center text-lg font-medium">{{ settings.receiptCopies }}</span>
                                <button
                                    @click="settings.receiptCopies = Math.min(5, settings.receiptCopies + 1)"
                                    class="w-10 h-10 bg-dark-800 rounded-lg text-lg hover:bg-dark-700"
                                >
                                    +
                                </button>
                            </div>
                        </div>
                    </div>
                </template>

                <!-- Security -->
                <template v-else-if="activeSection === 'security'">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-medium">Безопасность</h3>
                        <button
                            v-if="sectionHasChanges('security')"
                            @click="resetSection('security')"
                            class="text-sm text-gray-400 hover:text-white"
                        >
                            Сбросить
                        </button>
                    </div>
                    <div class="space-y-4 max-w-md">
                        <div>
                            <label class="block text-sm text-gray-400 mb-2">
                                Автовыход (минуты бездействия)
                            </label>
                            <div class="flex items-center gap-4">
                                <input
                                    type="range"
                                    v-model.number="settings.autoLogoutMinutes"
                                    min="5"
                                    max="120"
                                    step="5"
                                    class="flex-1 accent-accent"
                                />
                                <span class="w-16 text-center bg-dark-800 px-3 py-2 rounded-lg">
                                    {{ settings.autoLogoutMinutes }} мин
                                </span>
                            </div>
                            <div class="flex justify-between text-xs text-gray-500 mt-1">
                                <span>5 мин</span>
                                <span>120 мин</span>
                            </div>
                        </div>
                        <div class="bg-dark-800 rounded-lg p-4 space-y-3">
                            <label class="flex items-center justify-between cursor-pointer">
                                <div>
                                    <span class="text-sm">PIN для отмены заказа</span>
                                    <p class="text-xs text-gray-500">Требовать PIN менеджера</p>
                                </div>
                                <input
                                    type="checkbox"
                                    v-model="settings.requirePinForCancel"
                                    class="w-5 h-5 accent-accent rounded"
                                />
                            </label>
                            <label class="flex items-center justify-between cursor-pointer">
                                <div>
                                    <span class="text-sm">PIN для скидок</span>
                                    <p class="text-xs text-gray-500">Требовать PIN для применения скидки</p>
                                </div>
                                <input
                                    type="checkbox"
                                    v-model="settings.requirePinForDiscount"
                                    class="w-5 h-5 accent-accent rounded"
                                />
                            </label>
                            <label class="flex items-center justify-between cursor-pointer">
                                <div>
                                    <span class="text-sm">PIN для удаления позиций</span>
                                    <p class="text-xs text-gray-500">После отправки на кухню</p>
                                </div>
                                <input
                                    type="checkbox"
                                    v-model="settings.requirePinForRemoveItem"
                                    class="w-5 h-5 accent-accent rounded"
                                />
                            </label>
                        </div>
                    </div>
                </template>

                <!-- Notifications -->
                <template v-else-if="activeSection === 'notifications'">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-medium">Уведомления</h3>
                        <button
                            v-if="sectionHasChanges('notifications')"
                            @click="resetSection('notifications')"
                            class="text-sm text-gray-400 hover:text-white"
                        >
                            Сбросить
                        </button>
                    </div>
                    <div class="space-y-4 max-w-md">
                        <div class="bg-dark-800 rounded-lg p-4 space-y-3">
                            <label class="flex items-center justify-between cursor-pointer">
                                <div>
                                    <span class="text-sm">Звуковые уведомления</span>
                                    <p class="text-xs text-gray-500">При новых заказах</p>
                                </div>
                                <input
                                    type="checkbox"
                                    v-model="settings.soundNotifications"
                                    class="w-5 h-5 accent-accent rounded"
                                />
                            </label>
                            <label class="flex items-center justify-between cursor-pointer">
                                <div>
                                    <span class="text-sm">Уведомления о доставке</span>
                                    <p class="text-xs text-gray-500">Новые заказы на доставку</p>
                                </div>
                                <input
                                    type="checkbox"
                                    v-model="settings.deliveryNotifications"
                                    class="w-5 h-5 accent-accent rounded"
                                />
                            </label>
                            <label class="flex items-center justify-between cursor-pointer">
                                <div>
                                    <span class="text-sm">Уведомления о бронях</span>
                                    <p class="text-xs text-gray-500">Новые бронирования столов</p>
                                </div>
                                <input
                                    type="checkbox"
                                    v-model="settings.reservationNotifications"
                                    class="w-5 h-5 accent-accent rounded"
                                />
                            </label>
                        </div>
                        <div>
                            <label class="block text-sm text-gray-400 mb-2">Громкость звука</label>
                            <div class="flex items-center gap-4">
                                <span class="text-gray-500">🔈</span>
                                <input
                                    type="range"
                                    v-model.number="settings.soundVolume"
                                    min="0"
                                    max="100"
                                    class="flex-1 accent-accent"
                                />
                                <span class="text-gray-500">🔊</span>
                                <span class="w-12 text-center">{{ settings.soundVolume }}%</span>
                            </div>
                        </div>
                    </div>
                </template>

                <!-- Save Button -->
                <div class="mt-8 flex items-center gap-4">
                    <button
                        @click="saveSettings"
                        :disabled="saving || !hasUnsavedChanges"
                        class="px-6 py-2 bg-accent hover:bg-blue-600 rounded-lg text-white font-medium disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        {{ saving ? 'Сохранение...' : 'Сохранить' }}
                    </button>
                    <button
                        v-if="hasUnsavedChanges"
                        @click="resetAllSettings"
                        class="px-6 py-2 bg-dark-800 hover:bg-dark-700 rounded-lg text-gray-400"
                    >
                        Отменить изменения
                    </button>
                </div>

                <!-- Last saved info -->
                <p v-if="lastSaved" class="text-xs text-gray-500 mt-4">
                    Последнее сохранение: {{ formatDateTime(lastSaved) }}
                </p>
            </template>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue';
import api from '../../api';

const activeSection = ref('interface');
const loading = ref(false);
const saving = ref(false);
const lastSaved = ref(null);

const sections = [
    { id: 'interface', label: 'Интерфейс', icon: '🎨' },
    { id: 'cash', label: 'Касса', icon: '💰' },
    { id: 'printing', label: 'Печать', icon: '🖨️' },
    { id: 'security', label: 'Безопасность', icon: '🔒' },
    { id: 'notifications', label: 'Уведомления', icon: '🔔' }
];

const fontSizes = [
    { value: 'small', label: 'Мелкий', preview: '12px' },
    { value: 'medium', label: 'Средний', preview: '14px' },
    { value: 'large', label: 'Крупный', preview: '16px' }
];

// Default settings
const defaultSettings = {
    // Interface
    theme: 'dark',
    fontSize: 'medium',
    showDishPhotos: true,
    compactMenu: false,
    showTooltips: true,
    // Cash
    autoOpenShift: false,
    confirmCloseShift: true,
    defaultPaymentMethod: 'cash',
    quickAmounts: [100, 200, 500, 1000, 2000, 5000],
    // Printing
    autoPrintReceipt: false,
    autoPrintKitchen: true,
    autoPrintPrecheck: false,
    receiptCopies: 1,
    // Security
    autoLogoutMinutes: 30,
    requirePinForCancel: true,
    requirePinForDiscount: false,
    requirePinForRemoveItem: true,
    // Notifications
    soundNotifications: true,
    deliveryNotifications: true,
    reservationNotifications: true,
    soundVolume: 70
};

// Original settings (for comparison)
const originalSettings = ref({ ...defaultSettings });

// Current settings
const settings = reactive({ ...defaultSettings });

// Section to settings mapping
const sectionSettings = {
    interface: ['theme', 'fontSize', 'showDishPhotos', 'compactMenu', 'showTooltips'],
    cash: ['autoOpenShift', 'confirmCloseShift', 'defaultPaymentMethod', 'quickAmounts'],
    printing: ['autoPrintReceipt', 'autoPrintKitchen', 'autoPrintPrecheck', 'receiptCopies'],
    security: ['autoLogoutMinutes', 'requirePinForCancel', 'requirePinForDiscount', 'requirePinForRemoveItem'],
    notifications: ['soundNotifications', 'deliveryNotifications', 'reservationNotifications', 'soundVolume']
};

// Computed
const hasUnsavedChanges = computed(() => {
    return Object.keys(defaultSettings).some(key => {
        const current = settings[key];
        const original = originalSettings.value[key];
        if (Array.isArray(current) && Array.isArray(original)) {
            return JSON.stringify(current) !== JSON.stringify(original);
        }
        return current !== original;
    });
});

// Methods
const sectionHasChanges = (section) => {
    const keys = sectionSettings[section] || [];
    return keys.some(key => {
        const current = settings[key];
        const original = originalSettings.value[key];
        if (Array.isArray(current) && Array.isArray(original)) {
            return JSON.stringify(current) !== JSON.stringify(original);
        }
        return current !== original;
    });
};

const resetSection = (section) => {
    const keys = sectionSettings[section] || [];
    keys.forEach(key => {
        if (Array.isArray(originalSettings.value[key])) {
            settings[key] = [...originalSettings.value[key]];
        } else {
            settings[key] = originalSettings.value[key];
        }
    });
};

const resetAllSettings = () => {
    Object.keys(originalSettings.value).forEach(key => {
        if (Array.isArray(originalSettings.value[key])) {
            settings[key] = [...originalSettings.value[key]];
        } else {
            settings[key] = originalSettings.value[key];
        }
    });
};

const formatDateTime = (dt) => {
    if (!dt) return '';
    return new Date(dt).toLocaleString('ru-RU', {
        day: '2-digit',
        month: '2-digit',
        hour: '2-digit',
        minute: '2-digit'
    });
};

const saveSettings = async () => {
    saving.value = true;
    try {
        // Save to API
        await api.settings.save({ ...settings });

        // Save to localStorage as backup
        localStorage.setItem('menulab_settings', JSON.stringify(settings));
        localStorage.setItem('menulab_settings_saved', new Date().toISOString());

        // Update original settings
        Object.keys(settings).forEach(key => {
            if (Array.isArray(settings[key])) {
                originalSettings.value[key] = [...settings[key]];
            } else {
                originalSettings.value[key] = settings[key];
            }
        });

        lastSaved.value = new Date().toISOString();
        window.$toast?.('Настройки сохранены', 'success');
    } catch (error) {
        console.error('Error saving settings:', error);
        window.$toast?.('Ошибка сохранения настроек', 'error');
    } finally {
        saving.value = false;
    }
};

const loadSettings = async () => {
    loading.value = true;
    try {
        // Try to load from API
        const saved = await api.settings.get();
        if (saved) {
            Object.assign(settings, saved);
            Object.keys(saved).forEach(key => {
                if (Array.isArray(saved[key])) {
                    originalSettings.value[key] = [...saved[key]];
                } else {
                    originalSettings.value[key] = saved[key];
                }
            });
        }

        // Get last saved time from localStorage
        const savedTime = localStorage.getItem('menulab_settings_saved');
        if (savedTime) {
            lastSaved.value = savedTime;
        }
    } catch (error) {
        console.error('Error loading settings from API:', error);

        // Try to load from localStorage
        try {
            const localSettings = localStorage.getItem('menulab_settings');
            if (localSettings) {
                const parsed = JSON.parse(localSettings);
                Object.assign(settings, parsed);
                Object.keys(parsed).forEach(key => {
                    if (Array.isArray(parsed[key])) {
                        originalSettings.value[key] = [...parsed[key]];
                    } else {
                        originalSettings.value[key] = parsed[key];
                    }
                });
                window.$toast?.('Настройки загружены из локального хранилища', 'info');
            }
        } catch (localError) {
            console.error('Error loading settings from localStorage:', localError);
        }
    } finally {
        loading.value = false;
    }
};

// Lifecycle
onMounted(() => {
    loadSettings();
});

// Auto-save to localStorage on changes (debounced)
let saveTimeout = null;
watch(settings, () => {
    if (saveTimeout) clearTimeout(saveTimeout);
    saveTimeout = setTimeout(() => {
        localStorage.setItem('menulab_settings_draft', JSON.stringify(settings));
    }, 1000);
}, { deep: true });
</script>
