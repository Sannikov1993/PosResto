<template>
    <div class="clock-in-screen">
        <div class="avatar">
            <img v-if="user.avatar" :src="user.avatar" :alt="user.name">
            <div v-else class="avatar-placeholder">{{ initials }}</div>
        </div>

        <h2>{{ user.name }}</h2>
        <p class="role">{{ user.role_label || getRoleLabel(user.role) }}</p>

        <div class="current-time">{{ currentTime }}</div>
        <div class="current-date">{{ currentDate }}</div>

        <button
            @click="handleClockIn"
            :disabled="loading"
            class="btn-clock-in"
        >
            <span v-if="!loading">📍 Начать смену</span>
            <span v-else>⏳ Отметка...</span>
        </button>

        <button @click="handleLogout" class="btn-logout">
            Выйти из аккаунта
        </button>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import auth from '@/utils/auth'

const props = defineProps({
    user: {
        type: Object,
        required: true,
    },
})

const emit = defineEmits(['clock-in', 'logout'])

const currentTime = ref('')
const currentDate = ref('')
const loading = ref(false)
let interval = null

const initials = computed(() => {
    const words = props.user.name.split(' ')
    return words.slice(0, 2).map(w => w[0]).join('').toUpperCase()
})

onMounted(() => {
    updateTime()
    interval = setInterval(updateTime, 1000)
})

onUnmounted(() => {
    if (interval) clearInterval(interval)
})

function updateTime() {
    const now = new Date()

    currentTime.value = now.toLocaleTimeString('ru-RU', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    })

    currentDate.value = now.toLocaleDateString('ru-RU', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    })
}

async function handleClockIn() {
    loading.value = true
    try {
        await auth.clockIn()
        emit('clock-in')
    } catch (error) {
        alert('Ошибка начала смены: ' + (error.response?.data?.message || error.message))
    } finally {
        loading.value = false
    }
}

function handleLogout() {
    if (confirm('Вы уверены что хотите выйти?')) {
        emit('logout')
    }
}

function getRoleLabel(role) {
    const labels = {
        waiter: 'Официант',
        courier: 'Курьер',
        cook: 'Повар',
        manager: 'Менеджер',
        admin: 'Администратор',
        cashier: 'Кассир',
        hostess: 'Хостес',
    }
    return labels[role] || role
}
</script>

<style scoped>
.clock-in-screen {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-height: 100vh;
    padding: 2rem;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.avatar {
    margin-bottom: 1.5rem;
}

.avatar img {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    border: 4px solid white;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    object-fit: cover;
}

.avatar-placeholder {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    background: rgba(255,255,255,0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 3rem;
    font-weight: bold;
    border: 4px solid white;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}

h2 {
    font-size: 2rem;
    font-weight: bold;
    margin-bottom: 0.5rem;
    text-align: center;
}

.role {
    font-size: 1.125rem;
    opacity: 0.9;
    margin-bottom: 2rem;
}

.current-time {
    font-size: 4rem;
    font-weight: bold;
    margin-bottom: 0.5rem;
    font-variant-numeric: tabular-nums;
    font-family: 'Courier New', monospace;
}

.current-date {
    font-size: 1.25rem;
    opacity: 0.9;
    margin-bottom: 3rem;
    text-transform: capitalize;
    text-align: center;
}

.btn-clock-in {
    background: white;
    color: #667eea;
    font-size: 1.5rem;
    font-weight: 600;
    padding: 1.25rem 3rem;
    border-radius: 9999px;
    border: none;
    cursor: pointer;
    box-shadow: 0 10px 25px rgba(0,0,0,0.2);
    transition: all 0.3s;
    margin-bottom: 1rem;
}

.btn-clock-in:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 15px 35px rgba(0,0,0,0.3);
}

.btn-clock-in:active:not(:disabled) {
    transform: translateY(0);
}

.btn-clock-in:disabled {
    opacity: 0.7;
    cursor: not-allowed;
}

.btn-logout {
    background: transparent;
    color: white;
    border: none;
    cursor: pointer;
    font-size: 1rem;
    opacity: 0.8;
    padding: 0.75rem 1.5rem;
    transition: opacity 0.2s;
}

.btn-logout:hover {
    opacity: 1;
    text-decoration: underline;
}
</style>
