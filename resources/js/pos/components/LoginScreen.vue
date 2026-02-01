<template>
    <div class="h-full">
        <!-- User Selection -->
        <UserSelector
            v-if="mode === 'select'"
            @select-user="handleUserSelect"
            @show-full-login="mode = 'password'"
        />

        <!-- PIN Entry -->
        <div v-else-if="mode === 'pin'" class="h-full flex items-center justify-center bg-dark-950">
            <div class="bg-dark-800 rounded-2xl p-8 w-80 border border-gray-700">
                <button
                    @click="mode = 'select'"
                    class="text-gray-400 hover:text-white mb-4 flex items-center gap-2 transition-colors"
                >
                    ← Назад
                </button>

                <div class="text-center mb-8">
                    <div v-if="selectedUser.avatar" class="w-20 h-20 rounded-full overflow-hidden mx-auto mb-3 border-2 border-accent">
                        <img :src="selectedUser.avatar" :alt="selectedUser.name" class="w-full h-full object-cover" />
                    </div>
                    <div v-else class="w-20 h-20 rounded-full bg-gradient-to-br from-accent to-purple-600 flex items-center justify-center mx-auto mb-3 text-2xl font-bold text-white">
                        {{ getUserInitials(selectedUser.name) }}
                    </div>
                    <h2 class="text-xl font-semibold text-white">{{ selectedUser.name }}</h2>
                    <p class="text-gray-400 text-sm mt-1">{{ selectedUser.role_label }}</p>
                    <p class="text-gray-500 text-sm mt-4">Введите PIN-код:</p>
                </div>

                <!-- PIN Display -->
                <div class="flex justify-center gap-2 mb-6">
                    <div
                        v-for="i in 4"
                        :key="i"
                        :class="[
                            'w-12 h-12 rounded-xl border-2 flex items-center justify-center text-xl font-bold transition-all',
                            pin.length >= i
                                ? 'border-accent bg-accent/20 text-white'
                                : 'border-gray-600 bg-dark-900 text-gray-600'
                        ]"
                    >
                        {{ pin.length >= i ? '●' : '' }}
                    </div>
                </div>

                <!-- Error Message -->
                <p v-if="error" class="text-red-400 text-sm text-center mb-4">
                    {{ error }}
                </p>

                <!-- Number Pad -->
                <div class="grid grid-cols-3 gap-2">
                    <button
                        v-for="n in [1,2,3,4,5,6,7,8,9,'',0,'⌫']"
                        :key="n"
                        @click="handleKeyPress(n)"
                        :disabled="loading || n === ''"
                        :class="[
                            'h-14 rounded-xl font-semibold text-lg transition-all',
                            n === ''
                                ? 'invisible'
                                : n === '⌫'
                                    ? 'bg-red-600/20 text-red-400 hover:bg-red-600/30'
                                    : 'bg-dark-900 text-white hover:bg-gray-700 active:scale-95',
                            loading && 'opacity-50 cursor-not-allowed'
                        ]"
                    >
                        {{ n }}
                    </button>
                </div>

                <!-- Loading -->
                <div v-if="loading" class="mt-4 text-center text-gray-400">
                    Проверка...
                </div>

                <!-- Forgot PIN -->
                <div class="mt-4 text-center">
                    <button
                        @click="mode = 'password'"
                        class="text-accent text-sm hover:text-accent/80 transition-colors"
                    >
                        Забыли PIN? Войти по паролю
                    </button>
                </div>
            </div>
        </div>

        <!-- Password Login -->
        <div v-else-if="mode === 'password'" class="h-full flex items-center justify-center bg-dark-950">
            <div class="bg-dark-800 rounded-2xl p-8 w-96 border border-gray-700">
                <button
                    @click="mode = 'select'; selectedUser = null"
                    class="text-gray-400 hover:text-white mb-4 flex items-center gap-2 transition-colors"
                >
                    ← Назад
                </button>

                <div class="text-center mb-8">
                    <img src="/images/logo/menulab_icon.svg" alt="MenuLab" class="w-16 h-16 mx-auto mb-4" />
                    <h2 class="text-xl font-semibold text-white">Вход по паролю</h2>
                </div>

                <form @submit.prevent="handlePasswordLogin" class="space-y-4">
                    <div>
                        <input
                            v-model="form.login"
                            type="text"
                            placeholder="Логин"
                            required
                            autocomplete="username"
                            :disabled="loading"
                            class="w-full px-4 py-3 bg-dark-900 border border-gray-700 rounded-xl text-white placeholder-gray-500 focus:border-accent focus:outline-none transition-colors disabled:opacity-50"
                        />
                    </div>

                    <div>
                        <input
                            v-model="form.password"
                            type="password"
                            placeholder="Пароль"
                            required
                            autocomplete="current-password"
                            :disabled="loading"
                            class="w-full px-4 py-3 bg-dark-900 border border-gray-700 rounded-xl text-white placeholder-gray-500 focus:border-accent focus:outline-none transition-colors disabled:opacity-50"
                        />
                    </div>

                    <p v-if="error" class="text-red-400 text-sm text-center">
                        {{ error }}
                    </p>

                    <button
                        type="submit"
                        :disabled="loading"
                        class="w-full py-3 bg-accent hover:bg-accent/90 text-white font-semibold rounded-xl transition-all disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        {{ loading ? 'Вход...' : 'Войти' }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, watch } from 'vue'
import { useAuthStore } from '../stores/auth'
import auth from '@/utils/auth'
import UserSelector from './UserSelector.vue'

const emit = defineEmits(['login'])

const authStore = useAuthStore()

const mode = ref('select') // 'select', 'pin', 'password'
const selectedUser = ref(null)
const pin = ref('')
const error = ref('')
const loading = ref(false)

const form = ref({
    login: '',
    password: '',
})

function handleUserSelect(user) {
    selectedUser.value = user
    mode.value = 'pin'
    pin.value = ''
    error.value = ''
}

const handleKeyPress = (key) => {
    error.value = ''

    if (key === '⌫') {
        pin.value = pin.value.slice(0, -1)
    } else if (typeof key === 'number' && pin.value.length < 4) {
        pin.value += key
    }
}

// Auto-submit when 4 digits entered
watch(pin, async (newPin) => {
    if (newPin.length === 4) {
        loading.value = true
        error.value = ''

        const result = await authStore.loginWithPin(newPin, selectedUser.value?.id)

        if (result.success) {
            emit('login', authStore.user)
        } else {
            if (result.require_full_login) {
                error.value = ''
                mode.value = 'password'
                form.value.login = selectedUser.value?.email || ''
                alert(result.message || 'Необходимо войти по логину и паролю для авторизации устройства.')
            } else {
                error.value = result.message || 'Неверный PIN-код'
            }
            pin.value = ''
        }

        loading.value = false
    }
})

async function handlePasswordLogin() {
    loading.value = true
    error.value = ''

    try {
        const response = await auth.login(
            form.value.login,
            form.value.password,
            true // запоминаем устройство для POS
        )

        if (response.success) {
            // Обновляем auth store
            authStore.user = response.data.user
            authStore.token = response.data.token
            authStore.isLoggedIn = true

            emit('login', response.data.user)
        } else {
            error.value = response.message || 'Ошибка входа'
        }
    } catch (err) {
        error.value = err.response?.data?.message || 'Неверный логин или пароль'
    } finally {
        loading.value = false
    }
}

function getUserInitials(name) {
    const words = name.split(' ')
    if (words.length >= 2) {
        return (words[0][0] + words[1][0]).toUpperCase()
    }
    return name.substring(0, 2).toUpperCase()
}
</script>
