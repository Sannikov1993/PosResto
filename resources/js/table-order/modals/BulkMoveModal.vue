<template>
    <Teleport to="body">
        <div v-if="modelValue" class="fixed inset-0 bg-black/70 z-50 flex items-center justify-center p-4" @click.self="$emit('update:modelValue', false)">
            <div class="bg-gray-900 rounded-2xl w-full max-w-xs overflow-hidden">
                <div class="p-4 border-b border-gray-800 flex items-center justify-between">
                    <h3 class="text-white text-lg font-semibold">Перенести {{ selectedCount }} поз.</h3>
                    <button @click="$emit('update:modelValue', false)" class="text-gray-500 hover:text-white text-xl">✕</button>
                </div>
                <div class="p-4">
                    <p class="text-gray-500 text-xs mb-3">Выберите гостя:</p>
                    <div class="flex flex-col gap-2">
                        <template v-for="g in guests" :key="(g as any).number">
                            <!-- Скрываем текущего гостя и оплаченных гостей -->
                            <button v-if="(g as any).number !== fromGuest && !(g as any).isPaid"
                                    @click="$emit('moveToGuest', (g as any).number)"
                                    class="w-full py-3 bg-gray-800 hover:bg-blue-500/20 hover:border-blue-500/50 border border-gray-700 text-gray-300 hover:text-blue-400 rounded-xl text-sm font-medium transition-all flex items-center justify-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                                Гость {{ (g as any).number }}
                            </button>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<script setup lang="ts">
defineProps({
    modelValue: Boolean,
    selectedCount: Number,
    guests: Array,
    fromGuest: Number
});

defineEmits(['update:modelValue', 'moveToGuest']);
</script>
