<template>
    <div class="bg-slate-800/50 backdrop-blur rounded-2xl p-8 border border-slate-700/50 text-center">
        <!-- Success Icon -->
        <div class="w-20 h-20 mx-auto mb-6 bg-green-500/20 rounded-full flex items-center justify-center">
            <svg class="w-10 h-10 text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
            </svg>
        </div>

        <h2 class="text-2xl font-bold text-white mb-2">Добро пожаловать!</h2>
        <p class="text-gray-400 mb-6">Ваш аккаунт успешно создан</p>

        <!-- Account Info -->
        <div class="bg-slate-900/50 rounded-xl p-4 mb-6 text-left">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 bg-orange-500/20 rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5 text-orange-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                    </svg>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Организация</p>
                    <p class="text-white font-medium">{{ data.tenant?.name }}</p>
                </div>
            </div>
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 bg-blue-500/20 rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Владелец</p>
                    <p class="text-white font-medium">{{ data.user?.name }}</p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-purple-500/20 rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5 text-purple-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Пробный период</p>
                    <p class="text-white font-medium">{{ trialDays }} дней</p>
                </div>
            </div>
        </div>

        <!-- Redirect notice -->
        <div class="flex items-center justify-center gap-2 text-gray-400">
            <svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span>Переход в панель управления...</span>
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed, PropType } from 'vue'

const props = defineProps({
    data: {
        type: Object as PropType<Record<string, any>>,
        required: true
    }
})

const trialDays = computed(() => {
    if (!props.data.tenant?.trial_ends_at) return 14
    const end = new Date(props.data.tenant.trial_ends_at)
    const now = new Date()
    return Math.max(0, Math.ceil((Number(end) - Number(now)) / (1000 * 60 * 60 * 24)))
})
</script>
