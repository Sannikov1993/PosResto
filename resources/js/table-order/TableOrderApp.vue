<template>
    <div class="flex flex-col h-full">
        <!-- Header -->
        <OrderHeader
            :table="table"
            :linkedTableNumbers="linkedTableNumbers"
            :reservation="reservation"
            :orders="orders"
            :currentOrderIndex="currentOrderIndex"
            :availablePriceLists="availablePriceLists"
            :selectedPriceListId="selectedPriceListId"
            @update:currentOrderIndex="currentOrderIndex = $event"
            @createNewOrder="createNewOrder"
            @changePriceList="changePriceList"
        />

        <!-- Main Content -->
        <div class="flex flex-1 overflow-hidden">
            <!-- Left Panel: Guests -->
            <GuestPanel
                :guests="currentGuests"
                :selectedGuest="selectedGuest"
                :pendingItems="pendingItems"
                :readyItems="readyItems"
                :reservation="reservation"
                :customer="currentOrder?.customer"
                :currentOrder="currentOrder"
                :guestColors="guestColors"
                :selectMode="selectMode"
                :selectModeGuest="selectModeGuest"
                :selectedItems="selectedItems"
                :table="table"
                :discount="currentDiscount"
                :orderTotal="orderTotal"
                :unpaidTotal="unpaidTotal"
                :roundAmounts="roundAmounts"
                @selectGuest="selectGuest"
                @addGuest="addGuest"
                @toggleGuestCollapse="toggleGuestCollapse"
                @updateItemQuantity="updateItemQuantity"
                @removeItem="removeItem"
                @sendItemToKitchen="sendItemToKitchen"
                @openCommentModal="openCommentModal"
                @openMoveModal="openMoveModal"
                @markItemServed="markItemServed"
                @startSelectMode="startSelectMode"
                @cancelSelectMode="cancelSelectMode"
                @toggleItemSelection="toggleItemSelection"
                @selectAllGuestItems="selectAllGuestItems"
                @deselectAllItems="deselectAllItems"
                @openBulkMoveModal="openBulkMoveModal"
                @sendAllToKitchen="sendAllToKitchen"
                @serveAllReady="serveAllReady"
                @showSplitPayment="showSplitPayment = true"
                @showPaymentModal="showPaymentModal = true"
                @showDiscount="showDiscountModal = true"
                @deleteOrder="confirmDeleteOrder"
                @saveReservation="saveReservationChanges"
                @unlinkReservation="unlinkReservation"
                @printPrecheck="printPrecheck"
                @attachCustomer="attachCustomer"
                @detachCustomer="detachCustomer"
            />

            <!-- Right Panel: Menu -->
            <MenuPanel
                :categories="categories"
                :selectedCategory="selectedCategory"
                :searchQuery="searchQuery"
                :viewMode="viewMode"
                @update:selectedCategory="selectedCategory = $event"
                @update:searchQuery="searchQuery = $event"
                @addItem="addItem"
            />
        </div>

        <!-- Modals -->
        <SplitPaymentModal
            v-model="showSplitPayment"
            :guests="currentGuests"
            :guestColors="guestColors"
            :tipsPercent="tipsPercent"
            @update:tipsPercent="tipsPercent = $event"
            @pay="processSplitPayment"
        />

        <UnifiedPaymentModal
            ref="paymentModalRef"
            v-model="showPaymentModal"
            :total="unpaidTotal"
            :subtotal="unpaidSubtotal"
            :discount="currentDiscount"
            :loyaltyDiscount="loyaltyDiscount"
            :loyaltyLevelName="loyaltyLevelName"
            :paidAmount="paidDeposit"
            :guests="currentGuests"
            :paidGuests="paidGuestNumbers"
            :customer="currentOrder?.customer"
            :bonusSettings="bonusSettings"
            :roundAmounts="roundAmounts"
            :initialBonusToSpend="currentBonusToSpend"
            mode="payment"
            @confirm="confirmPayment"
        />

        <CommentModal
            v-model="commentModal.show"
            :item="commentModal.item"
            :text="commentModal.text"
            @update:text="commentModal.text = $event"
            @save="saveItemComment"
        />

        <MoveItemModal
            v-model="moveModal.show"
            :item="moveModal.item"
            :guests="currentGuests"
            :fromGuest="moveModal.fromGuest"
            @moveToGuest="moveItemToGuest"
        />

        <BulkMoveModal
            v-model="bulkMoveModal.show"
            :selectedCount="selectedItems.length"
            :guests="currentGuests"
            :fromGuest="selectModeGuest"
            @moveToGuest="bulkMoveToGuest"
        />

        <CancelItemModal
            v-model="cancelModal.show"
            :item="cancelModal.item"
            :orderId="currentOrder?.id"
            :canCancelItems="canCancelItems"
            @cancelled="onItemCancelled"
            @requestSent="onCancelRequestSent"
        />

        <CancelOrderModal
            v-model="cancelOrderModal.show"
            :order="currentOrder"
            :table="table"
            :canCancelOrders="canCancelOrders"
            @cancelled="onOrderCancelled"
            @requestSent="onOrderCancelRequestSent"
        />

        <DiscountModal
            v-if="currentOrder"
            v-model="showDiscountModal"
            :tableId="table.id"
            :orderId="currentOrder.id"
            :subtotal="orderSubtotal"
            :currentDiscount="currentDiscount"
            :currentDiscountPercent="currentDiscountPercent"
            :currentDiscountReason="currentDiscountReason"
            :currentAppliedDiscounts="currentOrder.applied_discounts || []"
            :customerId="currentOrder.customer_id"
            :customerName="currentOrder.customer?.name || ''"
            :customerLoyaltyLevel="currentOrder.customer?.loyaltyLevel"
            :customerBonusBalance="currentOrder.customer?.bonus_balance || 0"
            :bonusSettings="bonusSettings"
            :orderType="currentOrder.type || 'dine_in'"
            @apply="applyDiscount"
        />

        <!-- Toast -->
        <div v-if="toast.show"
             class="fixed top-4 right-4 px-6 py-3 rounded-xl shadow-lg z-50 transition-all text-white"
             :class="toast.type === 'success' ? 'bg-green-500' : toast.type === 'error' ? 'bg-red-500' : 'bg-blue-500'">
            {{ toast.message }}
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount, inject } from 'vue';
import { setTimezone } from '../utils/timezone';
import OrderHeader from './components/OrderHeader.vue';
import GuestPanel from './components/GuestPanel.vue';
import MenuPanel from './components/MenuPanel.vue';
import SplitPaymentModal from './modals/SplitPaymentModal.vue';
import UnifiedPaymentModal from '../components/UnifiedPaymentModal.vue';
import CommentModal from './modals/CommentModal.vue';
import MoveItemModal from './modals/MoveItemModal.vue';
import BulkMoveModal from './modals/BulkMoveModal.vue';
import CancelItemModal from './modals/CancelItemModal.vue';
import CancelOrderModal from './modals/CancelOrderModal.vue';
import DiscountModal from '../shared/components/modals/DiscountModal.vue';

