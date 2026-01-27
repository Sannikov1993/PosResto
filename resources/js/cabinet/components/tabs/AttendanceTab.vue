<template>
    <div class="space-y-4">
        <!-- Status Card -->
        <div class="bg-white rounded-xl p-4 shadow-sm">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-semibold text-gray-900">Учёт рабочего времени</h3>
                <span :class="['px-3 py-1 rounded-full text-xs font-medium', statusBadgeClass]">
                    {{ statusLabel }}
                </span>
            </div>

            <!-- Mode Info -->
            <div v-if="status" class="text-sm text-gray-600 mb-4">
                <template v-if="status.attendance_mode === 'disabled'">
                    Свободный режим - отметка не требуется
                </template>
                <template v-else-if="status.attendance_mode === 'device_only'">
                    Отметка только через терминал в ресторане
                </template>
                <template v-else-if="status.attendance_mode === 'qr_only'">
                    Отметка только через QR-код
                </template>
                <template v-else-if="status.attendance_mode === 'device_or_qr'">
                    Отметка через терминал или QR-код
                </template>
            </div>

            <!-- Today's Sessions -->
            <div v-if="status?.today_sessions?.length" class="mb-4">
                <p class="text-xs text-gray-500 mb-2">Сегодня</p>
                <div v-for="(session, idx) in status.today_sessions" :key="idx"
                     class="flex items-center justify-between py-2 border-b border-gray-100 last:border-0">
                    <div class="flex items-center gap-2">
                        <span class="text-green-500">⏺</span>
                        <span>{{ formatTime(session.clock_in) }}</span>
                    </div>
                    <div v-if="session.clock_out" class="flex items-center gap-2">
                        <span>{{ formatTime(session.clock_out) }}</span>
                        <span class="text-red-500">⏹</span>
                    </div>
                    <div v-else class="text-orange-500 text-sm">на смене</div>
                </div>
            </div>

            <!-- QR Scanner Button -->
            <button v-if="status?.qr_enabled && !status.is_clocked_in"
                    @click="openScanner"
                    class="w-full py-3 bg-green-500 text-white rounded-xl font-medium flex items-center justify-center gap-2 hover:bg-green-600 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/>
                </svg>
                Сканировать QR для начала смены
            </button>

            <button v-else-if="status?.qr_enabled && status.is_clocked_in"
                    @click="openScanner"
                    class="w-full py-3 bg-red-500 text-white rounded-xl font-medium flex items-center justify-center gap-2 hover:bg-red-600 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/>
                </svg>
                Сканировать QR для завершения смены
            </button>
        </div>

        <!-- History -->
        <div class="bg-white rounded-xl p-4 shadow-sm">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-semibold text-gray-900">История отметок</h3>
                <button @click="loadHistory" class="text-orange-500 text-sm">Обновить</button>
            </div>

            <div v-if="loading" class="py-8 text-center text-gray-500">
                <div class="w-8 h-8 border-2 border-orange-500 border-t-transparent rounded-full animate-spin mx-auto mb-2"></div>
                Загрузка...
            </div>

            <div v-else-if="!history.length" class="py-8 text-center text-gray-400">
                Нет записей
            </div>

            <div v-else class="space-y-2">
                <div v-for="event in history" :key="event.id"
                     class="flex items-center justify-between py-2 border-b border-gray-100 last:border-0">
                    <div class="flex items-center gap-3">
                        <span :class="event.event_type === 'clock_in' ? 'text-green-500' : 'text-red-500'">
                            {{ event.event_type === 'clock_in' ? '⏺' : '⏹' }}
                        </span>
                        <div>
                            <p class="text-sm font-medium">{{ event.event_type === 'clock_in' ? 'Приход' : 'Уход' }}</p>
                            <p class="text-xs text-gray-500">{{ event.source_label }}</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="text-sm">{{ formatDateTime(event.event_time) }}</p>
                        <p v-if="event.device" class="text-xs text-gray-500">{{ event.device }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- QR Scanner Modal -->
        <div v-if="showScanner" class="fixed inset-0 bg-black/90 z-50 flex flex-col">
            <!-- Header -->
            <div class="p-4 flex items-center justify-between text-white">
                <h3 class="font-semibold">Сканирование QR-кода</h3>
                <button @click="closeScanner" class="p-2">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <!-- Camera View -->
            <div class="flex-1 relative">
                <video ref="videoEl" class="w-full h-full object-cover" autoplay playsinline></video>

                <!-- Scan Frame -->
                <div class="absolute inset-0 flex items-center justify-center">
                    <div class="w-64 h-64 border-2 border-white/50 rounded-2xl relative">
                        <div class="absolute -top-1 -left-1 w-8 h-8 border-t-4 border-l-4 border-white rounded-tl-xl"></div>
                        <div class="absolute -top-1 -right-1 w-8 h-8 border-t-4 border-r-4 border-white rounded-tr-xl"></div>
                        <div class="absolute -bottom-1 -left-1 w-8 h-8 border-b-4 border-l-4 border-white rounded-bl-xl"></div>
                        <div class="absolute -bottom-1 -right-1 w-8 h-8 border-b-4 border-r-4 border-white rounded-br-xl"></div>
                    </div>
                </div>

                <!-- Scanning Animation -->
                <div v-if="scanning" class="absolute inset-0 flex items-center justify-center">
                    <div class="w-64 h-0.5 bg-green-400 animate-pulse"></div>
                </div>
            </div>

            <!-- Status / Error -->
            <div class="p-4 text-center">
                <p v-if="scanError" class="text-red-400">{{ scanError }}</p>
                <p v-else-if="scanning" class="text-white/70">Наведите камеру на QR-код</p>
                <p v-else class="text-white/70">Инициализация камеры...</p>
            </div>

            <!-- Geolocation Status -->
            <div v-if="geoStatus" class="px-4 pb-4">
                <div :class="['p-3 rounded-lg text-sm', geoStatus.success ? 'bg-green-500/20 text-green-300' : 'bg-yellow-500/20 text-yellow-300']">
                    {{ geoStatus.message }}
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, inject, watch } from 'vue';

