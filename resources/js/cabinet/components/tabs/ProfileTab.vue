<template>
    <div class="space-y-4">
        <!-- Profile Header -->
        <div class="bg-white rounded-xl shadow-sm p-6 text-center">
            <div class="w-20 h-20 mx-auto rounded-full bg-gradient-to-br from-orange-400 to-orange-600 flex items-center justify-center text-white text-3xl font-bold mb-3">
                {{ user?.name?.charAt(0)?.toUpperCase() || '?' }}
            </div>
            <h2 class="text-xl font-bold text-gray-900">{{ user?.name }}</h2>
            <p class="text-gray-500">{{ user?.role_label }}</p>
            <div class="flex justify-center gap-3 mt-3">
                <span v-if="user?.has_password" class="px-2 py-1 bg-green-100 text-green-700 rounded text-xs">Пароль</span>
                <span v-if="user?.has_pin" class="px-2 py-1 bg-blue-100 text-blue-700 rounded text-xs">PIN</span>
                <span v-if="user?.telegram_connected" class="px-2 py-1 bg-sky-100 text-sky-700 rounded text-xs">Telegram</span>
            </div>
        </div>

        <!-- Contact Info -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="px-4 py-3 bg-gray-50 border-b font-semibold text-gray-700">
                Контактная информация
            </div>
            <div class="p-4 space-y-3">
                <div class="flex items-center gap-3">
                    <span class="text-xl">📧</span>
                    <div class="flex-1">
                        <div class="text-sm text-gray-500">Email</div>
                        <div class="font-medium">{{ user?.email || 'Не указан' }}</div>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <span class="text-xl">📱</span>
                    <div class="flex-1">
                        <div class="text-sm text-gray-500">Телефон</div>
                        <div class="font-medium">{{ user?.phone || 'Не указан' }}</div>
                    </div>
                </div>
                <div v-if="user?.telegram_username" class="flex items-center gap-3">
                    <span class="text-xl">✈️</span>
                    <div class="flex-1">
                        <div class="text-sm text-gray-500">Telegram</div>
                        <div class="font-medium">@{{ user.telegram_username }}</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Security -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="px-4 py-3 bg-gray-50 border-b font-semibold text-gray-700">
                Безопасность
            </div>
            <div class="divide-y">
                <button @click="showPinModal = true"
                        class="w-full p-4 flex items-center justify-between hover:bg-gray-50 transition">
                    <div class="flex items-center gap-3">
                        <span class="text-xl">🔢</span>
                        <div class="text-left">
                            <div class="font-medium">PIN-код</div>
                            <div class="text-sm text-gray-500">{{ user?.has_pin ? 'Установлен' : 'Не установлен' }}</div>
                        </div>
                    </div>
                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </button>
                <button @click="showPasswordModal = true"
                        class="w-full p-4 flex items-center justify-between hover:bg-gray-50 transition">
                    <div class="flex items-center gap-3">
                        <span class="text-xl">🔐</span>
                        <div class="text-left">
                            <div class="font-medium">Пароль</div>
                            <div class="text-sm text-gray-500">{{ user?.has_password ? 'Установлен' : 'Не установлен' }}</div>
                        </div>
                    </div>
                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Device Sessions -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="px-4 py-3 bg-gray-50 border-b font-semibold text-gray-700">
                Устройства и сессии
            </div>
            <div class="p-4">
                <div v-if="loadingSessions" class="text-center py-4 text-gray-500">
                    Загрузка...
                </div>
                <div v-else-if="deviceSessions.length === 0" class="text-center py-4 text-gray-500">
                    Нет активных сессий
                </div>
                <div v-else class="space-y-3">
                    <div v-for="session in deviceSessions" :key="session.id"
                         class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                        <div class="flex items-center gap-3">
                            <span class="text-2xl">
                                {{ getDeviceIcon(session.app_type) }}
                            </span>
                            <div>
                                <div class="font-medium text-sm">{{ session.device_name || 'Неизвестное устройство' }}</div>
                                <div class="text-xs text-gray-500">{{ getAppTypeLabel(session.app_type) }}</div>
                                <div class="text-xs text-gray-400">
                                    Активность: {{ formatDate(session.last_activity_at) }}
                                </div>
                            </div>
                        </div>
                        <button @click="revokeSession(session.id)"
                                :disabled="revokingSession === session.id"
                                class="text-red-500 hover:text-red-700 p-2 disabled:opacity-50">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                        </button>
                    </div>
                </div>
                <div v-if="deviceSessions.length > 0" class="mt-4 pt-4 border-t">
                    <button @click="revokeAllSessions"
                            :disabled="revokingAll"
                            class="w-full py-2 text-sm text-red-600 hover:bg-red-50 rounded-lg transition disabled:opacity-50">
                        {{ revokingAll ? 'Выход...' : 'Выйти из всех устройств' }}
                    </button>
                    <p class="text-xs text-gray-500 mt-2 text-center">
                        Вы останетесь авторизованы только на текущем устройстве
                    </p>
                </div>
            </div>
        </div>

        <!-- Biometric Authentication -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="px-4 py-3 bg-gray-50 border-b font-semibold text-gray-700">
                Биометрическая аутентификация
            </div>
            <div class="p-4">
                <div v-if="!biometricSupported" class="text-center text-gray-500 py-2">
                    WebAuthn не поддерживается вашим браузером
                </div>
                <div v-else>
                    <!-- Status indicator -->
                    <div class="flex items-center gap-3 mb-4">
                        <span class="text-2xl">{{ biometricCredentials.length > 0 ? '✅' : '🔒' }}</span>
                        <div>
                            <div class="font-medium">
                                {{ biometricCredentials.length > 0 ? 'Биометрия настроена' : 'Биометрия не настроена' }}
                            </div>
                            <div class="text-sm text-gray-500">
                                Touch ID, Face ID или отпечаток пальца
                            </div>
                        </div>
                    </div>

                    <!-- Registered credentials -->
                    <div v-if="biometricCredentials.length > 0" class="mb-4 space-y-2">
                        <div class="text-sm text-gray-500 mb-2">Зарегистрированные устройства:</div>
                        <div v-for="cred in biometricCredentials" :key="cred.id"
                             class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                            <div class="flex items-center gap-3">
                                <span class="text-xl">
                                    {{ cred.device_type === 'face' ? '👤' : (cred.device_type === 'fingerprint' ? '👆' : '🔐') }}
                                </span>
                                <div>
                                    <div class="font-medium text-sm">{{ cred.name || 'Устройство' }}</div>
                                    <div class="text-xs text-gray-400">
                                        {{ cred.last_used_at ? 'Использовано: ' + formatDate(cred.last_used_at) : 'Не использовалось' }}
                                    </div>
                                </div>
                            </div>
                            <button @click="deleteBiometricCredential(cred.id)"
                                    class="text-red-500 hover:text-red-700 p-2">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Add new biometric -->
                    <button @click="registerBiometric"
                            :disabled="biometricLoading"
                            class="w-full py-3 bg-gradient-to-r from-orange-500 to-orange-600 text-white rounded-xl font-semibold hover:from-orange-600 hover:to-orange-700 transition disabled:opacity-50 flex items-center justify-center gap-2">
                        <span v-if="biometricLoading">Подождите...</span>
                        <template v-else>
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11c0 3.517-1.009 6.799-2.753 9.571m-3.44-2.04l.054-.09A13.916 13.916 0 008 11a4 4 0 118 0c0 1.017-.07 2.019-.203 3m-2.118 6.844A21.88 21.88 0 0015.171 17m3.839 1.132c.645-2.266.99-4.659.99-7.132A8 8 0 008 4.07M3 15.364c.64-1.319 1-2.8 1-4.364 0-1.457.39-2.823 1.07-4"/>
                            </svg>
                            {{ biometricCredentials.length > 0 ? 'Добавить устройство' : 'Настроить биометрию' }}
                        </template>
                    </button>

                    <!-- Require biometric for clock in/out -->
                    <div v-if="biometricCredentials.length > 0" class="mt-4 pt-4 border-t">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="font-medium text-sm">Требовать для учета времени</div>
                                <div class="text-xs text-gray-500">
                                    Биометрия при отметке прихода/ухода
                                </div>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" v-model="requireBiometricClock"
                                       @change="toggleBiometricRequirement"
                                       class="sr-only peer">
                                <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:bg-orange-500 after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-full"></div>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Push Notifications -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="px-4 py-3 bg-gray-50 border-b font-semibold text-gray-700">
                Push-уведомления
            </div>
            <div class="p-4">
                <div v-if="!pushSupported" class="text-center text-gray-500 py-2">
                    Push-уведомления не поддерживаются браузером
                </div>
                <div v-else-if="pushPermission === 'denied'" class="text-center text-red-500 py-2">
                    Push-уведомления заблокированы. Разрешите их в настройках браузера.
                </div>
                <div v-else>
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <div class="font-medium">Получать уведомления</div>
                            <div class="text-sm text-gray-500">
                                {{ pushSubscribed ? 'Устройство подписано' : 'Устройство не подписано' }}
                            </div>
                        </div>
                        <button @click="togglePushSubscription"
                                :disabled="pushLoading"
                                :class="[
                                    'px-4 py-2 rounded-lg font-medium text-sm transition',
                                    pushSubscribed
                                        ? 'bg-gray-200 text-gray-700 hover:bg-gray-300'
                                        : 'bg-orange-500 text-white hover:bg-orange-600',
                                    pushLoading && 'opacity-50 cursor-wait'
                                ]">
                            {{ pushLoading ? '...' : (pushSubscribed ? 'Отключить' : 'Включить') }}
                        </button>
                    </div>

                    <div v-if="pushSubscribed" class="space-y-2">
                        <button @click="sendTestPush"
                                :disabled="testingPush"
                                class="w-full py-2 text-sm text-orange-600 hover:bg-orange-50 rounded-lg transition">
                            {{ testingPush ? 'Отправка...' : 'Отправить тестовое уведомление' }}
                        </button>

                        <div v-if="pushDevices.length > 0" class="mt-3 pt-3 border-t">
                            <div class="text-sm text-gray-500 mb-2">Подписанные устройства:</div>
                            <div v-for="device in pushDevices" :key="device.id"
                                 class="text-sm py-1 flex justify-between items-center">
                                <span>{{ device.device_info }}</span>
                                <span class="text-xs text-gray-400">{{ device.last_used_at || device.created_at }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Notifications Settings -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="px-4 py-3 bg-gray-50 border-b font-semibold text-gray-700">
                Типы уведомлений
            </div>
            <div class="divide-y">
                <div v-for="(enabled, key) in notificationSettings" :key="key"
                     class="p-4 flex items-center justify-between">
                    <div>
                        <div class="font-medium">{{ getNotificationLabel(key) }}</div>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" v-model="notificationSettings[key]"
                               @change="saveNotificationSettings"
                               class="sr-only peer">
                        <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:bg-orange-500 after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-full"></div>
                    </label>
                </div>
            </div>
        </div>

        <!-- Logout -->
        <button @click="$emit('logout')"
                class="w-full p-4 bg-white rounded-xl shadow-sm text-red-600 font-medium hover:bg-red-50 transition">
            Выйти из аккаунта
        </button>

        <!-- PIN Modal -->
        <div v-if="showPinModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4"
             @click.self="showPinModal = false">
            <div class="bg-white rounded-2xl w-full max-w-sm p-6">
                <h3 class="text-lg font-semibold mb-4">{{ user?.has_pin ? 'Изменить PIN' : 'Установить PIN' }}</h3>
                <form @submit.prevent="changePin" class="space-y-4">
                    <div v-if="user?.has_pin">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Текущий PIN</label>
                        <input v-model="pinForm.current_pin" type="password" maxlength="4" inputmode="numeric"
                               class="w-full px-4 py-3 border rounded-xl text-center text-xl tracking-widest"
                               placeholder="****" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Новый PIN</label>
                        <input v-model="pinForm.new_pin" type="password" maxlength="4" inputmode="numeric"
                               class="w-full px-4 py-3 border rounded-xl text-center text-xl tracking-widest"
                               placeholder="****" />
                    </div>
                    <button type="submit" :disabled="saving"
                            class="w-full py-3 bg-orange-500 text-white rounded-xl font-semibold hover:bg-orange-600 transition disabled:opacity-50">
                        {{ saving ? 'Сохранение...' : 'Сохранить' }}
                    </button>
                </form>
            </div>
        </div>

        <!-- Password Modal -->
        <div v-if="showPasswordModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4"
             @click.self="showPasswordModal = false">
            <div class="bg-white rounded-2xl w-full max-w-sm p-6">
                <h3 class="text-lg font-semibold mb-4">{{ user?.has_password ? 'Изменить пароль' : 'Установить пароль' }}</h3>
                <form @submit.prevent="changePassword" class="space-y-4">
                    <div v-if="user?.has_password">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Текущий пароль</label>
                        <input v-model="passwordForm.current_password" type="password"
                               class="w-full px-4 py-3 border rounded-xl" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Новый пароль</label>
                        <input v-model="passwordForm.new_password" type="password"
                               class="w-full px-4 py-3 border rounded-xl" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Подтвердите пароль</label>
                        <input v-model="passwordForm.new_password_confirmation" type="password"
                               class="w-full px-4 py-3 border rounded-xl" />
                    </div>
                    <button type="submit" :disabled="saving"
                            class="w-full py-3 bg-orange-500 text-white rounded-xl font-semibold hover:bg-orange-600 transition disabled:opacity-50">
                        {{ saving ? 'Сохранение...' : 'Сохранить' }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted, inject } from 'vue';

const props = defineProps({
    user: Object,
});

const emit = defineEmits(['logout', 'updated']);

const api = inject('api') as any;
const showToast = inject('showToast') as any;

const saving = ref(false);
const showPinModal = ref(false);
const showPasswordModal = ref(false);

const pinForm = reactive<Record<string, any>>({
    current_pin: '',
    new_pin: '',
});

const passwordForm = reactive<Record<string, any>>({
    current_password: '',
    new_password: '',
    new_password_confirmation: '',
});

const notificationSettings = reactive<Record<string, any>>({
    schedule_published: true,
    shift_reminder: true,
    salary_paid: true,
    bonus_received: true,
    penalty_received: true,
});

const notificationLabels = {
    schedule_published: 'Публикация расписания',
    shift_reminder: 'Напоминания о сменах',
    salary_paid: 'Выплата зарплаты',
    bonus_received: 'Получение премии',
    penalty_received: 'Получение штрафа',
};

// Push notifications
const pushSupported = ref('serviceWorker' in navigator && 'PushManager' in window);
const pushPermission = ref(Notification?.permission || 'default');
const pushSubscribed = ref(false);
const pushLoading = ref(false);
const testingPush = ref(false);
const pushDevices = ref<any[]>([]);
const vapidPublicKey = ref<any>(null);

// Device Sessions
const deviceSessions = ref<any[]>([]);
const loadingSessions = ref(false);
const revokingSession = ref<any>(null);
const revokingAll = ref(false);

// Biometric (WebAuthn)
const biometricSupported = ref(window.PublicKeyCredential !== undefined);
const biometricCredentials = ref<any[]>([]);
const biometricLoading = ref(false);
const requireBiometricClock = ref(false);

function getNotificationLabel(key: any) {
    return (notificationLabels as Record<string, string>)[key] || key;
}

async function changePin() {
    if (!pinForm.new_pin || pinForm.new_pin.length !== 4) {
        showToast('PIN должен быть 4 цифры', 'error');
        return;
    }

    saving.value = true;
    try {
        await api('/cabinet/profile/pin', {
            method: 'POST',
            body: JSON.stringify(pinForm),
        });
        showToast('PIN изменён', 'success');
        showPinModal.value = false;
        pinForm.current_pin = '';
        pinForm.new_pin = '';
        emit('updated');
    } catch (e: any) {
        showToast(e.message || 'Ошибка', 'error');
    } finally {
        saving.value = false;
    }
}

async function changePassword() {
    if (!passwordForm.new_password || passwordForm.new_password.length < 6) {
        showToast('Пароль минимум 6 символов', 'error');
        return;
    }
    if (passwordForm.new_password !== passwordForm.new_password_confirmation) {
        showToast('Пароли не совпадают', 'error');
        return;
    }

    saving.value = true;
    try {
        await api('/cabinet/profile/password', {
            method: 'POST',
            body: JSON.stringify(passwordForm),
        });
        showToast('Пароль изменён', 'success');
        showPasswordModal.value = false;
        passwordForm.current_password = '';
        passwordForm.new_password = '';
        passwordForm.new_password_confirmation = '';
        emit('updated');
    } catch (e: any) {
        showToast(e.message || 'Ошибка', 'error');
    } finally {
        saving.value = false;
    }
}

async function saveNotificationSettings() {
    try {
        await api('/cabinet/profile/notifications', {
            method: 'PATCH',
            body: JSON.stringify({ settings: notificationSettings }),
        });
    } catch (e: any) {
        console.error('Failed to save notification settings:', e);
    }
}

// ==================== DEVICE SESSIONS ====================

async function loadDeviceSessions() {
    loadingSessions.value = true;
    try {
        const res = await api('/auth/device-sessions');
        if (res.success) {
            deviceSessions.value = res.data || [];
        }
    } catch (e: any) {
        console.error('Failed to load device sessions:', e);
    } finally {
        loadingSessions.value = false;
    }
}

async function revokeSession(sessionId: any) {
    if (!confirm('Выйти из этого устройства?')) return;

    revokingSession.value = sessionId;
    try {
        await api(`/auth/device-sessions/${sessionId}`, {
            method: 'DELETE',
        });
        showToast('Сессия отозвана', 'success');
        await loadDeviceSessions();
    } catch (e: any) {
        showToast(e.message || 'Ошибка', 'error');
    } finally {
        revokingSession.value = null;
    }
}

async function revokeAllSessions() {
    if (!confirm('Выйти из всех устройств? Вы останетесь авторизованы только на текущем устройстве.')) return;

    revokingAll.value = true;
    try {
        await api('/auth/device-sessions/revoke-all', {
            method: 'POST',
        });
        showToast('Выполнен выход из всех устройств', 'success');
        await loadDeviceSessions();
    } catch (e: any) {
        showToast(e.message || 'Ошибка', 'error');
    } finally {
        revokingAll.value = false;
    }
}

function getDeviceIcon(appType: any) {
    const icons = {
        pos: '🖥️',
        waiter: '📱',
        courier: '🚗',
        kitchen: '👨‍🍳',
        cabinet: '💼',
    };
    return (icons as Record<string, string>)[appType] || '📱';
}

function getAppTypeLabel(appType: any) {
    const labels = {
        pos: 'POS Терминал',
        waiter: 'Приложение официанта',
        courier: 'Приложение курьера',
        kitchen: 'Экран кухни',
        cabinet: 'Личный кабинет',
    };
    return (labels as Record<string, string>)[appType] || appType;
}

// ==================== PUSH NOTIFICATIONS ====================

async function initPush() {
    if (!pushSupported.value) return;

    try {
        // Get VAPID public key
        const keyRes = await api('/cabinet/push/vapid-key');
        if (keyRes.data?.public_key) {
            vapidPublicKey.value = keyRes.data.public_key;
        }

        // Check current subscription
        const registration = await navigator.serviceWorker.ready;
        const subscription = await registration.pushManager.getSubscription();
        pushSubscribed.value = !!subscription;

        // Load devices list
        await loadPushDevices();
    } catch (e: any) {
        console.error('Push init error:', e);
    }
}

async function loadPushDevices() {
    try {
        const res = await api('/cabinet/push/subscriptions');
        pushDevices.value = res.data || [];
    } catch (e: any) {
        console.error('Failed to load push devices:', e);
    }
}

async function togglePushSubscription() {
    if (pushSubscribed.value) {
        await unsubscribePush();
    } else {
        await subscribePush();
    }
}

async function subscribePush() {
    if (!vapidPublicKey.value) {
        showToast('Push-уведомления не настроены на сервере', 'error');
        return;
    }

    pushLoading.value = true;
    try {
        // Request permission
        const permission = await Notification.requestPermission();
        pushPermission.value = permission;

        if (permission !== 'granted') {
            showToast('Разрешите уведомления для подписки', 'warning');
            return;
        }

        // Register service worker if not ready
        let registration = await navigator.serviceWorker.getRegistration('/cabinet-sw.js');
        if (!registration) {
            registration = await navigator.serviceWorker.register('/cabinet-sw.js', { scope: '/cabinet' });
            await navigator.serviceWorker.ready;
        }

        // Subscribe to push
        const subscription = await registration.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: urlBase64ToUint8Array(vapidPublicKey.value),
        });

        // Send subscription to server
        const subJson = subscription.toJSON();
        await api('/cabinet/push/subscribe', {
            method: 'POST',
            body: JSON.stringify({
                endpoint: subJson.endpoint,
                keys: subJson.keys,
            }),
        });

        pushSubscribed.value = true;
        showToast('Push-уведомления включены', 'success');
        await loadPushDevices();

    } catch (e: any) {
        console.error('Push subscribe error:', e);
        showToast('Не удалось подписаться на уведомления', 'error');
    } finally {
        pushLoading.value = false;
    }
}

