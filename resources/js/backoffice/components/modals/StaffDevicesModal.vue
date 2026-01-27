<script setup>
/**
 * Модальное окно управления доступом сотрудника к устройствам биометрии
 *
 * Использование:
 * <StaffDevicesModal v-model="showModal" :user-id="selectedUserId" @updated="reload" />
 */
import { ref, watch, computed, onUnmounted } from 'vue';
import { useBackofficeStore } from '../../stores/backoffice';
import { formatDateTime } from '../../../utils/timezone';

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    userId: { type: [Number, String], required: true },
});

const emit = defineEmits(['update:modelValue', 'updated']);

const store = useBackofficeStore();

// State
const loading = ref(false);
const actionLoading = ref({});
const user = ref(null);
const devices = ref([]);
const biometricStatus = ref(null);
const showDeviceIdPrompt = ref(false);
const pendingDeviceId = ref(null);
const customDeviceUserId = ref('');
const editingDeviceUserId = ref(null); // For editing existing connection
let pollingInterval = null;

// Computed
const show = computed({
    get: () => props.modelValue,
    set: (val) => emit('update:modelValue', val)
});

const hasDevicesNeedingEnrollment = computed(() => {
    return devices.value.some(d =>
        d.access.granted &&
        (!d.access.is_synced || d.access.needs_enrollment)
    );
});

// Watch
watch(() => props.modelValue, async (val) => {
    if (val && props.userId) {
        await loadDevices();
        startPolling();
    } else {
        stopPolling();
    }
});

onUnmounted(() => {
    stopPolling();
});

// Methods
async function loadDevices() {
    if (!props.userId) return;

    loading.value = true;
    try {
        const [devicesRes, statusRes] = await Promise.all([
            store.api(`/backoffice/attendance/users/${props.userId}/devices`),
            store.api(`/backoffice/attendance/users/${props.userId}/biometric-status`),
        ]);

        if (devicesRes.success) {
            user.value = devicesRes.data.user;
            devices.value = devicesRes.data.devices || [];
        }

        if (statusRes.success) {
            biometricStatus.value = statusRes.data;
        }
    } catch (e) {
        console.error('Error loading devices:', e);
        store.showToast('Ошибка загрузки устройств', 'error');
    } finally {
        loading.value = false;
    }
}

function promptForDeviceId(deviceId) {
    pendingDeviceId.value = deviceId;
    customDeviceUserId.value = '';
    showDeviceIdPrompt.value = true;
}

function cancelDeviceIdPrompt() {
    showDeviceIdPrompt.value = false;
    pendingDeviceId.value = null;
    customDeviceUserId.value = '';
    editingDeviceUserId.value = null;
}

async function confirmGrantAccess() {
    if (!pendingDeviceId.value) return;

    const deviceUserId = customDeviceUserId.value ? String(customDeviceUserId.value).trim() : null;
    await grantAccess(pendingDeviceId.value, deviceUserId);
    cancelDeviceIdPrompt();
}

async function grantAccess(deviceId, deviceUserId = null) {
    actionLoading.value[deviceId] = true;
    try {
        const body = { user_id: props.userId };
        if (deviceUserId) {
            body.device_user_id = parseInt(deviceUserId, 10);
        }

        const res = await store.api(`/backoffice/attendance/devices/${deviceId}/device-users`, {
            method: 'POST',
            body: JSON.stringify(body)
        });

        if (res.success) {
            store.showToast(res.message || 'Доступ предоставлен', 'success');
            if (res.warning) {
                store.showToast(res.warning, 'warning');
            }
            await loadDevices();
            emit('updated');
        } else {
            store.showToast(res.message || 'Ошибка', 'error');
        }
    } catch (e) {
        console.error('Error granting access:', e);
        store.showToast('Ошибка сети', 'error');
    } finally {
        actionLoading.value[deviceId] = false;
    }
}

function startEditDeviceUserId(deviceId, currentDeviceUserId) {
    pendingDeviceId.value = deviceId;
    customDeviceUserId.value = currentDeviceUserId;
    editingDeviceUserId.value = currentDeviceUserId;
    showDeviceIdPrompt.value = true;
}

