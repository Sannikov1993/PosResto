<template>
    <div class="login-form">
        <div class="form-header">
            <h2>Вход в систему</h2>
            <p v-if="appName">{{ appName }}</p>
        </div>

        <form @submit.prevent="handleSubmit">
            <div class="form-group">
                <label for="login">Логин или Email</label>
                <input
                    id="login"
                    v-model="form.login"
                    type="text"
                    placeholder="Введите логин"
                    required
                    autocomplete="username"
                    :disabled="loading"
                />
            </div>

            <div class="form-group">
                <label for="password">Пароль</label>
                <input
                    id="password"
                    v-model="form.password"
                    type="password"
                    placeholder="Введите пароль"
                    required
                    autocomplete="current-password"
                    :disabled="loading"
                />
            </div>

            <div v-if="showRemember" class="form-group-checkbox">
                <label>
                    <input
                        v-model="form.rememberDevice"
                        type="checkbox"
                    />
                    <span>Запомнить это устройство</span>
                </label>
            </div>

            <p v-if="error" class="error-message">{{ error }}</p>

            <button
                type="submit"
                class="btn-submit"
                :disabled="loading"
            >
                <span v-if="!loading">Войти</span>
                <span v-else>Вход...</span>
            </button>
        </form>

        <div v-if="showForgotPassword" class="form-footer">
            <a href="#" @click.prevent="$emit('forgot-password')">Забыли пароль?</a>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import auth from '@/utils/auth'

const props = defineProps({
    showRemember: {
        type: Boolean,
        default: false,
    },
    showForgotPassword: {
        type: Boolean,
        default: false,
    },
})

const emit = defineEmits(['success', 'forgot-password'])

const form = ref({
    login: '',
    password: '',
    rememberDevice: false,
})

const loading = ref(false)
const error = ref('')

const appName = computed(() => {
    const appType = auth.getAppType()
    const names = {
        pos: 'POS-терминал',
        waiter: 'Приложение официанта',
        courier: 'Приложение курьера',
        kitchen: 'Кухня',
        backoffice: 'Бэк-офис',
    }
    return names[appType] || ''
})

async function handleSubmit() {
    error.value = ''
    loading.value = true

    try {
        const response = await auth.login(
            form.value.login,
            form.value.password,
            form.value.rememberDevice
        )

        if (response.success) {
            emit('success', response.data)
        } else {
            error.value = response.message || 'Ошибка входа'
        }
    } catch (err) {
        error.value = err.response?.data?.message || 'Неверный логин или пароль'
    } finally {
        loading.value = false
    }
}
</script>

<style scoped>
.login-form {
    background: white;
    padding: 2rem;
    border-radius: 0.75rem;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    max-width: 400px;
    width: 100%;
}

.form-header {
    text-align: center;
    margin-bottom: 2rem;
}

.form-header h2 {
    font-size: 1.75rem;
    font-weight: bold;
    color: #111827;
    margin-bottom: 0.5rem;
}

.form-header p {
    color: #6b7280;
    font-size: 0.875rem;
}

.form-group {
    margin-bottom: 1.25rem;
}

.form-group label {
    display: block;
    font-weight: 500;
    color: #374151;
    margin-bottom: 0.5rem;
}

.form-group input[type="text"],
.form-group input[type="password"] {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid #d1d5db;
    border-radius: 0.5rem;
    font-size: 1rem;
    transition: border-color 0.2s;
}

.form-group input:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.form-group input:disabled {
    background: #f3f4f6;
    cursor: not-allowed;
}

.form-group-checkbox {
    margin-bottom: 1.25rem;
}

.form-group-checkbox label {
    display: flex;
    align-items: center;
    cursor: pointer;
}

.form-group-checkbox input[type="checkbox"] {
    margin-right: 0.5rem;
}

.form-group-checkbox span {
    color: #374151;
    font-size: 0.875rem;
}

.error-message {
    color: #ef4444;
    background: #fee2e2;
    padding: 0.75rem;
    border-radius: 0.5rem;
    margin-bottom: 1rem;
    font-size: 0.875rem;
    text-align: center;
}

.btn-submit {
    width: 100%;
    padding: 0.875rem;
    background: #3b82f6;
    color: white;
    border: none;
    border-radius: 0.5rem;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.2s;
}

.btn-submit:hover:not(:disabled) {
    background: #2563eb;
}

.btn-submit:disabled {
    background: #93c5fd;
    cursor: not-allowed;
}

.form-footer {
    margin-top: 1rem;
    text-align: center;
}

.form-footer a {
    color: #3b82f6;
    font-size: 0.875rem;
    text-decoration: none;
}

.form-footer a:hover {
    text-decoration: underline;
}
</style>
