<template>
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/70" @click.self="$emit('close')">
        <div class="bg-dark-900 rounded-xl w-full max-w-3xl max-h-[90vh] overflow-hidden flex flex-col">
            <!-- Header -->
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-700">
                <h2 class="text-xl font-bold text-white">
                    {{ isEditing ? 'Редактирование ингредиента' : 'Новый ингредиент' }}
                </h2>
                <button @click="$emit('close')" class="text-gray-400 hover:text-white">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Tabs -->
            <div class="flex border-b border-gray-700 px-6">
                <button
                    v-for="tab in tabs"
                    :key="tab.id"
                    @click="activeTab = tab.id"
                    :class="[
                        'px-4 py-3 text-sm font-medium border-b-2 -mb-px transition-colors',
                        activeTab === tab.id
                            ? 'text-accent border-accent'
                            : 'text-gray-400 border-transparent hover:text-white'
                    ]"
                >
                    {{ tab.label }}
                </button>
            </div>

            <!-- Content -->
            <div class="flex-1 overflow-y-auto p-6">
                <!-- Tab: Основное -->
                <div v-if="activeTab === 'basic'" class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="col-span-2">
                            <label class="block text-sm font-medium text-gray-400 mb-1">Название *</label>
                            <input
                                v-model="form.name"
                                type="text"
                                class="w-full bg-dark-800 text-white rounded-lg px-4 py-2.5 border border-gray-700 focus:border-accent focus:outline-none"
                                placeholder="Например: Молоко 3.2%"
                            />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-400 mb-1">Категория</label>
                            <select
                                v-model="form.category_id"
                                class="w-full bg-dark-800 text-white rounded-lg px-4 py-2.5 border border-gray-700 focus:border-accent focus:outline-none"
                            >
                                <option :value="null">-- Без категории --</option>
                                <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-400 mb-1">Базовая единица *</label>
                            <select
                                v-model="form.unit_id"
                                class="w-full bg-dark-800 text-white rounded-lg px-4 py-2.5 border border-gray-700 focus:border-accent focus:outline-none"
                            >
                                <option v-for="unit in units" :key="unit.id" :value="unit.id">
                                    {{ unit.name }} ({{ unit.short_name }})
                                </option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-400 mb-1">Себестоимость</label>
                            <input
                                v-model.number="form.cost_price"
                                type="number"
                                step="0.01"
                                min="0"
                                class="w-full bg-dark-800 text-white rounded-lg px-4 py-2.5 border border-gray-700 focus:border-accent focus:outline-none"
                                placeholder="0.00"
                            />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-400 mb-1">Штрих-код</label>
                            <input
                                v-model="form.barcode"
                                type="text"
                                class="w-full bg-dark-800 text-white rounded-lg px-4 py-2.5 border border-gray-700 focus:border-accent focus:outline-none"
                            />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-400 mb-1">Мин. остаток</label>
                            <input
                                v-model.number="form.min_stock"
                                type="number"
                                step="0.001"
                                min="0"
                                class="w-full bg-dark-800 text-white rounded-lg px-4 py-2.5 border border-gray-700 focus:border-accent focus:outline-none"
                            />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-400 mb-1">Макс. остаток</label>
                            <input
                                v-model.number="form.max_stock"
                                type="number"
                                step="0.001"
                                min="0"
                                class="w-full bg-dark-800 text-white rounded-lg px-4 py-2.5 border border-gray-700 focus:border-accent focus:outline-none"
                            />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-400 mb-1">Срок годности (дней)</label>
                            <input
                                v-model.number="form.shelf_life_days"
                                type="number"
                                min="0"
                                class="w-full bg-dark-800 text-white rounded-lg px-4 py-2.5 border border-gray-700 focus:border-accent focus:outline-none"
                            />
                        </div>
                        <div class="flex items-center gap-4">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input v-model="form.track_stock" type="checkbox" class="w-4 h-4 accent-accent" />
                                <span class="text-sm text-gray-300">Вести учёт остатков</span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Tab: Конвертация -->
                <div v-if="activeTab === 'conversion'" class="space-y-6">
                    <div class="bg-dark-800 rounded-lg p-4">
                        <h3 class="text-white font-medium mb-3">Конвертация единиц</h3>
                        <p class="text-gray-400 text-sm mb-4">
                            Укажите параметры для автоматической конвертации между единицами измерения
                        </p>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-400 mb-1">
                                    Вес 1 штуки (кг)
                                    <span class="text-gray-500 text-xs ml-1">для штучных товаров</span>
                                </label>
                                <input
                                    v-model.number="form.piece_weight"
                                    type="number"
                                    step="0.0001"
                                    min="0"
                                    class="w-full bg-dark-700 text-white rounded-lg px-4 py-2.5 border border-gray-600 focus:border-accent focus:outline-none"
                                    placeholder="Например: 0.05 для яйца"
                                />
                                <p class="text-gray-500 text-xs mt-1">Яйцо = 0.05 кг, булочка = 0.06 кг</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-400 mb-1">
                                    Плотность (кг/л)
                                    <span class="text-gray-500 text-xs ml-1">для жидкостей</span>
                                </label>
                                <input
                                    v-model.number="form.density"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    class="w-full bg-dark-700 text-white rounded-lg px-4 py-2.5 border border-gray-600 focus:border-accent focus:outline-none"
                                    placeholder="Например: 1.03 для молока"
                                />
                                <p class="text-gray-500 text-xs mt-1">Молоко = 1.03, масло = 0.92, мёд = 1.42</p>
                            </div>
                        </div>

                        <!-- Автоподсказка -->
                        <button
                            v-if="form.name && !suggestionsApplied"
                            @click="applySuggestions"
                            class="mt-4 px-4 py-2 bg-blue-600/20 text-blue-400 rounded-lg text-sm hover:bg-blue-600/30 transition-colors"
                        >
                            Подобрать параметры автоматически
                        </button>
                    </div>

                    <!-- Калькулятор конвертации -->
                    <div v-if="isEditing && form.id" class="bg-dark-800 rounded-lg p-4">
                        <h3 class="text-white font-medium mb-3">Калькулятор</h3>
                        <div class="flex items-end gap-4">
                            <div>
                                <label class="block text-sm text-gray-400 mb-1">Количество</label>
                                <input
                                    v-model.number="calcQuantity"
                                    type="number"
                                    step="0.01"
                                    class="w-32 bg-dark-700 text-white rounded-lg px-3 py-2 border border-gray-600"
                                />
                            </div>
                            <div>
                                <label class="block text-sm text-gray-400 mb-1">Из</label>
                                <select
                                    v-model="calcFromUnit"
                                    class="bg-dark-700 text-white rounded-lg px-3 py-2 border border-gray-600"
                                >
                                    <option v-for="u in availableUnits" :key="u.id" :value="u.id">{{ u.short_name }}</option>
                                </select>
                            </div>
                            <div class="text-gray-400">=</div>
                            <div class="text-xl font-bold text-accent">{{ calcResult }}</div>
                            <div>
                                <select
                                    v-model="calcToUnit"
                                    class="bg-dark-700 text-white rounded-lg px-3 py-2 border border-gray-600"
                                >
                                    <option v-for="u in availableUnits" :key="u.id" :value="u.id">{{ u.short_name }}</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab: Потери -->
                <div v-if="activeTab === 'losses'" class="space-y-6">
                    <div class="bg-dark-800 rounded-lg p-4">
                        <h3 class="text-white font-medium mb-3">Потери при обработке</h3>
                        <p class="text-gray-400 text-sm mb-4">
                            Укажите процент потерь для автоматического расчёта брутто/нетто в рецептах
                        </p>

                        <div class="grid grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-400 mb-2">
                                    Потери при холодной обработке
                                    <span class="text-gray-500 text-xs block">очистка, разделка</span>
                                </label>
                                <div class="flex items-center gap-3">
                                    <input
                                        v-model.number="form.cold_loss_percent"
                                        type="number"
                                        step="0.5"
                                        min="0"
                                        max="100"
                                        class="w-24 bg-dark-700 text-white rounded-lg px-3 py-2.5 border border-gray-600 focus:border-accent focus:outline-none text-center"
                                    />
                                    <span class="text-gray-400">%</span>
                                </div>
                                <p class="text-gray-500 text-xs mt-2">
                                    Картофель 20%, морковь 20%, лук 16%, рыба 35%
                                </p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-400 mb-2">
                                    Потери при горячей обработке
                                    <span class="text-gray-500 text-xs block">варка, жарка</span>
                                </label>
                                <div class="flex items-center gap-3">
                                    <input
                                        v-model.number="form.hot_loss_percent"
                                        type="number"
                                        step="0.5"
                                        min="0"
                                        max="100"
                                        class="w-24 bg-dark-700 text-white rounded-lg px-3 py-2.5 border border-gray-600 focus:border-accent focus:outline-none text-center"
                                    />
                                    <span class="text-gray-400">%</span>
                                </div>
                                <p class="text-gray-500 text-xs mt-2">
                                    Курица жарка 35%, говядина 40%, овощи 10%
                                </p>
                            </div>
                        </div>

                        <!-- Итоговые потери -->
                        <div v-if="totalLossPercent > 0" class="mt-6 p-3 bg-dark-700 rounded-lg">
                            <div class="flex items-center justify-between">
                                <span class="text-gray-400">Общий выход продукта:</span>
                                <span class="text-xl font-bold" :class="totalLossPercent > 50 ? 'text-red-400' : 'text-green-400'">
                                    {{ (100 - totalLossPercent).toFixed(1) }}%
                                </span>
                            </div>
                            <p class="text-gray-500 text-sm mt-1">
                                Из 1 кг брутто получится {{ ((100 - totalLossPercent) / 100).toFixed(3) }} кг нетто
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Tab: Фасовки -->
                <div v-if="activeTab === 'packagings'" class="space-y-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-white font-medium">Фасовки</h3>
                            <p class="text-gray-400 text-sm">Варианты упаковки для приёмки товара</p>
                        </div>
                        <button
                            @click="addPackaging"
                            class="px-4 py-2 bg-accent hover:bg-accent/80 text-white rounded-lg text-sm font-medium transition-colors"
                        >
                            + Добавить фасовку
                        </button>
                    </div>

                    <!-- Список фасовок -->
                    <div v-if="packagings.length" class="space-y-3">
                        <div
                            v-for="(pkg, index) in packagings"
                            :key="pkg.id || index"
                            class="bg-dark-800 rounded-lg p-4"
                        >
                            <div class="flex items-start gap-4">
                                <div class="flex-1 grid grid-cols-4 gap-3">
                                    <div>
                                        <label class="block text-xs text-gray-500 mb-1">Единица</label>
                                        <select
                                            v-model="pkg.unit_id"
                                            class="w-full bg-dark-700 text-white rounded px-3 py-2 text-sm border border-gray-600"
                                        >
                                            <option v-for="u in packagingUnits" :key="u.id" :value="u.id">
                                                {{ u.name }}
                                            </option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-xs text-gray-500 mb-1">Кол-во в фасовке</label>
                                        <div class="flex items-center gap-1">
                                            <input
                                                v-model.number="pkg.quantity"
                                                type="number"
                                                step="0.01"
                                                min="0.01"
                                                class="w-full bg-dark-700 text-white rounded px-3 py-2 text-sm border border-gray-600"
                                            />
                                            <span class="text-gray-400 text-sm">{{ baseUnitShort }}</span>
                                        </div>
                                    </div>
                                    <div>
                                        <label class="block text-xs text-gray-500 mb-1">Название (опц.)</label>
                                        <input
                                            v-model="pkg.name"
                                            type="text"
                                            class="w-full bg-dark-700 text-white rounded px-3 py-2 text-sm border border-gray-600"
                                            placeholder="Коробка 30 шт"
                                        />
                                    </div>
                                    <div>
                                        <label class="block text-xs text-gray-500 mb-1">Штрих-код</label>
                                        <input
                                            v-model="pkg.barcode"
                                            type="text"
                                            class="w-full bg-dark-700 text-white rounded px-3 py-2 text-sm border border-gray-600"
                                        />
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 pt-5">
                                    <label class="flex items-center gap-1 text-xs text-gray-400">
                                        <input v-model="pkg.is_default" type="checkbox" class="w-3 h-3" @change="setDefaultPackaging(index)" />
                                        По умолч.
                                    </label>
                                    <button
                                        @click="removePackaging(index)"
                                        class="p-1.5 text-red-400 hover:text-red-300 hover:bg-red-500/10 rounded"
                                    >
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-else class="text-center py-8 text-gray-500">
                        Фасовки не добавлены. Добавьте фасовки для удобной приёмки товара.
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-700">
                <button
                    @click="$emit('close')"
                    class="px-6 py-2.5 bg-gray-700 hover:bg-gray-600 text-white rounded-lg font-medium transition-colors"
                >
                    Отмена
                </button>
                <button
                    @click="save"
                    :disabled="saving || !isValid"
                    class="px-6 py-2.5 bg-accent hover:bg-accent/80 text-white rounded-lg font-medium transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    {{ saving ? 'Сохранение...' : (isEditing ? 'Сохранить' : 'Создать') }}
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import api from '../../api';