const api = inject('api');
const showToast = inject('showToast');

const status = ref(null);
const history = ref([]);
const loading = ref(false);

// Scanner
const showScanner = ref(false);
const videoEl = ref(null);
const scanning = ref(false);
const scanError = ref(null);
const geoStatus = ref(null);

let stream = null;
let animationFrame = null;
let jsQR = null;

// Load status
async function loadStatus() {
    try {
        const res = await api('/cabinet/attendance/status');
        // Handle both response formats: { success, data } or direct data
        status.value = res?.data ?? res;
    } catch (e) {
        console.error('Failed to load attendance status:', e);
    }
}

// Load history
async function loadHistory() {
    loading.value = true;
    try {
        const res = await api('/cabinet/attendance/history');
        // Handle both response formats: { success, data } or direct data
        history.value = res?.data ?? res ?? [];
    } catch (e) {
        console.error('Failed to load attendance history:', e);
    } finally {
        loading.value = false;
    }
}

// Open scanner
async function openScanner() {
    showScanner.value = true;
    scanError.value = null;
    geoStatus.value = null;

    // Load jsQR from CDN if not loaded
    if (!jsQR) {
        try {
            await loadJsQR();
        } catch (e) {
            scanError.value = 'Не удалось загрузить библиотеку сканирования';
            return;
        }
    }

    // Get geolocation if required
    if (status.value?.require_geolocation) {
        getGeolocation();
    }

    // Start camera after modal is shown
    setTimeout(startCamera, 100);
}

// Close scanner
function closeScanner() {
    showScanner.value = false;
    stopCamera();
}

// Load jsQR library
function loadJsQR() {
    return new Promise((resolve, reject) => {
        if (window.jsQR) {
            jsQR = window.jsQR;
            resolve();
            return;
        }

        const script = document.createElement('script');
        script.src = 'https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js';
        script.onload = () => {
            jsQR = window.jsQR;
            resolve();
        };
        script.onerror = reject;
        document.head.appendChild(script);
    });
}

// Start camera
async function startCamera() {
    try {
        stream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: 'environment' }
        });

        if (videoEl.value) {
            videoEl.value.srcObject = stream;
            videoEl.value.onloadedmetadata = () => {
                scanning.value = true;
                scanFrame();
            };
        }
    } catch (e) {
        console.error('Camera error:', e);
        if (e.name === 'NotAllowedError') {
            scanError.value = 'Доступ к камере запрещён. Разрешите доступ в настройках браузера.';
        } else if (e.name === 'NotFoundError') {
            scanError.value = 'Камера не найдена';
        } else {
            scanError.value = 'Ошибка доступа к камере';
        }
    }
}

// Stop camera
function stopCamera() {
    scanning.value = false;
    if (animationFrame) {
        cancelAnimationFrame(animationFrame);
        animationFrame = null;
    }
    if (stream) {
        stream.getTracks().forEach(track => track.stop());
        stream = null;
    }
}

