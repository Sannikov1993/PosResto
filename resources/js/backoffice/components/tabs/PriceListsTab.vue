<template>
    <div>
        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <div>
                <h2 class="text-2xl font-bold text-gray-900">Прайс-листы</h2>
                <p class="text-sm text-gray-500 mt-1">Управление ценами для разных сценариев обслуживания</p>
            </div>
            <button @click="openCreateModal" class="btn-primary flex items-center gap-2">
                <span>+</span> Создать прайс-лист
            </button>
        </div>

        <!-- Loading -->
        <div v-if="loading" class="flex justify-center py-12">
            <div class="spinner"></div>
        </div>

        <!-- Price Lists Grid -->
        <div v-else-if="priceLists.length" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
            <div v-for="pl in priceLists" :key="pl.id"
                 class="card p-5 cursor-pointer hover:shadow-md transition-shadow"
                 :class="{ 'ring-2 ring-orange-400': pl.is_default }"
                 @click="selectPriceList(pl)">
                <div class="flex items-start justify-between mb-3">
                    <div>
                        <h3 class="font-semibold text-gray-900">{{ pl.name }}</h3>
                        <p v-if="pl.description" class="text-sm text-gray-500 mt-1">{{ pl.description }}</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <span v-if="pl.is_default" class="badge badge-info">По умолчанию</span>
                        <span :class="pl.is_active ? 'badge badge-success' : 'badge badge-danger'">
                            {{ pl.is_active ? 'Активен' : 'Неактивен' }}
                        </span>
                    </div>
                </div>
                <div class="flex items-center justify-between text-sm text-gray-500">
                    <span>{{ pl.items_count || 0 }} позиций</span>
                    <div class="flex gap-2" @click.stop>
                        <button @click="toggleActive(pl)" class="text-gray-400 hover:text-gray-600" :title="pl.is_active ? 'Деактивировать' : 'Активировать'">
                            {{ pl.is_active ? '🔴' : '🟢' }}
                        </button>
                        <button @click="setAsDefault(pl)" class="text-gray-400 hover:text-yellow-500" title="По умолчанию" :disabled="pl.is_default">
                            {{ pl.is_default ? '⭐' : '☆' }}
                        </button>
                        <button @click="openEditModal(pl)" class="text-gray-400 hover:text-blue-500" title="Редактировать">
                            ✏️
                        </button>
                        <button @click="deletePriceList(pl)" class="text-gray-400 hover:text-red-500" title="Удалить">
                            🗑️
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Empty State -->
        <div v-else class="text-center py-16">
            <p class="text-5xl mb-4">📋</p>
            <p class="text-gray-500 mb-4">Нет прайс-листов</p>
            <button @click="openCreateModal" class="btn-primary">Создать первый прайс-лист</button>
        </div>

        <!-- Selected Price List: Items Management -->
        <div v-if="selectedPriceList" class="card p-6">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-semibold">Позиции: {{ selectedPriceList.name }}</h3>
                <div class="flex items-center gap-3">
                    <button @click="openBulkModal" class="btn-secondary text-sm">Массовое добавление</button>
                    <button @click="selectedPriceList = null" class="text-gray-400 hover:text-gray-600 text-xl">&times;</button>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Left: All dishes -->
                <div>
                    <h4 class="font-medium text-gray-700 mb-3">Все блюда</h4>
                    <div class="flex gap-2 mb-3">
                        <input v-model="dishSearch" type="text" placeholder="Поиск блюд..." class="input text-sm flex-1" />
                        <select v-model="dishCategoryFilter" class="input text-sm w-auto">
                            <option :value="null">Все категории</option>
                            <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                        </select>
                    </div>
                    <div class="max-h-[500px] overflow-y-auto space-y-1">
                        <div v-for="dish in filteredDishes" :key="dish.id"
                             class="flex items-center justify-between p-2 rounded hover:bg-gray-50 text-sm">
                            <div class="flex-1 min-w-0">
                                <span class="text-gray-900 truncate block">{{ dish.name }}</span>
                                <span class="text-gray-400 text-xs">{{ dish.price }} руб.</span>
                            </div>
                            <div class="flex items-center gap-2 ml-2">
                                <span v-if="isInPriceList(dish.id)" class="text-green-500 text-xs">В прайсе</span>
                                <button v-else @click="openAddItemModal(dish)" class="text-orange-500 hover:text-orange-700 text-xs font-medium">
                                    + Добавить
                                </button>
                            </div>
                        </div>
                        <div v-if="!filteredDishes.length" class="text-center py-4 text-gray-400 text-sm">
                            Блюда не найдены
                        </div>
                    </div>
                </div>

                <!-- Right: Price list items -->
                <div>
                    <h4 class="font-medium text-gray-700 mb-3">В прайс-листе ({{ priceListItems.length }})</h4>
                    <div class="max-h-[500px] overflow-y-auto space-y-1">
                        <div v-for="item in priceListItems" :key="item.id"
                             class="flex items-center justify-between p-2 rounded hover:bg-gray-50 text-sm group">
                            <div class="flex-1 min-w-0">
                                <span class="text-gray-900 truncate block">{{ item.dish?.name || 'Блюдо удалено' }}</span>
                                <div class="text-xs">
                                    <span class="text-gray-400 line-through mr-2">{{ item.dish?.price }} руб.</span>
                                    <span class="text-orange-600 font-medium">{{ item.price }} руб.</span>
                                </div>
                            </div>
                            <div class="flex items-center gap-2 ml-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                <button @click="editItemPrice(item)" class="text-blue-500 hover:text-blue-700 text-xs">Цена</button>
                                <button @click="removeItem(item)" class="text-red-500 hover:text-red-700 text-xs">Удалить</button>
                            </div>
                        </div>
                        <div v-if="!priceListItems.length" class="text-center py-8 text-gray-400 text-sm">
                            Нет позиций в прайс-листе
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create/Edit Modal -->
        <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center modal-backdrop" @click.self="showModal = false">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
                <h3 class="text-lg font-semibold mb-4">{{ editingPriceList ? 'Редактировать' : 'Создать' }} прайс-лист</h3>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Название</label>
                        <input v-model="formData.name" type="text" class="input" placeholder="Например: Бизнес-ланч" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Описание</label>
                        <textarea v-model="formData.description" class="input" rows="2" placeholder="Описание прайс-листа"></textarea>
                    </div>
                    <div class="flex items-center gap-4">
                        <label class="flex items-center gap-2 text-sm">
                            <input v-model="formData.is_default" type="checkbox" class="rounded" />
                            По умолчанию
                        </label>
                        <label class="flex items-center gap-2 text-sm">
                            <input v-model="formData.is_active" type="checkbox" class="rounded" />
                            Активен
                        </label>
                    </div>
                </div>
                <div class="flex justify-end gap-3 mt-6">
                    <button @click="showModal = false" class="btn-secondary">Отмена</button>
                    <button @click="savePriceList" class="btn-primary" :disabled="!formData.name || saving">
                        {{ saving ? 'Сохранение...' : 'Сохранить' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Add Item Price Modal -->
        <div v-if="showAddItemModal" class="fixed inset-0 z-50 flex items-center justify-center modal-backdrop" @click.self="showAddItemModal = false">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-6">
                <h3 class="text-lg font-semibold mb-4">Установить цену</h3>
                <p class="text-sm text-gray-500 mb-2">{{ addingDish?.name }}</p>
                <p class="text-xs text-gray-400 mb-4">Базовая цена: {{ addingDish?.price }} руб.</p>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Цена в прайс-листе</label>
                    <input v-model.number="addItemPrice" type="number" step="0.01" min="0" class="input" />
                </div>
                <div class="flex justify-end gap-3 mt-6">
                    <button @click="showAddItemModal = false" class="btn-secondary">Отмена</button>
                    <button @click="confirmAddItem" class="btn-primary" :disabled="addItemPrice === null || addItemPrice < 0">Добавить</button>
                </div>
            </div>
        </div>

        <!-- Bulk Add Modal -->
        <div v-if="showBulkModal" class="fixed inset-0 z-50 flex items-center justify-center modal-backdrop" @click.self="showBulkModal = false">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
                <h3 class="text-lg font-semibold mb-4">Массовое добавление</h3>
                <p class="text-sm text-gray-500 mb-4">Добавить все блюда ресторана в прайс-лист с корректировкой цены</p>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Корректировка цены</label>
                        <div class="flex items-center gap-2">
                            <select v-model="bulkMode" class="input w-auto">
                                <option value="percent_up">Наценка %</option>
                                <option value="percent_down">Скидка %</option>
                                <option value="same">Базовая цена</option>
                            </select>
                            <input v-if="bulkMode !== 'same'" v-model.number="bulkValue" type="number" step="1" min="0" max="100" class="input w-24" />
                            <span v-if="bulkMode !== 'same'" class="text-gray-500">%</span>
                        </div>
                    </div>
                    <div>
                        <label class="flex items-center gap-2 text-sm">
                            <input v-model="bulkOverwrite" type="checkbox" class="rounded" />
                            Перезаписать существующие цены
                        </label>
                    </div>
                </div>
                <div class="flex justify-end gap-3 mt-6">
                    <button @click="showBulkModal = false" class="btn-secondary">Отмена</button>
                    <button @click="executeBulkAdd" class="btn-primary" :disabled="bulkSaving">
                        {{ bulkSaving ? 'Сохранение...' : 'Добавить все блюда' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useBackofficeStore } from '../../stores/backoffice';

const store = useBackofficeStore();

const loading = ref(true);
const saving = ref(false);
const priceLists = ref([]);
const selectedPriceList = ref(null);
const priceListItems = ref([]);
const categories = ref([]);
const allDishes = ref([]);

// Modal state
const showModal = ref(false);
const editingPriceList = ref(null);
const formData = ref({ name: '', description: '', is_default: false, is_active: true });

// Add item
const showAddItemModal = ref(false);
const addingDish = ref(null);
const addItemPrice = ref(0);
const editingItem = ref(null);

// Bulk
const showBulkModal = ref(false);
const bulkMode = ref('same');
const bulkValue = ref(10);
const bulkOverwrite = ref(false);
const bulkSaving = ref(false);

// Filters
const dishSearch = ref('');
const dishCategoryFilter = ref(null);

const filteredDishes = computed(() => {
    let list = allDishes.value;
    if (dishCategoryFilter.value) {
        list = list.filter(d => d.category_id === dishCategoryFilter.value);
    }
    if (dishSearch.value) {
        const q = dishSearch.value.toLowerCase();
        list = list.filter(d => d.name.toLowerCase().includes(q));
    }
    return list;
});

const isInPriceList = (dishId) => {
    return priceListItems.value.some(item => item.dish_id === dishId);
};

// Data loading
const loadPriceLists = async () => {
    try {
        const data = await store.api('/backoffice/price-lists');
        priceLists.value = data.data || [];
    } catch (e) {
        console.error('Failed to load price lists:', e);
    }
};

const loadCategories = async () => {
    try {
        const data = await store.api('/backoffice/menu/categories');
        categories.value = data.data || [];
    } catch (e) {
        console.error('Failed to load categories:', e);
    }
};

const loadDishes = async () => {
    try {
        const data = await store.api('/backoffice/menu/dishes?include_variants=1');
        allDishes.value = data.data || [];
    } catch (e) {
        console.error('Failed to load dishes:', e);
    }
};

const loadPriceListItems = async (priceListId) => {
    try {
        const data = await store.api(`/backoffice/price-lists/${priceListId}/items`);
        priceListItems.value = data.data || [];
    } catch (e) {
        console.error('Failed to load items:', e);
    }
};

// Actions
const selectPriceList = async (pl) => {
    selectedPriceList.value = pl;
    await loadPriceListItems(pl.id);
};

const openCreateModal = () => {
    editingPriceList.value = null;
    formData.value = { name: '', description: '', is_default: false, is_active: true };
    showModal.value = true;
};

const openEditModal = (pl) => {
    editingPriceList.value = pl;
    formData.value = { name: pl.name, description: pl.description || '', is_default: pl.is_default, is_active: pl.is_active };
    showModal.value = true;
};

const savePriceList = async () => {
    saving.value = true;
    try {
        if (editingPriceList.value) {
            await store.api(`/backoffice/price-lists/${editingPriceList.value.id}`, {
                method: 'PUT',
                body: JSON.stringify(formData.value),
            });
            store.showToast('Прайс-лист обновлён', 'success');
        } else {
            await store.api('/backoffice/price-lists', {
                method: 'POST',
                body: JSON.stringify(formData.value),
            });
            store.showToast('Прайс-лист создан', 'success');
        }
        showModal.value = false;
        await loadPriceLists();
    } catch (e) {
        store.showToast(e.message || 'Ошибка сохранения', 'error');
    } finally {
        saving.value = false;
    }
};

const deletePriceList = async (pl) => {
    if (!confirm(`Удалить прайс-лист "${pl.name}"?`)) return;
    try {
        await store.api(`/backoffice/price-lists/${pl.id}`, { method: 'DELETE' });
        store.showToast('Прайс-лист удалён', 'success');
        if (selectedPriceList.value?.id === pl.id) {
            selectedPriceList.value = null;
            priceListItems.value = [];
        }
        await loadPriceLists();
    } catch (e) {
        store.showToast(e.message || 'Ошибка удаления', 'error');
    }
};

const toggleActive = async (pl) => {
    try {
        await store.api(`/backoffice/price-lists/${pl.id}/toggle`, { method: 'POST' });
        await loadPriceLists();
    } catch (e) {
        store.showToast(e.message || 'Ошибка', 'error');
    }
};

const setAsDefault = async (pl) => {
    try {
        await store.api(`/backoffice/price-lists/${pl.id}/default`, { method: 'POST' });
        await loadPriceLists();
        store.showToast(`"${pl.name}" установлен по умолчанию`, 'success');
    } catch (e) {
        store.showToast(e.message || 'Ошибка', 'error');
    }
};

// Item management
const openAddItemModal = (dish) => {
    addingDish.value = dish;
    addItemPrice.value = parseFloat(dish.price) || 0;
    editingItem.value = null;
    showAddItemModal.value = true;
};

const editItemPrice = (item) => {
    addingDish.value = item.dish;
    addItemPrice.value = parseFloat(item.price) || 0;
    editingItem.value = item;
    showAddItemModal.value = true;
};

const confirmAddItem = async () => {
    if (!selectedPriceList.value || addItemPrice.value === null) return;
    try {
        await store.api(`/backoffice/price-lists/${selectedPriceList.value.id}/items`, {
            method: 'POST',
            body: JSON.stringify({
                items: [{ dish_id: addingDish.value.id, price: addItemPrice.value }],
            }),
        });
        showAddItemModal.value = false;
        await loadPriceListItems(selectedPriceList.value.id);
        await loadPriceLists();
    } catch (e) {
        store.showToast(e.message || 'Ошибка', 'error');
    }
};

const removeItem = async (item) => {
    if (!selectedPriceList.value) return;
    try {
        await store.api(`/backoffice/price-lists/${selectedPriceList.value.id}/items/${item.dish_id}`, {
            method: 'DELETE',
        });
        await loadPriceListItems(selectedPriceList.value.id);
        await loadPriceLists();
    } catch (e) {
        store.showToast(e.message || 'Ошибка удаления', 'error');
    }
};

// Bulk
const openBulkModal = () => {
    bulkMode.value = 'same';
    bulkValue.value = 10;
    bulkOverwrite.value = false;
    showBulkModal.value = true;
};

const executeBulkAdd = async () => {
    if (!selectedPriceList.value) return;
    bulkSaving.value = true;
    try {
        const dishesToAdd = bulkOverwrite.value
            ? allDishes.value
            : allDishes.value.filter(d => !isInPriceList(d.id));

        if (!dishesToAdd.length) {
            store.showToast('Нет блюд для добавления', 'warning');
            bulkSaving.value = false;
            return;
        }

        const items = dishesToAdd.map(dish => {
            let price = parseFloat(dish.price) || 0;
            if (bulkMode.value === 'percent_up') {
                price = Math.round(price * (1 + bulkValue.value / 100) * 100) / 100;
            } else if (bulkMode.value === 'percent_down') {
                price = Math.round(price * (1 - bulkValue.value / 100) * 100) / 100;
            }
            return { dish_id: dish.id, price: Math.max(0, price) };
        });

        await store.api(`/backoffice/price-lists/${selectedPriceList.value.id}/items`, {
            method: 'POST',
            body: JSON.stringify({ items }),
        });

        showBulkModal.value = false;
        store.showToast(`Добавлено ${items.length} позиций`, 'success');
        await loadPriceListItems(selectedPriceList.value.id);
        await loadPriceLists();
    } catch (e) {
        store.showToast(e.message || 'Ошибка', 'error');
    } finally {
        bulkSaving.value = false;
    }
};

onMounted(async () => {
    await Promise.all([loadPriceLists(), loadCategories(), loadDishes()]);
    loading.value = false;
});
</script>
