<template>
    <div>
        <!-- Stats Cards -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-green-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-green-600 mb-1">Выручка</p>
                        <p class="text-2xl font-bold text-green-900">{{ formatMoney(stats.revenue) }}</p>
                    </div>
                    <span class="text-3xl">💵</span>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-red-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-red-600 mb-1">Расходы</p>
                        <p class="text-2xl font-bold text-red-900">{{ formatMoney(stats.expenses) }}</p>
                    </div>
                    <span class="text-3xl">📉</span>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-blue-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-blue-600 mb-1">Прибыль</p>
                        <p class="text-2xl font-bold" :class="stats.profit >= 0 ? 'text-blue-900' : 'text-red-600'">{{ formatMoney(stats.profit) }}</p>
                    </div>
                    <span class="text-3xl">📊</span>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-purple-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-purple-600 mb-1">Транзакций</p>
                        <p class="text-2xl font-bold text-purple-900">{{ transactions.length }}</p>
                    </div>
                    <span class="text-3xl">📝</span>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="bg-white rounded-xl shadow-sm mb-6 overflow-hidden">
            <div class="flex border-b bg-gray-50">
                <button @click="activeTab = 'transactions'"
                        :class="['px-6 py-4 text-sm font-medium border-b-2 -mb-px flex items-center gap-2 transition',
                                 activeTab === 'transactions' ? 'text-orange-500 border-orange-500 bg-white' : 'text-gray-500 border-transparent hover:text-gray-700']">
                    <span>📋</span> Транзакции
                </button>
                <button @click="activeTab = 'categories'"
                        :class="['px-6 py-4 text-sm font-medium border-b-2 -mb-px flex items-center gap-2 transition',
                                 activeTab === 'categories' ? 'text-orange-500 border-orange-500 bg-white' : 'text-gray-500 border-transparent hover:text-gray-700']">
                    <span>📁</span> Категории
                </button>
                <button @click="activeTab = 'reports'"
                        :class="['px-6 py-4 text-sm font-medium border-b-2 -mb-px flex items-center gap-2 transition',
                                 activeTab === 'reports' ? 'text-orange-500 border-orange-500 bg-white' : 'text-gray-500 border-transparent hover:text-gray-700']">
                    <span>📈</span> Отчёты
                </button>
            </div>
        </div>

        <!-- Transactions Tab -->
        <div v-if="activeTab === 'transactions'">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-4">
                    <input v-model="dateFrom" type="date"
                           class="px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                    <span class="text-gray-400">—</span>
                    <input v-model="dateTo" type="date"
                           class="px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                    <select v-model="transactionType" class="px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                        <option value="">Все типы</option>
                        <option value="income">Доходы</option>
                        <option value="expense">Расходы</option>
                    </select>
                    <button @click="loadTransactions" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg font-medium transition">
                        Применить
                    </button>
                </div>
                <button @click="openTransactionModal()" class="px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white rounded-lg font-medium transition">
                    + Добавить
                </button>
            </div>

            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Дата</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Тип</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Категория</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Описание</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Сумма</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="tx in filteredTransactions" :key="tx.id" class="hover:bg-gray-50">
                            <td class="px-6 py-4 text-sm text-gray-500">{{ formatDate(tx.date) }}</td>
                            <td class="px-6 py-4">
                                <span :class="['px-2 py-1 text-xs font-medium rounded-full',
                                               tx.type === 'income' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700']">
                                    {{ tx.type === 'income' ? '↑ Доход' : '↓ Расход' }}
                                </span>
                            </td>
                            <td class="px-6 py-4">{{ tx.category?.name || '-' }}</td>
                            <td class="px-6 py-4">{{ tx.description || '-' }}</td>
                            <td class="px-6 py-4 text-right font-medium" :class="tx.type === 'income' ? 'text-green-600' : 'text-red-600'">
                                {{ tx.type === 'income' ? '+' : '-' }}{{ formatMoney(tx.amount) }}
                            </td>
                            <td class="px-6 py-4 text-right">
                                <button v-can="'finance.edit'" @click="openTransactionModal(tx)" class="text-gray-400 hover:text-orange-500 mr-2">✏️</button>
                                <button v-can="'finance.delete'" @click="deleteTransaction(tx.id)" class="text-gray-400 hover:text-red-500">🗑️</button>
                            </td>
                        </tr>
                        <tr v-if="!filteredTransactions.length">
                            <td colspan="6" class="px-6 py-8 text-center text-gray-400">Нет транзакций</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Categories Tab -->
        <div v-if="activeTab === 'categories'" class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Income Categories -->
            <div class="bg-white rounded-xl shadow-sm">
                <div class="p-6 border-b flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-green-600">Категории доходов</h3>
                    <button @click="openCategoryModal('income')" class="text-orange-500 text-sm font-medium">+ Добавить</button>
                </div>
                <div class="p-6 space-y-2">
                    <div v-for="cat in incomeCategories" :key="cat.id"
                         class="flex items-center justify-between p-3 bg-green-50 rounded-lg group">
                        <span>{{ cat.icon || '💰' }} {{ cat.name }}</span>
                        <div class="flex gap-2 opacity-0 group-hover:opacity-100 transition">
                            <button v-can="'finance.edit'" @click="openCategoryModal('income', cat)" class="text-gray-400 hover:text-orange-500">✏️</button>
                            <button v-can="'finance.delete'" @click="deleteCategory(cat.id)" class="text-gray-400 hover:text-red-500">🗑️</button>
                        </div>
                    </div>
                    <div v-if="!incomeCategories.length" class="text-center py-4 text-gray-400">Нет категорий</div>
                </div>
            </div>

            <!-- Expense Categories -->
            <div class="bg-white rounded-xl shadow-sm">
                <div class="p-6 border-b flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-red-600">Категории расходов</h3>
                    <button @click="openCategoryModal('expense')" class="text-orange-500 text-sm font-medium">+ Добавить</button>
                </div>
                <div class="p-6 space-y-2">
                    <div v-for="cat in expenseCategories" :key="cat.id"
                         class="flex items-center justify-between p-3 bg-red-50 rounded-lg group">
                        <span>{{ cat.icon || '📤' }} {{ cat.name }}</span>
                        <div class="flex gap-2 opacity-0 group-hover:opacity-100 transition">
                            <button v-can="'finance.edit'" @click="openCategoryModal('expense', cat)" class="text-gray-400 hover:text-orange-500">✏️</button>
                            <button v-can="'finance.delete'" @click="deleteCategory(cat.id)" class="text-gray-400 hover:text-red-500">🗑️</button>
                        </div>
                    </div>
                    <div v-if="!expenseCategories.length" class="text-center py-4 text-gray-400">Нет категорий</div>
                </div>
            </div>
        </div>

        <!-- Reports Tab -->
        <div v-if="activeTab === 'reports'" class="space-y-6">
            <!-- Summary by category -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h3 class="text-lg font-semibold mb-4">Сводка по категориям</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Income by category -->
                    <div>
                        <h4 class="text-sm font-medium text-gray-500 mb-3">Доходы</h4>
                        <div class="space-y-2">
                            <div v-for="item in reportByCategory.income" :key="item.category" class="flex items-center justify-between">
                                <span>{{ item.category }}</span>
                                <span class="font-medium text-green-600">{{ formatMoney(item.total) }}</span>
                            </div>
                            <div v-if="!reportByCategory.income?.length" class="text-gray-400 text-sm">Нет данных</div>
                        </div>
                    </div>
                    <!-- Expenses by category -->
                    <div>
                        <h4 class="text-sm font-medium text-gray-500 mb-3">Расходы</h4>
                        <div class="space-y-2">
                            <div v-for="item in reportByCategory.expense" :key="item.category" class="flex items-center justify-between">
                                <span>{{ item.category }}</span>
                                <span class="font-medium text-red-600">{{ formatMoney(item.total) }}</span>
                            </div>
                            <div v-if="!reportByCategory.expense?.length" class="text-gray-400 text-sm">Нет данных</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Monthly summary -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h3 class="text-lg font-semibold mb-4">По месяцам</h3>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="text-left text-sm text-gray-500 border-b">
                                <th class="pb-3 font-medium">Месяц</th>
                                <th class="pb-3 font-medium text-right">Доходы</th>
                                <th class="pb-3 font-medium text-right">Расходы</th>
                                <th class="pb-3 font-medium text-right">Прибыль</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="month in monthlyReport" :key="month.month" class="border-b">
                                <td class="py-3">{{ month.month }}</td>
                                <td class="py-3 text-right text-green-600">{{ formatMoney(month.income) }}</td>
                                <td class="py-3 text-right text-red-600">{{ formatMoney(month.expense) }}</td>
                                <td class="py-3 text-right font-medium" :class="month.profit >= 0 ? 'text-blue-600' : 'text-red-600'">
                                    {{ formatMoney(month.profit) }}
                                </td>
                            </tr>
                            <tr v-if="!monthlyReport.length">
                                <td colspan="4" class="py-8 text-center text-gray-400">Нет данных</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Transaction Modal -->
        <Teleport to="body">
            <div v-if="showTransactionModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" @click.self="showTransactionModal = false">
                <div class="bg-white rounded-2xl shadow-xl w-full max-w-md">
                    <div class="p-6 border-b">
                        <h3 class="text-lg font-semibold">{{ transactionForm.id ? 'Редактировать' : 'Новая' }} транзакция</h3>
                    </div>
                    <div class="p-6 space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Тип</label>
                            <div class="flex gap-3">
                                <label class="flex-1">
                                    <input type="radio" v-model="transactionForm.type" value="income" class="sr-only peer">
                                    <div class="p-3 border rounded-lg text-center cursor-pointer peer-checked:border-green-500 peer-checked:bg-green-50">
                                        ↑ Доход
                                    </div>
                                </label>
                                <label class="flex-1">
                                    <input type="radio" v-model="transactionForm.type" value="expense" class="sr-only peer">
                                    <div class="p-3 border rounded-lg text-center cursor-pointer peer-checked:border-red-500 peer-checked:bg-red-50">
                                        ↓ Расход
                                    </div>
                                </label>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Категория</label>
                            <select v-model="transactionForm.category_id"
                                    class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                                <option value="">Выберите категорию</option>
                                <option v-for="cat in transactionForm.type === 'income' ? incomeCategories : expenseCategories"
                                        :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Сумма *</label>
                            <input v-model.number="transactionForm.amount" type="number" min="0" step="0.01"
                                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Дата</label>
                            <input v-model="transactionForm.date" type="date"
                                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Описание</label>
                            <input v-model="transactionForm.description" type="text"
                                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                        </div>
                    </div>
                    <div class="p-6 border-t flex gap-3">
                        <button @click="showTransactionModal = false"
                                class="flex-1 px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg font-medium transition">
                            Отмена
                        </button>
                        <button @click="saveTransaction"
                                :disabled="!transactionForm.amount"
                                class="flex-1 px-4 py-2 bg-orange-500 hover:bg-orange-600 disabled:bg-gray-300 text-white rounded-lg font-medium transition">
                            Сохранить
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Category Modal -->
        <Teleport to="body">
            <div v-if="showCategoryModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" @click.self="showCategoryModal = false">
                <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm">
                    <div class="p-6 border-b">
                        <h3 class="text-lg font-semibold">{{ categoryForm.id ? 'Редактировать' : 'Новая' }} категория</h3>
                    </div>
                    <div class="p-6 space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Название *</label>
                            <input v-model="categoryForm.name" type="text"
                                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Иконка</label>
                            <div class="flex gap-2 flex-wrap">
                                <button v-for="icon in ['💵', '💳', '🏪', '🚚', '💡', '📦', '🍽️', '👨‍🍳', '🧹', '📝']" :key="icon"
                                        @click="categoryForm.icon = icon"
                                        :class="['w-10 h-10 rounded-lg text-xl flex items-center justify-center',
                                                 categoryForm.icon === icon ? 'bg-orange-100 ring-2 ring-orange-500' : 'bg-gray-100 hover:bg-gray-200']">
                                    {{ icon }}
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="p-6 border-t flex gap-3">
                        <button @click="showCategoryModal = false"
                                class="flex-1 px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg font-medium transition">
                            Отмена
                        </button>
                        <button @click="saveCategory"
                                :disabled="!categoryForm.name"
                                class="flex-1 px-4 py-2 bg-orange-500 hover:bg-orange-600 disabled:bg-gray-300 text-white rounded-lg font-medium transition">
                            Сохранить
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useBackofficeStore } from '../../stores/backoffice';

