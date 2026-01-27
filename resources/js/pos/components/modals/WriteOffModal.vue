<template>
    <Teleport to="body">
        <div v-if="modelValue" class="fixed inset-0 bg-black/80 z-50 flex items-center justify-center p-4" @click.self="close">
            <div class="bg-gray-900 rounded-2xl w-full max-w-5xl max-h-[90vh] flex flex-col">
                <!-- Header -->
                <div class="p-4 border-b border-gray-800 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-red-500/20 rounded-xl flex items-center justify-center">
                            <span class="text-xl">📝</span>
                        </div>
                        <h2 class="text-lg font-semibold text-white">Новое списание</h2>
                    </div>
                    <button @click="close" class="text-gray-500 hover:text-white text-2xl">&times;</button>
                </div>

                <!-- Mode tabs -->
                <div class="px-4 py-3 border-b border-gray-800 flex gap-2">
                    <button v-for="mode in modes" :key="mode.value"
                        @click="activeMode = mode.value"
                        :class="[
                            'px-4 py-2 rounded-lg text-sm font-medium transition-colors',
                            activeMode === mode.value
                                ? 'bg-accent text-white'
                                : 'bg-gray-800 text-gray-400 hover:bg-gray-700'
                        ]">
                        {{ mode.icon }} {{ mode.label }}
                    </button>
                </div>

                <!-- Content -->
                <div class="flex-1 flex overflow-hidden">
                    <!-- Left: Selection panel -->
                    <div class="w-1/2 flex flex-col border-r border-gray-800 overflow-hidden">
                        <!-- Manual mode -->
                        <div v-if="activeMode === 'manual'" class="p-4 space-y-4 overflow-y-auto">
                            <div>
                                <label class="block text-sm text-gray-400 mb-2">Сумма списания *</label>
                                <input v-model.number="manualAmount"
                                    type="number" min="0" step="0.01"
                                    placeholder="0.00"
                                    class="w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-3 text-white text-xl text-center focus:border-accent focus:outline-none" />
                            </div>
                        </div>

                        <!-- Menu mode -->
                        <template v-else-if="activeMode === 'menu'">
                            <!-- Search -->
                            <div class="p-3 border-b border-gray-800">
                                <input v-model="menuSearch"
                                    type="text" placeholder="Поиск блюда..."
                                    class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-white placeholder-gray-500 focus:border-accent focus:outline-none" />
                            </div>

                            <!-- Categories -->
                            <div class="flex gap-2 p-3 overflow-x-auto border-b border-gray-800 shrink-0">
                                <button @click="selectedCategory = null"
                                    :class="['px-3 py-1.5 rounded-lg text-sm whitespace-nowrap', !selectedCategory ? 'bg-accent text-white' : 'bg-gray-800 text-gray-400']">
                                    Все
                                </button>
                                <button v-for="cat in categories" :key="cat.id"
                                    @click="selectedCategory = cat.id"
                                    :class="['px-3 py-1.5 rounded-lg text-sm whitespace-nowrap', selectedCategory === cat.id ? 'bg-accent text-white' : 'bg-gray-800 text-gray-400']">
                                    {{ cat.icon || '📁' }} {{ cat.name }}
                                </button>
                            </div>

                            <!-- Dishes grid -->
                            <div class="flex-1 overflow-y-auto p-3">
                                <div class="grid grid-cols-2 gap-2">
                                    <div v-for="dish in filteredDishes" :key="dish.id"
                                        @click="addDishToItems(dish)"
                                        class="bg-gray-800 rounded-lg p-3 cursor-pointer hover:bg-gray-700 transition-colors">
                                        <div class="flex items-center gap-2">
                                            <span class="text-2xl">{{ dish.emoji || '🍽️' }}</span>
                                            <div class="flex-1 min-w-0">
                                                <p class="text-sm font-medium text-white truncate">{{ dish.name }}</p>
                                                <p class="text-accent text-sm font-bold">{{ dish.price }} ₽</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <p v-if="filteredDishes.length === 0" class="text-center text-gray-500 py-8">
                                    Блюда не найдены
                                </p>
                            </div>
                        </template>

                        <!-- Inventory mode -->
                        <template v-else-if="activeMode === 'inventory'">
                            <!-- Warehouse selector -->
                            <div class="p-3 border-b border-gray-800">
                                <label class="block text-sm text-gray-400 mb-2">Склад</label>
                                <select v-model="selectedWarehouse"
                                    class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-white">
                                    <option v-for="wh in warehouses" :key="wh.id" :value="wh.id">
                                        {{ wh.name }}
                                    </option>
                                </select>
                            </div>

                            <!-- Search -->
                            <div class="p-3 border-b border-gray-800">
                                <input v-model="ingredientSearch"
                                    type="text" placeholder="Поиск ингредиента..."
                                    class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-white placeholder-gray-500 focus:border-accent focus:outline-none" />
                            </div>

                            <!-- Ingredients list -->
                            <div class="flex-1 overflow-y-auto p-3">
                                <div v-if="loadingIngredients" class="text-center text-gray-500 py-8">
                                    Загрузка...
                                </div>
                                <div v-else class="space-y-2">
                                    <div v-for="ing in filteredIngredients" :key="ing.id"
                                        @click="addIngredientToItems(ing)"
                                        class="bg-gray-800 rounded-lg p-3 cursor-pointer hover:bg-gray-700 transition-colors">
                                        <div class="flex items-center justify-between">
                                            <div>
                                                <p class="text-sm font-medium text-white">{{ ing.name }}</p>
                                                <p class="text-xs text-gray-500">{{ ing.unit?.short_name || 'шт' }}</p>
                                            </div>
                                            <p class="text-accent text-sm font-bold">{{ ing.cost_price || 0 }} ₽</p>
                                        </div>
                                    </div>
                                </div>
                                <p v-if="!loadingIngredients && filteredIngredients.length === 0" class="text-center text-gray-500 py-8">
                                    Ингредиенты не найдены
                                </p>
                            </div>
                        </template>
                    </div>

                    <!-- Right: Selected items & photo -->
                    <div class="w-1/2 flex flex-col overflow-hidden">
                        <!-- Selected items list -->
                        <div class="flex-1 overflow-y-auto p-4">
                            <h3 class="text-sm text-gray-400 mb-3">
                                Выбранные позиции
                                <span v-if="selectedItems.length" class="text-white">({{ selectedItems.length }})</span>
                            </h3>

                            <div v-if="selectedItems.length === 0" class="text-center text-gray-600 py-8">
                                <p class="text-4xl mb-2">📋</p>
                                <p>Выберите позиции для списания</p>
                            </div>

                            <div v-else class="space-y-2">
                                <div v-for="(item, index) in selectedItems" :key="index"
                                    class="bg-gray-800 rounded-lg p-3 flex items-center gap-3">
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-white truncate">{{ item.name }}</p>
                                        <p class="text-xs text-gray-500">{{ item.unit_price }} ₽ × {{ item.quantity }}</p>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <button @click="decrementQuantity(index)"
                                            class="w-8 h-8 bg-gray-700 rounded-lg text-white hover:bg-gray-600">-</button>
                                        <span class="w-8 text-center text-white">{{ item.quantity }}</span>
                                        <button @click="incrementQuantity(index)"
                                            class="w-8 h-8 bg-gray-700 rounded-lg text-white hover:bg-gray-600">+</button>
                                    </div>
                                    <p class="text-accent font-bold w-20 text-right">{{ (item.unit_price * item.quantity).toFixed(0) }} ₽</p>
                                    <button @click="removeItem(index)" class="text-red-400 hover:text-red-300">✕</button>
                                </div>
                            </div>
                        </div>

                        <!-- Photo upload -->
                        <div class="p-4 border-t border-gray-800">
                            <h3 class="text-sm text-gray-400 mb-2">Фото (опционально)</h3>

                            <!-- Preview -->
                            <div v-if="photoPreview" class="relative mb-3">
                                <img :src="photoPreview" class="w-full h-32 object-cover rounded-lg" />
                                <button @click="removePhoto"
                                    class="absolute top-2 right-2 p-1.5 bg-black/60 rounded-full text-white hover:bg-black/80">
                                    ✕
                                </button>
                            </div>

                            <!-- Upload buttons -->
                            <div v-else class="flex gap-2">
                                <label class="flex-1 py-2 bg-gray-800 rounded-lg text-center cursor-pointer hover:bg-gray-700 text-sm text-gray-400">
                                    📷 Камера
                                    <input type="file" accept="image/*" capture="environment" @change="handlePhoto" class="hidden" />
                                </label>
                                <label class="flex-1 py-2 bg-gray-800 rounded-lg text-center cursor-pointer hover:bg-gray-700 text-sm text-gray-400">
                                    🖼️ Галерея
                                    <input type="file" accept="image/*" @change="handlePhoto" class="hidden" />
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="p-4 border-t border-gray-800 space-y-4">
                    <!-- Type & Description -->
                    <div class="flex gap-4">
                        <div class="w-1/3">
                            <label class="block text-sm text-gray-400 mb-1">Тип списания *</label>
                            <select v-model="form.type"
                                class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-white">
                                <option value="">Выберите тип</option>
                                <option value="spoilage">Порча продукта</option>
                                <option value="expired">Истек срок годности</option>
                                <option value="loss">Потеря/недостача</option>
                                <option value="staff_meal">Питание персонала</option>
                                <option value="promo">Промо/дегустация</option>
                                <option value="other">Другое</option>
                            </select>
                        </div>
                        <div class="flex-1">
                            <label class="block text-sm text-gray-400 mb-1">Описание</label>
                            <input v-model="form.description"
                                type="text" placeholder="Опишите причину списания..."
                                class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-white placeholder-gray-500 focus:border-accent focus:outline-none" />
                        </div>
                    </div>

                    <!-- Total & Actions -->
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-400">Итого:</p>
                            <p class="text-2xl font-bold text-red-400">-{{ formatPrice(totalAmount) }} ₽</p>
                            <p v-if="totalAmount > approvalThreshold" class="text-xs text-orange-400 mt-1">
                                ⚠ Требуется подтверждение менеджера
                            </p>
                        </div>
                        <div class="flex gap-3">
                            <button @click="close"
                                class="px-6 py-3 bg-gray-800 text-gray-400 rounded-xl hover:bg-gray-700">
                                Отмена
                            </button>
                            <button @click="submit"
                                :disabled="!canSubmit || loading"
                                :class="[
                                    'px-6 py-3 rounded-xl font-medium transition-colors',
                                    canSubmit && !loading
                                        ? 'bg-red-600 hover:bg-red-700 text-white'
                                        : 'bg-gray-700 text-gray-500 cursor-not-allowed'
                                ]">
                                <span v-if="loading" class="flex items-center gap-2">
                                    <svg class="animate-spin h-5 w-5" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    Сохранение...
                                </span>
                                <span v-else>Создать списание</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Manager PIN Modal -->
        <div v-if="showPinModal" class="fixed inset-0 bg-black/80 z-[60] flex items-center justify-center">
            <div class="bg-gray-900 rounded-xl w-full max-w-sm p-6">
                <h3 class="text-lg font-semibold text-white mb-2">Подтверждение менеджера</h3>
                <p class="text-sm text-gray-400 mb-4">
                    Сумма списания превышает {{ formatPrice(approvalThreshold) }} ₽.
                    Требуется PIN-код менеджера.
                </p>

                <input v-model="managerPin"
                    type="password" maxlength="6"
                    placeholder="Введите PIN"
                    class="w-full text-center text-2xl tracking-widest bg-gray-800 border border-gray-700 rounded-lg py-3 text-white mb-3 focus:border-accent focus:outline-none"
                    @keyup.enter="verifyPin" />

                <p v-if="pinError" class="text-red-400 text-sm text-center mb-3">{{ pinError }}</p>

                <div class="flex gap-3">
                    <button @click="closePinModal"
                        class="flex-1 py-2.5 bg-gray-800 text-gray-400 rounded-lg hover:bg-gray-700">
                        Отмена
                    </button>
                    <button @click="verifyPin"
                        :disabled="managerPin.length < 4 || verifyingPin"
                        :class="[
                            'flex-1 py-2.5 rounded-lg font-medium',
                            managerPin.length >= 4 && !verifyingPin
                                ? 'bg-accent text-white hover:bg-accent/90'
                                : 'bg-gray-700 text-gray-500 cursor-not-allowed'
                        ]">
                        {{ verifyingPin ? 'Проверка...' : 'Подтвердить' }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<script setup>
import { ref, reactive, computed, watch, onMounted } from 'vue';
import { usePosStore } from '../../stores/pos';
import api from '../../api';

const props = defineProps({
    modelValue: { type: Boolean, default: false }
});

const emit = defineEmits(['update:modelValue', 'created']);

const posStore = usePosStore();

// ==================== STATE ====================

const modes = [
    { value: 'manual', label: 'Вручную', icon: '✏️' },
    { value: 'menu', label: 'Из меню', icon: '🍽️' },
    { value: 'inventory', label: 'Со склада', icon: '📦' },
];

const activeMode = ref('menu');
const loading = ref(false);

// Form state
const form = reactive({
    type: '',
    description: '',
});

// Manual mode
const manualAmount = ref(0);

// Menu mode
const menuSearch = ref('');
const selectedCategory = ref(null);

// Inventory mode
const ingredientSearch = ref('');
const selectedWarehouse = ref(null);
const warehouses = ref([]);
const ingredients = ref([]);
const loadingIngredients = ref(false);

// Selected items
const selectedItems = ref([]);

// Photo
const photo = ref(null);
const photoPreview = ref(null);

// Manager approval
const approvalThreshold = ref(1000);
const showPinModal = ref(false);
const managerPin = ref('');
const pinError = ref('');
const verifyingPin = ref(false);
const verifiedManagerId = ref(null);

// ==================== COMPUTED ====================

const categories = computed(() => posStore.menuCategories || []);
const dishes = computed(() => posStore.menuDishes || []);

const filteredDishes = computed(() => {
    let result = dishes.value;

    if (menuSearch.value) {
        const search = menuSearch.value.toLowerCase();
        result = result.filter(d => d.name.toLowerCase().includes(search));
    } else if (selectedCategory.value) {
        result = result.filter(d => d.category_id === selectedCategory.value);
    }

    return result;
});

const filteredIngredients = computed(() => {
    if (!ingredientSearch.value) return ingredients.value;
    const search = ingredientSearch.value.toLowerCase();
    return ingredients.value.filter(i => i.name.toLowerCase().includes(search));
});

const totalAmount = computed(() => {
    if (activeMode.value === 'manual') {
        return manualAmount.value || 0;
    }
    return selectedItems.value.reduce((sum, item) => sum + (item.unit_price * item.quantity), 0);
});

const canSubmit = computed(() => {
    if (!form.type) return false;
    if (activeMode.value === 'manual') {
        return manualAmount.value > 0;
    }
    return selectedItems.value.length > 0;
});

// ==================== METHODS ====================

const formatPrice = (price) => {
    return new Intl.NumberFormat('ru-RU').format(price || 0);
};

// Add dish to items
const addDishToItems = (dish) => {
    const existing = selectedItems.value.find(i => i.dish_id === dish.id && i.item_type === 'dish');
    if (existing) {
        existing.quantity++;
    } else {
        selectedItems.value.push({
            item_type: 'dish',
            dish_id: dish.id,
            name: dish.name,
            unit_price: dish.price,
            quantity: 1,
        });
    }
};

// Add ingredient to items
const addIngredientToItems = (ingredient) => {
    const existing = selectedItems.value.find(i => i.ingredient_id === ingredient.id && i.item_type === 'ingredient');
    if (existing) {
        existing.quantity++;
    } else {
        selectedItems.value.push({
            item_type: 'ingredient',
            ingredient_id: ingredient.id,
            name: ingredient.name,
            unit_price: ingredient.cost_price || 0,
            quantity: 1,
        });
    }
};

const incrementQuantity = (index) => {
    selectedItems.value[index].quantity++;
};

const decrementQuantity = (index) => {
    if (selectedItems.value[index].quantity > 1) {
        selectedItems.value[index].quantity--;
    } else {
        removeItem(index);
    }
};

const removeItem = (index) => {
    selectedItems.value.splice(index, 1);
};

// Photo handling
const handlePhoto = (event) => {
    const file = event.target.files[0];
    if (!file) return;

    // Validate size (5MB max)
    if (file.size > 5 * 1024 * 1024) {
        window.$toast?.('Файл слишком большой (макс. 5 МБ)', 'error');
        return;
    }

    // Validate type
    if (!file.type.startsWith('image/')) {
        window.$toast?.('Разрешены только изображения', 'error');
        return;
    }

    photo.value = file;

    // Create preview
    const reader = new FileReader();
    reader.onload = (e) => {
        photoPreview.value = e.target.result;
    };
    reader.readAsDataURL(file);
};

const removePhoto = () => {
    photo.value = null;
    photoPreview.value = null;
};

// Load warehouses and ingredients
const loadWarehouses = async () => {
    try {
        const response = await fetch('/api/inventory/warehouses');
        const data = await response.json();
        warehouses.value = data.data || data || [];
        if (warehouses.value.length > 0) {
            selectedWarehouse.value = warehouses.value[0].id;
        }
    } catch (e) {
        console.error('Error loading warehouses:', e);
    }
};

const loadIngredients = async () => {
    loadingIngredients.value = true;
    try {
        const response = await fetch('/api/inventory/ingredients');
        const data = await response.json();
        ingredients.value = data.data || data || [];
    } catch (e) {
        console.error('Error loading ingredients:', e);
    } finally {
        loadingIngredients.value = false;
    }
};

const loadSettings = async () => {
    try {
        const settings = await api.writeOffs.getSettings();
        approvalThreshold.value = settings.approval_threshold || 1000;
    } catch (e) {
        console.error('Error loading settings:', e);
    }
};

// Manager PIN verification
const verifyPin = async () => {
    if (managerPin.value.length < 4) return;

    verifyingPin.value = true;
    pinError.value = '';

    try {
        const result = await api.writeOffs.verifyManager(managerPin.value);
        if (result.success) {
            verifiedManagerId.value = result.data.manager_id;
            showPinModal.value = false;
            // Proceed with submission
            await doSubmit();
        } else {
            pinError.value = result.message || 'Неверный PIN-код';
        }
    } catch (e) {
        pinError.value = e.response?.data?.message || 'Ошибка проверки PIN';
    } finally {
        verifyingPin.value = false;
    }
};

const closePinModal = () => {
    showPinModal.value = false;
    managerPin.value = '';
    pinError.value = '';
};

// Submit
const submit = async () => {
    if (!canSubmit.value) return;

    // Check if manager approval needed
    if (totalAmount.value > approvalThreshold.value && !verifiedManagerId.value) {
        showPinModal.value = true;
        return;
    }

    await doSubmit();
};

const doSubmit = async () => {
    loading.value = true;

    try {
        const data = {
            type: form.type,
            description: form.description,
            manager_id: verifiedManagerId.value,
        };

        // Add photo if exists
        if (photo.value) {
            data.photo = photo.value;
        }

        // Add warehouse if inventory mode
        if (activeMode.value === 'inventory' && selectedWarehouse.value) {
            data.warehouse_id = selectedWarehouse.value;
        }

        // Add items or amount
        if (activeMode.value === 'manual') {
            data.amount = manualAmount.value;
            data.items = [{
                item_type: 'manual',
                name: form.description || 'Ручное списание',
                unit_price: manualAmount.value,
                quantity: 1,
            }];
        } else {
            data.items = selectedItems.value;
        }

        const result = await api.writeOffs.create(data);

        if (result.success) {
            window.$toast?.('Списание создано', 'success');
            emit('created', result.data);
            close();
        } else {
            window.$toast?.(result.message || 'Ошибка создания списания', 'error');
        }
    } catch (e) {
        console.error('Error creating write-off:', e);
        const message = e.response?.data?.message || 'Ошибка создания списания';
        window.$toast?.(message, 'error');
    } finally {
        loading.value = false;
    }
};

const close = () => {
    emit('update:modelValue', false);
};

// Reset form when modal opens
const resetForm = () => {
    activeMode.value = 'menu';
    form.type = '';
    form.description = '';
    manualAmount.value = 0;
    menuSearch.value = '';
    selectedCategory.value = null;
    ingredientSearch.value = '';
    selectedItems.value = [];
    photo.value = null;
    photoPreview.value = null;
    verifiedManagerId.value = null;
    showPinModal.value = false;
    managerPin.value = '';
    pinError.value = '';
};

// ==================== WATCHERS ====================

watch(() => props.modelValue, (val) => {
    if (val) {
        resetForm();
        loadSettings();
        // Load menu if not loaded
        if (!posStore.menuDishes.length) {
            posStore.loadMenu();
        }
    }
});

watch(activeMode, (mode) => {
    if (mode === 'inventory' && warehouses.value.length === 0) {
        loadWarehouses();
        loadIngredients();
    }
});

// ==================== LIFECYCLE ====================

onMounted(() => {
    if (props.modelValue) {
        loadSettings();
    }
});
</script>
