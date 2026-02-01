<template>
    <div>
        <!-- Header -->
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold">Управление тенантами</h1>
            <button @click="loadDashboard" class="text-sm text-orange-500 hover:underline">
                Обновить статистику
            </button>
        </div>

        <!-- Dashboard Stats -->
        <div class="grid grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-xl p-4 shadow-sm">
                <div class="text-3xl font-bold text-orange-500">{{ dashboard.total_tenants || 0 }}</div>
                <div class="text-gray-500 text-sm">Всего тенантов</div>
            </div>
            <div class="bg-white rounded-xl p-4 shadow-sm">
                <div class="text-3xl font-bold text-green-500">{{ dashboard.active_tenants || 0 }}</div>
                <div class="text-gray-500 text-sm">Активных</div>
            </div>
            <div class="bg-white rounded-xl p-4 shadow-sm">
                <div class="text-3xl font-bold text-blue-500">{{ dashboard.trial_tenants || 0 }}</div>
                <div class="text-gray-500 text-sm">На триале</div>
            </div>
            <div class="bg-white rounded-xl p-4 shadow-sm">
                <div class="text-3xl font-bold text-purple-500">{{ dashboard.paid_tenants || 0 }}</div>
                <div class="text-gray-500 text-sm">Платных</div>
            </div>
        </div>

        <div class="grid grid-cols-3 gap-4 mb-6">
            <div class="bg-white rounded-xl p-4 shadow-sm">
                <div class="text-2xl font-bold">{{ dashboard.total_restaurants || 0 }}</div>
                <div class="text-gray-500 text-sm">Ресторанов</div>
            </div>
            <div class="bg-white rounded-xl p-4 shadow-sm">
                <div class="text-2xl font-bold">{{ dashboard.total_users || 0 }}</div>
                <div class="text-gray-500 text-sm">Пользователей</div>
            </div>
            <div class="bg-white rounded-xl p-4 shadow-sm">
                <div class="text-2xl font-bold text-yellow-500">{{ dashboard.expiring_tenants || 0 }}</div>
                <div class="text-gray-500 text-sm">Истекает подписка (7 дней)</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-xl p-4 shadow-sm mb-6">
            <div class="flex gap-4 items-center">
                <input v-model="filters.search" @input="debouncedSearch" type="text"
                       placeholder="Поиск по имени, email, телефону..."
                       class="flex-1 border rounded-lg px-3 py-2">
                <select v-model="filters.plan" @change="loadTenants" class="border rounded-lg px-3 py-2">
                    <option value="">Все тарифы</option>
                    <option value="trial">Trial</option>
                    <option value="start">Start</option>
                    <option value="business">Business</option>
                    <option value="premium">Premium</option>
                </select>
                <select v-model="filters.is_active" @change="loadTenants" class="border rounded-lg px-3 py-2">
                    <option value="">Все статусы</option>
                    <option value="1">Активные</option>
                    <option value="0">Заблокированные</option>
                </select>
            </div>
        </div>

        <!-- Tenants Table -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="text-left px-4 py-3 font-medium text-gray-600">Тенант</th>
                        <th class="text-left px-4 py-3 font-medium text-gray-600">Тариф</th>
                        <th class="text-left px-4 py-3 font-medium text-gray-600">Рестораны</th>
                        <th class="text-left px-4 py-3 font-medium text-gray-600">Пользователи</th>
                        <th class="text-left px-4 py-3 font-medium text-gray-600">Статус</th>
                        <th class="text-left px-4 py-3 font-medium text-gray-600">Истекает</th>
                        <th class="text-right px-4 py-3 font-medium text-gray-600">Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="loading" class="border-t">
                        <td colspan="7" class="px-4 py-8 text-center text-gray-400">Загрузка...</td>
                    </tr>
                    <tr v-else-if="tenants.length === 0" class="border-t">
                        <td colspan="7" class="px-4 py-8 text-center text-gray-400">Тенанты не найдены</td>
                    </tr>
                    <tr v-for="tenant in tenants" :key="tenant.id" class="border-t hover:bg-gray-50">
                        <td class="px-4 py-3">
                            <div class="font-medium">{{ tenant.name }}</div>
                            <div class="text-sm text-gray-500">{{ tenant.email }}</div>
                            <div v-if="tenant.phone" class="text-sm text-gray-400">{{ tenant.phone }}</div>
                        </td>
                        <td class="px-4 py-3">
                            <span :class="planBadgeClass(tenant.plan)">{{ planLabel(tenant.plan) }}</span>
                        </td>
                        <td class="px-4 py-3">{{ tenant.restaurants_count }}</td>
                        <td class="px-4 py-3">{{ tenant.users_count }}</td>
                        <td class="px-4 py-3">
                            <span v-if="tenant.is_active" class="px-2 py-1 bg-green-100 text-green-700 rounded text-sm">Активен</span>
                            <span v-else class="px-2 py-1 bg-red-100 text-red-700 rounded text-sm">Заблокирован</span>
                        </td>
                        <td class="px-4 py-3 text-sm">
                            <div v-if="tenant.plan === 'trial' && tenant.trial_ends_at">
                                {{ formatDate(tenant.trial_ends_at) }}
                            </div>
                            <div v-else-if="tenant.subscription_ends_at">
                                {{ formatDate(tenant.subscription_ends_at) }}
                            </div>
                            <div v-else class="text-gray-400">-</div>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex gap-2 justify-end">
                                <button @click="viewTenant(tenant)" class="px-3 py-1 bg-blue-100 text-blue-700 rounded text-sm hover:bg-blue-200">
                                    Детали
                                </button>
                                <button v-if="tenant.is_active" @click="blockTenant(tenant)" class="px-3 py-1 bg-red-100 text-red-700 rounded text-sm hover:bg-red-200">
                                    Блок
                                </button>
                                <button v-else @click="unblockTenant(tenant)" class="px-3 py-1 bg-green-100 text-green-700 rounded text-sm hover:bg-green-200">
                                    Разблок
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>

            <!-- Pagination -->
            <div v-if="meta.last_page > 1" class="flex justify-center gap-2 p-4 border-t">
                <button v-for="page in meta.last_page" :key="page"
                        @click="goToPage(page)"
                        :class="['px-3 py-1 rounded', page === meta.current_page ? 'bg-orange-500 text-white' : 'bg-gray-100 hover:bg-gray-200']">
                    {{ page }}
                </button>
            </div>
        </div>

        <!-- Tenant Details Modal -->
        <div v-if="showDetailsModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
            <div class="bg-white rounded-2xl w-[700px] max-h-[90vh] overflow-y-auto">
                <div class="p-6 border-b flex justify-between items-center">
                    <h2 class="text-xl font-bold">Детали тенанта</h2>
                    <button @click="showDetailsModal = false" class="text-gray-400 hover:text-gray-600">X</button>
                </div>
                <div v-if="selectedTenant" class="p-6">
                    <!-- Tenant Info -->
                    <div class="grid grid-cols-2 gap-4 mb-6">
                        <div>
                            <div class="text-sm text-gray-500">Название</div>
                            <div class="font-medium">{{ selectedTenant.tenant?.name }}</div>
                        </div>
                        <div>
                            <div class="text-sm text-gray-500">Email</div>
                            <div class="font-medium">{{ selectedTenant.tenant?.email }}</div>
                        </div>
                        <div>
                            <div class="text-sm text-gray-500">Телефон</div>
                            <div class="font-medium">{{ selectedTenant.tenant?.phone || '-' }}</div>
                        </div>
                        <div>
                            <div class="text-sm text-gray-500">Тариф</div>
                            <div>
                                <span :class="planBadgeClass(selectedTenant.tenant?.plan)">
                                    {{ planLabel(selectedTenant.tenant?.plan) }}
                                </span>
                            </div>
                        </div>
                        <div>
                            <div class="text-sm text-gray-500">Создан</div>
                            <div class="font-medium">{{ formatDate(selectedTenant.tenant?.created_at) }}</div>
                        </div>
                        <div>
                            <div class="text-sm text-gray-500">Подписка до</div>
                            <div class="font-medium">
                                {{ selectedTenant.tenant?.plan === 'trial'
                                    ? formatDate(selectedTenant.tenant?.trial_ends_at)
                                    : formatDate(selectedTenant.tenant?.subscription_ends_at) }}
                            </div>
                        </div>
                    </div>

                    <!-- Stats -->
                    <div class="grid grid-cols-3 gap-4 mb-6">
                        <div class="bg-gray-50 rounded-lg p-4 text-center">
                            <div class="text-2xl font-bold">{{ selectedTenant.stats?.total_orders || 0 }}</div>
                            <div class="text-sm text-gray-500">Заказов всего</div>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-4 text-center">
                            <div class="text-2xl font-bold">{{ selectedTenant.stats?.orders_this_month || 0 }}</div>
                            <div class="text-sm text-gray-500">Заказов за месяц</div>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-4 text-center">
                            <div class="text-2xl font-bold">{{ formatMoney(selectedTenant.stats?.total_revenue) }}</div>
                            <div class="text-sm text-gray-500">Выручка</div>
                        </div>
                    </div>

                    <!-- Restaurants -->
                    <div class="mb-6">
                        <h3 class="font-medium mb-2">Рестораны ({{ selectedTenant.tenant?.restaurants?.length || 0 }})</h3>
                        <div class="space-y-2">
                            <div v-for="r in selectedTenant.tenant?.restaurants" :key="r.id"
                                 class="bg-gray-50 rounded-lg p-3 flex justify-between items-center">
                                <div>
                                    <div class="font-medium">{{ r.name }}</div>
                                    <div class="text-sm text-gray-500">{{ r.address }}</div>
                                </div>
                                <span v-if="r.is_main" class="px-2 py-1 bg-orange-100 text-orange-700 rounded text-xs">Главный</span>
                            </div>
                        </div>
                    </div>

                    <!-- Users -->
                    <div class="mb-6">
                        <h3 class="font-medium mb-2">Пользователи ({{ selectedTenant.tenant?.users?.length || 0 }})</h3>
                        <div class="space-y-2">
                            <div v-for="u in selectedTenant.tenant?.users" :key="u.id"
                                 class="bg-gray-50 rounded-lg p-3 flex justify-between items-center">
                                <div>
                                    <div class="font-medium">
                                        {{ u.name }}
                                        <span v-if="u.is_tenant_owner" class="text-xs text-orange-500 ml-1">(владелец)</span>
                                    </div>
                                    <div class="text-sm text-gray-500">{{ u.email }}</div>
                                </div>
                                <span :class="['px-2 py-1 rounded text-xs', u.is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700']">
                                    {{ u.role }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="border-t pt-4 flex gap-3">
                        <button @click="showExtendModal = true" class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600">
                            Продлить подписку
                        </button>
                        <button @click="showChangePlanModal = true" class="px-4 py-2 bg-purple-500 text-white rounded-lg hover:bg-purple-600">
                            Изменить тариф
                        </button>
                        <button @click="impersonate" class="px-4 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600">
                            Войти как тенант
                        </button>
                        <button @click="deleteTenant" class="px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 ml-auto">
                            Удалить
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Extend Subscription Modal -->
        <div v-if="showExtendModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
            <div class="bg-white rounded-2xl w-[400px] p-6">
                <h2 class="text-xl font-bold mb-4">Продлить подписку</h2>
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-1">Количество дней</label>
                    <input v-model.number="extendDays" type="number" min="1" max="365"
                           class="w-full border rounded-lg px-3 py-2">
                </div>
                <div class="flex gap-3">
                    <button @click="extendSubscription" class="flex-1 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600">
                        Продлить
                    </button>
                    <button @click="showExtendModal = false" class="flex-1 py-2 bg-gray-200 rounded-lg hover:bg-gray-300">
                        Отмена
                    </button>
                </div>
            </div>
        </div>

        <!-- Change Plan Modal -->
        <div v-if="showChangePlanModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
            <div class="bg-white rounded-2xl w-[400px] p-6">
                <h2 class="text-xl font-bold mb-4">Изменить тариф</h2>
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-1">Новый тариф</label>
                    <select v-model="newPlan" class="w-full border rounded-lg px-3 py-2">
                        <option value="trial">Trial</option>
                        <option value="start">Start</option>
                        <option value="business">Business</option>
                        <option value="premium">Premium</option>
                    </select>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-1">Дней подписки</label>
                    <input v-model.number="newPlanDays" type="number" min="1" max="365"
                           class="w-full border rounded-lg px-3 py-2">
                </div>
                <div class="flex gap-3">
                    <button @click="changePlan" class="flex-1 py-2 bg-purple-500 text-white rounded-lg hover:bg-purple-600">
                        Изменить
                    </button>
                    <button @click="showChangePlanModal = false" class="flex-1 py-2 bg-gray-200 rounded-lg hover:bg-gray-300">
                        Отмена
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import { useAdminStore } from '../stores/admin';

const store = useAdminStore();

const loading = ref(false);
const dashboard = ref({});
const tenants = ref([]);
const meta = ref({ current_page: 1, last_page: 1 });

const filters = ref({
    search: '',
    plan: '',
    is_active: ''
});

const showDetailsModal = ref(false);
const selectedTenant = ref(null);

const showExtendModal = ref(false);
const extendDays = ref(30);

const showChangePlanModal = ref(false);
const newPlan = ref('start');
const newPlanDays = ref(30);

let searchTimeout = null;

function debouncedSearch() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        loadTenants();
    }, 300);
}

