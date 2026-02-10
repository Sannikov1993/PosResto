<template>
    <Teleport to="body">
        <div v-if="show" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" @click.self="$emit('close')">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-3xl max-h-[90vh] overflow-hidden flex flex-col">
                <!-- Header -->
                <div class="px-6 py-4 border-b flex items-center justify-between bg-gradient-to-r from-orange-500 to-amber-500">
                    <h3 class="text-lg font-semibold text-white">
                        {{ isEditing ? 'Редактировать ингредиент' : 'Новый ингредиент' }}
                    </h3>
                    <button @click="$emit('close')" class="text-white/80 hover:text-white text-xl">&times;</button>
                </div>

                <!-- Tabs -->
                <div class="flex border-b bg-gray-50">
                    <button
                        v-for="tab in tabs"
                        :key="tab.id"
                        @click="activeTab = tab.id"
                        :class="[
                            'px-6 py-3 text-sm font-medium border-b-2 -mb-px transition',
                            activeTab === tab.id
                                ? 'text-orange-500 border-orange-500 bg-white'
                                : 'text-gray-500 border-transparent hover:text-gray-700'
                        ]"
                    >
                        {{ tab.label }}
                    </button>
                </div>

                <!-- Content -->
                <div class="flex-1 overflow-y-auto p-6">
                    <!-- Tab: Basic -->
                    <div v-if="activeTab === 'basic'" class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div class="col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Название *</label>
                                <input
                                    v-model="form.name"
                                    type="text"
                                    class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                                    placeholder="Например: Молоко 3.2%"
                                />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Категория</label>
                                <select
                                    v-model="form.category_id"
                                    class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                                >
                                    <option :value="null">-- Без категории --</option>
                                    <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Базовая единица *</label>
                                <select
                                    v-model="form.unit_id"
                                    class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                                >
                                    <option v-for="unit in units" :key="unit.id" :value="unit.id">
                                        {{ unit.name }} ({{ unit.short_name }})
                                    </option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Себестоимость</label>
                                <input
                                    v-model.number="form.cost_price"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                                    placeholder="0.00"
                                />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Штрих-код</label>
                                <input
                                    v-model="form.barcode"
                                    type="text"
                                    class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                                />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Мин. остаток</label>
                                <input
                                    v-model.number="form.min_stock"
                                    type="number"
                                    step="0.001"
                                    min="0"
                                    class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                                />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Макс. остаток</label>
                                <input
                                    v-model.number="form.max_stock"
                                    type="number"
                                    step="0.001"
                                    min="0"
                                    class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                                />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Срок годности (дней)</label>
                                <input
                                    v-model.number="form.shelf_life_days"
                                    type="number"
                                    min="0"
                                    class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                                />
                            </div>
                            <div class="flex items-center gap-4">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input v-model="form.track_stock" type="checkbox" class="w-4 h-4 accent-orange-500 rounded" />
                                    <span class="text-sm text-gray-700">Вести учет остатков</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Tab: Conversion -->
                    <div v-if="activeTab === 'conversion'" class="space-y-6">
                        <div class="bg-orange-50 rounded-xl p-4 border border-orange-200">
                            <h4 class="font-medium text-orange-800 mb-3">Конвертация единиц</h4>
                            <p class="text-orange-700 text-sm mb-4">
                                Укажите параметры для автоматической конвертации между единицами измерения
                            </p>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Вес 1 штуки (кг)
                                    </label>
                                    <input
                                        v-model.number="form.piece_weight"
                                        type="number"
                                        step="0.0001"
                                        min="0"
                                        class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                                        placeholder="Например: 0.05 для яйца"
                                    />
                                    <p class="text-gray-500 text-xs mt-1">Яйцо = 0.05 кг, булочка = 0.06 кг</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Плотность (кг/л)
                                    </label>
                                    <input
                                        v-model.number="form.density"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                                        placeholder="Например: 1.03 для молока"
                                    />
                                    <p class="text-gray-500 text-xs mt-1">Молоко = 1.03, масло = 0.92</p>
                                </div>
                            </div>

                            <button
                                v-if="!suggestionsApplied"
                                @click="applySuggestions"
                                class="mt-4 px-4 py-2 bg-orange-100 hover:bg-orange-200 text-orange-700 rounded-lg text-sm font-medium transition"
                            >
                                Подсказать значения по названию
                            </button>
                        </div>
                    </div>

                    <!-- Tab: Losses -->
                    <div v-if="activeTab === 'losses'" class="space-y-6">
                        <div class="bg-yellow-50 rounded-xl p-4 border border-yellow-200">
                            <h4 class="font-medium text-yellow-800 mb-3">Потери при обработке</h4>
                            <p class="text-yellow-700 text-sm mb-4">
                                Укажите процент потерь для расчета брутто/нетто в рецептах
                            </p>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Холодная обработка (%)
                                    </label>
                                    <input
                                        v-model.number="form.cold_loss_percent"
                                        type="number"
                                        step="0.1"
                                        min="0"
                                        max="100"
                                        class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                                        placeholder="0"
                                    />
                                    <p class="text-gray-500 text-xs mt-1">Чистка, нарезка. Картофель ~20%, рыба ~35%</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Горячая обработка (%)
                                    </label>
                                    <input
                                        v-model.number="form.hot_loss_percent"
                                        type="number"
                                        step="0.1"
                                        min="0"
                                        max="100"
                                        class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                                        placeholder="0"
                                    />
                                    <p class="text-gray-500 text-xs mt-1">Жарка, варка. Мясо ~35%, овощи ~10%</p>
                                </div>
                            </div>
                        </div>

                        <!-- Example calculation -->
                        <div v-if="form.cold_loss_percent > 0 || form.hot_loss_percent > 0" class="bg-gray-50 rounded-xl p-4 border">
                            <h4 class="font-medium text-gray-700 mb-2">Пример расчета</h4>
                            <p class="text-sm text-gray-600">
                                Для получения <strong>100 г</strong> нетто нужно:
                            </p>
                            <ul class="text-sm text-gray-600 mt-2 space-y-1">
                                <li v-if="form.cold_loss_percent > 0">
                                    Холодная обработка: <strong>{{ calcGross(100, 'cold').toFixed(1) }} г</strong> брутто
                                </li>
                                <li v-if="form.hot_loss_percent > 0">
                                    Горячая обработка: <strong>{{ calcGross(100, 'hot').toFixed(1) }} г</strong> брутто
                                </li>
                                <li v-if="form.cold_loss_percent > 0 && form.hot_loss_percent > 0">
                                    Полная обработка: <strong>{{ calcGross(100, 'both').toFixed(1) }} г</strong> брутто
                                </li>
                            </ul>
                        </div>
                    </div>

                    <!-- Tab: Packagings -->
                    <div v-if="activeTab === 'packagings'" class="space-y-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <h4 class="font-medium text-gray-700">Фасовки</h4>
                                <p class="text-sm text-gray-500">Единицы для приёмки товара (коробки, упаковки)</p>
                            </div>
                            <button
                                @click="addPackaging"
                                class="px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white rounded-lg text-sm font-medium transition"
                            >
                                + Добавить фасовку
                            </button>
                        </div>

                        <div v-if="packagings.length" class="space-y-3">
                            <div
                                v-for="(pkg, index) in packagings"
                                :key="index"
                                class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg border"
                            >
                                <div class="flex-1">
                                    <input
                                        v-model="pkg.name"
                                        type="text"
                                        placeholder="Название (коробка, упаковка)"
                                        class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                                    />
                                </div>
                                <div class="w-24">
                                    <input
                                        v-model.number="pkg.quantity"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        placeholder="Кол-во"
                                        class="w-full px-3 py-2 border rounded-lg text-sm text-center focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                                    />
                                </div>
                                <div class="w-24">
                                    <select
                                        v-model="pkg.unit_id"
                                        class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                                    >
                                        <option v-for="u in units" :key="u.id" :value="u.id">{{ u.short_name }}</option>
                                    </select>
                                </div>
                                <div class="w-32">
                                    <input
                                        v-model="pkg.barcode"
                                        type="text"
                                        placeholder="Штрих-код"
                                        class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                                    />
                                </div>
                                <label class="flex items-center gap-1 cursor-pointer">
                                    <input v-model="pkg.is_default" type="checkbox" class="w-4 h-4 accent-orange-500 rounded" />
                                    <span class="text-xs text-gray-500">По умолч.</span>
                                </label>
                                <button
                                    @click="removePackaging(index)"
                                    class="p-1.5 text-red-500 hover:text-red-600 hover:bg-red-50 rounded transition"
                                >
                                    &times;
                                </button>
                            </div>
                        </div>

                        <div v-else class="text-center py-8 text-gray-400">
                            Фасовки не добавлены. Добавьте фасовки для удобной приёмки товара.
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="px-6 py-4 border-t flex items-center justify-end gap-3 bg-gray-50">
                    <button
                        @click="$emit('close')"
                        class="px-6 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg font-medium transition"
                    >
                        Отмена
                    </button>
                    <button
                        @click="save"
                        :disabled="saving || !isValid"
                        class="px-6 py-2.5 bg-orange-500 hover:bg-orange-600 disabled:bg-gray-300 text-white rounded-lg font-medium transition"
                    >
                        {{ saving ? 'Сохранение...' : (isEditing ? 'Сохранить' : 'Создать') }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted, PropType } from 'vue';
