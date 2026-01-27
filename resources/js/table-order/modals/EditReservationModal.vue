<template>
    <Teleport to="body">
        <Transition name="fade">
            <div v-if="modelValue" class="fixed inset-0 bg-black/60 z-[9998]" @click="$emit('update:modelValue', false)"></div>
        </Transition>

        <Transition name="slide-up">
            <div v-if="modelValue" class="fixed inset-0 z-[9999] flex items-center justify-center p-4">
                <div class="bg-[#1a1f2e] rounded-2xl w-full max-w-md shadow-2xl" @click.stop>
                    <!-- Header -->
                    <div class="flex items-center justify-between px-5 py-4 border-b border-gray-700/50">
                        <h3 class="text-lg font-semibold text-white">Редактировать бронь</h3>
                        <button @click="$emit('update:modelValue', false)"
                                class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-white/10 text-gray-400 hover:text-white transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <!-- Content -->
                    <div class="p-5 space-y-4">
                        <!-- Guest name -->
                        <div>
                            <label class="block text-xs text-gray-500 mb-1.5">Имя гостя</label>
                            <input type="text" v-model="form.guest_name"
                                   placeholder="Введите имя"
                                   class="w-full bg-[#252a3a] text-white px-4 py-3 rounded-xl border border-gray-700 focus:border-blue-500 outline-none transition-colors">
                        </div>

                        <!-- Phone -->
                        <div>
                            <label class="block text-xs text-gray-500 mb-1.5">Телефон</label>
                            <input type="tel" v-model="form.guest_phone"
                                   placeholder="+7 999 123-45-67"
                                   class="w-full bg-[#252a3a] text-white px-4 py-3 rounded-xl border border-gray-700 focus:border-blue-500 outline-none transition-colors">
                        </div>

                        <!-- Guests count -->
                        <div>
                            <label class="block text-xs text-gray-500 mb-1.5">Количество гостей</label>
                            <div class="flex items-center gap-3">
                                <button @click="form.guests_count = Math.max(1, form.guests_count - 1)"
                                        class="w-12 h-12 bg-[#252a3a] hover:bg-[#2d3348] text-white rounded-xl flex items-center justify-center text-xl font-bold transition-colors">
                                    -
                                </button>
                                <input type="number" v-model.number="form.guests_count" min="1" max="50"
                                       class="flex-1 bg-[#252a3a] text-white text-center text-xl font-bold px-4 py-3 rounded-xl border border-gray-700 focus:border-blue-500 outline-none">
                                <button @click="form.guests_count = Math.min(50, form.guests_count + 1)"
                                        class="w-12 h-12 bg-[#252a3a] hover:bg-[#2d3348] text-white rounded-xl flex items-center justify-center text-xl font-bold transition-colors">
                                    +
                                </button>
                            </div>
                        </div>

                        <!-- Time -->
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs text-gray-500 mb-1.5">Время начала</label>
                                <input type="time" v-model="form.time_from"
                                       class="w-full bg-[#252a3a] text-white px-4 py-3 rounded-xl border border-gray-700 focus:border-blue-500 outline-none transition-colors">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1.5">Время окончания</label>
                                <input type="time" v-model="form.time_to"
                                       class="w-full bg-[#252a3a] text-white px-4 py-3 rounded-xl border border-gray-700 focus:border-blue-500 outline-none transition-colors">
                            </div>
                        </div>

                        <!-- Notes -->
                        <div>
                            <label class="block text-xs text-gray-500 mb-1.5">Комментарий</label>
                            <textarea v-model="form.notes"
                                      placeholder="Заметки о бронировании..."
                                      rows="2"
                                      class="w-full bg-[#252a3a] text-white px-4 py-3 rounded-xl border border-gray-700 focus:border-blue-500 outline-none resize-none transition-colors"></textarea>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="flex items-center gap-3 px-5 py-4 border-t border-gray-700/50">
                        <button @click="$emit('update:modelValue', false)"
                                class="flex-1 py-3 bg-gray-700/50 hover:bg-gray-600/50 text-gray-300 rounded-xl font-medium transition-colors">
                            Отмена
                        </button>
                        <button @click="save" :disabled="saving"
                                class="flex-1 py-3 bg-blue-500 hover:bg-blue-600 text-white rounded-xl font-medium transition-colors disabled:opacity-50 flex items-center justify-center gap-2">
                            <svg v-if="saving" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            {{ saving ? 'Сохранение...' : 'Сохранить' }}
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup>
import { ref, watch } from 'vue';

const props = defineProps({
    modelValue: Boolean,
    reservation: Object
});

const emit = defineEmits(['update:modelValue', 'save']);

const saving = ref(false);

const form = ref({
    guest_name: '',
    guest_phone: '',
    guests_count: 2,
    time_from: '',
    time_to: '',
    notes: ''
});

// Заполняем форму при открытии
watch(() => props.modelValue, (val) => {
    if (val && props.reservation) {
        form.value = {
            guest_name: props.reservation.guest_name || '',
            guest_phone: props.reservation.guest_phone || '',
            guests_count: props.reservation.guests_count || 2,
            time_from: props.reservation.time_from?.substring(0, 5) || '',
            time_to: props.reservation.time_to?.substring(0, 5) || '',
            notes: props.reservation.notes || ''
        };
    }
});

const save = async () => {
    saving.value = true;
    try {
        emit('save', { ...form.value });
    } finally {
        saving.value = false;
    }
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

.slide-up-enter-active,
.slide-up-leave-active {
    transition: all 0.3s ease;
}
.slide-up-enter-from,
.slide-up-leave-to {
    opacity: 0;
    transform: translateY(20px);
}
</style>
