<template>
    <div class="flex bg-dark-800 rounded-lg p-1">
        <button
            v-for="mode in modes"
            :key="mode.value"
            @click="$emit('update:modelValue', mode.value)"
            :class="[
                'px-4 py-2 rounded-md text-sm font-medium transition-colors',
                modelValue === mode.value
                    ? 'bg-accent text-white'
                    : 'text-gray-400 hover:text-white'
            ]"
        >
            {{ mode.label }}
        </button>
    </div>
</template>

<script setup>
defineProps({
    modelValue: {
        type: String,
        default: 'kanban'
    }
});

defineEmits(['update:modelValue']);

const modes = [
    { value: 'kanban', label: 'Kanban' },
    { value: 'table', label: 'Таблица' }
];
</script>
