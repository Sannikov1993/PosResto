<template>
    <div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-gray-900 to-gray-800">
        <div class="text-center max-w-lg">
            <div class="text-8xl mb-6">📱</div>
            <h1 class="text-3xl font-bold mb-4">Устройство зарегистрировано</h1>
            <p class="text-xl text-gray-400 mb-8">Ожидает настройки в админке</p>

            <div class="bg-gray-800 rounded-2xl p-6 mb-6">
                <p class="text-sm text-gray-500 mb-2">ID устройства:</p>
                <p class="font-mono text-lg text-blue-400 break-all">{{ deviceId }}</p>
            </div>

            <div class="bg-yellow-500/20 rounded-xl p-4 text-yellow-300 text-sm">
                <p class="font-medium mb-2">Для настройки:</p>
                <ol class="text-left list-decimal list-inside space-y-1">
                    <li>Откройте Бэк-офис → Настройки → Устройства кухни</li>
                    <li>Найдите это устройство в списке</li>
                    <li>Назначьте ему цех (станцию)</li>
                </ol>
            </div>

            <button
                @click="$emit('check-status')"
                :disabled="isCheckingStatus"
                class="mt-6 px-6 py-3 bg-blue-600 hover:bg-blue-500 disabled:bg-gray-700 rounded-xl font-medium transition flex items-center gap-2 mx-auto"
            >
                <span v-if="isCheckingStatus" class="animate-spin">⏳</span>
                <span>{{ isCheckingStatus ? 'Проверка...' : 'Проверить статус' }}</span>
            </button>
        </div>
    </div>
</template>

<script setup>
/**
 * Device Pending Component
 *
 * Displays when device is registered but waiting
 * for station assignment in admin panel.
 */

defineProps({
    deviceId: {
        type: String,
        required: true,
    },
    isCheckingStatus: {
        type: Boolean,
        default: false,
    },
});

defineEmits(['check-status']);
</script>