async function loadDashboard() {
    try {
        const res = await axios.get('/api/super-admin/dashboard');
        if (res.data.success) {
            dashboard.value = res.data.data;
        }
    } catch (e) {
        console.error(e);
    }
}

async function loadTenants(page = 1) {
    loading.value = true;
    try {
        const params = new URLSearchParams();
        params.append('page', page);
        if (filters.value.search) params.append('search', filters.value.search);
        if (filters.value.plan) params.append('plan', filters.value.plan);
        if (filters.value.is_active !== '') params.append('is_active', filters.value.is_active);

        const res = await axios.get(`/api/super-admin/tenants?${params}`);
        if (res.data.success) {
            tenants.value = res.data.data;
            meta.value = res.data.meta;
        }
    } catch (e) {
        console.error(e);
    } finally {
        loading.value = false;
    }
}

async function viewTenant(tenant) {
    try {
        const res = await axios.get(`/api/super-admin/tenants/${tenant.id}`);
        if (res.data.success) {
            selectedTenant.value = res.data.data;
            showDetailsModal.value = true;
        }
    } catch (e) {
        store.showToast('Ошибка загрузки', 'error');
    }
}

async function blockTenant(tenant) {
    if (!confirm(`Заблокировать тенанта "${tenant.name}"?`)) return;
    try {
        await axios.post(`/api/super-admin/tenants/${tenant.id}/block`);
        store.showToast('Тенант заблокирован');
        loadTenants(meta.value.current_page);
    } catch (e) {
        store.showToast('Ошибка', 'error');
    }
}

