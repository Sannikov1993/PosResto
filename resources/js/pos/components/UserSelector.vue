<template>
    <div class="h-full flex items-center justify-center bg-dark-950" data-testid="user-selector">
        <div class="w-full max-w-2xl p-8">
            <div class="text-center mb-8">
                <img src="/images/logo/menulab_icon.svg" alt="MenuLab" class="w-16 h-16 mx-auto mb-4" data-testid="logo" />
                <h1 class="text-2xl font-semibold text-white">POS-Терминал</h1>
                <p class="text-gray-400 mt-2">Выберите сотрудника:</p>
            </div>

            <!-- Loading -->
            <div v-if="loading" class="text-center py-12">
                <div class="inline-block w-12 h-12 border-4 border-gray-700 border-t-accent rounded-full animate-spin"></div>
                <p class="text-gray-400 mt-4">Загрузка...</p>
            </div>

            <!-- Users Grid -->
            <div v-else-if="users.length > 0" class="space-y-6" data-testid="users-grid">
                <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                    <button
                        v-for="user in users"
                        :key="user.id"
                        @click="selectUser(user)"
                        :data-testid="`user-${user.id}`"
                        class="group bg-dark-800 hover:bg-dark-700 border-2 border-gray-700 hover:border-accent rounded-xl p-6 transition-all"
                    >
                        <div class="flex flex-col items-center">
                            <div v-if="user.avatar" class="w-20 h-20 rounded-full overflow-hidden mb-3 border-2 border-gray-700 group-hover:border-accent transition-colors">
                                <img :src="user.avatar" :alt="user.name" class="w-full h-full object-cover" />
                            </div>
                            <div v-else class="w-20 h-20 rounded-full bg-gradient-to-br from-accent to-purple-600 flex items-center justify-center mb-3 text-2xl font-bold text-white">
                                {{ getUserInitials(user.name) }}
                            </div>
                            <h3 class="text-white font-semibold text-center mb-1">{{ user.name }}</h3>
                            <p class="text-gray-400 text-sm">{{ user.role_label }}</p>
                            <div v-if="user.has_pin" class="mt-2 text-xs text-accent">
                                PIN настроен
                            </div>
                        </div>
                    </button>
                </div>

                <div class="text-center pt-4 border-t border-gray-700">
                    <button
                        @click="$emit('show-full-login')"
                        data-testid="show-password-login"
                        class="text-accent hover:text-accent/80 transition-colors"
                    >
                        Войти по логину и паролю →
                    </button>
                </div>
            </div>

            <!-- Empty State -->
            <div v-else class="text-center py-12">
                <div class="text-6xl mb-4">👥</div>
                <h3 class="text-xl text-white font-semibold mb-2">Устройство новое</h3>
                <p class="text-gray-400 mb-6">На этом терминале еще никто не входил</p>
                <button
                    @click="$emit('show-full-login')"
                    class="px-6 py-3 bg-accent hover:bg-accent/90 text-white font-semibold rounded-xl transition-colors"
                >
                    Войти по логину и паролю
                </button>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import auth from '@/utils/auth'
import { createLogger } from '../../shared/services/logger.js';

const log = createLogger('UserSelector');

const emit = defineEmits(['select-user', 'show-full-login'])

const users = ref<any[]>([])
const loading = ref(true)

onMounted(async () => {
    try {
        const response = await auth.getDeviceUsers('pos')
        users.value = response.data || []
    } catch (error: any) {
        log.error('Failed to load device users:', error)
        users.value = []
    } finally {
        loading.value = false
    }
})

function selectUser(user: any) {
    emit('select-user', user)
}

function getUserInitials(name: any) {
    const words = name.split(' ')
    if (words.length >= 2) {
        return (words[0][0] + words[1][0]).toUpperCase()
    }
    return name.substring(0, 2).toUpperCase()
}
</script>
