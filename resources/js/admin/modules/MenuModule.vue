<template>
    <div>
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold">Меню</h1>
            <button @click="openDishModal()" class="bg-orange-500 text-white px-4 py-2 rounded-lg">+ Добавить блюдо</button>
        </div>

        <!-- Categories -->
        <div class="mb-6">
            <div class="flex gap-2 flex-wrap">
                <button @click="selectedCategory = null"
                        :class="['px-4 py-2 rounded-lg', !selectedCategory ? 'bg-orange-500 text-white' : 'bg-white']">
                    Все
                </button>
                <button v-for="cat in store.categories" :key="cat.id"
                        @click="selectedCategory = cat.id"
                        :class="['px-4 py-2 rounded-lg', selectedCategory === cat.id ? 'bg-orange-500 text-white' : 'bg-white']">
                    {{ cat.name }}
                </button>
            </div>
        </div>

        <!-- Dishes -->
        <div class="grid grid-cols-3 gap-4">
            <div v-for="dish in filteredDishes" :key="dish.id"
                 class="bg-white rounded-xl shadow-sm p-4">
                <h3 class="font-semibold">{{ dish.name }}</h3>
                <p class="text-gray-500 text-sm">{{ dish.category_name }}</p>
                <p class="text-orange-500 font-bold mt-2">{{ store.formatMoney(dish.price) }}</p>
                <div class="flex gap-2 mt-3">
                    <button @click="openDishModal(dish)" class="text-blue-500 text-sm">Редактировать</button>
                    <button @click="deleteDish(dish)" class="text-red-500 text-sm">Удалить</button>
                </div>
            </div>
        </div>

        <!-- Dish Modal -->
        <div v-if="showModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
            <div class="bg-white rounded-2xl w-[500px] p-6">
                <h2 class="text-xl font-bold mb-4">{{ editingDish ? 'Редактировать' : 'Новое блюдо' }}</h2>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">Название</label>
                        <input v-model="form.name" class="w-full border rounded-lg px-3 py-2">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Категория</label>
                        <select v-model="form.category_id" class="w-full border rounded-lg px-3 py-2">
                            <option v-for="cat in store.categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Цена</label>
                        <input v-model.number="form.price" type="number" class="w-full border rounded-lg px-3 py-2">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Описание</label>
                        <textarea v-model="form.description" rows="2" class="w-full border rounded-lg px-3 py-2"></textarea>
                    </div>
                </div>
                <div class="flex gap-3 mt-6">
                    <button @click="showModal = false" class="flex-1 py-2 bg-gray-200 rounded-lg">Отмена</button>
                    <button @click="saveDish" class="flex-1 py-2 bg-orange-500 text-white rounded-lg">Сохранить</button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useAdminStore } from '../stores/admin';

const store = useAdminStore();
const selectedCategory = ref<any>(null);
const showModal = ref(false);
const editingDish = ref<any>(null);
const form = ref({ name: '', category_id: '', price: 0, description: '' });

const filteredDishes = computed(() => {
    if (!selectedCategory.value) return store.dishes;
    return store.dishes.filter((d: any) => d.category_id === selectedCategory.value);
});

function openDishModal(dish: any = null) {
    editingDish.value = dish;
    if (dish) {
        form.value = { ...dish };
    } else {
        form.value = { name: '', category_id: store.categories[0]?.id, price: 0, description: '' };
    }
    showModal.value = true;
}

async function saveDish() {
    if (editingDish.value) (form.value as any).id = editingDish.value.id;
    const result = await store.saveDish(form.value);
    if (result.success) showModal.value = false;
}

async function deleteDish(dish: any) {
    if (confirm('Удалить блюдо?')) {
        await store.deleteDish(dish.id);
    }
}

onMounted(() => {
    store.loadCategories();
    store.loadDishes();
});
</script>