// Get initial data from Blade
const initialData = inject('initialData');

// Core data
const table = ref(initialData.table);
const orders = ref(initialData.orders);
const categories = ref(initialData.categories);
const reservation = ref(initialData.reservation);
const linkedTableIds = ref(initialData.linkedTableIds);
const initialGuests = ref(initialData.initialGuests);

// UI State
const currentOrderIndex = ref(0);
const selectedGuest = ref(1);
const createdGuests = ref(initialGuests.value ? Array.from({ length: initialGuests.value }, (_, i) => i + 1) : [1]);
const searchQuery = ref('');
const selectedCategory = ref(null);
const tipsPercent = ref(10);
const viewMode = ref('grid'); // 'grid' или 'list'

// Price list state
const availablePriceLists = ref([]);
const selectedPriceListId = ref(null);

// Bonus settings
const bonusSettings = ref(null);
const roundAmounts = ref(false);

// Modal states
const showSplitPayment = ref(false);
const showPaymentModal = ref(false);
const paymentModalRef = ref(null);
const commentModal = ref({ show: false, item: null, text: '' });
const moveModal = ref({ show: false, item: null, fromGuest: null });
const bulkMoveModal = ref({ show: false });
const cancelModal = ref({ show: false, item: null });
const cancelOrderModal = ref({ show: false });
const showDiscountModal = ref(false);

// Discount state - computed для автоматической синхронизации с заказом
const currentDiscount = computed(() => {
    return parseFloat(currentOrder.value?.discount_amount) || 0;
});
const currentDiscountPercent = computed(() => {
    const reason = currentOrder.value?.discount_reason || '';
    const match = reason.match(/(\d+)%/);
    return match ? parseInt(match[1]) : 0;
});
const currentDiscountReason = computed(() => {
    return currentOrder.value?.discount_reason || '';
});
// Loyalty level discount
const loyaltyDiscount = computed(() => {
    return parseFloat(currentOrder.value?.loyalty_discount_amount) || 0;
});
const loyaltyLevelName = computed(() => {
    return currentOrder.value?.loyalty_level?.name || currentOrder.value?.customer?.loyaltyLevel?.name || '';
});
// Bonus spending from DiscountModal
const currentBonusToSpend = ref(0);

// Multi-select
const selectMode = ref(false);
const selectModeGuest = ref(null);
const selectedItems = ref([]);

// Toast
const toast = ref({ show: false, message: '', type: 'info' });

// Guest colors
const guestColors = [
    'bg-gradient-to-br from-blue-400 to-blue-600',
    'bg-gradient-to-br from-pink-400 to-pink-600',
    'bg-gradient-to-br from-green-400 to-green-600',
    'bg-gradient-to-br from-purple-400 to-purple-600',
    'bg-gradient-to-br from-yellow-400 to-yellow-600',
];

// CSRF setup
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

// Computed
const linkedTableNumbers = computed(() => {
    // Используем переданные с сервера названия столов
    if (initialData.linkedTableNumbers) {
        return initialData.linkedTableNumbers;
    }
    // Fallback на номер основного стола
    return table.value.name || table.value.number;
});

const currentOrder = computed(() => orders.value[currentOrderIndex.value] || null);