async function updateDeviceUserId() {
    if (!pendingDeviceId.value || !editingDeviceUserId.value) return;

    const newDeviceUserId = customDeviceUserId.value ? String(customDeviceUserId.value).trim() : '';
    if (!newDeviceUserId) {
        store.showToast('Введите ID сотрудника', 'error');
        return;
    }

    actionLoading.value[pendingDeviceId.value] = true;
    try {
        const res = await store.api(`/backoffice/attendance/devices/${pendingDeviceId.value}/device-users/${editingDeviceUserId.value}`, {
            method: 'PATCH',
            body: JSON.stringify({ device_user_id: parseInt(newDeviceUserId, 10) })
        });

        if (res.success) {
            store.showToast('ID обновлён', 'success');
            await loadDevices();
            emit('updated');
        } else {
            store.showToast(res.message || 'Ошибка', 'error');
        }
    } catch (e) {
        console.error('Error updating device user ID:', e);
        store.showToast('Ошибка сети', 'error');
    } finally {
        actionLoading.value[pendingDeviceId.value] = false;
        cancelDeviceIdPrompt();
    }
}

async function revokeAccess(deviceId, deviceUserId) {
    if (!confirm('Отозвать доступ к устройству?')) return;

    actionLoading.value[deviceId] = true;
    try {
        const res = await store.api(`/backoffice/attendance/devices/${deviceId}/device-users/${deviceUserId}`, {
            method: 'DELETE'
        });

        if (res.success) {
            store.showToast('Доступ отозван', 'success');
            if (res.warning) {
                // Показываем предупреждение если устройство не ответило
                setTimeout(() => store.showToast(res.warning, 'warning'), 500);
            }
            await loadDevices();
            emit('updated');
        } else {
            store.showToast(res.message || 'Ошибка', 'error');
        }
    } catch (e) {
        console.error('Error revoking access:', e);
        store.showToast('Ошибка сети', 'error');
    } finally {
        actionLoading.value[deviceId] = false;
    }
}

function startPolling() {
    stopPolling();
    pollingInterval = setInterval(() => {
        if (hasDevicesNeedingEnrollment.value) {
            loadDevices();
        }
    }, 5000);
}

function stopPolling() {
    if (pollingInterval) {
        clearInterval(pollingInterval);
        pollingInterval = null;
    }
}

function close() {
    show.value = false;
}

function getInitials(name) {
    if (!name) return '?';
    return name.split(' ').map(w => w[0]).join('').substring(0, 2).toUpperCase();
}

function getOverallStatusLabel(status) {
    const labels = {
        none: 'Нет доступа',
        pending: 'Синхронизация...',
        synced: 'Синхронизирован',
        needs_enrollment: 'Требуется Face ID',
        enrolled: 'Face ID активен',
        error: 'Ошибка',
    };
    return labels[status] || status;
}

function getOverallStatusClass(status) {
    const classes = {
        none: 'bg-gray-100 text-gray-600',
        pending: 'bg-amber-100 text-amber-700',
        synced: 'bg-blue-100 text-blue-700',
        needs_enrollment: 'bg-orange-100 text-orange-700',
        enrolled: 'bg-green-100 text-green-700',
        error: 'bg-red-100 text-red-700',
    };
    return classes[status] || 'bg-gray-100 text-gray-600';
}

function getSyncStatusClass(access) {
    if (access.sync_error) return 'bg-blue-100 text-blue-700'; // Manual registration needed
    if (!access.is_synced) return 'bg-amber-100 text-amber-700';
    return 'bg-green-100 text-green-700';
}

function getSyncStatusLabel(access) {
    if (access.sync_error) return 'Ручная регистрация';
    if (!access.is_synced) return 'Ожидает синхронизации';
    return 'Синхронизирован';
}

function getFaceStatusClass(status) {
    const classes = {
        none: 'bg-gray-100 text-gray-500',
        pending: 'bg-amber-100 text-amber-700',
        enrolled: 'bg-green-100 text-green-700',
        failed: 'bg-red-100 text-red-700',
    };
    return classes[status] || 'bg-gray-100 text-gray-500';
}

function getFaceStatusLabel(status) {
    const labels = {
        none: 'Не настроен',
        pending: 'Ожидает регистрации',
        enrolled: 'Зарегистрирован',
        failed: 'Ошибка',
    };
    return labels[status] || status;
}

function formatDate(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleDateString('ru-RU', {
        day: 'numeric',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
    });
}
</script>