async function unsubscribePush() {
    pushLoading.value = true;
    try {
        const registration = await navigator.serviceWorker.ready;
        const subscription = await registration.pushManager.getSubscription();

        if (subscription) {
            await subscription.unsubscribe();

            await api('/cabinet/push/unsubscribe', {
                method: 'DELETE',
                body: JSON.stringify({ endpoint: subscription.endpoint }),
            });
        }

        pushSubscribed.value = false;
        showToast('Push-уведомления отключены', 'success');
        await loadPushDevices();

    } catch (e: any) {
        console.error('Push unsubscribe error:', e);
        showToast('Не удалось отписаться', 'error');
    } finally {
        pushLoading.value = false;
    }
}

async function sendTestPush() {
    testingPush.value = true;
    try {
        const res = await api('/cabinet/push/test', { method: 'POST' });
        if (res.success) {
            showToast('Тестовое уведомление отправлено', 'success');
        } else {
            showToast(res.message || 'Ошибка отправки', 'warning');
        }
    } catch (e: any) {
        showToast('Ошибка отправки', 'error');
    } finally {
        testingPush.value = false;
    }
}

function urlBase64ToUint8Array(base64String: any) {
    const padding = '='.repeat((4 - base64String.length % 4) % 4);
    const base64 = (base64String + padding)
        .replace(/-/g, '+')
        .replace(/_/g, '/');
    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);
    for (let i = 0; i < rawData.length; ++i) {
        outputArray[i] = rawData.charCodeAt(i);
    }
    return outputArray;
}

