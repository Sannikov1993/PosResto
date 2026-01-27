<template>
    <div class="h-screen flex flex-col bg-dark-900 text-white overflow-hidden">
        <!-- Login Screen -->
        <LoginScreen v-if="!isAuthenticated" @login="handleLogin" />

        <!-- Main App -->
        <template v-else>
            <!-- Header -->
            <header class="flex-shrink-0 bg-dark-800 px-4 py-3 flex items-center justify-between safe-top">
                <div class="flex items-center gap-3">
                    <button @click="showSideMenu = true" class="text-2xl">☰</button>
                    <img src="/images/logo/poslab_icon.svg" alt="PosLab" class="w-8 h-8" />
                    <div>
                        <h1 class="font-semibold">{{ headerTitle }}</h1>
                        <p class="text-xs text-gray-500">{{ onlineStatus }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <span class="text-sm text-gray-400">{{ currentTime }}</span>
                    <div class="relative">
                        <button class="text-xl">🔔</button>
                        <span v-if="notifications.length" class="absolute -top-1 -right-1 w-4 h-4 bg-red-500 rounded-full text-xs flex items-center justify-center">
                            {{ notifications.length }}
                        </span>
                    </div>
                </div>
            </header>

            <!-- Main Content -->
            <main class="flex-1 overflow-hidden">
                <!-- Tables Tab -->
                <TablesTab
                    v-show="currentTab === 'tables'"
                    :zones="zones"
                    :tables="tables"
                    :selectedZone="selectedZone"
                    @selectZone="selectedZone = $event"
                    @selectTable="handleTableSelect"
                />

                <!-- Orders Tab -->
                <OrdersTab
                    v-show="currentTab === 'orders'"
                    :orders="filteredOrders"
                    :filter="orderFilter"
                    @changeFilter="orderFilter = $event"
                    @selectOrder="handleOrderSelect"
                />

                <!-- Table Order Tab -->
                <TableOrderTab
                    v-show="currentTab === 'table'"
                    :table="selectedTable"
                    :order="currentOrder"
                    :categories="categories"
                    @back="goBack"
                    @addItem="addToOrder"
                    @removeItem="removeFromOrder"
                    @sendToKitchen="sendToKitchen"
                    @requestBill="requestBill"
                />

                <!-- Profile Tab -->
                <ProfileTab
                    v-show="currentTab === 'profile'"
                    :user="currentUser"
                    :stats="myStats"
                    @logout="handleLogout"
                />
            </main>

            <!-- Bottom Navigation -->
            <nav class="flex-shrink-0 bg-dark-800 border-t border-gray-800 safe-bottom">
                <div class="flex justify-around py-2">
                    <button v-for="tab in tabs" :key="tab.id"
                            @click="currentTab = tab.id"
                            :class="['flex flex-col items-center py-2 px-4 rounded-lg transition',
                                     currentTab === tab.id ? 'text-orange-500' : 'text-gray-500']">
                        <span class="text-xl">{{ tab.icon }}</span>
                        <span class="text-xs mt-1">{{ tab.label }}</span>
                    </button>
                </div>
            </nav>

            <!-- Side Menu -->
            <div :class="['side-menu-overlay', showSideMenu ? 'open' : '']" @click="showSideMenu = false"></div>
            <div :class="['side-menu', showSideMenu ? 'open' : '']">
                <SideMenu
                    :user="currentUser"
                    :soundEnabled="soundEnabled"
                    :notificationsEnabled="notificationsEnabled"
                    @close="showSideMenu = false"
                    @toggleSound="soundEnabled = !soundEnabled"
                    @toggleNotifications="notificationsEnabled = !notificationsEnabled"
                    @logout="handleLogout"
                />
            </div>

            <!-- Payment Modal -->
            <PaymentModal
                v-model="showPaymentModal"
                :order="paymentOrder"
                @paid="handlePayment"
            />

            <!-- Toast -->
            <div v-if="toast.show"
                 :class="['fixed bottom-24 left-4 right-4 p-4 rounded-xl text-center font-medium z-50 transition',
                          toast.type === 'success' ? 'bg-green-500' : toast.type === 'error' ? 'bg-red-500' : 'bg-blue-500']">
                {{ toast.message }}
            </div>
        </template>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
import { setTimezone, getCurrentTime } from '../utils/timezone';
import LoginScreen from './components/LoginScreen.vue';
import TablesTab from './components/tabs/TablesTab.vue';
import OrdersTab from './components/tabs/OrdersTab.vue';
import TableOrderTab from './components/tabs/TableOrderTab.vue';
import ProfileTab from './components/tabs/ProfileTab.vue';
import SideMenu from './components/SideMenu.vue';
import PaymentModal from './components/PaymentModal.vue';

// Auth
const isAuthenticated = ref(false);
const currentUser = ref(null);

// Data
const zones = ref([]);
const tables = ref([]);
const orders = ref([]);
const categories = ref([]);
const myStats = ref({});
const notifications = ref([]);

// UI
const currentTab = ref('tables');
const selectedZone = ref(null);
const orderFilter = ref('active');
const selectedTable = ref(null);
const currentOrder = ref(null);
const showSideMenu = ref(false);
const showPaymentModal = ref(false);
const paymentOrder = ref(null);

