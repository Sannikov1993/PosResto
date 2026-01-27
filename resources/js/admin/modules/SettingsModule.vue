<template>
    <div>
        <h1 class="text-2xl font-bold mb-6">Настройки</h1>

        <div class="bg-white rounded-xl shadow-sm p-6 max-w-2xl">
            <div class="space-y-6">
                <div>
                    <label class="block text-sm font-medium mb-1">Название заведения</label>
                    <input v-model="form.restaurant_name" class="w-full border rounded-lg px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Адрес</label>
                    <input v-model="form.address" class="w-full border rounded-lg px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Телефон</label>
                    <input v-model="form.phone" class="w-full border rounded-lg px-3 py-2">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">Открытие</label>
                        <input v-model="form.open_time" type="time" class="w-full border rounded-lg px-3 py-2">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Закрытие</label>
                        <input v-model="form.close_time" type="time" class="w-full border rounded-lg px-3 py-2">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Валюта</label>
                    <select v-model="form.currency" class="w-full border rounded-lg px-3 py-2">
                        <option value="RUB">Рубль (₽)</option>
                        <option value="USD">Доллар ($)</option>
                        <option value="EUR">Евро (€)</option>
                    </select>
                </div>

                <button @click="save" class="w-full py-3 bg-orange-500 text-white rounded-lg font-medium">
                    Сохранить настройки
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue';
import { useAdminStore } from '../stores/admin';

const store = useAdminStore();
const form = ref({
    restaurant_name: '',
    address: '',
    phone: '',
    open_time: '10:00',
    close_time: '22:00',
    currency: 'RUB'
});

watch(() => store.settings, (val) => {
    if (val) form.value = { ...form.value, ...val };
}, { immediate: true });

async function save() {
    await store.saveSettings(form.value);
}

onMounted(() => store.loadSettings());
</script>