// ==================== BIOMETRIC (WEBAUTHN) ====================

async function initBiometric() {
    if (!biometricSupported.value) return;

    try {
        // Load registered credentials
        const res = await api('/cabinet/biometric/credentials');
        biometricCredentials.value = res.data || [];

        // Load requirement setting
        requireBiometricClock.value = props.user?.require_biometric_clock || false;
    } catch (e: any) {
        console.error('Biometric init error:', e);
    }
}

async function registerBiometric() {
    if (!biometricSupported.value) {
        showToast('WebAuthn не поддерживается', 'error');
        return;
    }

    biometricLoading.value = true;
    try {
        // Get registration options from server
        const optionsRes = await api('/cabinet/biometric/register-options');
        const options = optionsRes.data;

        // Convert base64 strings to ArrayBuffer
        options.challenge = base64ToArrayBuffer(options.challenge);
        options.user.id = base64ToArrayBuffer(options.user.id);
        if (options.excludeCredentials) {
            options.excludeCredentials = options.excludeCredentials.map((cred: any) => ({
                ...cred,
                id: base64ToArrayBuffer(cred.id),
            }));
        }

        // Create credential
        const credential = await navigator.credentials.create({
            publicKey: options,
        }) as PublicKeyCredential | null;

        if (!credential) {
            throw new Error('Не удалось создать учетные данные');
        }

        const attestationResponse = credential.response as AuthenticatorAttestationResponse;

        // Prepare response for server
        const response = {
            id: credential.id,
            rawId: arrayBufferToBase64(credential.rawId),
            type: credential.type,
            response: {
                clientDataJSON: arrayBufferToBase64(attestationResponse.clientDataJSON),
                attestationObject: arrayBufferToBase64(attestationResponse.attestationObject),
            },
        };

        // Determine device name
        let deviceName = 'Устройство';
        const ua = navigator.userAgent.toLowerCase();
        if (ua.includes('iphone') || ua.includes('ipad')) {
            deviceName = 'Apple Touch ID / Face ID';
        } else if (ua.includes('android')) {
            deviceName = 'Android Fingerprint';
        } else if (ua.includes('mac')) {
            deviceName = 'Mac Touch ID';
        } else if (ua.includes('windows')) {
            deviceName = 'Windows Hello';
        }

        // Send to server
        await api('/cabinet/biometric/register', {
            method: 'POST',
            body: JSON.stringify({
                credential: response,
                name: deviceName,
            }),
        });

        showToast('Биометрия успешно настроена', 'success');
        await initBiometric();

    } catch (e: any) {
        console.error('Biometric registration error:', e);
        if (e.name === 'NotAllowedError') {
            showToast('Регистрация отменена пользователем', 'warning');
        } else if (e.name === 'SecurityError') {
            showToast('Ошибка безопасности. Используйте HTTPS.', 'error');
        } else {
            showToast(e.message || 'Не удалось настроить биометрию', 'error');
        }
    } finally {
        biometricLoading.value = false;
    }
}