const props = defineProps({
    ingredient: { type: Object, default: null },
    categories: { type: Array, default: () => [] },
    units: { type: Array, default: () => [] }
});

const emit = defineEmits(['close', 'saved']);

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

// Форма
const form = ref({
    id: null,
    name: '',
    category_id: null,
    unit_id: null,
    cost_price: 0,
    barcode: '',
    min_stock: 0,
    max_stock: null,
    shelf_life_days: null,
    track_stock: true,
    piece_weight: null,
    density: null,
    cold_loss_percent: 0,
    hot_loss_percent: 0
});

// Фасовки
const packagings = ref([]);
const packagingsToDelete = ref([]);

// Калькулятор конвертации
const calcQuantity = ref(1);
const calcFromUnit = ref(null);
const calcToUnit = ref(null);
const calcResult = ref('-');
const availableUnits = ref([]);

// Единицы для фасовок (упаковки)
const packagingUnits = computed(() => {
    return props.units.filter(u => u.type === 'pack' || ['уп', 'кор', 'бут'].includes(u.short_name));
});

// Базовая единица
const baseUnitShort = computed(() => {
    const unit = props.units.find(u => u.id === form.value.unit_id);
    return unit?.short_name || '';
});

// Общий процент потерь
const totalLossPercent = computed(() => {
    let remaining = 1;
    if (form.value.cold_loss_percent > 0) {
        remaining *= (1 - form.value.cold_loss_percent / 100);
    }
    if (form.value.hot_loss_percent > 0) {
        remaining *= (1 - form.value.hot_loss_percent / 100);
    }
    return Math.round((1 - remaining) * 1000) / 10;
});