const orderSubtotal = computed(() => {
    if (!currentOrder.value) return 0;
    return currentOrder.value.items?.reduce((sum, item) => {
        if (['cancelled', 'voided'].includes(item.status)) return sum;
        return sum + (item.price * item.quantity);
    }, 0) || 0;
});

// Итого с учётом скидки (для оплаты) - используем order.total напрямую
const orderTotal = computed(() => {
    if (!currentOrder.value) return 0;
    // Используем total из заказа - он уже посчитан на бэкенде с округлением
    return parseFloat(currentOrder.value.total) || 0;
});

// Сумма неоплаченных позиций
const unpaidSubtotal = computed(() => {
    if (!currentOrder.value) return 0;
    return currentOrder.value.items?.reduce((sum, item) => {
        if (['cancelled', 'voided'].includes(item.status)) return sum;
        if (item.is_paid) return sum; // Пропускаем оплаченные
        return sum + (item.price * item.quantity);
    }, 0) || 0;
});

// Итого к оплате (неоплаченные с учётом скидки)
const unpaidTotal = computed(() => {
    // Если нет заказа - 0
    if (!currentOrder.value) return 0;

    const totalSubtotal = orderSubtotal.value;
    const unpaid = unpaidSubtotal.value;

    // Если всё оплачено - 0
    if (unpaid <= 0) return 0;

    // Если все позиции неоплачены - используем order.total напрямую (уже с округлением)
    if (unpaid >= totalSubtotal && totalSubtotal > 0) {
        return parseFloat(currentOrder.value.total) || 0;
    }

    // Частичная оплата - пропорциональный расчёт скидки
    const discount = currentDiscount.value;
    const loyalty = loyaltyDiscount.value;
    const totalDiscount = discount + loyalty;

    if (totalSubtotal <= 0) return 0;

    const discountRatio = unpaid / totalSubtotal;
    const proportionalDiscount = totalDiscount * discountRatio;

    // Округляем до целых рублей
    return Math.floor(Math.max(0, unpaid - proportionalDiscount));
});

// Оплаченный депозит из брони
const paidDeposit = computed(() => {
    if (!reservation.value) return 0;
    // Депозит учитывается только если он оплачен и ещё не переведён в заказ
    const isPaid = reservation.value.deposit_paid || reservation.value.deposit_status === 'paid';
    const notTransferred = reservation.value.deposit_status !== 'transferred';
    if (isPaid && notTransferred && reservation.value.deposit > 0) {
        return parseFloat(reservation.value.deposit) || 0;
    }
    return 0;
});

const currentGuests = computed(() => {
    if (!currentOrder.value) return [];

    const guestNumbers = new Set(createdGuests.value);
    currentOrder.value.items?.forEach(item => {
        guestNumbers.add(item.guest_number || 1);
    });

    // Общая сумма заказа (для расчёта пропорции скидки)
    const orderSubtotalAll = currentOrder.value.items?.reduce((sum, item) => {
        if (['cancelled', 'voided'].includes(item.status)) return sum;
        return sum + (item.price * item.quantity);
    }, 0) || 0;

    const discount = currentDiscount.value || parseFloat(currentOrder.value?.discount_amount) || 0;
    const loyalty = loyaltyDiscount.value || parseFloat(currentOrder.value?.loyalty_discount_amount) || 0;
    const totalDiscount = discount + loyalty;

    return Array.from(guestNumbers).sort((a, b) => a - b).map(num => {
        const guestItems = currentOrder.value.items?.filter(item => (item.guest_number || 1) === num) || [];
        const unpaidItems = guestItems.filter(item => !item.is_paid);
        const paidItems = guestItems.filter(item => item.is_paid);

        // Сумма товаров гостя (без скидки)
        const subtotal = guestItems.reduce((sum, item) => {
            if (['cancelled', 'voided'].includes(item.status)) return sum;
            return sum + (item.price * item.quantity);
        }, 0);

        const unpaidSubtotal = unpaidItems.reduce((sum, item) => {
            if (['cancelled', 'voided'].includes(item.status)) return sum;
            return sum + (item.price * item.quantity);
        }, 0);

        // Пропорциональная скидка для гостя (включая скидку уровня)
        const discountRatio = orderSubtotalAll > 0 ? subtotal / orderSubtotalAll : 0;
        const guestDiscount = Math.round(totalDiscount * discountRatio);

        const unpaidDiscountRatio = orderSubtotalAll > 0 ? unpaidSubtotal / orderSubtotalAll : 0;
        const guestUnpaidDiscount = Math.round(totalDiscount * unpaidDiscountRatio);

        // Итого с учётом скидки
        const total = Math.max(0, subtotal - guestDiscount);
        const unpaidTotal = Math.max(0, unpaidSubtotal - guestUnpaidDiscount);

        // Гость полностью оплачен если все его позиции оплачены
        const isPaid = guestItems.length > 0 && unpaidItems.length === 0;

        return {
            number: num,
            items: guestItems,
            subtotal,
            total,
            unpaidSubtotal,
            unpaidTotal,
            discount: guestDiscount,
            isPaid,
            collapsed: false
        };
    });
});

