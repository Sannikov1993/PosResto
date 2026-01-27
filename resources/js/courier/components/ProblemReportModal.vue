<template>
    <div class="fixed inset-0 z-50 bg-black/50" @click.self="close">
        <div class="absolute inset-x-0 bottom-0 bg-white rounded-t-2xl max-h-[90vh] overflow-y-auto animate-slide-up">
            <!-- Header -->
            <div class="sticky top-0 bg-white border-b border-gray-100 px-4 py-3 flex items-center justify-between">
                <div>
                    <h2 class="font-semibold text-lg text-gray-800">Сообщить о проблеме</h2>
                    <p class="text-gray-500 text-sm">Заказ {{ order?.order_number }}</p>
                </div>
                <button @click="close" class="p-2 hover:bg-gray-100 rounded-full">
                    <svg class="w-6 h-6 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <!-- Content -->
            <div class="p-4 space-y-4">
                <!-- Problem types -->
                <div>
                    <h3 class="font-medium text-gray-800 mb-3">Тип проблемы</h3>
                    <div class="grid grid-cols-2 gap-2">
                        <button
                            v-for="type in problemTypes"
                            :key="type.value"
                            @click="selectedType = type.value"
                            class="p-3 rounded-xl border-2 transition-colors text-left"
                            :class="selectedType === type.value
                                ? 'border-purple-500 bg-purple-50'
                                : 'border-gray-200 bg-gray-50 hover:border-gray-300'"
                        >
                            <div class="text-2xl mb-1">{{ type.icon }}</div>
                            <div class="text-sm font-medium" :class="selectedType === type.value ? 'text-purple-700' : 'text-gray-700'">
                                {{ type.label }}
                            </div>
                        </button>
                    </div>
                </div>

                <!-- Description -->
                <div>
                    <h3 class="font-medium text-gray-800 mb-2">Описание (опционально)</h3>
                    <textarea
                        v-model="description"
                        rows="3"
                        placeholder="Опишите проблему подробнее..."
                        class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent resize-none text-gray-800"
                    ></textarea>
                </div>

                <!-- Photo -->
                <div>
                    <h3 class="font-medium text-gray-800 mb-2">Фото (опционально)</h3>

                    <!-- Photo preview -->
                    <div v-if="photoPreview" class="relative mb-3">
                        <img :src="photoPreview" class="w-full h-48 object-cover rounded-xl" />
                        <button
                            @click="removePhoto"
                            class="absolute top-2 right-2 p-2 bg-black/50 rounded-full text-white hover:bg-black/70 transition-colors"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <!-- Photo buttons -->
                    <div v-else class="flex gap-2">
                        <label class="flex-1 py-3 bg-gray-100 rounded-xl font-medium text-gray-700 hover:bg-gray-200 transition-colors cursor-pointer flex items-center justify-center gap-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            Камера
                            <input type="file" accept="image/*" capture="environment" @change="handlePhoto" class="hidden" />
                        </label>
                        <label class="flex-1 py-3 bg-gray-100 rounded-xl font-medium text-gray-700 hover:bg-gray-200 transition-colors cursor-pointer flex items-center justify-center gap-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            Галерея
                            <input type="file" accept="image/*" @change="handlePhoto" class="hidden" />
                        </label>
                    </div>
                </div>

                <!-- Location info -->
                <div v-if="location" class="bg-green-50 rounded-xl p-3 flex items-center gap-3">
                    <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    <span class="text-green-700 text-sm">Геолокация прикреплена</span>
                </div>
                <div v-else class="bg-yellow-50 rounded-xl p-3 flex items-center gap-3">
                    <svg class="w-5 h-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    <span class="text-yellow-700 text-sm">Геолокация недоступна</span>
                </div>
            </div>

            <!-- Actions -->
            <div class="sticky bottom-0 bg-white border-t border-gray-100 p-4 safe-bottom">
                <button
                    @click="submitProblem"
                    :disabled="!selectedType || submitting"
                    class="w-full py-3 bg-red-600 text-white rounded-xl font-semibold hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors flex items-center justify-center gap-2"
                >
                    <svg v-if="submitting" class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    {{ submitting ? 'Отправка...' : 'Отправить' }}
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';

const props = defineProps({
    order: {
        type: Object,
        required: true
    }
});

const emit = defineEmits(['close', 'submitted']);

// Problem types
const problemTypes = [
    { value: 'customer_unavailable', label: 'Клиент не отвечает', icon: '📵' },
    { value: 'wrong_address', label: 'Неверный адрес', icon: '📍' },
    { value: 'door_locked', label: 'Закрытая дверь', icon: '🔒' },
    { value: 'payment_issue', label: 'Проблема с оплатой', icon: '💳' },
    { value: 'damaged_item', label: 'Повреждённый товар', icon: '📦' },
    { value: 'other', label: 'Другое', icon: '❓' },
];

// State
const selectedType = ref('');
const description = ref('');
const photo = ref(null);
const photoPreview = ref(null);
const location = ref(null);
const submitting = ref(false);

// Get geolocation on mount
onMounted(() => {
    getLocation();
});

function getLocation() {
    if ('geolocation' in navigator) {
        navigator.geolocation.getCurrentPosition(
            (position) => {
                location.value = {
                    latitude: position.coords.latitude,
                    longitude: position.coords.longitude,
                };
            },
            (error) => {
                console.warn('Geolocation error:', error);
                location.value = null;
            },
            { enableHighAccuracy: true, timeout: 10000 }
        );
    }
}

function handlePhoto(event) {
    const file = event.target.files[0];
    if (file) {
        photo.value = file;
        const reader = new FileReader();
        reader.onload = (e) => {
            photoPreview.value = e.target.result;
        };
        reader.readAsDataURL(file);
    }
}

function removePhoto() {
    photo.value = null;
    photoPreview.value = null;
}

function close() {
    emit('close');
}

async function submitProblem() {
    if (!selectedType.value || submitting.value) return;

    submitting.value = true;

    try {
        const formData = new FormData();
        formData.append('type', selectedType.value);

        if (description.value.trim()) {
            formData.append('description', description.value.trim());
        }

        if (photo.value) {
            formData.append('photo', photo.value);
        }

        if (location.value) {
            formData.append('latitude', location.value.latitude);
            formData.append('longitude', location.value.longitude);
        }

        const response = await fetch(`/api/delivery/orders/${props.order.id}/problem`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
            },
            body: formData,
        });

        const result = await response.json();

        if (result.success) {
            emit('submitted', result.data);
            close();
        } else {
            alert(result.message || 'Ошибка при отправке');
        }
    } catch (error) {
        console.error('Error submitting problem:', error);
        alert('Ошибка при отправке проблемы');
    } finally {
        submitting.value = false;
    }
}
</script>

<style scoped>
@keyframes slide-up {
    from { transform: translateY(100%); }
    to { transform: translateY(0); }
}
.animate-slide-up {
    animation: slide-up 0.3s ease-out;
}
.safe-bottom { padding-bottom: env(safe-area-inset-bottom, 16px); }
</style>
