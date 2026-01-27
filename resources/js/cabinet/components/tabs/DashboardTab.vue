<template>
    <div class="space-y-4">
        <!-- Welcome Card -->
        <div class="bg-gradient-to-r from-orange-500 to-orange-600 rounded-2xl p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-orange-100">Добро пожаловать,</p>
                    <h2 class="text-2xl font-bold">{{ data?.user?.name || 'Сотрудник' }}</h2>
                </div>
                <div class="text-4xl">
                    {{ getRoleEmoji(data?.user?.role) }}
                </div>
            </div>
        </div>

        <!-- Today's Shift -->
        <div class="bg-white rounded-xl shadow-sm p-4">
            <h3 class="font-semibold text-gray-700 mb-3 flex items-center gap-2">
                <span class="text-xl">📅</span> Сегодня
            </h3>
            <div v-if="data?.today_shift" class="flex items-center justify-between">
                <div>
                    <div class="text-lg font-bold text-gray-900">
                        {{ formatTime(data.today_shift.start_time) }} - {{ formatTime(data.today_shift.end_time) }}
                    </div>
                    <div class="text-sm text-gray-500">{{ data.today_shift.position || 'Рабочая смена' }}</div>
                </div>
                <div class="text-right">
                    <div class="text-2xl font-bold text-orange-500">{{ data.today_shift.work_hours }}ч</div>
                    <div class="text-xs text-gray-400">рабочих часов</div>
                </div>
            </div>
            <div v-else class="text-center py-4 text-gray-400">
                <div class="text-3xl mb-2">😎</div>
                <p>Сегодня выходной</p>
            </div>
        </div>

        <!-- Month Stats -->
        <div class="grid grid-cols-3 gap-3">
            <div class="bg-white rounded-xl shadow-sm p-4 text-center">
                <div class="text-2xl font-bold text-blue-600">{{ data?.month_stats?.hours_worked || 0 }}</div>
                <div class="text-xs text-gray-500">Часов</div>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-4 text-center">
                <div class="text-2xl font-bold text-green-600">{{ data?.month_stats?.days_worked || 0 }}</div>
                <div class="text-xs text-gray-500">Дней</div>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-4 text-center">
                <div class="text-2xl font-bold text-purple-600">{{ formatMoney(data?.salary?.net_amount || 0) }}</div>
                <div class="text-xs text-gray-500">К выплате</div>
            </div>
        </div>

        <!-- Waiter Stats (if applicable) -->
        <div v-if="data?.sales" class="bg-white rounded-xl shadow-sm p-4">
            <h3 class="font-semibold text-gray-700 mb-3 flex items-center gap-2">
                <span class="text-xl">📊</span> Мои продажи за месяц
            </h3>
            <div class="grid grid-cols-3 gap-4">
                <div class="text-center">
                    <div class="text-xl font-bold text-gray-900">{{ data.sales.orders_count }}</div>
                    <div class="text-xs text-gray-500">заказов</div>
                </div>
                <div class="text-center">
                    <div class="text-xl font-bold text-green-600">{{ formatMoney(data.sales.total) }}</div>
                    <div class="text-xs text-gray-500">выручка</div>
                </div>
                <div class="text-center">
                    <div class="text-xl font-bold text-yellow-600">{{ formatMoney(data.tips || 0) }}</div>
                    <div class="text-xs text-gray-500">чаевые</div>
                </div>
            </div>
        </div>

        <!-- Upcoming Shifts -->
        <div class="bg-white rounded-xl shadow-sm p-4">
            <h3 class="font-semibold text-gray-700 mb-3 flex items-center gap-2">
                <span class="text-xl">📆</span> Ближайшие смены
            </h3>
            <div v-if="data?.upcoming_shifts?.length" class="space-y-2">
                <div v-for="shift in data.upcoming_shifts" :key="shift.id"
                     class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                    <div>
                        <div class="font-medium text-gray-900">{{ formatDate(shift.date) }}</div>
                        <div class="text-sm text-gray-500">{{ formatTime(shift.start_time) }} - {{ formatTime(shift.end_time) }}</div>
                    </div>
                    <div class="text-lg font-bold text-orange-500">{{ shift.work_hours }}ч</div>
                </div>
            </div>
            <div v-else class="text-center py-4 text-gray-400">
                Нет запланированных смен
            </div>
        </div>

        <!-- Salary Status -->
        <div v-if="data?.salary" class="bg-white rounded-xl shadow-sm p-4">
            <h3 class="font-semibold text-gray-700 mb-3 flex items-center gap-2">
                <span class="text-xl">💵</span> Зарплата за текущий период
            </h3>
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-sm text-gray-500">Начислено</div>
                    <div class="text-xl font-bold text-gray-900">{{ formatMoney(data.salary.net_amount) }}</div>
                </div>
                <div class="text-center">
                    <div class="text-sm text-gray-500">Выплачено</div>
                    <div class="text-xl font-bold text-green-600">{{ formatMoney(data.salary.paid_amount) }}</div>
                </div>
                <div class="text-right">
                    <div class="text-sm text-gray-500">Остаток</div>
                    <div class="text-xl font-bold" :class="data.salary.balance > 0 ? 'text-yellow-600' : 'text-gray-400'">
                        {{ formatMoney(data.salary.balance) }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Pull to Refresh hint -->
        <div class="text-center text-gray-400 text-sm py-2">
            <button @click="$emit('refresh')" class="underline">Обновить</button>
        </div>
    </div>
</template>

<script setup>
defineProps({
    data: Object,
});

defineEmits(['refresh']);

function getRoleEmoji(role) {
    const emojis = {
        waiter: '🍽️',
        cook: '👨‍🍳',
        bartender: '🍸',
        cashier: '💳',
        courier: '🚴',
        hostess: '👋',
        manager: '📋',
        admin: '👑',
    };
    return emojis[role] || '👤';
}

function formatTime(time) {
    if (!time) return '';
    return time.substring(0, 5);
}

function formatDate(date) {
    if (!date) return '';
    const d = new Date(date);
    const days = ['Вс', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб'];
    return `${days[d.getDay()]}, ${d.getDate()}.${String(d.getMonth() + 1).padStart(2, '0')}`;
}

function formatMoney(amount) {
    return new Intl.NumberFormat('ru-RU').format(amount || 0) + ' ₽';
}
</script>
