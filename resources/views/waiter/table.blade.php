@extends('waiter.layout')

@section('title', 'Стол ' . $table->number)

@section('content')
<div id="table-app" class="h-full flex flex-col bg-dark-900">
    <!-- Header -->
    <header class="bg-dark-800 px-4 py-3 safe-top flex items-center justify-between shrink-0 border-b border-dark-700">
        <a href="{{ route('waiter.hall') }}" class="flex items-center gap-2 text-gray-400">
            <span class="text-xl">←</span>
            <span>Зал</span>
        </a>
        <div class="text-center">
            <span class="text-gray-400 text-sm" v-text="timeElapsed"></span>
        </div>
        <button class="px-4 py-2 bg-orange-500 rounded-xl font-semibold">
            Стол {{ $table->number }}
        </button>
    </header>

    <!-- Order Tabs -->
    <div class="px-4 py-2 flex gap-2 overflow-x-auto scroll-y bg-dark-800 shrink-0">
        <button v-for="(order, idx) in orders" :key="order.id"
                @click="activeOrderIdx = idx"
                :class="['px-4 py-2 rounded-lg text-sm font-medium whitespace-nowrap transition-all',
                         activeOrderIdx === idx ? 'bg-dark-900 text-white border-b-2 border-orange-500' : 'text-gray-400']">
            Заказ @{{ idx + 1 }}
        </button>
        <button @click="createNewOrder" class="px-4 py-2 text-orange-500 font-medium">
            + Новый
        </button>
    </div>

    <!-- Guest Selector -->
    <div class="px-4 py-3 bg-dark-800 border-b border-dark-700 shrink-0">
        <div class="flex items-center gap-2">
            <span class="text-gray-400 text-sm">Добавление для:</span>
            <button @click="showGuestPicker = !showGuestPicker"
                    class="flex items-center gap-2 px-3 py-1.5 bg-dark-700 rounded-lg">
                <span class="guest-badge" :style="{ backgroundColor: guestColor(selectedGuest) }">
                    @{{ selectedGuest }}
                </span>
                <span>Гость @{{ selectedGuest }}</span>
                <span class="text-gray-500">▼</span>
            </button>
        </div>

        <!-- Guest Picker Dropdown -->
        <div v-if="showGuestPicker" class="absolute mt-2 bg-dark-700 rounded-xl shadow-xl z-50 py-2">
            <button v-for="g in guestsCount" :key="g"
                    @click="selectedGuest = g; showGuestPicker = false"
                    class="w-full px-4 py-2 flex items-center gap-3 hover:bg-dark-600">
                <span class="guest-badge" :style="{ backgroundColor: guestColor(g) }">@{{ g }}</span>
                <span>Гость @{{ g }}</span>
            </button>
            <button @click="addGuest" class="w-full px-4 py-2 text-orange-500 border-t border-dark-600 mt-2">
                + Добавить гостя
            </button>
        </div>
    </div>

    <!-- Guests Sections (Accordion) -->
    <div class="flex-1 scroll-y p-4 space-y-3">
        <div v-for="guest in groupedItems" :key="guest.number"
             class="bg-dark-800 rounded-2xl overflow-hidden">
            <!-- Guest Header -->
            <button @click="toggleGuest(guest.number)"
                    :class="['w-full px-4 py-3 flex items-center justify-between',
                             selectedGuest === guest.number ? 'bg-dark-700' : '']">
                <div class="flex items-center gap-3">
                    <span :class="['text-lg transition-transform', expandedGuests[guest.number] ? 'rotate-90' : '']">▶</span>
                    <span class="guest-badge" :style="{ backgroundColor: guestColor(guest.number) }">
                        @{{ guest.number }}
                    </span>
                    <span class="font-medium">Гость @{{ guest.number }}</span>
                </div>
                <span class="font-bold text-lg">@{{ formatMoney(guest.total) }}</span>
            </button>

            <!-- Guest Items -->
            <div v-show="expandedGuests[guest.number]" class="border-t border-dark-700">
                <div v-for="item in guest.items" :key="item.id"
                     class="relative px-4 py-3 flex items-center gap-3 border-b border-dark-700/50 last:border-0">
                    <!-- Status Bar -->
                    <div class="status-bar" :class="item.status"></div>

                    <!-- Item Info -->
                    <div class="flex-1 pl-2">
                        <p class="font-medium">@{{ item.name }}</p>
                        <p v-if="item.comment" class="text-sm text-gray-500">@{{ item.comment }}</p>
                        <p class="text-sm text-gray-400">@{{ getStatusLabel(item.status) }}</p>
                    </div>

                    <!-- Quantity Controls -->
                    <div class="flex items-center gap-2">
                        <template v-if="canEdit(item)">
                            <button @click="decreaseQty(item)"
                                    class="w-8 h-8 bg-white/10 rounded-lg flex items-center justify-center text-lg">
                                −
                            </button>
                            <span class="w-6 text-center font-medium">@{{ item.quantity }}</span>
                            <button @click="increaseQty(item)"
                                    class="w-8 h-8 bg-white/10 rounded-lg flex items-center justify-center text-lg">
                                +
                            </button>
                        </template>
                        <template v-else>
                            <span class="text-gray-400">×@{{ item.quantity }}</span>
                        </template>
                    </div>

                    <!-- Price -->
                    <span class="font-medium w-20 text-right">@{{ formatMoney(item.total) }}</span>
                </div>
            </div>
        </div>

        <!-- Add Guest Button -->
        <button @click="addGuest" class="w-full py-3 text-orange-500 text-center font-medium">
            + Добавить гостя
        </button>
    </div>

    <!-- Footer -->
    <footer class="bg-dark-800 border-t border-dark-700 p-4 safe-bottom shrink-0">
        <div class="flex items-center justify-between mb-3">
            <span class="text-gray-400">Итого:</span>
            <span class="text-2xl font-bold">@{{ formatMoney(orderTotal) }}</span>
        </div>
        <div class="flex gap-3">
            <button v-if="pendingCount > 0"
                    @click="sendToKitchen"
                    class="flex-1 py-3 bg-orange-500 rounded-xl font-semibold flex items-center justify-center gap-2">
                <span>📤</span>
                <span>Отправить (@{{ pendingCount }})</span>
            </button>
            <button v-else-if="readyCount > 0"
                    @click="serveReady"
                    class="flex-1 py-3 bg-green-500 rounded-xl font-semibold flex items-center justify-center gap-2">
                <span>✓</span>
                <span>Отнести готовое (@{{ readyCount }})</span>
            </button>
            <button v-else
                    disabled
                    class="flex-1 py-3 bg-dark-700 text-gray-500 rounded-xl font-semibold">
                Нет позиций
            </button>
            <button @click="openPayment" class="px-6 py-3 bg-dark-700 rounded-xl">
                💳
            </button>
        </div>
    </footer>

    <!-- FAB Buttons -->
    <div class="fixed bottom-24 right-4 flex flex-col gap-3 z-50">
        <button class="w-14 h-14 bg-dark-700 rounded-full shadow-lg flex items-center justify-center text-2xl">
            🧾
        </button>
        <button @click="openMenu"
                class="w-14 h-14 bg-orange-500 rounded-full shadow-lg flex items-center justify-center text-2xl">
            +
        </button>
    </div>

    <!-- Side Menu (Categories) -->
    <div class="side-menu-overlay" :class="{ open: menuOpen }" @click="closeMenu"></div>
    <div class="side-menu" :class="{ open: menuOpen }">
        <div class="h-full flex flex-col">
            <!-- Menu Header -->
            <header class="px-4 py-3 bg-dark-800 flex items-center justify-between shrink-0 safe-top">
                <button @click="menuBack" class="text-gray-400 flex items-center gap-1">
                    <span>‹</span>
                    <span>@{{ menuTitle }}</span>
                </button>
                <button @click="closeMenu" class="text-2xl text-gray-400">×</button>
            </header>

            <!-- Guest Indicator -->
            <div class="px-4 py-2 bg-dark-700/50 text-sm flex items-center gap-2">
                <span class="text-gray-400">Для:</span>
                <span class="guest-badge" :style="{ backgroundColor: guestColor(selectedGuest) }">
                    @{{ selectedGuest }}
                </span>
                <span>Гость @{{ selectedGuest }}</span>
            </div>

            <!-- Categories / Products List -->
            <div class="flex-1 scroll-y">
                <!-- Categories -->
                <div v-if="!currentCategory">
                    <button v-for="cat in categories" :key="cat.id"
                            @click="selectCategory(cat)"
                            class="w-full px-4 py-4 flex items-center justify-between border-b border-dark-700 hover:bg-dark-700">
                        <span class="font-medium">@{{ cat.name }}</span>
                        <span class="text-gray-500">▶</span>
                    </button>
                </div>

                <!-- Products -->
                <div v-else>
                    <button v-for="product in products" :key="product.id"
                            @click="addProduct(product)"
                            :disabled="!product.is_available"
                            :class="['w-full px-4 py-4 flex items-center justify-between border-b border-dark-700',
                                     product.is_available ? 'hover:bg-dark-700' : 'opacity-40']">
                        <div>
                            <p class="font-medium text-left">@{{ product.name }}</p>
                            <p v-if="!product.is_available" class="text-red-400 text-sm">СТОП</p>
                        </div>
                        <span class="font-medium">@{{ formatMoney(product.price) }}</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast -->
    <div class="toast" :class="{ show: toast.show }">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <span class="text-2xl">@{{ toast.icon }}</span>
                <div>
                    <p class="font-medium">@{{ toast.title }}</p>
                    <p class="text-sm text-gray-400">@{{ toast.subtitle }}</p>
                </div>
            </div>
            <button @click="undoLastAction" class="text-orange-500 font-medium">Отмена</button>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
