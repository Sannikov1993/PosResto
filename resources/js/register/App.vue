<template>
    <div class="min-h-screen bg-gradient-to-br from-slate-900 to-slate-800 flex items-center justify-center p-6">
        <div class="w-full max-w-md">
            <!-- Logo -->
            <div class="text-center mb-10">
                <img src="/images/logo/menulab_logo_dark_bg.svg" alt="MenuLab" class="h-16 mx-auto mb-4" />
                <p class="text-gray-400 text-lg">Регистрация сотрудника</p>
            </div>

            <RegisterForm v-if="!registered" @success="handleSuccess" />
            <SuccessMessage v-else :user="registeredUser" />
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue'
import RegisterForm from './components/RegisterForm.vue'
import SuccessMessage from './components/SuccessMessage.vue'

const registered = ref(false)
const registeredUser = ref(null)

function handleSuccess(userData) {
    registered.value = true
    registeredUser.value = userData

    // Сохраняем токен и пользователя для автологина в кабинет
    localStorage.setItem('cabinet_token', userData.token)
    localStorage.setItem('cabinet_user', JSON.stringify(userData.user))

    // Флаг что пользователь только что зарегистрировался (для показа модалки)
    localStorage.setItem('just_registered', 'true')

    // Редирект в личный кабинет через 2 секунды
    setTimeout(() => {
        window.location.href = '/cabinet'
    }, 2000)
}
</script>
