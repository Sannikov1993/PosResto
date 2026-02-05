<template>
    <!-- Вариант 1: Компактная карточка-попап -->
    <Teleport to="body">
        <Transition name="fade">
            <div v-if="show" class="fixed inset-0 z-[10000]" @click="$emit('close')">
                <div
                    class="absolute bg-[#1e2235] rounded-2xl shadow-2xl border border-gray-700/50 w-80 flex flex-col overflow-hidden"
                    :style="positionStyle"
                    @click.stop
                >
                    <!-- Header с аватаром -->
                    <div class="relative bg-gradient-to-r from-accent/20 to-purple-500/20 px-4 pt-4 pb-12 flex-shrink-0">
                        <!-- Кнопка закрытия -->
                        <button @click="$emit('close')" class="absolute top-2 right-2 w-7 h-7 flex items-center justify-center rounded-full hover:bg-white/10 text-gray-400 hover:text-white transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>

                        <!-- Badge: День рождения -->
                        <div v-if="isBirthdaySoon" class="absolute top-2 left-2 px-2 py-1 bg-pink-500/20 border border-pink-500/30 rounded-full flex items-center gap-1">
                            <span class="text-base">🎂</span>
                            <span class="text-pink-400 text-xs font-medium">{{ birthdayLabel }}</span>
                        </div>
                    </div>

                    <!-- Аватар (выступает над header) -->
                    <div class="relative -mt-10 px-4 flex-shrink-0">
                        <div class="w-20 h-20 rounded-2xl bg-gradient-to-br from-accent to-purple-500 flex items-center justify-center text-white text-2xl font-bold shadow-lg border-4 border-[#1e2235]">
                            {{ customerInitials }}
                        </div>
                    </div>

                    <!-- Имя и телефон -->
                    <div class="px-4 pt-3 pb-2 flex-shrink-0">
                        <h3 class="text-lg font-semibold text-white">{{ customer?.name || 'Гость' }}</h3>
                        <a :href="'tel:' + customer?.phone" class="text-accent text-sm hover:underline">{{ formatPhone(customer?.phone) }}</a>
                    </div>

                    <!-- Статистика в grid (скрываем для новых клиентов) -->
                    <div v-if="!customer?.is_new" class="grid grid-cols-3 gap-2 px-4 py-3 bg-[#161927] flex-shrink-0">
                        <!-- Бонусы -->
                        <div class="text-center p-2 rounded-xl bg-[#1e2235]">
                            <div class="text-yellow-400 text-lg font-bold">{{ customerData?.bonus_balance ?? 0 }}</div>
                            <div class="text-gray-500 text-xs">бонусов</div>
                        </div>
                        <!-- Заказов (из загруженных данных) -->
                        <div class="text-center p-2 rounded-xl bg-[#1e2235]">
                            <div class="text-white text-lg font-bold">{{ loadingOrders ? '...' : calculatedOrdersCount }}</div>
                            <div class="text-gray-500 text-xs">заказов</div>
                        </div>
                        <!-- Потрачено (из загруженных данных) -->
                        <div class="text-center p-2 rounded-xl bg-[#1e2235]">
                            <div class="text-green-400 text-lg font-bold">{{ loadingOrders ? '...' : formatCompactPrice(calculatedTotalSpent) }}</div>
                            <div class="text-gray-500 text-xs">потрачено</div>
                        </div>
                    </div>

                    <!-- Информация (скрываем для новых клиентов) -->
                    <div v-if="!customer?.is_new" class="px-4 py-3 space-y-2 flex-shrink-0">
                        <!-- День рождения -->
                        <div class="flex items-center justify-between">
                            <span class="text-gray-400 text-sm">День рождения</span>
                            <div class="flex items-center gap-2">
                                <span v-if="customer?.birth_date" class="text-white text-sm">{{ formatBirthday(customer.birth_date) }}</span>
                                <button
                                    @click="showBirthdayInput = !showBirthdayInput"
                                    class="w-6 h-6 flex items-center justify-center rounded-lg hover:bg-white/10 text-gray-400 hover:text-accent transition-colors"
                                >
                                    <svg v-if="!customer?.birth_date" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                    </svg>
                                    <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <!-- Input для дня рождения -->
                        <Transition name="slide-down">
                            <div v-if="showBirthdayInput" class="flex items-center gap-2">
                                <!-- День -->
                                <select
                                    v-model="birthdayDay"
                                    class="bg-dark-800 border border-gray-700 rounded-lg px-2 py-1.5 text-white text-sm focus:border-accent focus:outline-none appearance-none cursor-pointer hover:bg-dark-700 hover:border-gray-600 transition-colors"
                                >
                                    <option value="" disabled>День</option>
                                    <option v-for="d in 31" :key="d" :value="d">{{ d }}</option>
                                </select>
                                <!-- Месяц -->
                                <select
                                    v-model="birthdayMonth"
                                    class="flex-1 bg-dark-800 border border-gray-700 rounded-lg px-2 py-1.5 text-white text-sm focus:border-accent focus:outline-none appearance-none cursor-pointer hover:bg-dark-700 hover:border-gray-600 transition-colors"
                                >
                                    <option value="" disabled>Месяц</option>
                                    <option v-for="(m, idx) in months" :key="idx" :value="idx + 1">{{ m }}</option>
                                </select>
                                <!-- Кнопка сохранить -->
                                <button
                                    @click="saveBirthday"
                                    :disabled="!birthdayDay || !birthdayMonth || saving"
                                    class="w-8 h-8 flex items-center justify-center bg-accent hover:bg-blue-600 disabled:bg-gray-600 disabled:cursor-not-allowed rounded-lg text-white transition-colors"
                                >
                                    <svg v-if="saving" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                    </svg>
                                    <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                </button>
                            </div>
                        </Transition>

                        <!-- Уровень лояльности -->
                        <div v-if="customer?.loyalty_level" class="flex items-center justify-between">
                            <span class="text-gray-400 text-sm">Уровень</span>
                            <div class="flex items-center gap-2">
                                <span
                                    class="px-2 py-0.5 rounded-full text-xs font-medium flex items-center gap-1"
                                    :style="getLevelStyle(customer.loyalty_level)"
                                >
                                    <span v-if="customer.loyalty_level.icon">{{ customer.loyalty_level.icon }}</span>
                                    <span v-else>{{ getLevelIcon(customer.loyalty_level.name) }}</span>
                                    {{ customer.loyalty_level.name }}
                                </span>
                                <!-- Бонусы уровня -->
                                <span v-if="customer.loyalty_level.discount_percent > 0" class="text-green-400 text-xs">
                                    -{{ customer.loyalty_level.discount_percent }}%
                                </span>
                                <span v-if="customer.loyalty_level.cashback_percent > 0" class="text-yellow-400 text-xs">
                                    +{{ customer.loyalty_level.cashback_percent }}%
                                </span>
                            </div>
                        </div>

                        <!-- Email -->
                        <div v-if="customer?.email" class="flex items-center justify-between">
                            <span class="text-gray-400 text-sm">Email</span>
                            <span class="text-white text-sm">{{ customer.email }}</span>
                        </div>

                        <!-- Теги -->
                        <div v-if="customer?.tags?.length" class="flex items-center gap-1 flex-wrap pt-1">
                            <span
                                v-for="tag in parsedTags"
                                :key="tag.key"
                                :class="['px-2 py-0.5 rounded-full text-xs font-medium', tag.class]"
                            >
                                {{ tag.label }}
                            </span>
                        </div>
                    </div>

                    <!-- Новый клиент - предложение создать -->
                    <div v-if="customer?.is_new" class="px-4 py-4 border-t border-gray-700/50">
                        <div class="text-center">
                            <div class="w-12 h-12 rounded-full bg-accent/20 flex items-center justify-center mx-auto mb-3">
                                <svg class="w-6 h-6 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                                </svg>
                            </div>
                            <p class="text-white font-medium mb-1">Новый клиент</p>
                            <p class="text-gray-400 text-sm mb-3">Этот гость ещё не зарегистрирован в системе</p>
                            <p class="text-gray-500 text-xs">Клиент будет создан автоматически при оплате заказа</p>
                        </div>
                    </div>

                    <!-- Последние заказы -->
                    <div v-else class="px-4 py-3 border-t border-gray-700/50 flex-1 overflow-hidden flex flex-col min-h-0">
                        <div class="flex items-center justify-between mb-2 flex-shrink-0">
                            <div class="flex items-center gap-2">
                                <button
                                    v-if="showFullHistory"
                                    @click="showFullHistory = false"
                                    class="w-6 h-6 flex items-center justify-center rounded-lg hover:bg-white/10 text-gray-400 hover:text-white transition-colors"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                                    </svg>
                                </button>
                                <span class="text-gray-400 text-sm">{{ showFullHistory ? 'История заказов' : 'Последние заказы' }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span v-if="showFullHistory" class="text-gray-500 text-xs">{{ displayedOrders.length }} заказов</span>
                                <div v-if="loadingOrders || loadingFullHistory" class="w-4 h-4">
                                    <svg class="animate-spin text-accent" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                    </svg>
                                </div>
                            </div>
                        </div>

                        <!-- Список заказов -->
                        <div
                            v-if="displayedOrders.length > 0"
                            class="space-y-2 flex-1 overflow-y-auto custom-scrollbar"
                            :class="{ 'pr-1': showFullHistory }"
                        >
                            <div
                                v-for="order in displayedOrders"
                                :key="order.id"
                                class="flex items-center justify-between py-1.5 px-2 rounded-lg bg-[#161927] hover:bg-[#1d2230] transition-colors"
                            >
                                <div class="flex items-center gap-2">
                                    <span class="text-gray-400 text-xs w-16">{{ formatOrderDate(order.created_at) }}</span>
                                    <span class="text-white text-sm font-medium">{{ formatPrice(order.total) }}</span>
                                </div>
                                <div class="flex items-center gap-1">
                                    <!-- Бонусы начислены -->
                                    <span v-if="order.bonus_earned > 0" class="text-green-400 text-xs">
                                        +{{ order.bonus_earned }}
                                    </span>
                                    <!-- Бонусы списаны -->
                                    <span v-if="order.bonus_spent > 0" class="text-red-400 text-xs">
                                        -{{ order.bonus_spent }}
                                    </span>
                                    <!-- Иконка бонусов -->
                                    <svg v-if="order.bonus_earned > 0 || order.bonus_spent > 0" class="w-3 h-3 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                    </svg>
                                </div>
                            </div>
                        </div>

                        <!-- Нет заказов -->
                        <div v-else-if="!loadingOrders && !loadingFullHistory" class="text-center py-3">
                            <span class="text-gray-500 text-sm">Нет заказов</span>
                        </div>

                        <!-- Кнопка "Вся история" / "Свернуть" -->
                        <button
                            v-if="recentOrders.length > 0 && !showFullHistory"
                            @click="loadFullHistory"
                            class="w-full mt-2 py-2 text-center text-accent text-sm hover:text-blue-400 transition-colors flex-shrink-0"
                        >
                            Вся история →
                        </button>
                    </div>

                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { authFetch } from '../shared/services/auth';

const props = defineProps({
    show: Boolean,
    customer: Object,
    anchorEl: Object, // Element to position near
    position: { type: String, default: 'bottom' } // 'bottom', 'top', 'left', 'right'
});

const emit = defineEmits(['close', 'update', 'view-history', 'edit']);

// State
const showBirthdayInput = ref(false);
const birthdayDay = ref('');
const birthdayMonth = ref('');
const saving = ref(false);

// Свежие данные клиента (с актуальным bonus_balance)
const freshCustomerData = ref(null);

// Месяцы
const months = ['января', 'февраля', 'марта', 'апреля', 'мая', 'июня', 'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря'];
const recentOrders = ref([]);
const loadingOrders = ref(false);
const showFullHistory = ref(false);
const fullHistory = ref([]);
const loadingFullHistory = ref(false);

// Computed: данные клиента (свежие или из props)
const customerData = computed(() => freshCustomerData.value || props.customer);

// Watch customer changes - parse day and month from birth_date
watch(() => props.customer?.birth_date, (val) => {
    if (val) {
        const date = new Date(val);
        birthdayDay.value = date.getDate();
        birthdayMonth.value = date.getMonth() + 1;
    } else {
        birthdayDay.value = '';
        birthdayMonth.value = '';
    }
}, { immediate: true });

// Load data on open, reset on close
watch(() => props.show, async (val) => {
    if (val && props.customer?.id) {
        loadRecentOrders();
        loadFreshCustomerData();
    } else {
        showBirthdayInput.value = false;
        showFullHistory.value = false;
        freshCustomerData.value = null;
    }
});

// Загрузка свежих данных клиента (с актуальным bonus_balance)
const loadFreshCustomerData = async () => {
    if (!props.customer?.id) return;
    try {
        const response = await authFetch(`/api/customers/${props.customer.id}`);
        const data = await response.json();
        freshCustomerData.value = data.data || data;
    } catch (error) {
        console.error('Failed to load customer data:', error);
    }
};

// Position calculation
const positionStyle = computed(() => {
    if (!props.anchorEl) {
        return { top: '50%', left: '50%', transform: 'translate(-50%, -50%)', maxHeight: '90vh' };
    }

    const rect = props.anchorEl.getBoundingClientRect();
    const cardWidth = 320;
    const padding = 16;

    let top = rect.bottom + padding;
    let left = rect.left;

    // Adjust horizontal position
    if (left + cardWidth > window.innerWidth) {
        left = window.innerWidth - cardWidth - padding;
    }
    if (left < padding) {
        left = padding;
    }

    // Calculate available space below
    const availableBelow = window.innerHeight - top - padding;

    // Use available space (no artificial limit)
    const maxHeight = Math.max(availableBelow, 200);

    return {
        top: `${top}px`,
        left: `${left}px`,
        maxHeight: `${maxHeight}px`
    };
});

// Computed
const customerInitials = computed(() => {
    if (!props.customer?.name) return '?';
    return props.customer.name
        .split(' ')
        .map(w => w[0])
        .slice(0, 2)
        .join('')
        .toUpperCase();
});

const isBirthdaySoon = computed(() => {
    if (!props.customer?.birth_date) return false;

    const today = new Date();
    const birth = new Date(props.customer.birth_date);
    birth.setFullYear(today.getFullYear());

    const diffDays = Math.ceil((birth - today) / (1000 * 60 * 60 * 24));

    // 3 дня до и 15 дней после
    return diffDays >= -15 && diffDays <= 3;
});

const birthdayLabel = computed(() => {
    if (!props.customer?.birth_date) return '';

    const today = new Date();
    const birth = new Date(props.customer.birth_date);
    birth.setFullYear(today.getFullYear());

    const diffDays = Math.ceil((birth - today) / (1000 * 60 * 60 * 24));

    if (diffDays === 0) return 'Сегодня!';
    if (diffDays === 1) return 'Завтра';
    if (diffDays > 0) return `через ${diffDays} дн.`;
    if (diffDays === -1) return 'Вчера был';
    return `${Math.abs(diffDays)} дн. назад`;
});

const parsedTags = computed(() => {
    if (!props.customer?.tags) return [];

    const tagConfig = {
        vip: { label: 'VIP', class: 'bg-yellow-500/20 text-yellow-400' },
        corporate: { label: 'Корпоратив', class: 'bg-blue-500/20 text-blue-400' },
        blogger: { label: 'Блогер', class: 'bg-pink-500/20 text-pink-400' },
        regular: { label: 'Постоянный', class: 'bg-green-500/20 text-green-400' },
        problem: { label: 'Проблемный', class: 'bg-red-500/20 text-red-400' }
    };

    const tags = Array.isArray(props.customer.tags)
        ? props.customer.tags
        : (typeof props.customer.tags === 'string' ? JSON.parse(props.customer.tags) : []);

    return tags.map(key => ({
        key,
        ...tagConfig[key] || { label: key, class: 'bg-gray-500/20 text-gray-400' }
    }));
});

// Отображаемые заказы (5 последних или все)
const displayedOrders = computed(() => {
    if (showFullHistory.value) {
        return fullHistory.value;
    }
    return recentOrders.value.slice(0, 5);
});

// Вычисляем статистику из загруженных заказов (актуальные данные)
const calculatedOrdersCount = computed(() => {
    return recentOrders.value.length;
});

const calculatedTotalSpent = computed(() => {
    return recentOrders.value.reduce((sum, order) => sum + (Number(order.total) || 0), 0);
});

// Methods
const formatPhone = (phone) => {
    if (!phone) return '';
    const digits = phone.replace(/\D/g, '');
    if (digits.length === 11) {
        return `+7 (${digits.slice(1, 4)}) ${digits.slice(4, 7)}-${digits.slice(7, 9)}-${digits.slice(9)}`;
    }
    return phone;
};

const formatBirthday = (date) => {
    if (!date) return '';
    const d = new Date(date);
    return d.toLocaleDateString('ru-RU', { day: 'numeric', month: 'long' });
};

const formatCompactPrice = (price) => {
    const num = Number(price) || 0;
    if (num >= 1000000) return (num / 1000000).toFixed(1) + 'M';
    if (num >= 1000) return (num / 1000).toFixed(0) + 'K';
    return num.toString();
};

const saveBirthday = async () => {
    if (!birthdayDay.value || !birthdayMonth.value || !props.customer?.id) return;

    saving.value = true;
    try {
        // Формируем дату (год не важен, используем 2000 как placeholder)
        const day = String(birthdayDay.value).padStart(2, '0');
        const month = String(birthdayMonth.value).padStart(2, '0');
        const birthDate = `2000-${month}-${day}`;

        await authFetch(`/api/customers/${props.customer.id}`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ birth_date: birthDate })
        });

        emit('update', { ...props.customer, birth_date: birthDate });
        showBirthdayInput.value = false;
        window.$toast?.('День рождения сохранён', 'success');
    } catch (error) {
        console.error('Failed to save birthday:', error);
        window.$toast?.('Ошибка сохранения', 'error');
    } finally {
        saving.value = false;
    }
};