// Вычисляем оплаченных гостей из данных позиций (с сервера)
const paidGuestNumbers = computed(() => {
    if (!currentOrder.value?.items) return [];

    // Группируем позиции по гостям
    const guestItems = {};
    currentOrder.value.items.forEach(item => {
        if (['cancelled', 'voided'].includes(item.status)) return;
        const guestNum = item.guest_number || 1;
        if (!guestItems[guestNum]) {
            guestItems[guestNum] = { total: 0, paid: 0 };
        }
        guestItems[guestNum].total++;
        if (item.is_paid) {
            guestItems[guestNum].paid++;
        }
    });

    // Гость оплачен, если все его позиции оплачены
    return Object.entries(guestItems)
        .filter(([_, counts]) => counts.total > 0 && counts.paid === counts.total)
        .map(([guestNum]) => parseInt(guestNum));
});

const pendingItems = computed(() => {
    return currentOrder.value?.items?.filter(i => i.status === 'pending').length || 0;
});

const readyItems = computed(() => {
    return currentOrder.value?.items?.filter(i => i.status === 'ready').length || 0;
});

// Methods
const showToast = (message, type = 'info') => {
    toast.value = { show: true, message, type };
    setTimeout(() => toast.value.show = false, 3000);
};

const formatPrice = (price) => {
    return new Intl.NumberFormat('ru-RU').format(price || 0) + ' ₽';
};

const selectGuest = (guestNumber) => {
    selectedGuest.value = guestNumber;
};

const addGuest = () => {
    const maxGuest = Math.max(...createdGuests.value, 0);
    createdGuests.value.push(maxGuest + 1);
    selectedGuest.value = maxGuest + 1;
};

const toggleGuestCollapse = (guest) => {
    guest.collapsed = !guest.collapsed;
};

// Price lists
const loadPriceLists = async () => {
    try {
        const response = await fetch('/api/price-lists?active=1');
        const result = await response.json();
        const data = result.data || result;
        availablePriceLists.value = Array.isArray(data) ? data : [];
    } catch (e) {
        console.warn('Failed to load price lists:', e);
    }
};

const reloadMenu = async (priceListId) => {
    try {
        const url = `/pos/table/${table.value.id}/menu${priceListId ? '?price_list_id=' + priceListId : ''}`;
        const response = await fetch(url);
        const data = await response.json();
        categories.value = Array.isArray(data) ? data : (data.data || []);
    } catch (e) {
        console.error('Failed to reload menu:', e);
    }
};

const changePriceList = async (priceListId) => {
    selectedPriceListId.value = priceListId;
    await reloadMenu(priceListId);

    // Update current order's price_list_id
    if (currentOrder.value?.id) {
        try {
            await fetch(`/pos/table/${table.value.id}/order/${currentOrder.value.id}/price-list`, {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify({ price_list_id: priceListId })
            });
        } catch (e) {
            console.warn('Failed to update order price list:', e);
        }
    }
};

const createNewOrder = async () => {
    try {
        const response = await fetch(`/pos/table/${table.value.id}/order`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({
                linked_table_ids: linkedTableIds.value,
                price_list_id: selectedPriceListId.value,
            })
        });
        const data = await response.json();
        if (data.success) {
            orders.value.push(data.order);
            currentOrderIndex.value = orders.value.length - 1;
            createdGuests.value = [1];
            selectedGuest.value = 1;
        }
    } catch (e) {
        showToast('Ошибка создания заказа', 'error');
    }
};

const addItem = async (payload) => {
    // Support both old format (product) and new format ({ dish, variant, modifiers })
    const dish = payload.dish || payload;
    const variant = payload.variant || null;
    const modifiers = payload.modifiers || [];

    if (!dish.is_available) return;

    // Determine the product ID and name
    const productId = variant ? variant.id : dish.id;
    const productName = variant ? `${dish.name} ${variant.variant_name}` : dish.name;

    try {
        const response = await fetch(`/pos/table/${table.value.id}/order/${currentOrder.value.id}/item`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({
                product_id: productId,
                guest_id: selectedGuest.value,
                quantity: 1,
                modifiers: modifiers,
                price_list_id: selectedPriceListId.value,
            })
        });
        const data = await response.json();
        if (data.success) {
            if (!currentOrder.value.items) {
                currentOrder.value.items = [];
            }
            currentOrder.value.items.push(data.item);
            showToast(`${productName} добавлено`, 'success');
        } else {
            showToast(data.message || 'Ошибка', 'error');
        }
    } catch (e) {
        showToast('Ошибка добавления', 'error');
    }
};

const updateItemQuantity = async (item, delta) => {
    const newQty = item.quantity + delta;
    if (newQty < 1) {
        await removeItem(item);
        return;
    }

    try {
        const response = await fetch(`/pos/table/${table.value.id}/order/${currentOrder.value.id}/item/${item.id}`, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({ quantity: newQty })
        });
        const data = await response.json();
        if (data.success) {
            item.quantity = newQty;
        }
    } catch (e) {
        showToast('Ошибка обновления', 'error');
    }
};