// Валидация
const isValid = computed(() => {
    return form.value.name?.trim() && form.value.unit_id;
});

// Инициализация
onMounted(() => {
    if (props.ingredient) {
        // Редактирование
        form.value = {
            id: props.ingredient.id,
            name: props.ingredient.name || '',
            category_id: props.ingredient.category_id,
            unit_id: props.ingredient.unit_id,
            cost_price: props.ingredient.cost_price || 0,
            barcode: props.ingredient.barcode || '',
            min_stock: props.ingredient.min_stock || 0,
            max_stock: props.ingredient.max_stock,
            shelf_life_days: props.ingredient.shelf_life_days,
            track_stock: props.ingredient.track_stock ?? true,
            piece_weight: props.ingredient.piece_weight,
            density: props.ingredient.density,
            cold_loss_percent: props.ingredient.cold_loss_percent || 0,
            hot_loss_percent: props.ingredient.hot_loss_percent || 0
        };

        // Загружаем фасовки
        if (props.ingredient.packagings) {
            packagings.value = props.ingredient.packagings.map(p => ({ ...p }));
        }

        // Загружаем доступные единицы для калькулятора
        loadAvailableUnits();
    } else {
        // Новый ингредиент - устанавливаем первую единицу
        if (props.units.length) {
            form.value.unit_id = props.units[0].id;
        }
    }
});

