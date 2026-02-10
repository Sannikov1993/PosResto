<template>
    <div class="bg-slate-800/50 rounded-2xl p-10 shadow-xl border border-slate-700/50 text-center">
        <!-- Success icon -->
        <div class="w-20 h-20 bg-green-500/10 rounded-full flex items-center justify-center mx-auto mb-6 animate-bounce-once">
            <svg class="w-10 h-10 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
        </div>

        <h2 class="text-2xl font-bold text-white mb-3">Регистрация успешна!</h2>
        <p class="text-gray-400 text-lg mb-6">
            Добро пожаловать в команду, <span class="text-white font-medium">{{ user.name }}</span>!
        </p>

        <!-- Info box -->
        <div class="bg-amber-500/10 border border-amber-500/30 rounded-xl px-5 py-4 mb-6">
            <div class="flex items-center justify-center gap-2">
                <svg class="w-5 h-5 text-amber-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <p class="text-amber-300 text-sm">Не забудьте установить PIN-код в личном кабинете для быстрого входа</p>
            </div>
        </div>

        <p class="text-purple-400 text-sm flex items-center justify-center gap-2">
            <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
            </svg>
            Перенаправление в личный кабинет...
        </p>
    </div>
</template>

<script setup lang="ts">
import type { PropType } from 'vue';
defineProps({
    user: {
        type: Object as PropType<Record<string, any>>,
        required: true,
    },
})
</script>

<style scoped>
.animate-bounce-once {
    animation: bounceOnce 0.6s ease;
}

@keyframes bounceOnce {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-20px); }
}
</style>
