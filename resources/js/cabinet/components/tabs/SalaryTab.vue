<template>
    <div class="space-y-4">
        <!-- Current Rate Info -->
        <div class="bg-gradient-to-r from-green-500 to-emerald-600 rounded-2xl p-5 text-white">
            <div class="text-green-100 text-sm mb-1">Моя ставка</div>
            <div class="flex items-baseline gap-2">
                <span class="text-3xl font-bold">{{ formatMoney(getRateAmount()) }}</span>
                <span class="text-green-100">{{ getRateLabel() }}</span>
            </div>
            <div class="mt-2 text-green-100 text-sm">{{ currentInfo?.salary_type_label }}</div>
        </div>

        <!-- Calculations History -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="px-4 py-3 bg-gray-50 border-b font-semibold text-gray-700">
                Расчётные листки
            </div>

            <div v-if="calculations.length" class="divide-y">
                <div v-for="calc in calculations" :key="calc.id"
                     @click="showDetails(calc)"
                     class="p-4 flex items-center justify-between cursor-pointer hover:bg-gray-50 transition">
                    <div>
                        <div class="font-medium text-gray-900">{{ calc.period?.name }}</div>
                        <div class="text-sm text-gray-500">
                            {{ calc.hours_worked || 0 }}ч / {{ calc.days_worked || 0 }} дней
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="font-bold text-gray-900">{{ formatMoney(calc.net_amount) }}</div>
                        <div class="text-sm" :class="getStatusColor(calc)">
                            {{ getStatusLabel(calc) }}
                        </div>
                    </div>
                </div>
            </div>

            <div v-else class="p-8 text-center text-gray-400">
                Нет расчётов
            </div>
        </div>

        <!-- Recent Payments -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="px-4 py-3 bg-gray-50 border-b font-semibold text-gray-700">
                История выплат
            </div>

            <div v-if="payments.length" class="divide-y">
                <div v-for="payment in payments" :key="payment.id"
                     class="p-4 flex items-center justify-between">
                    <div>
                        <div class="font-medium text-gray-900">{{ getPaymentTypeLabel(payment.type) }}</div>
                        <div class="text-sm text-gray-500">{{ formatDateTime(payment.created_at) }}</div>
                        <div v-if="payment.description" class="text-xs text-gray-400 mt-1">{{ payment.description }}</div>
                    </div>
                    <div class="text-right">
                        <div class="font-bold" :class="payment.amount >= 0 ? 'text-green-600' : 'text-red-600'">
                            {{ payment.amount >= 0 ? '+' : '' }}{{ formatMoney(payment.amount) }}
                        </div>
                        <div class="text-xs" :class="payment.status === 'paid' ? 'text-green-500' : 'text-yellow-500'">
                            {{ payment.status === 'paid' ? 'Выплачено' : 'Ожидает' }}
                        </div>
                    </div>
                </div>
            </div>

            <div v-else class="p-8 text-center text-gray-400">
                Нет выплат
            </div>
        </div>

        <!-- Details Modal -->
        <div v-if="selectedCalc" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4"
             @click.self="selectedCalc = null">
            <div class="bg-white rounded-2xl w-full max-w-md max-h-[80vh] overflow-hidden">
                <div class="p-4 border-b flex items-center justify-between">
                    <h3 class="font-semibold">{{ selectedCalc.period?.name }}</h3>
                    <button @click="selectedCalc = null" class="text-gray-400 hover:text-gray-600 text-xl">&times;</button>
                </div>
                <div class="p-4 space-y-3 overflow-y-auto max-h-[60vh]">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500">Отработано</span>
                        <span class="font-medium">{{ selectedCalc.hours_worked }}ч / {{ selectedCalc.days_worked }}д</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500">Базовый оклад</span>
                        <span class="font-medium">{{ formatMoney(selectedCalc.base_amount) }}</span>
                    </div>
                    <div v-if="selectedCalc.hourly_amount > 0" class="flex justify-between text-sm">
                        <span class="text-gray-500">За часы</span>
                        <span class="font-medium">{{ formatMoney(selectedCalc.hourly_amount) }}</span>
                    </div>
                    <div v-if="selectedCalc.overtime_amount > 0" class="flex justify-between text-sm">
                        <span class="text-gray-500">Сверхурочные</span>
                        <span class="font-medium">{{ formatMoney(selectedCalc.overtime_amount) }}</span>
                    </div>
                    <div v-if="selectedCalc.percent_amount > 0" class="flex justify-between text-sm">
                        <span class="text-gray-500">% от продаж</span>
                        <span class="font-medium">{{ formatMoney(selectedCalc.percent_amount) }}</span>
                    </div>
                    <div v-if="selectedCalc.bonus_amount > 0" class="flex justify-between text-sm">
                        <span class="text-gray-500">Премии</span>
                        <span class="font-medium text-green-600">+{{ formatMoney(selectedCalc.bonus_amount) }}</span>
                    </div>
                    <div v-if="selectedCalc.penalty_amount > 0" class="flex justify-between text-sm">
                        <span class="text-gray-500">Штрафы</span>
                        <span class="font-medium text-red-600">-{{ formatMoney(selectedCalc.penalty_amount) }}</span>
                    </div>
                    <hr />
                    <div class="flex justify-between">
                        <span class="font-semibold">Итого начислено</span>
                        <span class="font-bold text-lg">{{ formatMoney(selectedCalc.net_amount) }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500">Выплачено</span>
                        <span class="font-medium text-green-600">{{ formatMoney(selectedCalc.paid_amount) }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500">Остаток</span>
                        <span class="font-medium" :class="selectedCalc.balance > 0 ? 'text-yellow-600' : 'text-gray-400'">
                            {{ formatMoney(selectedCalc.balance) }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Loading -->
        <div v-if="loading" class="fixed inset-0 bg-black/20 flex items-center justify-center z-50">
            <div class="bg-white rounded-xl p-4 shadow-lg">
                <div class="animate-spin w-8 h-8 border-4 border-orange-500 border-t-transparent rounded-full"></div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted, inject } from 'vue';

const api = inject('api');

const loading = ref(false);
const calculations = ref<any[]>([]);
const payments = ref<any[]>([]);
const currentInfo = ref<any>(null);
const selectedCalc = ref<any>(null);

function formatMoney(amount: any) {
    return new Intl.NumberFormat('ru-RU').format(amount || 0) + ' ₽';
}

function formatDateTime(dateStr: any) {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    return `${d.getDate()}.${String(d.getMonth() + 1).padStart(2, '0')}.${d.getFullYear()} ${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`;
}

function getRateAmount() {
    if (!currentInfo.value) return 0;
    switch (currentInfo.value.salary_type) {
        case 'fixed': return currentInfo.value.base_salary;
        case 'hourly': return currentInfo.value.hourly_rate;
        case 'percent': return currentInfo.value.percent_rate;
        default: return currentInfo.value.base_salary || currentInfo.value.hourly_rate;
    }
}

function getRateLabel() {
    if (!currentInfo.value) return '';
    switch (currentInfo.value.salary_type) {
        case 'fixed': return '/ мес';
        case 'hourly': return '/ час';
        case 'percent': return '%';
        default: return '';
    }
}

function getStatusColor(calc: any) {
    if (calc.balance <= 0) return 'text-green-500';
    if (calc.paid_amount > 0) return 'text-yellow-500';
    return 'text-gray-400';
}

function getStatusLabel(calc: any) {
    if (calc.balance <= 0) return 'Выплачено';
    if (calc.paid_amount > 0) return `Остаток: ${formatMoney(calc.balance)}`;
    return 'Ожидает выплаты';
}

function getPaymentTypeLabel(type: any) {
    const labels = {
        salary: 'Зарплата',
        advance: 'Аванс',
        bonus: 'Премия',
        penalty: 'Штраф',
    };
    return (labels as Record<string, any>)[type] || type;
}

function showDetails(calc: any) {
    selectedCalc.value = calc;
}

async function loadSalary() {
    loading.value = true;
    try {
        const res = await (api as any)('/cabinet/salary');
        calculations.value = res.data?.calculations || [];
        payments.value = res.data?.payments || [];
        currentInfo.value = res.data?.current_info || null;
    } catch (e: any) {
        console.error('Failed to load salary:', e);
    } finally {
        loading.value = false;
    }
}

onMounted(() => {
    loadSalary();
});
</script>
