<template>
    <span
        :class="[
            'w-12 h-7 rounded-full transition-colors flex items-center flex-shrink-0',
            enabled ? colorClass : 'bg-gray-600',
            enabled ? 'justify-end' : 'justify-start'
        ]"
    >
        <span class="w-6 h-6 bg-white rounded-full mx-0.5 shadow"></span>
    </span>
</template>

<script setup>
/**
 * Toggle Switch Component
 *
 * Reusable toggle switch with customizable colors.
 */

import { computed } from 'vue';

const props = defineProps({
    enabled: {
        type: Boolean,
        default: false,
    },
    color: {
        type: String,
        default: 'cyan',
        validator: (v) => ['cyan', 'purple', 'orange', 'green', 'blue', 'red', 'yellow'].includes(v),
    },
});

const colorClass = computed(() => {
    const colors = {
        cyan: 'bg-cyan-500',
        purple: 'bg-purple-500',
        orange: 'bg-orange-500',
        green: 'bg-green-500',
        blue: 'bg-blue-500',
        red: 'bg-red-500',
        yellow: 'bg-yellow-500',
    };
    return colors[props.color] || colors.cyan;
});
</script>