// Загрузка доступных единиц
const loadAvailableUnits = async () => {
    if (!form.value.id) return;
    try {
        const result = await api.warehouse.getAvailableUnits(form.value.id);
        availableUnits.value = result;
        if (result.length >= 2) {
            calcFromUnit.value = result[0].id;
            calcToUnit.value = result[1].id;
        }
    } catch (e) {
        console.error('Failed to load available units:', e);
    }
};

// Автоподсказка параметров
const applySuggestions = async () => {
    if (!form.value.id) {
        // Для нового ингредиента используем локальные подсказки
        const name = form.value.name.toLowerCase();

        // Плотность
        const densities = {
            'молоко': 1.03, 'сливки': 1.01, 'масло': 0.92,
            'мёд': 1.42, 'сметана': 1.05, 'кефир': 1.03
        };
        for (const [key, val] of Object.entries(densities)) {
            if (name.includes(key)) {
                form.value.density = val;
                break;
            }
        }

        // Вес штуки
        const weights = {
            'яйцо': 0.05, 'лимон': 0.12, 'апельсин': 0.2,
            'банан': 0.15, 'яблоко': 0.18, 'булочка': 0.06
        };
        for (const [key, val] of Object.entries(weights)) {
            if (name.includes(key)) {
                form.value.piece_weight = val;
                break;
            }
        }

        // Потери
        const coldLosses = {
            'картофель': 20, 'морковь': 20, 'лук': 16,
            'капуста': 20, 'рыба': 35, 'курица': 15
        };
        for (const [key, val] of Object.entries(coldLosses)) {
            if (name.includes(key)) {
                form.value.cold_loss_percent = val;
                break;
            }
        }

        suggestionsApplied.value = true;
        return;
    }

    try {
        const result = await api.warehouse.suggestParameters(form.value.id);
        if (result.density) form.value.density = result.density;
        if (result.piece_weight) form.value.piece_weight = result.piece_weight;
        if (result.cold_loss_percent) form.value.cold_loss_percent = result.cold_loss_percent;
        if (result.hot_loss_percent) form.value.hot_loss_percent = result.hot_loss_percent;
        suggestionsApplied.value = true;
    } catch (e) {
        console.error('Failed to get suggestions:', e);
    }
};