const { createApp, ref, computed, onMounted, watch } = Vue;

createApp({
    setup() {
        const tableId = {{ $table->id }};

        // State
        const orders = ref([]);
        const activeOrderIdx = ref(0);
        const selectedGuest = ref(1);
        const guestsCount = ref(1);
        const expandedGuests = ref({ 1: true });
        const showGuestPicker = ref(false);
        const timeElapsed = ref('0 мин');

        // Menu
        const menuOpen = ref(false);
        const categories = ref([]);
        const products = ref([]);
        const currentCategory = ref(null);
        const menuTitle = ref('Меню');

        // Toast
        const toast = ref({ show: false, icon: '', title: '', subtitle: '' });
        const lastAction = ref(null);

        // Computed
        const activeOrder = computed(() => orders.value[activeOrderIdx.value] || null);

        const groupedItems = computed(() => {
            if (!activeOrder.value) return [];
            const groups = {};
            (activeOrder.value.items || []).forEach(item => {
                const g = item.guest_number || 1;
                if (!groups[g]) {
                    groups[g] = { number: g, items: [], total: 0 };
                }
                groups[g].items.push(item);
                groups[g].total += parseFloat(item.total) || 0;
            });
            return Object.values(groups).sort((a, b) => a.number - b.number);
        });

        const orderTotal = computed(() => {
            return groupedItems.value.reduce((sum, g) => sum + g.total, 0);
        });

        const pendingCount = computed(() => {
            if (!activeOrder.value) return 0;
            return (activeOrder.value.items || []).filter(i => i.status === 'pending').length;
        });

        const readyCount = computed(() => {
            if (!activeOrder.value) return 0;
            return (activeOrder.value.items || []).filter(i => i.status === 'ready').length;
        });

        // Methods
        const guestColor = (num) => window.GUEST_COLORS[num] || '#6b7280';
        const formatMoney = (amount) => window.formatMoney(amount);

        const getStatusLabel = (status) => ({
            'pending': 'Ожидает отправки',
            'cooking': 'На кухне',
            'ready': 'Готово к выдаче',
            'served': 'Подано',
        }[status] || status);

        const canEdit = (item) => ['new', 'pending'].includes(item.status);

        const loadTable = async () => {
            const data = await api(`/waiter/table/${tableId}`);
            if (data.success) {
                orders.value = data.data.orders || [];
                if (orders.value.length > 0) {
                    guestsCount.value = orders.value[0].guests_count || 1;
                    // Expand all guests by default
                    for (let i = 1; i <= guestsCount.value; i++) {
                        expandedGuests.value[i] = true;
                    }
                }
            }
        };

        const toggleGuest = (num) => {
            expandedGuests.value[num] = !expandedGuests.value[num];
            selectedGuest.value = num;
        };

        const addGuest = async () => {
            guestsCount.value++;
            expandedGuests.value[guestsCount.value] = true;
            selectedGuest.value = guestsCount.value;
            showGuestPicker.value = false;
        };

        // Menu
        const openMenu = async () => {
            menuOpen.value = true;
            currentCategory.value = null;
            menuTitle.value = 'Меню';
            await loadCategories();
        };

        const closeMenu = () => {
            menuOpen.value = false;
        };

        const menuBack = () => {
            if (currentCategory.value) {
                currentCategory.value = null;
                menuTitle.value = 'Меню';
                products.value = [];
            } else {
                closeMenu();
            }
        };

        const loadCategories = async () => {
            const data = await api('/waiter/menu/categories');
            if (data.success) {
                categories.value = data.data;
            }
        };

        const selectCategory = async (cat) => {
            currentCategory.value = cat;
            menuTitle.value = cat.name;
            const data = await api(`/waiter/menu/category/${cat.id}/products`);
            if (data.success) {
                products.value = data.data;
            }
        };

        const addProduct = async (product) => {
            if (!product.is_available) return;

            const data = await api('/waiter/order/item', {
                method: 'POST',
                body: JSON.stringify({
                    table_id: tableId,
                    dish_id: product.id,
                    guest_number: selectedGuest.value,
                    quantity: 1
                })
            });

            if (data.success) {
                showToast('🍹', `${product.name} добавлен`, `Гость ${selectedGuest.value} • ${formatMoney(product.price)}`);
                lastAction.value = { type: 'add', item: data.data };
                await loadTable();
            }
        };

        const showToast = (icon, title, subtitle) => {
            toast.value = { show: true, icon, title, subtitle };
            setTimeout(() => toast.value.show = false, 3000);
        };

        const undoLastAction = async () => {
            if (lastAction.value?.type === 'add' && lastAction.value.item) {
                await api(`/waiter/order/item/${lastAction.value.item.id}`, { method: 'DELETE' });
                await loadTable();
            }
            toast.value.show = false;
            lastAction.value = null;
        };

        // Quantity
        const increaseQty = async (item) => {
            await api(`/waiter/order/item/${item.id}`, {
                method: 'PUT',
                body: JSON.stringify({ quantity: item.quantity + 1 })
            });
            await loadTable();
        };

        const decreaseQty = async (item) => {
            if (item.quantity <= 1) {
                if (confirm('Удалить позицию?')) {
                    await api(`/waiter/order/item/${item.id}`, { method: 'DELETE' });
                    await loadTable();
                }
            } else {
                await api(`/waiter/order/item/${item.id}`, {
                    method: 'PUT',
                    body: JSON.stringify({ quantity: item.quantity - 1 })
                });
                await loadTable();
            }
        };

        // Actions
        const sendToKitchen = async () => {
            if (!activeOrder.value) return;
            const data = await api(`/waiter/order/${activeOrder.value.id}/send`, { method: 'POST' });
            if (data.success) {
                showToast('👨‍🍳', 'Отправлено на кухню', `${data.sent_count} позиций`);
                await loadTable();
            }
        };

        const serveReady = async () => {
            if (!activeOrder.value) return;
            await api(`/waiter/order/${activeOrder.value.id}/serve`, { method: 'POST' });
            showToast('✅', 'Выдано гостям', '');
            await loadTable();
        };

        const openPayment = () => {
            // TODO: implement payment modal
            alert('Оплата - в разработке');
        };

        const createNewOrder = () => {
            // Create empty order placeholder
            orders.value.push({
                id: null,
                items: [],
                guests_count: 1
            });
            activeOrderIdx.value = orders.value.length - 1;
            guestsCount.value = 1;
            selectedGuest.value = 1;
        };

        // Timer
        const updateTimer = () => {
            if (activeOrder.value?.created_at) {
                const mins = Math.floor((Date.now() - new Date(activeOrder.value.created_at).getTime()) / 60000);
                timeElapsed.value = `${mins} мин`;
            }
        };

        onMounted(() => {
            loadTable();
            setInterval(updateTimer, 60000);
        });

        return {
            orders, activeOrderIdx, selectedGuest, guestsCount, expandedGuests, showGuestPicker, timeElapsed,
            menuOpen, categories, products, currentCategory, menuTitle,
            toast,
            activeOrder, groupedItems, orderTotal, pendingCount, readyCount,
            guestColor, formatMoney, getStatusLabel, canEdit,
            loadTable, toggleGuest, addGuest,
            openMenu, closeMenu, menuBack, selectCategory, addProduct,
            increaseQty, decreaseQty, sendToKitchen, serveReady, openPayment, createNewOrder,
            undoLastAction
        };
    }
}).mount('#table-app');
</script>
@endsection