<template>
    <Teleport to="body">
        <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="close">
            <div class="bg-white rounded-2xl w-[700px] max-h-[90vh] overflow-hidden shadow-2xl">
                <!-- Header -->
                <div class="p-6 border-b flex items-center justify-between bg-gradient-to-r from-blue-50 to-white">
                    <div class="flex items-center gap-4">
                        <div v-if="user" class="w-12 h-12 rounded-xl bg-blue-500 text-white flex items-center justify-center text-lg font-bold">
                            {{ getInitials(user.name) }}
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold">Доступ к устройствам</h3>
                            <p v-if="user" class="text-sm text-gray-500">{{ user.name }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <span v-if="biometricStatus"
                              :class="['px-3 py-1 rounded-full text-sm font-medium', getOverallStatusClass(biometricStatus.overall_status)]">
                            {{ getOverallStatusLabel(biometricStatus.overall_status) }}
                        </span>
                        <button @click="close" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
                    </div>
                </div>

                <!-- Content -->
                <div class="p-6 overflow-y-auto max-h-[65vh]">
                    <!-- Loading -->
                    <div v-if="loading" class="text-center py-8">
                        <svg class="w-8 h-8 animate-spin mx-auto text-blue-500" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        <p class="mt-2 text-gray-500">Загрузка устройств...</p>
                    </div>

                    <!-- Devices List -->
                    <div v-else-if="devices.length > 0" class="space-y-4">
                        <div v-for="item in devices" :key="item.device.id"
                             :class="['border rounded-xl p-4 transition', item.access.granted ? 'border-blue-300 bg-blue-50/30' : 'border-gray-200']">

                            <!-- Device Header -->
                            <div class="flex items-start justify-between mb-3">
                                <div class="flex items-center gap-3">
                                    <div :class="['w-10 h-10 rounded-lg flex items-center justify-center', item.device.is_online ? 'bg-green-100 text-green-600' : 'bg-gray-100 text-gray-400']">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <rect x="2" y="3" width="20" height="14" rx="2" ry="2" stroke-width="2"></rect>
                                            <line x1="8" y1="21" x2="16" y2="21" stroke-width="2"></line>
                                            <line x1="12" y1="17" x2="12" y2="21" stroke-width="2"></line>
                                        </svg>
                                    </div>
                                    <div>
                                        <h4 class="font-medium text-gray-900">{{ item.device.name }}</h4>
                                        <div class="flex items-center gap-2 text-xs">
                                            <span :class="['px-1.5 py-0.5 rounded', item.device.is_online ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500']">
                                                {{ item.device.is_online ? 'Онлайн' : 'Оффлайн' }}
                                            </span>
                                            <span class="text-gray-400">{{ item.device.type_label }}</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Access Button -->
                                <button v-if="!item.access.granted"
                                        @click="promptForDeviceId(item.device.id)"
                                        :disabled="actionLoading[item.device.id]"
                                        class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition disabled:opacity-50 text-sm font-medium">
                                    {{ actionLoading[item.device.id] ? 'Обработка...' : 'Дать доступ' }}
                                </button>
                            </div>

                            <!-- Access Details -->
                            <div v-if="item.access.granted" class="space-y-3">
                                <!-- Status Row -->
                                <div class="flex flex-wrap gap-2">
                                    <span :class="['px-2 py-1 rounded text-xs font-medium', getSyncStatusClass(item.access)]">
                                        {{ getSyncStatusLabel(item.access) }}
                                    </span>
                                    <span :class="['px-2 py-1 rounded text-xs font-medium', getFaceStatusClass(item.access.face_status)]">
                                        Face ID: {{ getFaceStatusLabel(item.access.face_status) }}
                                    </span>
                                    <span v-if="item.access.fingerprint_status !== 'none'"
                                          :class="['px-2 py-1 rounded text-xs font-medium', getFaceStatusClass(item.access.fingerprint_status)]">
                                        Отпечаток: {{ getFaceStatusLabel(item.access.fingerprint_status) }}
                                    </span>
                                </div>

                                <!-- Manual Registration Instructions -->
                                <div v-if="item.access.sync_error || (!item.access.is_synced && item.access.face_status === 'none')"
                                     class="flex gap-3 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                                    <div class="text-blue-500 flex-shrink-0">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                                        </svg>
                                    </div>
                                    <div class="text-sm flex-1">
                                        <p class="font-semibold text-blue-800 text-base">Добавьте сотрудника на устройство</p>
                                        <div class="mt-3 p-3 bg-white rounded-lg border border-blue-200">
                                            <div class="flex items-center gap-3">
                                                <span class="text-gray-600">ID на устройстве:</span>
                                                <code class="bg-blue-600 text-white px-3 py-1 rounded font-mono text-lg font-bold">{{ item.access.device_user_id }}</code>
                                                <button @click="startEditDeviceUserId(item.device.id, item.access.device_user_id)"
                                                        class="text-xs text-blue-600 hover:text-blue-800 underline">
                                                    изменить
                                                </button>
                                            </div>
                                            <div class="flex items-center gap-3 mt-2">
                                                <span class="text-gray-600">Имя:</span>
                                                <span class="font-medium">{{ user?.name }}</span>
                                            </div>
                                        </div>
                                        <p class="text-blue-600 mt-3">После регистрации Face ID на устройстве, статус обновится автоматически при первой отметке.</p>
                                    </div>
                                </div>

                                <!-- Enrollment Instructions (when synced but no face) -->
                                <div v-else-if="item.access.needs_enrollment"
                                     class="flex gap-3 p-3 bg-orange-50 border border-orange-200 rounded-lg">
                                    <div class="text-orange-500 flex-shrink-0">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                        </svg>
                                    </div>
                                    <div class="text-sm">
                                        <p class="font-medium text-orange-700">Требуется регистрация Face ID</p>
                                        <p class="text-orange-600 mt-1">
                                            ID сотрудника на устройстве: <code class="bg-orange-100 px-1.5 py-0.5 rounded font-mono font-bold">{{ item.access.device_user_id }}</code>
                                        </p>
                                        <p class="text-orange-600 mt-1">Попросите сотрудника подойти к устройству и зарегистрировать лицо.</p>
                                    </div>
                                </div>

                                <!-- Face Enrolled Success -->
                                <div v-if="item.access.face_status === 'enrolled'"
                                     class="flex items-center gap-2 p-3 bg-green-50 border border-green-200 rounded-lg text-green-700 text-sm">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    <span>Face ID зарегистрирован {{ formatDateTime(item.access.face_enrolled_at) }}</span>
                                </div>


                                <!-- Actions -->
                                <div class="flex justify-end">
                                    <button @click="revokeAccess(item.device.id, item.access.device_user_id)"
                                            :disabled="actionLoading[item.device.id]"
                                            class="px-3 py-1.5 text-sm text-red-600 hover:bg-red-50 rounded-lg transition">
                                        Отозвать доступ
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Empty State -->
                    <div v-else class="text-center py-8 text-gray-500">
                        <svg class="w-12 h-12 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <rect x="2" y="3" width="20" height="14" rx="2" ry="2" stroke-width="2"></rect>
                            <line x1="8" y1="21" x2="16" y2="21" stroke-width="2"></line>
                            <line x1="12" y1="17" x2="12" y2="21" stroke-width="2"></line>
                        </svg>
                        <p>Нет доступных устройств</p>
                        <p class="text-sm mt-1">Устройства настраиваются в разделе "Учёт времени" &rarr; "Устройства"</p>
                    </div>
                </div>

                <!-- Footer -->
                <div class="p-4 border-t bg-gray-50 flex justify-between items-center">
                    <div v-if="biometricStatus" class="text-sm text-gray-500">
                        Устройств: {{ biometricStatus.stats.devices_with_access }} / {{ biometricStatus.stats.total_devices }}
                        <span v-if="biometricStatus.stats.face_enrolled > 0" class="ml-2">
                            | Face ID: {{ biometricStatus.stats.face_enrolled }}
                        </span>
                    </div>
                    <button @click="close" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded-lg transition">
                        Закрыть
                    </button>
                </div>
            </div>

            <!-- Device ID Prompt Modal -->
            <div v-if="showDeviceIdPrompt" class="fixed inset-0 z-[60] flex items-center justify-center bg-black/50" @click.self="cancelDeviceIdPrompt">
                <div class="bg-white rounded-xl w-[400px] shadow-2xl p-6">
                    <h4 class="text-lg font-semibold mb-4">
                        {{ editingDeviceUserId ? 'Изменить ID на устройстве' : 'Привязка к устройству' }}
                    </h4>

                    <div v-if="!editingDeviceUserId" class="mb-4 p-3 bg-blue-50 border border-blue-200 rounded-lg text-sm text-blue-700">
                        <p class="font-medium mb-2">Сотрудник уже есть на устройстве?</p>
                        <p>Если да — введите его ID с устройства для синхронизации.</p>
                        <p class="mt-1">Если нет — оставьте поле пустым, система сгенерирует новый ID.</p>
                    </div>

                    <div v-else class="mb-4 p-3 bg-amber-50 border border-amber-200 rounded-lg text-sm text-amber-700">
                        <p>Укажите ID сотрудника на устройстве для синхронизации данных.</p>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">ID сотрудника на устройстве</label>
                        <input
                            v-model="customDeviceUserId"
                            type="number"
                            min="1"
                            max="65535"
                            :placeholder="editingDeviceUserId ? 'Введите ID' : 'Оставьте пустым для автоматического ID'"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            @keyup.enter="editingDeviceUserId ? updateDeviceUserId() : confirmGrantAccess()"
                        />
                    </div>

                    <div class="flex gap-3 justify-end">
                        <button @click="cancelDeviceIdPrompt" class="px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-lg transition">
                            Отмена
                        </button>
                        <button
                            @click="editingDeviceUserId ? updateDeviceUserId() : confirmGrantAccess()"
                            class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition">
                            {{ editingDeviceUserId ? 'Сохранить' : 'Дать доступ' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>