import { useBackofficeStore } from '../stores/backoffice';

const props = defineProps({
    show: { type: Boolean, default: false },
    ingredient: { type: Object as PropType<Record<string, any>>, default: null },
    categories: { type: Array as PropType<any[]>, default: () => [] },
    units: { type: Array as PropType<any[]>, default: () => [] }
});

const emit = defineEmits(['close', 'saved']);

const store = useBackofficeStore();

const tabs = [
    { id: 'basic', label: 'Основное' },
    { id: 'conversion', label: 'Конвертация' },
    { id: 'losses', label: 'Потери' },
    { id: 'packagings', label: 'Фасовки' }
];

const activeTab = ref('basic');
const saving = ref(false);
const suggestionsApplied = ref(false);

const isEditing = computed(() => !!props.ingredient?.id);

// Form
const form = ref({
    id: null as any,
    name: '',
    category_id: null as any,
    unit_id: null as any,
    cost_price: 0,
    barcode: '',
    min_stock: 0,
    max_stock: null as any,
    shelf_life_days: null as any,
    track_stock: true,
    piece_weight: null as any,
    density: null as any,
    cold_loss_percent: 0,
    hot_loss_percent: 0
});

// Packagings
const packagings = ref<any[]>([]);
const packagingsToDelete = ref<any[]>([]);