// Загрузка заказов (все сразу)
const loadRecentOrders = async () => {
    if (!props.customer?.id) return;

    loadingOrders.value = true;
    try {
        const response = await authFetch(`/api/customers/${props.customer.id}/orders`);
        const data = await response.json();
        recentOrders.value = data.data || data || [];
    } catch (error) {
        console.error('Failed to load orders:', error);
        recentOrders.value = [];
    } finally {
        loadingOrders.value = false;
    }
};

// Показать полную историю (просто переключаем флаг)
const loadFullHistory = () => {
    fullHistory.value = recentOrders.value; // Копируем уже загруженные заказы
    showFullHistory.value = true;
};

// Форматирование даты заказа
const formatOrderDate = (date) => {
    if (!date) return '';
    const d = new Date(date);
    const now = new Date();

    // Сравниваем только даты без времени
    const orderDate = new Date(d.getFullYear(), d.getMonth(), d.getDate());
    const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    const diffDays = Math.floor((today - orderDate) / (1000 * 60 * 60 * 24));

    if (diffDays === 0) return 'Сегодня';
    if (diffDays === 1) return 'Вчера';

    // Для остальных дат показываем дату
    return d.toLocaleDateString('ru-RU', { day: 'numeric', month: 'short' });
};

// Форматирование цены
const formatPrice = (price) => {
    const num = Number(price) || 0;
    return num.toLocaleString('ru-RU') + '₽';
};

