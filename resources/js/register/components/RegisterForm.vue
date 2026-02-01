<template>
    <div class="bg-slate-800/50 rounded-2xl p-8 shadow-xl border border-slate-700/50">
        <!-- Loading -->
        <div v-if="loading" class="text-center py-12">
            <div class="w-10 h-10 border-3 border-slate-600 border-t-purple-500 rounded-full animate-spin mx-auto mb-4"></div>
            <p class="text-gray-400">Проверка приглашения...</p>
        </div>

        <!-- Error -->
        <div v-else-if="error" class="text-center py-8">
            <div class="w-16 h-16 bg-red-500/10 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
            </div>
            <h3 class="text-xl font-semibold text-white mb-2">Ошибка</h3>
            <p class="text-gray-400">{{ error }}</p>
        </div>

        <!-- Form -->
        <form v-else @submit.prevent="handleSubmit" class="space-y-5">
            <!-- Read-only fields -->
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-1.5">Имя</label>
                    <div class="bg-slate-700/50 rounded-xl px-4 py-3 text-white/60 text-sm border border-slate-600/50">
                        {{ invitation.name }}
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-1.5">Должность</label>
                    <div class="bg-slate-700/50 rounded-xl px-4 py-3 text-white/60 text-sm border border-slate-600/50">
                        {{ invitation.role_label }}
                    </div>
                </div>
            </div>

            <!-- Email -->
            <div>
                <label class="block text-sm font-medium text-gray-300 mb-1.5">
                    Email <span class="text-red-400">*</span>
                </label>
                <input
                    v-model="form.email"
                    type="email"
                    placeholder="pavel@example.com"
                    required
                    :disabled="submitting"
                    :class="[
                        'w-full bg-slate-700/50 rounded-xl px-4 py-3 text-white text-sm placeholder-gray-500 border transition-colors focus:outline-none focus:ring-1',
                        errors.email
                            ? 'border-red-500/50 focus:ring-red-500'
                            : 'border-slate-600/50 focus:ring-purple-500 focus:border-purple-500',
                        submitting ? 'opacity-50 cursor-not-allowed' : ''
                    ]"
                />
                <p v-if="errors.email" class="text-red-400 text-xs mt-1.5">{{ errors.email }}</p>
                <p v-else class="text-gray-500 text-xs mt-1.5">Ваш рабочий email адрес</p>
            </div>

            <!-- Password -->
            <div>
                <label class="block text-sm font-medium text-gray-300 mb-1.5">
                    Пароль <span class="text-red-400">*</span>
                </label>
                <input
                    v-model="form.password"
                    type="password"
                    placeholder="Минимум 6 символов"
                    minlength="6"
                    required
                    :disabled="submitting"
                    :class="[
                        'w-full bg-slate-700/50 rounded-xl px-4 py-3 text-white text-sm placeholder-gray-500 border transition-colors focus:outline-none focus:ring-1',
                        errors.password
                            ? 'border-red-500/50 focus:ring-red-500'
                            : 'border-slate-600/50 focus:ring-purple-500 focus:border-purple-500',
                        submitting ? 'opacity-50 cursor-not-allowed' : ''
                    ]"
                />
                <p v-if="errors.password" class="text-red-400 text-xs mt-1.5">{{ errors.password }}</p>
            </div>

            <!-- Password Confirm -->
            <div>
                <label class="block text-sm font-medium text-gray-300 mb-1.5">
                    Подтверждение пароля <span class="text-red-400">*</span>
                </label>
                <input
                    v-model="form.password_confirm"
                    type="password"
                    placeholder="Повторите пароль"
                    required
                    :disabled="submitting"
                    :class="[
                        'w-full bg-slate-700/50 rounded-xl px-4 py-3 text-white text-sm placeholder-gray-500 border transition-colors focus:outline-none focus:ring-1',
                        errors.password_confirm
                            ? 'border-red-500/50 focus:ring-red-500'
                            : 'border-slate-600/50 focus:ring-purple-500 focus:border-purple-500',
                        submitting ? 'opacity-50 cursor-not-allowed' : ''
                    ]"
                />
                <p v-if="errors.password_confirm" class="text-red-400 text-xs mt-1.5">{{ errors.password_confirm }}</p>
            </div>

            <!-- General error -->
            <div v-if="errors.general" class="bg-red-500/10 border border-red-500/30 rounded-xl px-4 py-3">
                <p class="text-red-400 text-sm">{{ errors.general }}</p>
            </div>

            <!-- Submit -->
            <button
                type="submit"
                :disabled="submitting"
                :class="[
                    'w-full py-3.5 rounded-xl text-white font-semibold text-sm transition-all',
                    submitting
                        ? 'bg-purple-500/40 cursor-not-allowed'
                        : 'bg-gradient-to-r from-purple-600 to-violet-700 hover:from-purple-500 hover:to-violet-600 hover:shadow-lg hover:shadow-purple-500/25'
                ]"
            >
                <span v-if="submitting" class="flex items-center justify-center gap-2">
                    <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                    Регистрация...
                </span>
                <span v-else>Зарегистрироваться</span>
            </button>
        </form>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '../api'

const emit = defineEmits(['success'])

const loading = ref(true)
const error = ref(null)
const invitation = ref({})
const submitting = ref(false)
const errors = ref({})

const form = ref({
    token: '',
    email: '',
    password: '',
    password_confirm: '',
})

onMounted(async () => {
    // Пытаемся получить token из query параметра или path
    const urlParams = new URLSearchParams(window.location.search)
    let token = urlParams.get('token')

    // Если нет в query, пробуем из path: /register/invite/{token}
    if (!token) {
        const pathMatch = window.location.pathname.match(/\/register\/invite\/([^/]+)/)
        if (pathMatch) {
            token = pathMatch[1]
        }
    }

    if (!token) {
        error.value = 'Токен приглашения не найден. Проверьте ссылку.'
        loading.value = false
        return
    }

    form.value.token = token

    try {
        const response = await api.validateToken(token)
        invitation.value = response.data
    } catch (err) {
        error.value = err.response?.data?.message || 'Ошибка проверки приглашения'
    } finally {
        loading.value = false
    }
})

async function handleSubmit() {
    errors.value = {}

    // Валидация email
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
    if (!form.value.email || !emailRegex.test(form.value.email)) {
        errors.value.email = 'Введите корректный email адрес (например: ivan@example.com)'
        return
    }

    // Валидация пароля
    if (form.value.password.length < 6) {
        errors.value.password = 'Пароль должен быть не менее 6 символов'
        return
    }

    // Проверка совпадения паролей
    if (form.value.password !== form.value.password_confirm) {
        errors.value.password_confirm = 'Пароли не совпадают'
        return
    }

    submitting.value = true

    try {
        const response = await api.register(form.value)
        emit('success', response.data)
    } catch (err) {
        const errorData = err.response?.data

        if (errorData?.errors) {
            errors.value = errorData.errors
        } else {
            errors.value.general = errorData?.message || 'Ошибка регистрации'
        }
    } finally {
        submitting.value = false
    }
}
</script>
