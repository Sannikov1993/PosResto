<template>
    <Teleport to="body">
        <div class="fixed bottom-4 right-4 z-50 space-y-2">
            <TransitionGroup name="fade">
                <div v-for="toast in store.toasts" :key="toast.id"
                     :class="['px-4 py-3 rounded-lg shadow-lg text-white', toastClass(toast.type)]">
                    {{ toast.message }}
                </div>
            </TransitionGroup>
        </div>
    </Teleport>
</template>

<script setup>
import { useBackofficeStore } from '../../stores/backoffice';

const store = useBackofficeStore();

const toastClass = (type) => {
    if (type === 'success') return 'bg-green-500';
    if (type === 'error') return 'bg-red-500';
    if (type === 'warning') return 'bg-yellow-500';
    return 'bg-gray-800';
};
</script>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: all 0.3s ease;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
    transform: translateX(30px);
}
</style>
