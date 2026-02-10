<template>
    <div>
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold">Персонал</h1>
            <button @click="openModal()" class="bg-orange-500 text-white px-4 py-2 rounded-lg">+ Добавить</button>
        </div>

        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-sm font-medium text-gray-500">Имя</th>
                        <th class="px-6 py-3 text-left text-sm font-medium text-gray-500">Должность</th>
                        <th class="px-6 py-3 text-left text-sm font-medium text-gray-500">Телефон</th>
                        <th class="px-6 py-3 text-left text-sm font-medium text-gray-500">Статус</th>
                        <th class="px-6 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-for="person in store.staff" :key="person.id">
                        <td class="px-6 py-4 font-medium">{{ person.name }}</td>
                        <td class="px-6 py-4 text-gray-500">{{ person.role }}</td>
                        <td class="px-6 py-4 text-gray-500">{{ person.phone }}</td>
                        <td class="px-6 py-4">
                            <span :class="['px-2 py-1 rounded-full text-xs', person.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700']">
                                {{ person.is_active ? 'Активен' : 'Неактивен' }}
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            <button @click="openModal(person)" class="text-blue-500 text-sm">Редактировать</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Modal -->
        <div v-if="showModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
            <div class="bg-white rounded-2xl w-[500px] p-6">
                <h2 class="text-xl font-bold mb-4">{{ editingPerson ? 'Редактировать' : 'Новый сотрудник' }}</h2>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">Имя</label>
                        <input v-model="form.name" class="w-full border rounded-lg px-3 py-2">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Должность</label>
                        <select v-model="form.role" class="w-full border rounded-lg px-3 py-2">
                            <option value="waiter">Официант</option>
                            <option value="bartender">Бармен</option>
                            <option value="cook">Повар</option>
                            <option value="manager">Менеджер</option>
                            <option value="admin">Администратор</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Телефон</label>
                        <input v-model="form.phone" class="w-full border rounded-lg px-3 py-2">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">PIN-код</label>
                        <input v-model="form.pin" type="text" maxlength="4" class="w-full border rounded-lg px-3 py-2">
                    </div>
                </div>
                <div class="flex gap-3 mt-6">
                    <button @click="showModal = false" class="flex-1 py-2 bg-gray-200 rounded-lg">Отмена</button>
                    <button @click="save" class="flex-1 py-2 bg-orange-500 text-white rounded-lg">Сохранить</button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useAdminStore } from '../stores/admin';

const store = useAdminStore();
const showModal = ref(false);
const editingPerson = ref<any>(null);
const form = ref({ name: '', role: 'waiter', phone: '', pin: '' });

function openModal(person: any = null) {
    editingPerson.value = person;
    if (person) form.value = { ...person };
    else form.value = { name: '', role: 'waiter', phone: '', pin: '' };
    showModal.value = true;
}

async function save() {
    if (editingPerson.value) (form.value as any).id = editingPerson.value.id;
    const result = await store.saveStaffMember(form.value);
    if (result.success) showModal.value = false;
}

onMounted(() => store.loadStaff());
</script>
