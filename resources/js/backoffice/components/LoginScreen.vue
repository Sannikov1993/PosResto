<template>
    <div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-orange-500 via-orange-600 to-red-600">
        <div class="bg-white p-8 rounded-2xl shadow-2xl w-[400px]">
            <div class="text-center mb-8">
                <img src="/images/logo/posresto_icon.svg" alt="PosResto" class="w-16 h-16 mx-auto mb-4" />
                <h1 class="text-2xl font-bold text-gray-900">PosResto BackOffice</h1>
                <p class="text-gray-500 mt-1">Управление рестораном</p>
            </div>
            <form @submit.prevent="handleLogin" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Email</label>
                    <input v-model="form.email" type="email" class="input" placeholder="admin@posresto.ru">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Пароль</label>
                    <input v-model="form.password" type="password" class="input" placeholder="Введите пароль">
                </div>
                <button type="submit" :disabled="loading" class="btn-primary w-full mt-2">
                    {{ loading ? 'Вход...' : 'Войти в систему' }}
                </button>
                <p v-if="error" class="text-red-500 text-center text-sm">{{ error }}</p>
            </form>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { useBackofficeStore } from '../stores/backoffice';

const emit = defineEmits(['login']);
const store = useBackofficeStore();

const form = ref({
    email: 'admin@posresto.ru',
    password: 'admin123'
});
const loading = ref(false);
const error = ref('');

const handleLogin = async () => {
    loading.value = true;
    error.value = '';

    const result = await store.login(form.value.email, form.value.password);

    if (result.success) {
        emit('login');
    } else {
        error.value = result.message || 'Ошибка входа';
    }

    loading.value = false;
};
</script>
