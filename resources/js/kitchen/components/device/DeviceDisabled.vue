<template>
    <div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-gray-900 to-red-900/30">
        <div class="text-center max-w-lg">
            <div class="text-8xl mb-6">🚫</div>
            <h1 class="text-3xl font-bold mb-4 text-red-400">Устройство отключено</h1>
            <p class="text-xl text-gray-400 mb-8">Обратитесь к администратору</p>

            <div class="bg-gray-800 rounded-2xl p-6">
                <p class="text-sm text-gray-500 mb-2">ID устройства:</p>
                <p class="font-mono text-lg text-gray-400 break-all">{{ deviceId }}</p>
            </div>

            <button
                @click="$emit('retry')"
                class="mt-6 px-6 py-3 bg-gray-700 hover:bg-gray-600 rounded-xl font-medium transition"
            >
                Повторить попытку
            </button>
        </div>
    </div>
</template>

<script setup lang="ts">
/**
 * Device Disabled Component
 *
 * Displays when device has been disabled by admin.
 */

defineProps({
    deviceId: {
        type: String,
        required: true,
    },
});

defineEmits(['retry']);
</script>
