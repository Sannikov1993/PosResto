<template>
    <Teleport to="body">
        <div v-if="show" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" @click.self="$emit('close')">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-4xl max-h-[90vh] overflow-hidden flex flex-col">
                <!-- Header -->
                <div class="px-6 py-4 border-b flex items-center justify-between bg-gradient-to-r from-purple-600 to-indigo-600">
                    <h3 class="text-lg font-semibold text-white">{{ form.id ? 'Редактировать промокод' : 'Новый промокод' }}</h3>
                    <button @click="$emit('close')" class="text-white/80 hover:text-white">✕</button>
                </div>

                <!-- Content -->
                <div class="flex-1 overflow-y-auto">
                    <div class="flex min-h-[500px]">
                        <!-- Left Panel: Main Settings + Conditions -->
                        <div class="w-1/2 p-6 border-r space-y-6">
                            <!-- Code Section -->
                            <div>
                                <h4 class="font-semibold text-gray-800 mb-3">Промокод</h4>
                                <div class="flex gap-2">
                                    <input v-model="form.code" type="text" placeholder="PROMO2024"
                                           class="flex-1 px-4 py-3 border rounded-xl font-mono uppercase text-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                                    <button @click="generateCode" class="px-4 py-3 bg-gray-100 hover:bg-gray-200 rounded-xl transition" title="Сгенерировать">
                                        🎲
                                    </button>
                                </div>
                            </div>

                            <!-- Type Section -->
                            <div>
                                <h4 class="font-semibold text-gray-800 mb-3">Что получит клиент?</h4>
                                <div class="grid grid-cols-2 gap-2">
                                    <button v-for="(label, key) in promoTypes" :key="key"
                                            @click="form.type = key"
                                            :class="['px-3 py-2 rounded-lg text-sm font-medium transition border',
                                                     form.type === key ? 'bg-purple-100 border-purple-500 text-purple-700' : 'bg-white border-gray-200 hover:border-purple-300']">
                                        {{ label }}
                                    </button>
                                </div>

                                <!-- Value Input (скрыть для gift и free_delivery) -->
                                <div v-if="!['gift', 'free_delivery'].includes(form.type)" class="mt-4 flex items-center gap-3">
                                    <input v-model.number="form.discount_value" type="number" min="0" step="0.01"
                                           class="w-32 px-4 py-2 border rounded-lg text-center text-lg font-semibold focus:ring-2 focus:ring-purple-500">
                                    <span class="text-gray-600">{{ valueLabel }}</span>
                                </div>

                                <!-- Max Discount -->
                                <div v-if="form.type === 'discount_percent'" class="mt-3 flex items-center gap-2">
                                    <input type="checkbox" v-model="hasMaxDiscount" id="hasMaxDiscount" class="rounded">
                                    <label for="hasMaxDiscount" class="text-sm text-gray-600">Макс. скидка:</label>
                                    <input v-if="hasMaxDiscount" v-model.number="form.max_discount" type="number" min="0"
                                           class="w-24 px-3 py-1 border rounded-lg text-center">
                                    <span v-if="hasMaxDiscount" class="text-gray-500">₽</span>
                                </div>

                                <!-- Gift Dish Selector -->
                                <div v-if="form.type === 'gift'" class="mt-4">
                                    <h5 class="font-medium text-gray-700 mb-2">🎁 Выберите блюдо в подарок</h5>

                                    <!-- Selected dish preview -->
                                    <div v-if="selectedGiftDish" class="mb-3 p-3 bg-green-50 border border-green-200 rounded-lg flex items-center justify-between">
                                        <div class="flex items-center gap-3">
                                            <span class="text-2xl">🎁</span>
                                            <div>
                                                <div class="font-medium text-green-800">{{ selectedGiftDish.name }}</div>
                                                <div class="text-sm text-green-600">{{ selectedGiftDish.category?.name }} • {{ selectedGiftDish.price }}₽</div>
                                            </div>
                                        </div>
                                        <button @click="clearGiftDish" class="p-1 text-green-600 hover:text-red-500 transition">
                                            ✕
                                        </button>
                                    </div>

                                    <!-- Search input -->
                                    <div v-if="!selectedGiftDish" class="relative">
                                        <input
                                            v-model="giftDishSearch"
                                            type="text"
                                            placeholder="Поиск блюда..."
                                            class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500"
                                        >
                                        <span v-if="loadingDishes" class="absolute right-3 top-2.5 text-gray-400">⏳</span>
                                    </div>

                                    <!-- Dishes list -->
                                    <div v-if="!selectedGiftDish && filteredDishes.length > 0" class="mt-2 max-h-48 overflow-y-auto border rounded-lg divide-y">
                                        <button
                                            v-for="dish in filteredDishes.slice(0, 20)"
                                            :key="dish.id"
                                            @click="selectGiftDish(dish)"
                                            class="w-full px-3 py-2 text-left hover:bg-purple-50 flex items-center justify-between transition"
                                        >
                                            <div>
                                                <div class="font-medium text-gray-800">{{ dish.name }}</div>
                                                <div class="text-xs text-gray-500">{{ dish.category?.name }}</div>
                                            </div>
                                            <span class="text-sm text-gray-600">{{ dish.price }}₽</span>
                                        </button>
                                    </div>

                                    <!-- Empty state -->
                                    <div v-if="!selectedGiftDish && !loadingDishes && allDishes.length === 0" class="mt-2 p-4 text-center text-gray-500 bg-gray-50 rounded-lg">
                                        Нет доступных блюд
                                    </div>
                                </div>
                            </div>

                            <!-- Conditions Section -->
                            <div>
                                <h4 class="font-semibold text-gray-800 mb-3">Добавить условия</h4>

                                <!-- Purchase Conditions -->
                                <div class="mb-4">
                                    <h5 class="font-medium text-gray-700 mb-2 text-sm">Покупки</h5>
                                    <div class="flex flex-wrap gap-2">
                                        <button @click="toggleCondition('min_amount')" :class="conditionBtnClass('min_amount')">
                                            Мин. сумма
                                        </button>
                                        <button @click="toggleCondition('schedule')" :class="conditionBtnClass('schedule')">
                                            Расписание
                                        </button>
                                        <button @click="toggleCondition('order_types')" :class="conditionBtnClass('order_types')">
                                            Тип заказа
                                        </button>
                                    </div>
                                </div>

                                <!-- Customer Conditions -->
                                <div class="mb-4">
                                    <h5 class="font-medium text-gray-700 mb-2 text-sm">Клиентам</h5>
                                    <div class="flex flex-wrap gap-2">
                                        <button @click="toggleCondition('loyalty_levels')" :class="conditionBtnClass('loyalty_levels')">
                                            Владельцам карт
                                        </button>
                                        <button @click="form.is_birthday_only = !form.is_birthday_only" :class="conditionBtnClass('birthday')">
                                            В день рождения
                                        </button>
                                        <button @click="form.is_first_order_only = !form.is_first_order_only" :class="conditionBtnClass('first_order')">
                                            За первый заказ
                                        </button>
                                    </div>
                                </div>

                                <!-- Stacking -->
                                <div>
                                    <h5 class="font-medium text-gray-700 mb-2 text-sm">Совместимость</h5>
                                    <div class="flex flex-wrap gap-2">
                                        <button @click="form.stackable = !form.stackable" :class="conditionBtnClass('stackable')">
                                            Совместим с акциями
                                        </button>
                                        <button @click="form.is_exclusive = !form.is_exclusive" :class="conditionBtnClass('exclusive')">
                                            Эксклюзивный
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Active Conditions Display -->
                            <div v-if="hasActiveConditions" class="space-y-3 bg-gray-50 rounded-xl p-4">
                                <h5 class="font-medium text-gray-700 text-sm">Активные условия:</h5>

                                <!-- Min Amount -->
                                <div v-if="activeConditions.min_amount" class="flex items-center gap-2">
                                    <span class="text-gray-600">Мин. сумма:</span>
                                    <input type="number" v-model.number="form.min_order_amount" min="0"
                                           class="w-28 px-3 py-1 border rounded-lg text-center">
                                    <span class="text-gray-500">₽</span>
                                    <button @click="activeConditions.min_amount = false; form.min_order_amount = 0" class="text-red-500 ml-auto">✕</button>
                                </div>

                                <!-- Schedule -->
                                <div v-if="activeConditions.schedule" class="space-y-2">
                                    <div class="flex items-center gap-2">
                                        <span class="text-gray-600">Расписание:</span>
                                        <button @click="activeConditions.schedule = false" class="text-red-500 ml-auto">✕</button>
                                    </div>
                                    <div class="flex flex-wrap gap-1">
                                        <button v-for="(day, idx) in daysOfWeek" :key="idx"
                                                @click="toggleScheduleDay(idx)"
                                                :class="['w-9 h-9 rounded-lg text-xs font-medium transition',
                                                         form.schedule?.days?.includes(idx as any) ? 'bg-purple-500 text-white' : 'bg-white border text-gray-600 hover:border-purple-300']">
                                            {{ day }}
                                        </button>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <input type="time" v-model="scheduleTimeFrom" class="px-2 py-1 border rounded-lg text-sm">
                                        <span class="text-gray-400">—</span>
                                        <input type="time" v-model="scheduleTimeTo" class="px-2 py-1 border rounded-lg text-sm">
                                    </div>
                                </div>

                                <!-- Order Types -->
                                <div v-if="activeConditions.order_types" class="flex items-center gap-2 flex-wrap">
                                    <span class="text-gray-600">Типы заказов:</span>
                                    <label v-for="(label, key) in orderTypes" :key="key" class="flex items-center gap-1">
                                        <input type="checkbox" :value="key" v-model="form.order_types" class="rounded text-purple-500">
                                        <span class="text-sm">{{ label }}</span>
                                    </label>
                                    <button @click="activeConditions.order_types = false; form.order_types = []" class="text-red-500 ml-auto">✕</button>
                                </div>

                                <!-- Loyalty Levels -->
                                <div v-if="activeConditions.loyalty_levels" class="flex items-center gap-2 flex-wrap">
                                    <span class="text-gray-600">Уровни:</span>
                                    <label v-for="level in loyaltyLevels" :key="level.id" class="flex items-center gap-1">
                                        <input type="checkbox" :value="level.id" v-model="form.loyalty_levels" class="rounded text-purple-500">
                                        <span class="text-sm">{{ level.name }}</span>
                                    </label>
                                    <button @click="activeConditions.loyalty_levels = false; form.loyalty_levels = []" class="text-red-500 ml-auto">✕</button>
                                </div>

                                <!-- Birthday -->
                                <div v-if="form.is_birthday_only" class="space-y-2">
                                    <div class="flex items-center gap-2">
                                        <span class="text-green-600">✓ Ко дню рождения</span>
                                        <button @click="form.is_birthday_only = false; form.birthday_days_before = 0; form.birthday_days_after = 0" class="text-red-500 ml-auto">✕</button>
                                    </div>
                                    <div class="flex items-center gap-2 text-sm">
                                        <input type="number" v-model.number="form.birthday_days_before" min="0" max="30"
                                               class="w-16 px-2 py-1 border rounded text-center">
                                        <span class="text-gray-600">дн. до ДР и</span>
                                        <input type="number" v-model.number="form.birthday_days_after" min="0" max="30"
                                               class="w-16 px-2 py-1 border rounded text-center">
                                        <span class="text-gray-600">дн. после</span>
                                    </div>
                                </div>

                                <!-- First Order -->
                                <div v-if="form.is_first_order_only" class="flex items-center gap-2">
                                    <span class="text-green-600">✓ За первый заказ</span>
                                    <button @click="form.is_first_order_only = false" class="text-red-500 ml-auto">✕</button>
                                </div>

                                <!-- Stackable -->
                                <div v-if="form.stackable" class="flex items-center gap-2">
                                    <span class="text-green-600">✓ Совместим с акциями</span>
                                    <button @click="form.stackable = false" class="text-red-500 ml-auto">✕</button>
                                </div>

                                <!-- Exclusive -->
                                <div v-if="form.is_exclusive" class="flex items-center gap-2">
                                    <span class="text-amber-600">⚡ Эксклюзивный (отменяет другие скидки)</span>
                                    <button @click="form.is_exclusive = false" class="text-red-500 ml-auto">✕</button>
                                </div>
                            </div>
                        </div>

                        <!-- Right Panel: Details -->
                        <div class="w-1/2 p-6 space-y-6 bg-gray-50">
                            <!-- Tabs -->
                            <div class="flex gap-2 border-b pb-2">
                                <button @click="rightTab = 'description'" :class="['px-3 py-1 rounded-lg text-sm font-medium transition', rightTab === 'description' ? 'bg-purple-100 text-purple-700' : 'text-gray-600 hover:bg-gray-100']">
                                    Описание
                                </button>
                                <button @click="rightTab = 'limits'" :class="['px-3 py-1 rounded-lg text-sm font-medium transition', rightTab === 'limits' ? 'bg-purple-100 text-purple-700' : 'text-gray-600 hover:bg-gray-100']">
                                    Лимиты
                                </button>
                                <button @click="rightTab = 'dates'" :class="['px-3 py-1 rounded-lg text-sm font-medium transition', rightTab === 'dates' ? 'bg-purple-100 text-purple-700' : 'text-gray-600 hover:bg-gray-100']">
                                    Даты
                                </button>
                            </div>

                            <!-- Description Tab -->
                            <div v-if="rightTab === 'description'" class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Название</label>
                                    <input v-model="form.name" type="text" placeholder="Скидка новым клиентам"
                                           class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-purple-500">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Описание</label>
                                    <textarea v-model="form.description" rows="3" placeholder="Описание для клиентов..."
                                              class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-purple-500"></textarea>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Внутренние заметки</label>
                                    <textarea v-model="form.internal_notes" rows="2" placeholder="Заметки для сотрудников..."
                                              class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-purple-500"></textarea>
                                </div>
                                <div class="flex items-center gap-4">
                                    <label class="flex items-center gap-2">
                                        <input type="checkbox" v-model="form.is_active" class="rounded text-purple-500">
                                        <span>Активен</span>
                                    </label>
                                    <label class="flex items-center gap-2">
                                        <input type="checkbox" v-model="form.is_public" class="rounded text-purple-500">
                                        <span>Публичный</span>
                                    </label>
                                </div>
                            </div>

                            <!-- Limits Tab -->
                            <div v-if="rightTab === 'limits'" class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Лимит использований (всего)</label>
                                    <input v-model.number="form.usage_limit" type="number" min="0" placeholder="∞"
                                           class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-purple-500">
                                    <p class="text-xs text-gray-500 mt-1">0 = без лимита</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Лимит на клиента</label>
                                    <input v-model.number="form.usage_per_customer" type="number" min="0" placeholder="∞"
                                           class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-purple-500">
                                    <p class="text-xs text-gray-500 mt-1">0 = без лимита</p>
                                </div>
                                <div v-if="form.id" class="p-3 bg-white rounded-lg">
                                    <span class="text-sm text-gray-600">Использовано:</span>
                                    <span class="font-semibold ml-2">{{ form.usage_count || 0 }}</span>
                                    <span v-if="form.usage_limit" class="text-gray-400"> / {{ form.usage_limit }}</span>
                                </div>
                            </div>

                            <!-- Dates Tab -->
                            <div v-if="rightTab === 'dates'" class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Начало действия</label>
                                    <input v-model="form.starts_at" type="datetime-local"
                                           class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-purple-500">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Окончание действия</label>
                                    <input v-model="form.ends_at" type="datetime-local"
                                           class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-purple-500">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="px-6 py-4 border-t flex items-center gap-3 bg-gray-50">
                    <button v-if="form.id" @click="$emit('delete', form.id)"
                            class="px-4 py-2 text-red-600 hover:bg-red-50 rounded-lg font-medium transition">
                        Удалить
                    </button>
                    <div class="flex-1"></div>
                    <button @click="$emit('close')"
                            class="px-6 py-2 bg-gray-200 hover:bg-gray-300 rounded-lg font-medium transition">
                        Отмена
                    </button>
                    <button @click="handleSave" :disabled="!form.code"
                            class="px-6 py-2 bg-purple-600 hover:bg-purple-700 disabled:bg-gray-300 text-white rounded-lg font-medium transition">
                        Сохранить
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<script setup lang="ts">
import { ref, reactive, computed, watch, PropType } from 'vue';