// Helper для локальной даты (не UTC!)
const getLocalDateString = (date = new Date()) => {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
};

const store = useBackofficeStore();

// State
const activeTab = ref('transactions');
const dateFrom = ref('');
const dateTo = ref('');
const transactionType = ref('');

const transactions = ref<any[]>([]);
const categories = ref<any[]>([]);
const stats = ref({ revenue: 0, expenses: 0, profit: 0 });
const monthlyReport = ref<any[]>([]);
const reportByCategory = ref({ income: [] as any[], expense: [] as any[] });

// Modals
const showTransactionModal = ref(false);
const showCategoryModal = ref(false);

const transactionForm = ref({
    id: null, type: 'expense', category_id: '', amount: 0, date: '', description: ''
});

const categoryForm = ref({
    id: null, type: 'expense', name: '', icon: '💵'
});

// Computed
const incomeCategories = computed(() => categories.value.filter((c: any) => c.type === 'income'));
const expenseCategories = computed(() => categories.value.filter((c: any) => c.type === 'expense'));

const filteredTransactions = computed(() => {
    let result = transactions.value;
    if (transactionType.value) {
        result = result.filter((t: any) => t.type === transactionType.value);
    }
    return result;
});

// Methods
function formatMoney(val: any) {
    return new Intl.NumberFormat('ru-RU', { style: 'currency', currency: 'RUB', maximumFractionDigits: 0 }).format(val || 0);
}