// Validation
const isValid = computed(() => {
    return form.value.name?.trim() && form.value.unit_id;
});

// Calculate gross from net
function calcGross(net: any, type: any) {
    const coldLoss = form.value.cold_loss_percent || 0;
    const hotLoss = form.value.hot_loss_percent || 0;

    if (type === 'cold') {
        return net / (1 - coldLoss / 100);
    } else if (type === 'hot') {
        return net / (1 - hotLoss / 100);
    } else if (type === 'both') {
        const afterCold = net / (1 - hotLoss / 100);
        return afterCold / (1 - coldLoss / 100);
    }
    return net;
}

// Suggestions based on name
const defaultValues = {
    density: {
        'молоко': 1.03, 'сливки': 1.01, 'кефир': 1.03, 'йогурт': 1.05,
        'масло': 0.92, 'растительное': 0.92, 'оливковое': 0.91,
        'мёд': 1.42, 'сироп': 1.3, 'соус': 1.1
    },
    piece_weight: {
        'яйцо': 0.05, 'яйца': 0.05, 'лимон': 0.12, 'апельсин': 0.2,
        'помидор': 0.15, 'огурец': 0.12, 'картофель': 0.1, 'картошка': 0.1,
        'луковица': 0.08, 'лук': 0.08, 'морковь': 0.1, 'яблоко': 0.18,
        'банан': 0.15, 'булочка': 0.06, 'хлеб': 0.4
    },
    cold_loss: {
        'картофель': 20, 'картошка': 20, 'морковь': 20, 'свёкла': 20, 'свекла': 20,
        'лук': 16, 'капуста': 20, 'рыба': 35, 'мясо': 26, 'курица': 22,
        'говядина': 26, 'свинина': 15
    },
    hot_loss: {
        'мясо': 35, 'говядина': 40, 'свинина': 35, 'курица': 30,
        'рыба': 20, 'овощи': 10, 'картофель': 3
    }
};

function applySuggestions() {
    const name = form.value.name?.toLowerCase() || '';

    // Find matching values
    for (const [key, value] of Object.entries(defaultValues.density)) {
        if (name.includes(key) && !form.value.density) {
            form.value.density = value;
            break;
        }
    }

    for (const [key, value] of Object.entries(defaultValues.piece_weight)) {
        if (name.includes(key) && !form.value.piece_weight) {
            form.value.piece_weight = value;
            break;
        }
    }

    for (const [key, value] of Object.entries(defaultValues.cold_loss)) {
        if (name.includes(key) && !form.value.cold_loss_percent) {
            form.value.cold_loss_percent = value;
            break;
        }
    }

    for (const [key, value] of Object.entries(defaultValues.hot_loss)) {
        if (name.includes(key) && !form.value.hot_loss_percent) {
            form.value.hot_loss_percent = value;
            break;
        }
    }

    suggestionsApplied.value = true;
}