const props = defineProps({
    show: Boolean,
    promoCode: Object,
    loyaltyLevels: { type: Array as PropType<any[]>, default: () => [] },
});

const emit = defineEmits(['close', 'save', 'delete']);

// Types
const promoTypes = {
    'discount_percent': 'Скидка %',
    'discount_fixed': 'Скидка ₽',
    'free_delivery': 'Бесплатная доставка',
    'gift': 'Подарок',
    'bonus_multiply': 'Множитель бонусов',
    'bonus_add': 'Бонусы за заказ',
};

const orderTypes = { 'dine_in': 'В зале', 'delivery': 'Доставка', 'pickup': 'Самовывоз' };
const daysOfWeek = ['Вс', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб'];

const defaultForm = () => ({
    id: null as any,
    code: '',
    name: '',
    description: '',
    internal_notes: '',
    type: 'discount_percent',
    discount_value: 10,
    max_discount: null as any,
    min_order_amount: 0,
    applies_to: 'whole_order',
    applicable_categories: [] as any[],
    applicable_dishes: [] as any[],
    excluded_dishes: [] as any[],
    excluded_categories: [] as any[],
    order_types: [] as any[],
    schedule: { days: [] as any[], time_from: '', time_to: '' },
    is_first_order_only: false,
    is_birthday_only: false,
    birthday_days_before: 0,
    birthday_days_after: 0,
    loyalty_levels: [] as any[],
    stackable: false,
    is_exclusive: false,
    gift_dish_id: null as any,
    usage_limit: 0,
    usage_per_customer: 0,
    usage_count: 0,
    starts_at: '',
    ends_at: '',
    is_active: true,
    is_public: false,
});

const form = reactive(defaultForm());

// UI State
const rightTab = ref('description');
const hasMaxDiscount = ref(false);
const scheduleTimeFrom = ref('');
const scheduleTimeTo = ref('');

// Gift dish state
const allDishes = ref<any[]>([]);
const giftDishSearch = ref('');
const loadingDishes = ref(false);

const activeConditions = reactive<Record<string, any>>({
    min_amount: false,
    schedule: false,
    order_types: false,
    loyalty_levels: false,
});

// Computed
const valueLabel = computed(() => {
    switch (form.type) {
        case 'discount_percent': return '%';
        case 'discount_fixed': return '₽';
        case 'bonus_multiply': return 'x множитель';
        case 'bonus_add': return 'бонусов';
        case 'free_delivery': return '(бесплатная доставка)';
        case 'gift': return '(подарок)';
        default: return '';
    }
});

// Gift dish computed
const filteredDishes = computed(() => {
    if (!giftDishSearch.value) return allDishes.value;
    const search = giftDishSearch.value.toLowerCase();
    return allDishes.value.filter((dish: any) =>
        dish.name.toLowerCase().includes(search) ||
        dish.category?.name?.toLowerCase().includes(search)
    );
});

const selectedGiftDish = computed(() => {
    if (!form.gift_dish_id) return null;
    return allDishes.value.find((d: any) => d.id === form.gift_dish_id);
});

const hasActiveConditions = computed(() => {
    return Object.values(activeConditions).some((v: any) => v) ||
           form.is_birthday_only ||
           form.is_first_order_only ||
           form.stackable ||
           form.is_exclusive;
});

// Methods
function toggleCondition(condition: any) {
    activeConditions[condition] = !activeConditions[condition];
}

function conditionBtnClass(condition: any) {
    let isActive = false;
    switch (condition) {
        case 'birthday': isActive = form.is_birthday_only; break;
        case 'first_order': isActive = form.is_first_order_only; break;
        case 'stackable': isActive = form.stackable; break;
        case 'exclusive': isActive = form.is_exclusive; break;
        default: isActive = activeConditions[condition];
    }
    return isActive
        ? 'px-3 py-1.5 rounded-lg text-sm font-medium bg-purple-100 text-purple-700 border border-purple-300'
        : 'px-3 py-1.5 rounded-lg text-sm font-medium bg-white text-gray-600 border border-gray-200 hover:border-purple-300';
}

function toggleScheduleDay(day: any) {
    if (!form.schedule) {
        form.schedule = { days: [] as any[], time_from: '', time_to: '' };
    }
    if (!Array.isArray(form.schedule.days)) {
        form.schedule.days = [] as any[];
    }
    const idx = form.schedule.days.indexOf(day as any);
    if (idx >= 0) {
        form.schedule.days.splice(idx, 1);
    } else {
        form.schedule.days.push(day as any);
    }
}

async function loadDishes() {
    if (allDishes.value.length > 0) return; // Already loaded
    loadingDishes.value = true;
    try {
        const response = await fetch('/api/menu/dishes');
        const data = await response.json();
        if (data.success) {
            // Обрабатываем блюда: для parent показываем варианты, для simple - сам товар
            const processed = [];
            for (const dish of (data.data || [])) {
                if (dish.product_type === 'parent' && dish.variants?.length > 0) {
                    // Добавляем варианты вместо родительского товара
                    for (const variant of dish.variants) {
                        processed.push({
                            ...variant,
                            // Формируем полное имя: "Пицца Маргарита (30 см)"
                            name: variant.variant_name ? `${dish.name} (${variant.variant_name})` : dish.name,
                            category: dish.category,
                        });
                    }
                } else if (dish.product_type !== 'parent') {
                    // Simple товары показываем как есть
                    processed.push(dish);
                }
            }
            allDishes.value = processed;
        }
    } catch (e: any) {
        console.error('Failed to load dishes:', e);
    } finally {
        loadingDishes.value = false;
    }
}

function selectGiftDish(dish: any) {
    form.gift_dish_id = dish.id;
    giftDishSearch.value = '';
}

function clearGiftDish() {
    form.gift_dish_id = null;
}

function generateCode() {
    const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    form.code = Array.from({ length: 8 }, () => chars[Math.floor(Math.random() * chars.length)]).join('');
}

function handleSave() {

    // Sync schedule times
    if (activeConditions.schedule) {
        form.schedule.time_from = scheduleTimeFrom.value;
        form.schedule.time_to = scheduleTimeTo.value;
    }

    // Clear inactive conditions
    if (!activeConditions.min_amount) form.min_order_amount = 0;
    if (!activeConditions.schedule) form.schedule = { days: [], time_from: '', time_to: '' };
    if (!activeConditions.order_types) form.order_types = [];
    if (!activeConditions.loyalty_levels) form.loyalty_levels = [];
    if (!hasMaxDiscount.value) form.max_discount = null;

    emit('save', { ...form });
}

// Watch for promo code changes
watch(() => props.promoCode, (code) => {
    if (code) {
        Object.assign(form, defaultForm(), code);

        // Map legacy field names to new ones
        if (code.value !== undefined && code.discount_value === undefined) {
            form.discount_value = code.value;
        }
        if (code.expires_at !== undefined && code.ends_at === undefined) {
            form.ends_at = code.expires_at;
        }
        if (code.first_order_only !== undefined && code.is_first_order_only === undefined) {
            form.is_first_order_only = code.first_order_only;
        }

        // Ensure schedule is always an object
        if (!form.schedule || typeof form.schedule !== 'object') {
            form.schedule = { days: [], time_from: '', time_to: '' };
        }
        if (!Array.isArray(form.schedule.days)) {
            form.schedule.days = [];
        }

        // Ensure arrays are always arrays
        form.order_types = Array.isArray(form.order_types) ? form.order_types : [];
        form.loyalty_levels = Array.isArray(form.loyalty_levels) ? form.loyalty_levels : [];

        // Restore active conditions
        activeConditions.min_amount = !!form.min_order_amount;
        activeConditions.schedule = !!form.schedule?.days?.length || !!form.schedule?.time_from;
        activeConditions.order_types = !!form.order_types?.length;
        activeConditions.loyalty_levels = !!form.loyalty_levels?.length;

        hasMaxDiscount.value = !!form.max_discount;

        scheduleTimeFrom.value = form.schedule.time_from || '';
        scheduleTimeTo.value = form.schedule.time_to || '';

        // Convert dates for input
        if (form.starts_at) {
            form.starts_at = new Date(form.starts_at).toISOString().slice(0, 16);
        }
        if (form.ends_at) {
            form.ends_at = new Date(form.ends_at).toISOString().slice(0, 16);
        }
    } else {
        Object.assign(form, defaultForm());
        Object.keys(activeConditions).forEach((k: any) => activeConditions[k] = false);
        hasMaxDiscount.value = false;
        scheduleTimeFrom.value = '';
        scheduleTimeTo.value = '';
    }
}, { immediate: true });

watch(() => props.show, (show) => {
    if (!show) {
        rightTab.value = 'description';
    }
});

// Load dishes when type is gift
watch(() => form.type, (type) => {
    if (type === 'gift') {
        loadDishes();
    }
}, { immediate: true });
</script>