function formatDate(date: any) {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('ru-RU');
}

async function loadFinance() {
    try {
        const [txRes, catRes, statsRes, reportRes] = await Promise.all([
            store.api('/backoffice/finance/transactions'),
            store.api('/backoffice/finance/categories'),
            store.api('/backoffice/finance/stats'),
            store.api('/backoffice/finance/report')
        ]);

        transactions.value = (txRes as any).data || txRes || [];
        categories.value = (catRes as any).data || catRes || [];
        stats.value = (statsRes as any).data || statsRes || stats.value;
        if (reportRes.data || reportRes) {
            monthlyReport.value = ((reportRes as any).data || reportRes).monthly || [];
            reportByCategory.value = ((reportRes as any).data || reportRes).byCategory || { income: [] as any[], expense: [] as any[] };
        }
    } catch (e: any) {
        console.error('Failed to load finance:', e);
        loadMockData();
    }
}

async function loadTransactions() {
    try {
        const params = new URLSearchParams();
        if (dateFrom.value) params.append('from', dateFrom.value);
        if (dateTo.value) params.append('to', dateTo.value);
        if (transactionType.value) params.append('type', transactionType.value);

        const res = await store.api(`/backoffice/finance/transactions?${params.toString()}`);
        transactions.value = (res as any).data || res || [];
    } catch (e: any) {
        console.error('Failed to load transactions:', e);
    }
}