// Стиль для бейджа уровня лояльности
const getLevelStyle = (level) => {
    const color = level.color || '#8b5cf6'; // purple по умолчанию
    return {
        backgroundColor: `${color}20`,
        color: color
    };
};

// Иконка уровня по названию (если не задана в БД)
const getLevelIcon = (name) => {
    const nameLower = (name || '').toLowerCase();
    if (nameLower.includes('бронз') || nameLower.includes('bronze')) return '🥉';
    if (nameLower.includes('серебр') || nameLower.includes('silver')) return '🥈';
    if (nameLower.includes('золот') || nameLower.includes('gold')) return '🥇';
    if (nameLower.includes('платин') || nameLower.includes('premium') || nameLower.includes('vip')) return '💎';
    return '⭐';
};
</script>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.15s ease;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

.slide-down-enter-active,
.slide-down-leave-active {
    transition: all 0.2s ease;
}
.slide-down-enter-from,
.slide-down-leave-to {
    opacity: 0;
    transform: translateY(-8px);
}

.slide-up-enter-active,
.slide-up-leave-active {
    transition: all 0.25s ease;
}
.slide-up-enter-from,
.slide-up-leave-to {
    opacity: 0;
    transform: translateY(20px);
}

/* Custom scrollbar */
.custom-scrollbar::-webkit-scrollbar {
    width: 4px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.2);
    border-radius: 2px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.3);
}

/* Custom select styling */
select {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3E%3Cpath stroke='%239ca3af' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3E%3C/svg%3E");
    background-position: right 4px center;
    background-repeat: no-repeat;
    background-size: 16px;
    padding-right: 24px;
}
select option {
    background: #1e2235;
    color: white;
}
</style>