// Settings
const soundEnabled = ref(true);
const notificationsEnabled = ref(true);
const onlineStatus = ref('Онлайн');
const currentTime = ref('');

// Toast
const toast = ref({ show: false, message: '', type: 'info' });

// Tabs
const tabs = [
    { id: 'tables', label: 'Столы', icon: '🪑' },
    { id: 'orders', label: 'Заказы', icon: '📋' },
    { id: 'profile', label: 'Профиль', icon: '👤' }
];

// Computed
const headerTitle = computed(() => {
    if (currentTab.value === 'table' && selectedTable.value) {
        return `Стол ${selectedTable.value.number}`;
    }
    const tab = tabs.find(t => t.id === currentTab.value);
    return tab?.label || 'PosLab';
});

const filteredOrders = computed(() => {
    if (orderFilter.value === 'active') {
        return orders.value.filter(o => ['confirmed', 'cooking', 'ready'].includes(o.status));
    }
    return orders.value.filter(o => o.status === orderFilter.value);
});

// Methods
const showToast = (message, type = 'info') => {
    toast.value = { show: true, message, type };
    setTimeout(() => toast.value.show = false, 3000);
};

const handleLogin = async (user) => {
    currentUser.value = user;
    isAuthenticated.value = true;
    await loadData();
};

const handleLogout = () => {
    isAuthenticated.value = false;
    currentUser.value = null;
    showSideMenu.value = false;
};

const loadData = async () => {
    try {
        const [zonesRes, tablesRes, ordersRes, categoriesRes] = await Promise.all([
            axios.get('/api/zones'),
            axios.get('/api/tables'),
            axios.get('/api/orders?today=true'),
            axios.get('/api/menu/categories')
        ]);

        zones.value = zonesRes.data.data || [];
        tables.value = tablesRes.data.data || [];
        orders.value = ordersRes.data.data || [];
        categories.value = categoriesRes.data.data || [];

        if (zones.value.length && !selectedZone.value) {
            selectedZone.value = zones.value[0].id;
        }
    } catch (e) {
        console.error('Error loading data:', e);
    }
};

const handleTableSelect = (table) => {
    selectedTable.value = table;
    currentOrder.value = table.active_order || null;
    currentTab.value = 'table';
};

const handleOrderSelect = (order) => {
    const table = tables.value.find(t => t.id === order.table_id);
    selectedTable.value = table;
    currentOrder.value = order;
    currentTab.value = 'table';
};

const goBack = () => {
    currentTab.value = 'tables';
    selectedTable.value = null;
    currentOrder.value = null;
};

const addToOrder = async (dish) => {
    if (!selectedTable.value) return;

    try {
        const res = await axios.post(`/pos/table/${selectedTable.value.id}/order`, {
            dish_id: dish.id,
            quantity: 1
        });

        if (res.data.success) {
            currentOrder.value = res.data.data;
            showToast('Добавлено', 'success');
        }
    } catch (e) {
        showToast('Ошибка', 'error');
    }
};

const removeFromOrder = async (item) => {
    if (!currentOrder.value) return;

    try {
        await axios.delete(`/pos/table/${selectedTable.value.id}/order/${currentOrder.value.id}/item/${item.id}`);
        // Reload order
        const res = await axios.get(`/api/orders/${currentOrder.value.id}`);
        currentOrder.value = res.data.data;
    } catch (e) {
        showToast('Ошибка', 'error');
    }
};

const sendToKitchen = async () => {
    if (!currentOrder.value) return;

    try {
        await axios.post(`/pos/table/${selectedTable.value.id}/order/${currentOrder.value.id}/send-kitchen`);
        showToast('Отправлено на кухню', 'success');
        await loadData();
    } catch (e) {
        showToast('Ошибка', 'error');
    }
};

const requestBill = () => {
    if (!currentOrder.value) return;
    paymentOrder.value = currentOrder.value;
    showPaymentModal.value = true;
};

const handlePayment = async ({ method }) => {
    if (!paymentOrder.value) return;

    try {
        await axios.post(`/pos/table/${selectedTable.value.id}/order/${paymentOrder.value.id}/payment`, {
            method,
            amount: paymentOrder.value.total
        });

        showToast('Оплата принята', 'success');
        showPaymentModal.value = false;
        goBack();
        await loadData();
    } catch (e) {
        showToast('Ошибка оплаты', 'error');
    }
};

const updateTime = () => {
    currentTime.value = getCurrentTime();
};

// Lifecycle
let timeInterval, dataInterval;

onMounted(async () => {
    // Load timezone from settings
    try {
        const response = await fetch('/api/settings/general');
        const data = await response.json();
        if (data.success && data.data?.timezone) {
            setTimezone(data.data.timezone);
        }
    } catch (e) {
        console.warn('[Waiter] Failed to load timezone:', e);
    }

    updateTime();
    timeInterval = setInterval(updateTime, 1000);

    // Check auth
    const savedUser = localStorage.getItem('waiter_user');
    if (savedUser) {
        currentUser.value = JSON.parse(savedUser);
        isAuthenticated.value = true;
        loadData();
    }

    // Refresh data periodically
    dataInterval = setInterval(() => {
        if (isAuthenticated.value && !document.hidden) {
            loadData();
        }
    }, 30000);
});

onUnmounted(() => {
    clearInterval(timeInterval);
    clearInterval(dataInterval);
});
</script>