// Калькулятор конвертации
watch([calcQuantity, calcFromUnit, calcToUnit], async () => {
    if (!form.value.id || !calcQuantity.value || !calcFromUnit.value || !calcToUnit.value) {
        calcResult.value = '-';
        return;
    }

    try {
        const result = await api.warehouse.convertUnits(
            form.value.id,
            calcQuantity.value,
            calcFromUnit.value,
            calcToUnit.value
        );
        calcResult.value = result.to_quantity;
    } catch (e) {
        calcResult.value = 'Ошибка';
    }
});

// Фасовки
const addPackaging = () => {
    const defaultUnit = packagingUnits.value[0];
    packagings.value.push({
        id: null,
        unit_id: defaultUnit?.id,
        quantity: 1,
        name: '',
        barcode: '',
        is_default: packagings.value.length === 0,
        is_purchase: true
    });
};

const removePackaging = (index) => {
    const pkg = packagings.value[index];
    if (pkg.id) {
        packagingsToDelete.value.push(pkg.id);
    }
    packagings.value.splice(index, 1);
};

const setDefaultPackaging = (index) => {
    packagings.value.forEach((p, i) => {
        p.is_default = i === index;
    });
};

// Сохранение
const save = async () => {
    if (!isValid.value || saving.value) return;

    saving.value = true;

    try {
        let savedIngredient;

        if (isEditing.value) {
            // Обновление
            savedIngredient = await api.warehouse.updateIngredient(form.value.id, form.value);
        } else {
            // Создание
            savedIngredient = await api.warehouse.createIngredient(form.value);
        }

        const ingredientId = savedIngredient.id || form.value.id;

        // Удаляем удалённые фасовки
        for (const pkgId of packagingsToDelete.value) {
            try {
                await api.warehouse.deletePackaging(pkgId);
            } catch (e) {
                console.error('Failed to delete packaging:', e);
            }
        }

        // Сохраняем фасовки
        for (const pkg of packagings.value) {
            if (pkg.id) {
                // Обновление
                await api.warehouse.updatePackaging(pkg.id, {
                    quantity: pkg.quantity,
                    name: pkg.name,
                    barcode: pkg.barcode,
                    is_default: pkg.is_default,
                    is_purchase: pkg.is_purchase
                });
            } else {
                // Создание
                await api.warehouse.createPackaging(ingredientId, {
                    unit_id: pkg.unit_id,
                    quantity: pkg.quantity,
                    name: pkg.name,
                    barcode: pkg.barcode,
                    is_default: pkg.is_default,
                    is_purchase: pkg.is_purchase
                });
            }
        }

        emit('saved', savedIngredient);
        emit('close');
    } catch (e) {
        console.error('Failed to save ingredient:', e);
        alert('Ошибка сохранения: ' + (e.response?.data?.message || e.message));
    } finally {
        saving.value = false;
    }
};
</script>
