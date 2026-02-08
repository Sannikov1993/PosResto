<template>
    <Teleport to="body">
        <div v-if="modelValue" class="fixed inset-0 bg-black/70 z-50 flex items-center justify-center p-4" @click.self="$emit('update:modelValue', false)">
            <div class="bg-gray-900 rounded-2xl w-full max-w-md overflow-hidden">
                <div class="p-4 border-b border-gray-800 flex items-center justify-between">
                    <h3 class="text-white text-lg font-semibold">💬 Комментарий для кухни</h3>
                    <button @click="$emit('update:modelValue', false)" class="text-gray-500 hover:text-white text-xl">✕</button>
                </div>
                <div class="p-4">
                    <p class="text-gray-400 text-sm mb-2">{{ item?.name }}</p>
                    <textarea :value="text"
                              @input="$emit('update:text', $event.target.value)"
                              placeholder="Например: без лука, поострее, не солить..."
                              class="w-full bg-gray-800 border border-gray-700 rounded-xl px-4 py-3 text-white placeholder-gray-500 focus:border-blue-500 focus:outline-none resize-none"
                              rows="3"
                              ref="commentInput"></textarea>

                    <!-- Quick buttons -->
                    <div class="flex flex-wrap gap-2 mt-3">
                        <button v-for="quick in quickOptions"
                                :key="quick"
                                @click="addQuickOption(quick)"
                                class="px-3 py-1.5 bg-gray-800 text-gray-400 rounded-lg text-sm hover:bg-gray-700 hover:text-white">
                            {{ quick }}
                        </button>
                    </div>
                </div>
                <div class="p-4 border-t border-gray-800 flex gap-3">
                    <button @click="$emit('update:modelValue', false)"
                            class="flex-1 py-3 bg-gray-700 text-gray-300 rounded-xl font-medium hover:bg-gray-600">
                        Отмена
                    </button>
                    <button @click="$emit('save', { item: item, text: text })"
                            class="flex-1 py-3 bg-blue-500 text-white rounded-xl font-medium hover:bg-blue-600">
                        Сохранить
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<script setup>
const props = defineProps({
    modelValue: Boolean,
    item: Object,
    text: String
});

const emit = defineEmits(['update:modelValue', 'update:text', 'save']);

const quickOptions = ['Без лука', 'Поострее', 'Не солить', 'Без соуса', 'На вынос'];

const addQuickOption = (option) => {
    const current = (props.text || '').replace(/,\s*$/, '').trim();
    const newText = current ? current + ', ' + option.toLowerCase() : option.toLowerCase();
    emit('update:text', newText + ', ');
};
</script>
