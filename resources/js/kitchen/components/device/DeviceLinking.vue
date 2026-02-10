<template>
    <div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-gray-900 to-gray-800">
        <div class="text-center max-w-lg">
            <div class="text-8xl mb-6">🔑</div>
            <h1 class="text-3xl font-bold mb-4">Привязка устройства</h1>
            <p class="text-xl text-gray-400 mb-8">Введите 6-значный код из Бэк-офиса</p>

            <!-- Code Input -->
            <div class="flex justify-center gap-2 mb-6">
                <input
                    v-for="i in 6"
                    :key="i"
                    :ref="el => setInputRef(i - 1, el)"
                    type="text"
                    inputmode="numeric"
                    maxlength="1"
                    v-model="linkingCodeDigits[i - 1]"
                    @input="onCodeDigitInput(i - 1, $event)"
                    @keydown="onCodeDigitKeydown(i - 1, $event)"
                    @paste="onCodePaste"
                    class="w-14 h-16 text-center text-3xl font-bold bg-gray-800 border-2 border-gray-600 rounded-xl focus:border-blue-500 focus:outline-none transition"
                />
            </div>

            <!-- Error Message -->
            <p v-if="linkingError" class="text-red-400 mb-4">{{ linkingError }}</p>

            <!-- Submit Button -->
            <button
                @click="submitLinkingCode"
                :disabled="!isLinkingCodeComplete() || isLinking"
                class="px-8 py-4 bg-blue-600 hover:bg-blue-500 disabled:bg-gray-700 disabled:text-gray-500 rounded-xl font-medium text-lg transition flex items-center gap-2 mx-auto"
            >
                <span v-if="isLinking" class="animate-spin">⏳</span>
                <span>{{ isLinking ? 'Привязка...' : 'Привязать' }}</span>
            </button>

            <!-- Instructions -->
            <div class="mt-8 bg-gray-800/50 rounded-xl p-4 text-left">
                <p class="text-sm text-gray-400 mb-2">Как получить код:</p>
                <ol class="text-sm text-gray-500 list-decimal list-inside space-y-1">
                    <li>Откройте Бэк-офис → Настройки → Устройства кухни</li>
                    <li>Добавьте новое устройство или найдите существующее</li>
                    <li>Скопируйте код привязки и введите его здесь</li>
                </ol>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
/**
 * Device Linking Component
 *
 * Displays linking code input for connecting
 * a kitchen display device to the system.
 */

import { useKitchenDevice } from '../../composables/useKitchenDevice.js';

const {
    linkingCodeDigits,
    codeInputRefs,
    isLinking,
    linkingError,
    isLinkingCodeComplete,
    onCodeDigitInput,
    onCodeDigitKeydown,
    onCodePaste,
    submitLinkingCode,
} = useKitchenDevice({ autoInit: false, autoPoll: false });

/**
 * Set input ref at index
 */
function setInputRef(index: any, el: any) {
    codeInputRefs.value[index] = el;
}
</script>