// Scan frame
function scanFrame() {
    if (!scanning.value || !videoEl.value || !jsQR) return;

    const video = videoEl.value;
    if (video.readyState !== video.HAVE_ENOUGH_DATA) {
        animationFrame = requestAnimationFrame(scanFrame);
        return;
    }

    const canvas = document.createElement('canvas');
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;

    const ctx = canvas.getContext('2d');
    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

    const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
    const code = jsQR(imageData.data, imageData.width, imageData.height);

    if (code) {
        handleQrCode(code.data);
    } else {
        animationFrame = requestAnimationFrame(scanFrame);
    }
}

// Handle QR code
async function handleQrCode(data) {
    scanning.value = false;
    stopCamera();

    // Extract token from URL or use raw data
    let token = data;
    try {
        const url = new URL(data);
        token = url.searchParams.get('token') || data;
    } catch (e) {
        // Not a URL, use raw data
    }

    // Determine action (clock in or out)
    const action = status.value?.is_clocked_in ? 'clock-out' : 'clock-in';
    const endpoint = `/cabinet/attendance/qr/${action}`;

    try {
        const payload = { qr_token: token };

        // Add geolocation if available
        if (geoStatus.value?.coords) {
            payload.latitude = geoStatus.value.coords.latitude;
            payload.longitude = geoStatus.value.coords.longitude;
        }

        const res = await api(endpoint, {
            method: 'POST',
            body: JSON.stringify(payload),
        });

        // Handle both response formats
        const success = res?.success ?? (res && !res.error);
        if (success) {
            showToast(action === 'clock-in' ? 'Смена начата!' : 'Смена завершена!', 'success');
            closeScanner();
            await loadStatus();
            await loadHistory();
        } else {
            scanError.value = res?.message || res?.error || 'Ошибка отметки';
            // Restart scanning after error
            setTimeout(() => {
                startCamera();
            }, 2000);
        }
    } catch (e) {
        scanError.value = e?.message || 'Ошибка отметки';
        // Restart scanning after error
        setTimeout(() => {
            startCamera();
        }, 2000);
    }
}

// Get geolocation
function getGeolocation() {
    if (!navigator.geolocation) {
        geoStatus.value = { success: false, message: 'Геолокация не поддерживается' };
        return;
    }

    geoStatus.value = { success: false, message: 'Определение местоположения...' };

    navigator.geolocation.getCurrentPosition(
        (position) => {
            geoStatus.value = {
                success: true,
                message: 'Местоположение определено',
                coords: {
                    latitude: position.coords.latitude,
                    longitude: position.coords.longitude,
                },
            };
        },
        (error) => {
            let message = 'Не удалось определить местоположение';
            if (error.code === error.PERMISSION_DENIED) {
                message = 'Доступ к геолокации запрещён';
            }
            geoStatus.value = { success: false, message };
        },
        { enableHighAccuracy: true, timeout: 10000 }
    );
}

// Format helpers
function formatTime(dateStr) {
    if (!dateStr) return '';
    const date = new Date(dateStr);
    return date.toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' });
}

function formatDateTime(dateStr) {
    if (!dateStr) return '';
    const date = new Date(dateStr);
    return date.toLocaleDateString('ru-RU', { day: '2-digit', month: '2-digit' }) + ' ' +
           date.toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' });
}

// Computed
const statusLabel = ref('Загрузка...');
const statusBadgeClass = ref('bg-gray-100 text-gray-600');

watch(status, (val) => {
    if (!val) return;

    if (val.is_clocked_in) {
        statusLabel.value = 'На смене';
        statusBadgeClass.value = 'bg-green-100 text-green-700';
    } else {
        statusLabel.value = 'Не на смене';
        statusBadgeClass.value = 'bg-gray-100 text-gray-600';
    }
}, { immediate: true });

// Check URL for token (when opened from QR scan)
function checkUrlToken() {
    const params = new URLSearchParams(window.location.search);
    const token = params.get('token');

    if (token) {
        // Remove token from URL
        const url = new URL(window.location.href);
        url.searchParams.delete('token');
        window.history.replaceState({}, '', url);

        // Process token
        setTimeout(() => handleQrCode(token), 500);
    }
}

// Lifecycle
onMounted(() => {
    loadStatus();
    loadHistory();
    checkUrlToken();
});

onUnmounted(() => {
    stopCamera();
});
</script>
