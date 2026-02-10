<template>
    <div
        v-if="show"
        class="fixed inset-0 bg-blue-500/90 flex items-center justify-center z-50"
        @click="$emit('dismiss')"
    >
        <div class="text-center text-white">
            <p class="text-9xl mb-8">📥</p>
            <p class="text-6xl font-bold mb-4">НОВЫЙ ЗАКАЗ!</p>
            <p class="text-4xl font-bold">#{{ orderNumber }}</p>
            <p class="text-2xl mt-8 opacity-75">Нажмите чтобы закрыть</p>
        </div>
    </div>
</template>

<script setup lang="ts">
/**
 * New Order Alert Component
 *
 * Full-screen alert for new order notification.
 */

defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    orderNumber: {
        type: [String, Number],
        default: '',
        validator: (v) => v === '' || v !== null,
    },
});

defineEmits(['dismiss']);
</script>