async function unblockTenant(tenant) {
    try {
        await axios.post(`/api/super-admin/tenants/${tenant.id}/unblock`);
        store.showToast('Тенант разблокирован');
        loadTenants(meta.value.current_page);
    } catch (e) {
        store.showToast('Ошибка', 'error');
    }
}

async function extendSubscription() {
    try {
        await axios.post(`/api/super-admin/tenants/${selectedTenant.value.tenant.id}/extend`, {
            days: extendDays.value
        });
        store.showToast(`Подписка продлена на ${extendDays.value} дней`);
        showExtendModal.value = false;
        viewTenant(selectedTenant.value.tenant);
        loadTenants(meta.value.current_page);
    } catch (e) {
        store.showToast('Ошибка', 'error');
    }
}

async function changePlan() {
    try {
        await axios.post(`/api/super-admin/tenants/${selectedTenant.value.tenant.id}/change-plan`, {
            plan: newPlan.value,
            days: newPlanDays.value
        });
        store.showToast('Тариф изменён');
        showChangePlanModal.value = false;
        viewTenant(selectedTenant.value.tenant);
        loadTenants(meta.value.current_page);
    } catch (e) {
        store.showToast('Ошибка', 'error');
    }
}

async function impersonate() {
    try {
        const res = await axios.post(`/api/super-admin/tenants/${selectedTenant.value.tenant.id}/impersonate`);
        if (res.data.success) {
            // Save current admin token
            const currentToken = localStorage.getItem('admin_token');
            const currentUser = localStorage.getItem('admin_user');
            localStorage.setItem('super_admin_backup_token', currentToken);
            localStorage.setItem('super_admin_backup_user', currentUser);

            // Set new token
            localStorage.setItem('admin_token', res.data.data.token);
            localStorage.setItem('admin_user', JSON.stringify(res.data.data.user));

            store.showToast(`Вход как ${res.data.data.tenant.name}`);

            // Reload page
            setTimeout(() => window.location.reload(), 1000);
        }
    } catch (e) {
        store.showToast('Ошибка входа', 'error');
    }
}