// Packagings
function addPackaging() {
    packagings.value.push({
        id: null as any,
        name: '',
        unit_id: form.value.unit_id,
        quantity: 1,
        barcode: '',
        is_default: packagings.value.length === 0
    });
}

function removePackaging(index: any) {
    const pkg = packagings.value[index];
    if (pkg.id) {
        packagingsToDelete.value.push(pkg.id);
    }
    packagings.value.splice(index, 1);
}

// Save
async function save() {
    if (!isValid.value) return;

    saving.value = true;
    try {
        const data = { ...form.value };

        let result;
        if (isEditing.value) {
            result = await store.api(`/backoffice/inventory/ingredients/${form.value.id}`, {
                method: 'PUT',
                body: JSON.stringify(data)
            });
        } else {
            result = await store.api('/backoffice/inventory/ingredients', {
                method: 'POST',
                body: JSON.stringify(data)
            });
        }

        const savedIngredient = result.data || result;

        // Save packagings
        if ((savedIngredient as any)?.id) {
            // Delete removed packagings
            for (const id of packagingsToDelete.value) {
                await store.api(`/backoffice/inventory/packagings/${id}`, { method: 'DELETE' }).catch(() => {});
            }

            // Save new/updated packagings
            for (const pkg of packagings.value) {
                const pkgData = { ...pkg, ingredient_id: (savedIngredient as any).id };
                if (pkg.id) {
                    await store.api(`/backoffice/inventory/packagings/${pkg.id}`, {
                        method: 'PUT',
                        body: JSON.stringify(pkgData)
                    }).catch(() => {});
                } else {
                    await store.api(`/backoffice/inventory/ingredients/${(savedIngredient as any).id}/packagings`, {
                        method: 'POST',
                        body: JSON.stringify(pkgData)
                    }).catch(() => {});
                }
            }
        }

        store.showToast(isEditing.value ? 'Ингредиент обновлен' : 'Ингредиент создан', 'success');
        emit('saved', savedIngredient);
    } catch (e: any) {
        console.error('Error saving ingredient:', e);
        store.showToast(e.response?.data?.message || 'Ошибка сохранения', 'error');
    } finally {
        saving.value = false;
    }
}

// Initialize form
function initForm() {
    if (props.ingredient) {
        form.value = {
            id: props.ingredient.id,
            name: props.ingredient.name || '',
            category_id: props.ingredient.category_id || null,
            unit_id: props.ingredient.unit_id || props.units[0]?.id,
            cost_price: props.ingredient.cost_price || 0,
            barcode: props.ingredient.barcode || '',
            min_stock: props.ingredient.min_stock || 0,
            max_stock: props.ingredient.max_stock || null,
            shelf_life_days: props.ingredient.shelf_life_days || null,
            track_stock: props.ingredient.track_stock !== false,
            piece_weight: props.ingredient.piece_weight || null,
            density: props.ingredient.density || null,
            cold_loss_percent: props.ingredient.cold_loss_percent || 0,
            hot_loss_percent: props.ingredient.hot_loss_percent || 0
        };
        packagings.value = (props.ingredient.packagings || []).map((p: any) => ({ ...p }));
    } else {
        form.value = {
            id: null as any,
            name: '',
            category_id: null as any,
            unit_id: props.units[0]?.id || null,
            cost_price: 0,
            barcode: '',
            min_stock: 0,
            max_stock: null as any,
            shelf_life_days: null as any,
            track_stock: true,
            piece_weight: null as any,
            density: null as any,
            cold_loss_percent: 0,
            hot_loss_percent: 0
        };
        packagings.value = [];
    }
    packagingsToDelete.value = [];
    suggestionsApplied.value = false;
    activeTab.value = 'basic';
}

// Watch for changes
watch(() => props.show, (newVal) => {
    if (newVal) {
        initForm();
    }
});

watch(() => props.ingredient, () => {
    if (props.show) {
        initForm();
    }
});

onMounted(() => {
    if (props.show) {
        initForm();
    }
});
</script>