function loadMockData() {
    categories.value = [
        { id: 1, type: 'income', name: 'Продажи', icon: '💵' },
        { id: 2, type: 'income', name: 'Доставка', icon: '🚚' },
        { id: 3, type: 'expense', name: 'Продукты', icon: '🍽️' },
        { id: 4, type: 'expense', name: 'Зарплата', icon: '👨‍🍳' },
        { id: 5, type: 'expense', name: 'Аренда', icon: '🏪' },
        { id: 6, type: 'expense', name: 'Коммунальные', icon: '💡' }
    ];

    transactions.value = [
        { id: 1, type: 'income', category: { name: 'Продажи' }, amount: 125000, date: '2024-01-20', description: 'Выручка за день' },
        { id: 2, type: 'expense', category: { name: 'Продукты' }, amount: 45000, date: '2024-01-19', description: 'Закупка продуктов' },
        { id: 3, type: 'expense', category: { name: 'Аренда' }, amount: 80000, date: '2024-01-15', description: 'Аренда за январь' },
        { id: 4, type: 'income', category: { name: 'Продажи' }, amount: 98000, date: '2024-01-18', description: 'Выручка за день' }
    ];

    stats.value = { revenue: 1250000, expenses: 780000, profit: 470000 };

    monthlyReport.value = [
        { month: 'Январь 2024', income: 1250000, expense: 780000, profit: 470000 },
        { month: 'Декабрь 2023', income: 1180000, expense: 720000, profit: 460000 },
        { month: 'Ноябрь 2023', income: 1050000, expense: 650000, profit: 400000 }
    ];

    reportByCategory.value = {
        income: [
            { category: 'Продажи', total: 1150000 },
            { category: 'Доставка', total: 100000 }
        ],
        expense: [
            { category: 'Продукты', total: 350000 },
            { category: 'Зарплата', total: 280000 },
            { category: 'Аренда', total: 80000 },
            { category: 'Коммунальные', total: 70000 }
        ]
    };
}

