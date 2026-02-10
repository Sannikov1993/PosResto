<template>
    <Teleport to="body">
        <Transition name="fade">
            <div v-if="modelValue" class="fixed inset-0 bg-black/80 z-[10000] flex items-center justify-center p-4" @click.self="cancel">
                <Transition name="scale">
                    <div v-if="modelValue" class="bg-gray-900 rounded-2xl w-full max-w-sm shadow-2xl">
                        <!-- Header -->
                        <div class="p-5 text-center">
                            <!-- Icon -->
                            <div class="mx-auto w-14 h-14 rounded-full flex items-center justify-center mb-4"
                                 :class="iconBgClass">
                                <span class="text-2xl">{{ icon }}</span>
                            </div>

                            <!-- Title -->
                            <h3 class="text-lg font-semibold text-white mb-2">{{ title }}</h3>

                            <!-- Message -->
                            <p class="text-gray-400 text-sm">{{ message }}</p>
                        </div>

                        <!-- Actions -->
                        <div class="flex border-t border-gray-800">
                            <button
                                @click="cancel"
                                class="flex-1 py-3.5 text-gray-400 font-medium hover:bg-gray-800/50 transition-colors rounded-bl-2xl"
                            >
                                {{ cancelText }}
                            </button>
                            <button
                                @click="confirm"
                                :disabled="loading"
                                class="flex-1 py-3.5 font-medium transition-colors rounded-br-2xl border-l border-gray-800"
                                :class="confirmButtonClass"
                            >
                                <span v-if="loading" class="flex items-center justify-center gap-2">
                                    <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                </span>
                                <span v-else>{{ confirmText }}</span>
                            </button>
                        </div>
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    title: { type: String, default: 'Подтверждение' },
    message: { type: String, default: 'Вы уверены?' },
    confirmText: { type: String, default: 'Да' },
    cancelText: { type: String, default: 'Отмена' },
    type: { type: String, default: 'danger' }, // danger, warning, info, success
    icon: { type: String, default: null },
    loading: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue', 'confirm', 'cancel']);

const defaultIcons = {
    danger: '⚠️',
    warning: '⚠️',
    info: 'ℹ️',
    success: '✓',
};

const icon = computed(() => props.icon || (defaultIcons as Record<string, any>)[props.type] || '❓');

const iconBgClass = computed(() => {
    const classes = {
        danger: 'bg-red-500/20',
        warning: 'bg-orange-500/20',
        info: 'bg-blue-500/20',
        success: 'bg-green-500/20',
    };
    return (classes as Record<string, any>)[props.type] || 'bg-gray-500/20';
});

const confirmButtonClass = computed(() => {
    const classes = {
        danger: 'text-red-400 hover:bg-red-500/10',
        warning: 'text-orange-400 hover:bg-orange-500/10',
        info: 'text-blue-400 hover:bg-blue-500/10',
        success: 'text-green-400 hover:bg-green-500/10',
    };
    return (classes as Record<string, any>)[props.type] || 'text-accent hover:bg-accent/10';
});

const confirm = () => {
    emit('confirm');
};

const cancel = () => {
    emit('update:modelValue', false);
    emit('cancel');
};
</script>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.2s ease;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

.scale-enter-active,
.scale-leave-active {
    transition: all 0.2s ease;
}
.scale-enter-from,
.scale-leave-to {
    opacity: 0;
    transform: scale(0.95);
}
</style>