async function deleteBiometricCredential(credentialId: any) {
    if (!confirm('Удалить это устройство?')) return;

    try {
        await api(`/cabinet/biometric/${credentialId}`, {
            method: 'DELETE',
        });
        showToast('Устройство удалено', 'success');
        await initBiometric();
    } catch (e: any) {
        showToast(e.message || 'Ошибка удаления', 'error');
    }
}

async function toggleBiometricRequirement() {
    try {
        await api('/cabinet/biometric/toggle-requirement', {
            method: 'POST',
            body: JSON.stringify({
                require: requireBiometricClock.value,
            }),
        });
        showToast(
            requireBiometricClock.value
                ? 'Биометрия будет требоваться для учета времени'
                : 'Биометрия отключена для учета времени',
            'success'
        );
    } catch (e: any) {
        requireBiometricClock.value = !requireBiometricClock.value;
        showToast(e.message || 'Ошибка сохранения', 'error');
    }
}

function base64ToArrayBuffer(base64: any) {
    const binaryString = window.atob(base64.replace(/-/g, '+').replace(/_/g, '/'));
    const bytes = new Uint8Array(binaryString.length);
    for (let i = 0; i < binaryString.length; i++) {
        bytes[i] = binaryString.charCodeAt(i);
    }
    return bytes.buffer;
}

function arrayBufferToBase64(buffer: any) {
    const bytes = new Uint8Array(buffer);
    let binary = '';
    for (let i = 0; i < bytes.byteLength; i++) {
        binary += String.fromCharCode(bytes[i]);
    }
    return window.btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, '');
}

function formatDate(dateString: any) {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleDateString('ru-RU', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

onMounted(() => {
    if (props.user?.notification_settings) {
        Object.assign(notificationSettings, props.user.notification_settings);
    }
    loadDeviceSessions();
    initPush();
    initBiometric();
});
</script>