const removeItem = async (item) => {
    // Если позиция на кухне - показываем модалку отмены
    if (!['pending', 'saved'].includes(item.status)) {
        openCancelModal(item);
        return;
    }

    // Позиция не на кухне - удаляем сразу
    try {
        const response = await fetch(`/pos/table/${table.value.id}/order/${currentOrder.value.id}/item/${item.id}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': csrfToken
            }
        });
        const data = await response.json();
        if (data.success) {
            const idx = currentOrder.value.items.findIndex(i => i.id === item.id);
            if (idx >= 0) {
                currentOrder.value.items.splice(idx, 1);
            }
            if (data.order_deleted) {
                window.location.href = '/pos#hall';
            }
        }
    } catch (e) {
        showToast('Ошибка удаления', 'error');
    }
};

// Проверка прав из сессии POS
const hasSessionPermission = (perm) => {
    try {
        const session = JSON.parse(localStorage.getItem('menulab_session'));
        const role = session?.user?.role;
        if (role === 'super_admin' || role === 'owner') return true;
        const perms = session?.permissions || [];
        return perms.includes('*') || perms.includes(perm);
    } catch {
        return false;
    }
};

const canCancelItems = computed(() => hasSessionPermission('orders.cancel'));
const canCancelOrders = computed(() => hasSessionPermission('orders.cancel'));

const openCancelModal = (item) => {
    cancelModal.value = {
        show: true,
        item: item
    };
};

const onItemCancelled = (newStatus) => {
    if (cancelModal.value.item) {
        cancelModal.value.item.status = newStatus;
    }
    showToast('Позиция отменена', 'success');
};

const onCancelRequestSent = (newStatus) => {
    if (cancelModal.value.item && newStatus) {
        cancelModal.value.item.status = newStatus;
    }
    showToast('Заявка на отмену отправлена', 'info');
};

const sendItemToKitchen = async (item) => {
    try {
        const response = await fetch(`/pos/table/${table.value.id}/order/${currentOrder.value.id}/send-kitchen`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({ item_ids: [item.id] })
        });
        const data = await response.json();
        if (data.success) {
            item.status = 'cooking';
            showToast('Отправлено на кухню', 'success');
        }
    } catch (e) {
        showToast('Ошибка', 'error');
    }
};

const sendAllToKitchen = async () => {
    const pendingIds = currentOrder.value.items
        .filter(i => i.status === 'pending')
        .map(i => i.id);

    if (pendingIds.length === 0) return;

    try {
        const response = await fetch(`/pos/table/${table.value.id}/order/${currentOrder.value.id}/send-kitchen`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({ item_ids: pendingIds })
        });
        const data = await response.json();
        if (data.success) {
            currentOrder.value.items.forEach(item => {
                if (item.status === 'pending') {
                    item.status = 'cooking';
                }
            });
            showToast(`${pendingIds.length} поз. на кухню`, 'success');
        }
    } catch (e) {
        showToast('Ошибка', 'error');
    }
};

const markItemServed = async (item) => {
    try {
        const response = await fetch(`/pos/table/${table.value.id}/order/${currentOrder.value.id}/item/${item.id}`, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({ status: 'served' })
        });
        if (response.ok) {
            item.status = 'served';
            // Уведомляем бар-панель об изменении (для мгновенного обновления)
            localStorage.setItem('bar_refresh', Date.now().toString());
        }
    } catch (e) {
        showToast('Ошибка', 'error');
    }
};

const serveAllReady = async () => {
    const readyItemsList = currentOrder.value.items.filter(i => i.status === 'ready');
    for (const item of readyItemsList) {
        await markItemServed(item);
    }
    showToast('Все блюда поданы', 'success');
};

// Reservation actions
const saveReservationChanges = async (formData) => {
    try {
        const response = await fetch(`/api/reservations/${reservation.value.id}`, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify(formData)
        });
        const data = await response.json();
        if (data.success) {
            // Обновляем данные бронирования
            reservation.value = { ...reservation.value, ...formData };
            editReservationModal.value.show = false;
            showToast('Бронирование обновлено', 'success');
        } else {
            showToast(data.message || 'Ошибка сохранения', 'error');
        }
    } catch (e) {
        showToast('Ошибка сохранения', 'error');
    }
};

const unlinkReservation = async () => {
    if (!confirm('Снять бронирование со стола? Гости останутся за столом.')) return;

    try {
        // Используем специальный endpoint для снятия со стола
        const response = await fetch(`/api/reservations/${reservation.value.id}/unseat`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            }
        });
        const data = await response.json();
        if (data.success || response.ok) {
            reservation.value = null;
            showToast('Бронирование снято со стола', 'success');
        } else {
            showToast(data.message || 'Ошибка', 'error');
        }
    } catch (e) {
        showToast('Ошибка снятия бронирования', 'error');
    }
};

// Привязка клиента к заказу
const attachCustomer = async (customer) => {
    if (!currentOrder.value) return;

    try {
        const response = await fetch(`/api/table-order/${currentOrder.value.id}/customer`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({ customer_id: customer.id })
        });
        const data = await response.json();
        if (data.success || response.ok) {
            // Обновляем заказ с данными от сервера (включая скидки)
            if (data.order) {
                orders.value[currentOrderIndex.value] = data.order;
                // Обновляем customer из отдельного поля (там правильный bonus_balance)
                if (data.customer) {
                    orders.value[currentOrderIndex.value].customer = data.customer;
                }
            } else {
                currentOrder.value.customer_id = customer.id;
                currentOrder.value.customer = data.customer || customer;
            }
            // Скидки теперь автоматически обновляются через computed из orders.value

            // Формируем сообщение о скидках
            let discountParts = [];
            if (data.loyalty_discount > 0) {
                discountParts.push(`${data.loyalty_level}: -${data.loyalty_discount}₽`);
            }
            if (data.promotion_discount > 0 && data.applied_promotions?.length > 0) {
                const promoNames = data.applied_promotions.map(p => p.name).join(', ');
                discountParts.push(`${promoNames}: -${data.promotion_discount}₽`);
            }
            const discountInfo = discountParts.length > 0 ? ` (${discountParts.join(', ')})` : '';
            showToast(`Клиент ${customer.name} привязан к заказу${discountInfo}`, 'success');
        } else {
            showToast(data.message || 'Ошибка привязки клиента', 'error');
        }
    } catch (e) {
        showToast('Ошибка привязки клиента', 'error');
    }
};

// Отвязка клиента от заказа
const detachCustomer = async () => {
    if (!currentOrder.value?.customer_id) return;

    try {
        const response = await fetch(`/api/table-order/${currentOrder.value.id}/customer`, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            }
        });
        const data = await response.json();
        if (data.success || response.ok) {
            // Обновляем заказ с данными от сервера
            if (data.order) {
                orders.value[currentOrderIndex.value] = data.order;
            } else {
                currentOrder.value.customer_id = null;
                currentOrder.value.customer = null;
            }
            // Скидки автоматически обновляются через computed из orders.value
            showToast('Клиент отвязан от заказа', 'success');
        } else {
            showToast(data.message || 'Ошибка', 'error');
        }
    } catch (e) {
        showToast('Ошибка отвязки клиента', 'error');
    }
};

// Удаление/отмена заказа - открываем модальное окно
const confirmDeleteOrder = () => {
    if (!currentOrder.value) return;
    cancelOrderModal.value.show = true;
};

// Обработчик успешной отмены заказа
const onOrderCancelled = () => {
    showToast('Заказ отменён', 'success');
    // Добавляем timestamp чтобы страница полностью перезагрузилась
    window.location.href = '/pos?t=' + Date.now() + '#hall';
};

// Обработчик отправки заявки на списание
const onOrderCancelRequestSent = () => {
    showToast('Заявка на списание отправлена', 'success');
};

// Применение скидки
const applyDiscount = async (discountData) => {
    if (!currentOrder.value) return;

    // Скидки обновятся автоматически через computed после обновления заказа

    // Сохраняем скидку в заказ
    const requestBody = {
        discount_amount: discountData.discountAmount,
        discount_percent: discountData.discountPercent,
        discount_max_amount: discountData.discountMaxAmount || null,
        discount_reason: discountData.discountReason,
        promo_code: discountData.promoCode,
        gift_item: discountData.giftItem || null,
        applied_discounts: discountData.appliedDiscounts || []
    };
    console.log('Sending discount request:', requestBody);

    try {
        const response = await fetch(`/pos/table/${table.value.id}/order/${currentOrder.value.id}/discount`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify(requestBody)
        });
        const data = await response.json();
        console.log('Discount response:', response.status, data);

        if (response.ok && data.success) {
            showToast('Скидка применена', 'success');
            // Обновляем заказ
            if (data.order) {
                orders.value[currentOrderIndex.value] = data.order;
            }
            // Сохраняем бонусы для передачи в PaymentModal
            currentBonusToSpend.value = discountData.bonusToSpend || 0;
        } else {
            // Показываем сообщение об ошибке
            let errorMessage = 'Ошибка применения скидки';
            if (data.message) {
                errorMessage = data.message;
            } else if (data.errors) {
                errorMessage = Object.values(data.errors).flat().join(', ');
            }
            showToast(errorMessage, 'error');
            console.error('Discount error:', data);
        }
    } catch (e) {
        console.error('Discount exception:', e);
        showToast('Ошибка применения скидки', 'error');
    }
};

// Comment modal
const openCommentModal = (item) => {
    commentModal.value = {
        show: true,
        item,
        text: item.comment || ''
    };
};

const saveItemComment = async () => {
    const item = commentModal.value.item;
    try {
        const response = await fetch(`/pos/table/${table.value.id}/order/${currentOrder.value.id}/item/${item.id}`, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({ comment: commentModal.value.text })
        });
        if (response.ok) {
            item.comment = commentModal.value.text;
            commentModal.value.show = false;
            showToast('Комментарий сохранён', 'success');
        }
    } catch (e) {
        showToast('Ошибка', 'error');
    }
};

// Move item modal
const openMoveModal = (item, guest) => {
    moveModal.value = {
        show: true,
        item,
        fromGuest: guest.number
    };
};

const moveItemToGuest = async (toGuestNumber) => {
    const item = moveModal.value.item;
    try {
        const response = await fetch(`/pos/table/${table.value.id}/order/${currentOrder.value.id}/item/${item.id}`, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({ guest_number: toGuestNumber })
        });
        if (response.ok) {
            item.guest_number = toGuestNumber;
            moveModal.value.show = false;
            showToast('Перенесено', 'success');
        }
    } catch (e) {
        showToast('Ошибка', 'error');
    }
};

// Multi-select
const startSelectMode = (guestNumber) => {
    selectMode.value = true;
    selectModeGuest.value = guestNumber;
    selectedItems.value = [];
};

const cancelSelectMode = () => {
    selectMode.value = false;
    selectModeGuest.value = null;
    selectedItems.value = [];
};

const toggleItemSelection = (itemId) => {
    const idx = selectedItems.value.indexOf(itemId);
    if (idx >= 0) {
        selectedItems.value.splice(idx, 1);
    } else {
        selectedItems.value.push(itemId);
    }
};

const selectAllGuestItems = (guest) => {
    selectedItems.value = guest.items.map(i => i.id);
};

const deselectAllItems = () => {
    selectedItems.value = [];
};

const openBulkMoveModal = () => {
    bulkMoveModal.value.show = true;
};

const bulkMoveToGuest = async (toGuestNumber) => {
    for (const itemId of selectedItems.value) {
        const item = currentOrder.value.items.find(i => i.id === itemId);
        if (item) {
            try {
                await fetch(`/pos/table/${table.value.id}/order/${currentOrder.value.id}/item/${itemId}`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({ guest_number: toGuestNumber })
                });
                item.guest_number = toGuestNumber;
            } catch (e) {
                console.error(e);
            }
        }
    }
    bulkMoveModal.value.show = false;
    cancelSelectMode();
    showToast('Перенесено', 'success');
};

// Print precheck
const printPrecheck = async (type = 'all') => {
    if (!currentOrder.value) return;

    try {
        if (type === 'split') {
            // Печатаем раздельные чеки для каждого гостя
            const guestNumbers = [...new Set(currentOrder.value.items?.map(item => item.guest_number || 1) || [1])];

            for (const guestNumber of guestNumbers) {
                const response = await fetch(`/api/orders/${currentOrder.value.id}/print/precheck`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({ guest_number: guestNumber })
                });
                const data = await response.json();
                if (!data.success) {
                    showToast(data.message || `Ошибка печати для гостя ${guestNumber}`, 'error');
                    return;
                }
            }
            showToast(`Напечатано ${guestNumbers.length} счетов по гостям`, 'success');
        } else {
            // Общий счёт
            const response = await fetch(`/api/orders/${currentOrder.value.id}/print/precheck`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                }
            });
            const data = await response.json();
            if (data.success) {
                showToast('Счёт отправлен на печать', 'success');
            } else {
                showToast(data.message || 'Ошибка печати счёта', 'error');
            }
        }
    } catch (e) {
        console.error('Print precheck error:', e);
        showToast('Ошибка печати', 'error');
    }
};

// Create persistent overlay that survives Vue unmount (smooth transition to POS)
const createPersistentOverlay = () => {
    const overlay = document.createElement('div');
    overlay.id = 'payment-success-overlay';
    overlay.style.cssText = `
        position: fixed;
        inset: 0;
        background: linear-gradient(135deg, #0a0a0f 0%, #1a1a2e 100%);
        z-index: 99999;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-direction: column;
    `;
    // Stylish loading animation matching POS App
    overlay.innerHTML = `
        <div style="position: relative; width: 80px; height: 80px; margin-bottom: 24px;">
            <img src="/images/logo/menulab_icon.svg" alt="" style="width: 64px; height: 64px; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); animation: logoPulse 2s ease-in-out infinite;" />
            <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 64px; height: 64px; border: 2px solid rgba(139, 92, 246, 0.3); border-radius: 50%; animation: ringExpand 2s ease-out infinite;"></div>
        </div>
        <div style="display: flex; gap: 8px;">
            <span style="width: 8px; height: 8px; background: linear-gradient(135deg, #8b5cf6, #6366f1); border-radius: 50%; animation: dotBounce 1.4s ease-in-out infinite;"></span>
            <span style="width: 8px; height: 8px; background: linear-gradient(135deg, #8b5cf6, #6366f1); border-radius: 50%; animation: dotBounce 1.4s ease-in-out 0.2s infinite;"></span>
            <span style="width: 8px; height: 8px; background: linear-gradient(135deg, #8b5cf6, #6366f1); border-radius: 50%; animation: dotBounce 1.4s ease-in-out 0.4s infinite;"></span>
        </div>
        <style>
            @keyframes logoPulse {
                0%, 100% { transform: translate(-50%, -50%) scale(1); opacity: 1; }
                50% { transform: translate(-50%, -50%) scale(1.05); opacity: 0.8; }
            }
            @keyframes ringExpand {
                0% { width: 64px; height: 64px; opacity: 0.6; }
                100% { width: 120px; height: 120px; opacity: 0; }
            }
            @keyframes dotBounce {
                0%, 80%, 100% { transform: scale(0.6); opacity: 0.4; }
                40% { transform: scale(1); opacity: 1; }
            }
        </style>
    `;
    document.body.appendChild(overlay);
};

// Payment
const confirmPayment = async ({ amount, method, change, refundAmount, fullyPaidByDeposit, depositUsed, cashAmount, cardAmount, splitByGuests, guestNumbers, bonusUsed, _handled, _stayOpen }) => {
    // If _handled flag is set, this is a callback after modal animation
    if (_handled) {
        // If _stayOpen - modal stays open for next guest payment, do nothing
        if (_stayOpen) {
            return;
        }
        // Full payment complete - create persistent overlay and redirect
        createPersistentOverlay();
        window.location.href = '/pos#hall';
        return;
    }

    try {
        const paymentData = {
            payment_method: method,
            amount: amount,
            change: change,
            refund_amount: refundAmount || 0,
            fully_paid_by_deposit: fullyPaidByDeposit || false,
            deposit_used: depositUsed || 0,
            bonus_used: bonusUsed || 0,
            reservation_id: reservation.value?.id || null
        };

        // Для смешанной оплаты добавляем отдельные суммы
        if (method === 'mixed') {
            paymentData.cash_amount = cashAmount || 0;
            paymentData.card_amount = cardAmount || 0;
        }

        // Для раздельной оплаты по гостям
        if (splitByGuests && guestNumbers && guestNumbers.length > 0) {
            paymentData.split_by_guests = true;
            paymentData.guest_numbers = guestNumbers;
        }

        const response = await fetch(`/pos/table/${table.value.id}/order/${currentOrder.value.id}/payment`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify(paymentData)
        });

        // Check if response is JSON
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            const text = await response.text();
            console.error('Non-JSON response:', text.substring(0, 500));
            throw new Error('Сервер вернул неожиданный ответ');
        }

        const data = await response.json();
        if (data.success) {
            // Reset bonus after successful payment
            currentBonusToSpend.value = 0;

            // Если оплата по гостям - помечаем их позиции как оплаченные
            if (splitByGuests && guestNumbers && guestNumbers.length > 0) {
                // Обновляем is_paid у позиций оплаченных гостей (computed paidGuestNumbers обновится автоматически)
                if (currentOrder.value?.items) {
                    currentOrder.value.items.forEach(item => {
                        if (guestNumbers.includes(item.guest_number || 1)) {
                            item.is_paid = true;
                        }
                    });
                }

                // Используем ответ сервера для определения есть ли ещё неоплаченные
                const allPaid = data.remaining === false;

                if (allPaid) {
                    // All guests paid - show success and redirect
                    paymentModalRef.value?.showSuccessAndClose({ splitByGuests, guestNumbers: null }, false);
                } else {
                    // Partial payment - show success and stay in modal
                    paymentModalRef.value?.showSuccessAndClose({ splitByGuests, guestNumbers }, true);
                }
            } else {
                // Обычная оплата всего заказа - show success and redirect
                paymentModalRef.value?.showSuccessAndClose({}, false);
            }
        } else {
            console.error('Payment error:', data);
            paymentModalRef.value?.showError(data.message || 'Ошибка оплаты');
        }
    } catch (e) {
        console.error('Payment exception:', e);
        paymentModalRef.value?.showError(e.message || 'Ошибка оплаты');
    }
};

const processSplitPayment = async ({ guestIds, method }) => {
    try {
        const response = await fetch(`/pos/table/${table.value.id}/order/${currentOrder.value.id}/payment`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({
                payment_method: 'split',
                guest_ids: guestIds,
                tips_percent: tipsPercent.value
            })
        });
        const data = await response.json();
        if (data.success) {
            showSplitPayment.value = false;
            if (!data.remaining) {
                showToast('Заказ полностью оплачен', 'success');
                setTimeout(() => {
                    window.location.href = '/pos#hall';
                }, 1000);
            } else {
                showToast(`Оплачено ${formatPrice(data.paid_amount)}`, 'success');
            }
        } else {
            showToast(data.message || 'Ошибка', 'error');
        }
    } catch (e) {
        showToast('Ошибка оплаты', 'error');
    }
};

// Cleanup on page leave
const cleanupEmptyOrders = () => {
    navigator.sendBeacon(`/pos/table/${table.value.id}/cleanup`, JSON.stringify({
        _token: csrfToken
    }));
};

onMounted(async () => {
    window.addEventListener('beforeunload', cleanupEmptyOrders);

    // Remove any leftover payment overlay from previous navigation
    const leftoverOverlay = document.getElementById('payment-success-overlay');
    if (leftoverOverlay) {
        leftoverOverlay.remove();
    }

    // Load price lists
    loadPriceLists();

    // Load bonus settings
    try {
        const response = await fetch('/api/loyalty/bonus-settings');
        const data = await response.json();
        console.log('Bonus settings response:', data);
        if (data.success && data.data) {
            bonusSettings.value = data.data;
        } else if (data && !data.success) {
            // Fallback: если API вернул данные напрямую без обертки success
            bonusSettings.value = data;
        }
    } catch (e) {
        console.warn('Failed to load bonus settings:', e);
    }

    // Load general settings (rounding, timezone)
    try {
        const response = await fetch('/api/settings/general');
        const data = await response.json();
        if (data.success && data.data) {
            roundAmounts.value = data.data.round_amounts || false;
            if (data.data.timezone) {
                setTimezone(data.data.timezone);
            }
        }
    } catch (e) {
        console.warn('Failed to load general settings:', e);
    }
});

onBeforeUnmount(() => {
    window.removeEventListener('beforeunload', cleanupEmptyOrders);
});
</script>
