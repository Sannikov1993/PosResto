<template>
    <Teleport to="body">
        <div class="fixed top-4 right-4 z-[99999] flex flex-col gap-2" role="region" aria-label="Уведомления" aria-live="polite">
            <TransitionGroup name="toast">
                <div
                    v-for="toast in toasts"
                    :key="toast.id"
                    role="alert" :class="[
                        'px-4 py-3 rounded-lg shadow-lg flex items-center gap-3 min-w-[280px] max-w-[400px]',
                        (toastClasses as Record<string, any>)[toast.type]
                    ]"
                >
                    <span class="text-xl">{{ (toastIcons as Record<string, any>)[toast.type] }}</span>
                    <span class="flex-1">{{ toast.message }}</span>
                    <button
                        @click="removeToast(toast.id)"
                        class="text-white/60 hover:text-white"
                    >
                        ✕
                    </button>
                </div>
            </TransitionGroup>
        </div>
    </Teleport>
</template>

<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue';

const toasts = ref<any[]>([]);
let toastId = 0;

const toastClasses = {
    success: 'bg-green-600 text-white',
    error: 'bg-red-600 text-white',
    warning: 'bg-yellow-600 text-white',
    info: 'bg-blue-600 text-white'
};

const toastIcons = {
    success: '✓',
    error: '✕',
    warning: '⚠',
    info: 'ℹ'
};

const addToast = (message: any, type = 'success', duration = 3000) => {
    const id = ++toastId;
    toasts.value.push({ id, message, type });

    if (duration > 0) {
        setTimeout(() => removeToast(id), duration);
    }
};

const removeToast = (id: any) => {
    const index = toasts.value.findIndex((t: any) => t.id === id);
    if (index > -1) {
        toasts.value.splice(index, 1);
    }
};

// Global toast function
const showToast = (message: any, type = 'success') => {
    addToast(message, type);
};

// Expose globally
onMounted(() => {
    window.$toast = showToast;
});

onUnmounted(() => {
    delete window.$toast;
});

// Expose for composition API usage
defineExpose({ show: showToast });
</script>

<style scoped>
.toast-enter-active,
.toast-leave-active {
    transition: all 0.3s ease;
}

.toast-enter-from {
    opacity: 0;
    transform: translateX(100%);
}

.toast-leave-to {
    opacity: 0;
    transform: translateX(100%);
}
</style>