function openTransactionModal(tx: any = null) {
    if (tx) {
        transactionForm.value = { ...tx, category_id: tx.category_id || '' };
    } else {
        const today = getLocalDateString();
        transactionForm.value = { id: null, type: 'expense', category_id: '', amount: 0, date: today, description: '' };
    }
    showTransactionModal.value = true;
}

async function saveTransaction() {
    try {
        if (transactionForm.value.id) {
            await store.api(`/backoffice/finance/transactions/${transactionForm.value.id}`, {
                method: 'PUT', body: JSON.stringify(transactionForm.value)
            });
        } else {
            await store.api('/backoffice/finance/transactions', {
                method: 'POST', body: JSON.stringify(transactionForm.value)
            });
        }
        showTransactionModal.value = false;
        store.showToast('Транзакция сохранена', 'success');
        loadFinance();
    } catch (e: any) {
        store.showToast('Ошибка сохранения', 'error');
    }
}

async function deleteTransaction(id: any) {
    if (!confirm('Удалить транзакцию?')) return;
    try {
        await store.api(`/backoffice/finance/transactions/${id}`, { method: 'DELETE' });
        transactions.value = transactions.value.filter((t: any) => t.id !== id);
        store.showToast('Транзакция удалена', 'success');
    } catch (e: any) {
        store.showToast('Ошибка удаления', 'error');
    }
}

function openCategoryModal(type: any, cat: any = null) {
    if (cat) {
        categoryForm.value = { ...cat };
    } else {
        categoryForm.value = { id: null, type, name: '', icon: type === 'income' ? '💵' : '📤' };
    }
    showCategoryModal.value = true;
}

async function saveCategory() {
    try {
        if (categoryForm.value.id) {
            await store.api(`/backoffice/finance/categories/${categoryForm.value.id}`, {
                method: 'PUT', body: JSON.stringify(categoryForm.value)
            });
        } else {
            await store.api('/backoffice/finance/categories', {
                method: 'POST', body: JSON.stringify(categoryForm.value)
            });
        }
        showCategoryModal.value = false;
        store.showToast('Категория сохранена', 'success');
        loadFinance();
    } catch (e: any) {
        store.showToast('Ошибка сохранения', 'error');
    }
}

async function deleteCategory(id: any) {
    if (!confirm('Удалить категорию?')) return;
    try {
        await store.api(`/backoffice/finance/categories/${id}`, { method: 'DELETE' });
        categories.value = categories.value.filter((c: any) => c.id !== id);
        store.showToast('Категория удалена', 'success');
    } catch (e: any) {
        store.showToast('Ошибка удаления', 'error');
    }
}

// Init
onMounted(() => {
    const today = new Date();
    const monthAgo = new Date(today);
    monthAgo.setMonth(monthAgo.getMonth() - 1);

    dateTo.value = getLocalDateString(today);
    dateFrom.value = getLocalDateString(monthAgo);

    loadFinance();
});
</script>