async function deleteTenant() {
    if (!confirm(`Удалить тенанта "${selectedTenant.value.tenant.name}"? Это действие нельзя отменить!`)) return;
    try {
        await axios.delete(`/api/super-admin/tenants/${selectedTenant.value.tenant.id}`);
        store.showToast('Тенант удалён');
        showDetailsModal.value = false;
        loadTenants(meta.value.current_page);
        loadDashboard();
    } catch (e) {
        store.showToast('Ошибка удаления', 'error');
    }
}

function goToPage(page) {
    loadTenants(page);
}

function planLabel(plan) {
    const labels = { trial: 'Trial', start: 'Start', business: 'Business', premium: 'Premium' };
    return labels[plan] || plan;
}

function planBadgeClass(plan) {
    const classes = {
        trial: 'px-2 py-1 bg-gray-100 text-gray-700 rounded text-sm',
        start: 'px-2 py-1 bg-blue-100 text-blue-700 rounded text-sm',
        business: 'px-2 py-1 bg-purple-100 text-purple-700 rounded text-sm',
        premium: 'px-2 py-1 bg-orange-100 text-orange-700 rounded text-sm'
    };
    return classes[plan] || 'px-2 py-1 bg-gray-100 text-gray-700 rounded text-sm';
}

function formatDate(date) {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('ru-RU');
}

function formatMoney(amount) {
    return new Intl.NumberFormat('ru-RU', { style: 'currency', currency: 'RUB', maximumFractionDigits: 0 }).format(amount || 0);
}

onMounted(() => {
    loadDashboard();
    loadTenants();
});
</script>
