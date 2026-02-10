<template>
    <div class="min-h-screen bg-gradient-to-br from-slate-900 to-slate-800 flex items-center justify-center p-6">
        <div class="w-full max-w-lg">
            <!-- Logo -->
            <div class="text-center mb-8">
                <img src="/images/logo/menulab_logo_dark_bg.svg" alt="MenuLab" class="h-14 mx-auto mb-4" />
                <h1 class="text-2xl font-bold text-white mb-2">Начните работу с MenuLab</h1>
                <p class="text-gray-400">Создайте аккаунт и получите 14 дней бесплатного доступа</p>
            </div>

            <RegisterForm v-if="!registered" @success="handleSuccess" />
            <SuccessMessage v-else :data="registrationData" />

            <!-- Login link -->
            <div v-if="!registered" class="text-center mt-6">
                <p class="text-gray-500 text-sm">
                    Уже есть аккаунт?
                    <a href="/backoffice" class="text-orange-400 hover:text-orange-300 font-medium">Войти</a>
                </p>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import RegisterForm from './components/RegisterForm.vue'
import SuccessMessage from './components/SuccessMessage.vue'

const registered = ref(false)
const registrationData = ref<any>(null)

function handleSuccess(data: any) {
    registered.value = true
    registrationData.value = data

    // Сохраняем токен для автоматического входа
    localStorage.setItem('backoffice_token', data.token)

    // Редирект в бэк-офис через 3 секунды
    setTimeout(() => {
        window.location.href = '/backoffice'
    }, 3000)
}
</script>
