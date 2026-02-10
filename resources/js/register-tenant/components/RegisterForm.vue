<template>
    <form @submit.prevent="submit" class="bg-slate-800/50 backdrop-blur rounded-2xl p-8 border border-slate-700/50">
        <!-- Organization Name -->
        <div class="mb-5">
            <label class="block text-sm font-medium text-gray-300 mb-2">
                Название организации <span class="text-red-400">*</span>
            </label>
            <input
                v-model="form.organization_name"
                type="text"
                placeholder="Кафе Ромашка"
                class="w-full px-4 py-3 bg-slate-900/50 border border-slate-600 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500 transition"
                :class="{ 'border-red-500': errors.organization_name }"
                required
            />
            <p v-if="errors.organization_name" class="mt-1 text-sm text-red-400">{{ errors.organization_name[0] }}</p>
        </div>

        <!-- Owner Name -->
        <div class="mb-5">
            <label class="block text-sm font-medium text-gray-300 mb-2">
                Ваше имя <span class="text-red-400">*</span>
            </label>
            <input
                v-model="form.owner_name"
                type="text"
                placeholder="Иван Петров"
                class="w-full px-4 py-3 bg-slate-900/50 border border-slate-600 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500 transition"
                :class="{ 'border-red-500': errors.owner_name }"
                required
            />
            <p v-if="errors.owner_name" class="mt-1 text-sm text-red-400">{{ errors.owner_name[0] }}</p>
        </div>

        <!-- Email -->
        <div class="mb-5">
            <label class="block text-sm font-medium text-gray-300 mb-2">
                Email <span class="text-red-400">*</span>
            </label>
            <input
                v-model="form.email"
                type="email"
                placeholder="email@example.com"
                class="w-full px-4 py-3 bg-slate-900/50 border border-slate-600 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500 transition"
                :class="{ 'border-red-500': errors.email }"
                required
            />
            <p v-if="errors.email" class="mt-1 text-sm text-red-400">{{ errors.email[0] }}</p>
        </div>

        <!-- Phone -->
        <div class="mb-5">
            <label class="block text-sm font-medium text-gray-300 mb-2">
                Телефон
            </label>
            <input
                v-model="form.phone"
                type="tel"
                placeholder="+7 (900) 123-45-67"
                class="w-full px-4 py-3 bg-slate-900/50 border border-slate-600 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500 transition"
                :class="{ 'border-red-500': errors.phone }"
            />
            <p v-if="errors.phone" class="mt-1 text-sm text-red-400">{{ errors.phone[0] }}</p>
        </div>

        <!-- Password -->
        <div class="mb-5">
            <label class="block text-sm font-medium text-gray-300 mb-2">
                Пароль <span class="text-red-400">*</span>
            </label>
            <div class="relative">
                <input
                    v-model="form.password"
                    :type="showPassword ? 'text' : 'password'"
                    placeholder="Минимум 6 символов"
                    class="w-full px-4 py-3 bg-slate-900/50 border border-slate-600 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500 transition pr-12"
                    :class="{ 'border-red-500': errors.password }"
                    required
                    minlength="6"
                />
                <button
                    type="button"
                    @click="showPassword = !showPassword"
                    class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-300"
                >
                    <svg v-if="showPassword" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                    </svg>
                    <svg v-else class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                </button>
            </div>
            <p v-if="errors.password" class="mt-1 text-sm text-red-400">{{ errors.password[0] }}</p>
        </div>

        <!-- Confirm Password -->
        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-300 mb-2">
                Подтверждение пароля <span class="text-red-400">*</span>
            </label>
            <input
                v-model="form.password_confirmation"
                :type="showPassword ? 'text' : 'password'"
                placeholder="Повторите пароль"
                class="w-full px-4 py-3 bg-slate-900/50 border border-slate-600 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500 transition"
                :class="{ 'border-red-500': passwordMismatch }"
                required
            />
            <p v-if="passwordMismatch" class="mt-1 text-sm text-red-400">Пароли не совпадают</p>
        </div>

        <!-- Error message -->
        <div v-if="errorMessage" class="mb-4 p-4 bg-red-500/10 border border-red-500/50 rounded-xl">
            <p class="text-red-400 text-sm">{{ errorMessage }}</p>
        </div>

        <!-- Submit -->
        <button
            type="submit"
            :disabled="loading || (passwordMismatch as any)"
            class="w-full py-4 bg-gradient-to-r from-orange-500 to-orange-600 text-white font-semibold rounded-xl hover:from-orange-600 hover:to-orange-700 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2 focus:ring-offset-slate-800 transition disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
        >
            <svg v-if="loading" class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            {{ loading ? 'Регистрация...' : 'Создать аккаунт' }}
        </button>

        <!-- Trial info -->
        <p class="mt-4 text-center text-sm text-gray-500">
            14 дней бесплатно, без привязки карты
        </p>
    </form>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'

const emit = defineEmits(['success'])

const form = ref({
    organization_name: '',
    owner_name: '',
    email: '',
    phone: '',
    password: '',
    password_confirmation: ''
})

const errors = ref<Record<string, any>>({})
const errorMessage = ref('')
const loading = ref(false)
const showPassword = ref(false)

const passwordMismatch = computed(() => {
    return form.value.password && form.value.password_confirmation && form.value.password !== form.value.password_confirmation
})

async function submit() {
    if (passwordMismatch.value) return

    loading.value = true
    errors.value = {}
    errorMessage.value = ''

    try {
        const response = await fetch('/api/register/tenant', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content
            },
            body: JSON.stringify(form.value)
        })

        const data = await response.json()

        if (!response.ok) {
            if (response.status === 422 && data.errors) {
                errors.value = data.errors
            } else {
                errorMessage.value = data.message || 'Ошибка регистрации'
            }
            return
        }

        if (data.success) {
            emit('success', data.data)
        } else {
            errorMessage.value = data.message || 'Ошибка регистрации'
        }
    } catch (e: any) {
        errorMessage.value = 'Ошибка сети. Попробуйте позже.'
        console.error('Registration error:', e)
    } finally {
        loading.value = false
    }
}
</script>
