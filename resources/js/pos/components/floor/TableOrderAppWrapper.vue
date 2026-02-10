<template>
    <div class="flex flex-col h-full">
        <!-- Header -->
        <OrderHeader
            :table="table"
            :linkedTableNumbers="linkedTableNumbers"
            :reservation="reservation"
            :orders="orders"
            :currentOrderIndex="currentOrderIndex"
            :useEmitBack="true"
            :availablePriceLists="availablePriceLists"
            :selectedPriceListId="selectedPriceListId"
            @update:currentOrderIndex="currentOrderIndex = $event"
            @createNewOrder="createNewOrder"
            @changePriceList="changePriceList"
            @back="$emit('close')"
        />

        <!-- Main Content -->
        <div class="flex flex-1 overflow-hidden">
            <!-- Left Panel: Guests -->
            <GuestPanel
                :guests="currentGuests"
                :selectedGuest="selectedGuest"
                :pendingItems="pendingItemsCount"
                :readyItems="readyItemsCount"
                :reservation="reservation"
                :customer="currentOrder?.customer"
                :currentOrder="currentOrder"
                :table="table"
                :discount="currentDiscount"
                :discountReason="currentDiscountReason"
                :loyaltyDiscount="loyaltyDiscount"
                :loyaltyLevelName="loyaltyLevelName"
                :orderTotal="orderTotal"
                :unpaidTotal="unpaidTotal"
                :pendingBonusSpend="currentBonusToSpend"
            />

            <!-- Right Panel: Menu -->
            <MenuPanel
                ref="menuPanelRef"
                :categories="categories"
                :editingItem="editingItemForModifiers"
                :selectedCategory="selectedCategory"
                :searchQuery="searchQuery"
                :viewMode="viewMode"
                @update:selectedCategory="selectedCategory = $event"
                @update:searchQuery="searchQuery = $event"
                @addItem="addItem"
                @updateItemModifiers="updateItemModifiers"
                @clearEditingItem="editingItemForModifiers = null"
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
            :bonusSettings="bonusSettings"
            :customer="currentOrder?.customer"
            :initialBonusToSpend="currentBonusToSpend"
            @confirm="processPayment"
        />

        <CommentModal
            v-model="commentModal.show"
            :item="(commentModal as any).item"
            :text="commentModal.text"
            @update:text="commentModal.text = $event"
            @save="saveComment"
        />

        <MoveItemModal
            v-model="moveModal.show"
            :item="(moveModal as any).item"
            :fromGuest="(moveModal as any).fromGuest"
            :guests="currentGuests"
            :orders="orders"
            :currentOrderIndex="currentOrderIndex"
            :guestColors="guestColors"
            @move="moveItem"
        />

        <BulkMoveModal
            v-model="bulkMoveModal.show"
            :selectedCount="selectedItems.length"
            :guests="currentGuests"
            :fromGuest="selectModeGuest"
            @moveToGuest="(toGuest) => bulkMoveItems({ toGuest })"
        />

        <CancelItemModal
            v-model="cancelModal.show"
            :item="(cancelModal as any).item"
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
            v-model="showDiscountModal"
            :tableId="table?.id"
            :orderId="currentOrder?.id"
            :currentDiscount="currentDiscount"
            :currentDiscountPercent="currentDiscountPercent"
            :currentDiscountReason="currentDiscountReason"
            :currentPromoCode="currentPromoCode"
            :currentAppliedDiscounts="currentOrder?.applied_discounts || []"
            :currentBonusToSpend="currentBonusToSpend"
            :subtotal="orderSubtotal"
            :customerId="currentOrder?.customer_id"
            :customerName="currentOrder?.customer?.name"
            :customerLoyaltyLevel="currentOrder?.customer?.loyaltyLevel"
            :customerBonusBalance="currentOrder?.customer?.bonus_balance || 0"
            :bonusSettings="bonusSettings"
            :orderType="currentOrder?.type || 'dine_in'"
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

<script setup lang="ts">
import { ref, shallowRef, triggerRef, computed, watch, onMounted, onUnmounted, onBeforeUnmount, PropType } from 'vue';
import { provideOrderActions, provideOrderState } from '../../../table-order/composables/useOrderContext';
import { setTimezone } from '../../../utils/timezone';
import { useAuthStore } from '../../stores/auth';
import { useCurrentCustomer } from '../../composables/useCurrentCustomer';
import api from '../../api';
import authService from '../../../shared/services/auth';
import { createLogger } from '../../../shared/services/logger.js';
import { TOAST_DURATION } from '../../../shared/config/uiConfig.js';

// Import components from table-order
import OrderHeader from '../../../table-order/components/OrderHeader.vue';
import GuestPanel from '../../../table-order/components/GuestPanel.vue';
import MenuPanel from '../../../table-order/components/MenuPanel.vue';
import SplitPaymentModal from '../../../table-order/modals/SplitPaymentModal.vue';
import UnifiedPaymentModal from '../../../components/UnifiedPaymentModal.vue';
import CommentModal from '../../../table-order/modals/CommentModal.vue';
import MoveItemModal from '../../../table-order/modals/MoveItemModal.vue';
import BulkMoveModal from '../../../table-order/modals/BulkMoveModal.vue';
import CancelItemModal from '../../../table-order/modals/CancelItemModal.vue';
import CancelOrderModal from '../../../table-order/modals/CancelOrderModal.vue';
import DiscountModal from '../../../shared/components/modals/DiscountModal.vue';

const authStore = useAuthStore();
const log = createLogger('POS:Order');

// Global customer state management
const { setFromOrder, clear: clearCurrentCustomer } = useCurrentCustomer();

const props = defineProps({
    initialData: { type: Object as PropType<Record<string, any>>, required: true }
});

const emit = defineEmits(['close', 'orderUpdated']);

// Core data
const table = ref(props.initialData.table);
const orders = shallowRef(props.initialData.orders);
const categories = ref(props.initialData.categories);
const reservation = ref(props.initialData.reservation);
const linkedTableIds = ref(props.initialData.linkedTableIds);
const linkedTableNumbers = ref(props.initialData.linkedTableNumbers || table.value?.name || table.value?.number);
const initialGuests = ref(props.initialData.initialGuests);

// Watch for external data updates (e.g., from real-time events)
// silentRefresh заменяет orderData целиком — ссылка меняется, deep не нужен
watch(() => props.initialData.orders, (newOrders) => {
    if (newOrders) {
        orders.value = newOrders;
    }
});

// Reset global customer state based on initial data
// This prevents customer persistence across modal opens
(() => {
    const firstOrder = orders.value?.[0];
    if (firstOrder?.customer) {
        setFromOrder(firstOrder);
    } else if (reservation.value?.customer) {
        // Customer from reservation
    } else {
        // No customer - clear global state
        clearCurrentCustomer();
    }
})();

// UI State
const currentOrderIndex = ref(0);
const selectedGuest = ref(1);
const createdGuests = ref(initialGuests.value ? Array.from({ length: initialGuests.value }, (_, i) => i + 1) : [1]);
const searchQuery = ref('');
const selectedCategory = ref<any>(null);
const tipsPercent = ref(10);
const viewMode = ref('grid');

// Price list state
const availablePriceLists = ref<any[]>([]);
const selectedPriceListId = ref<any>(null);

// Bonus settings
const bonusSettings = ref<any>(null);
const roundAmounts = ref(false);

// Modal states
const showSplitPayment = ref(false);
const showPaymentModal = ref(false);
const paymentModalRef = ref<any>(null);
const commentModal = ref({ show: false, item: null, text: '' });
const moveModal = ref({ show: false, item: null, fromGuest: null });
const bulkMoveModal = ref({ show: false });
const cancelModal = ref({ show: false, item: null });
const cancelOrderModal = ref({ show: false });
const showDiscountModal = ref(false);

// Modifiers editing
const menuPanelRef = ref<any>(null);
const editingItemForModifiers = ref<any>(null);

// Discount state
const currentDiscount = ref(0);
const currentDiscountPercent = ref(0);
const currentDiscountReason = ref('');
const currentPromoCode = ref('');
const currentBonusToSpend = ref(0); // Бонусы для списания (из DiscountModal)
const loyaltyDiscount = ref(0);
const loyaltyLevelName = ref('');

// Multi-select
const selectMode = ref(false);
const selectModeGuest = ref<any>(null);
const selectedItems = ref<any[]>([]);

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

// CSRF
const csrfToken = (document.querySelector('meta[name="csrf-token"]') as any)?.content;

// Computed
const currentOrder = computed(() => orders.value[currentOrderIndex.value] || null);

const orderSubtotal = computed(() => {
    if (!currentOrder.value) return 0;
    return currentOrder.value.items?.reduce((sum: any, item: any) => {
        if (['cancelled', 'voided'].includes(item.status)) return sum;
        return sum + (item.price * item.quantity);
    }, 0) || 0;
});

const orderTotal = computed(() => {
    const subtotal = orderSubtotal.value;
    const discount = currentDiscount.value || parseFloat(currentOrder.value?.discount_amount) || 0;
    const loyalty = loyaltyDiscount.value || parseFloat(currentOrder.value?.loyalty_discount_amount) || 0;
    const bonusSpend = currentBonusToSpend.value || 0; // Enterprise: бонусы к списанию
    return Math.max(0, subtotal - discount - loyalty - bonusSpend);
});

const unpaidSubtotal = computed(() => {
    if (!currentOrder.value) return 0;
    return currentOrder.value.items?.reduce((sum: any, item: any) => {
        if (['cancelled', 'voided'].includes(item.status)) return sum;
        if (item.is_paid) return sum;
        return sum + (item.price * item.quantity);
    }, 0) || 0;
});

const unpaidTotal = computed(() => {
    const totalSubtotal = orderSubtotal.value;
    const unpaid = unpaidSubtotal.value;
    const discount = currentDiscount.value || parseFloat(currentOrder.value?.discount_amount) || 0;
    const loyalty = loyaltyDiscount.value || parseFloat(currentOrder.value?.loyalty_discount_amount) || 0;
    const bonusSpend = currentBonusToSpend.value || 0; // Enterprise: бонусы к списанию
    const totalDiscount = discount + loyalty + bonusSpend;

    if (totalSubtotal <= 0) return 0;

    const discountRatio = unpaid / totalSubtotal;
    const proportionalDiscount = totalDiscount * discountRatio;

    return Math.max(0, unpaid - proportionalDiscount);
});

const paidDeposit = computed(() => {
    if (!reservation.value) return 0;
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
    currentOrder.value.items?.forEach((item: any) => {
        guestNumbers.add(item.guest_number || 1);
    });

    const orderSubtotalAll = currentOrder.value.items?.reduce((sum: any, item: any) => {
        if (['cancelled', 'voided'].includes(item.status)) return sum;
        return sum + (item.price * item.quantity);
    }, 0) || 0;

    const discount = currentDiscount.value || parseFloat(currentOrder.value?.discount_amount) || 0;
    const loyalty = loyaltyDiscount.value || parseFloat(currentOrder.value?.loyalty_discount_amount) || 0;
    const totalDiscount = discount + loyalty;

    return Array.from(guestNumbers).sort((a: any, b: any) => a - b).map((num: any) => {
        const guestItems = currentOrder.value.items?.filter((i: any) =>
            (i.guest_number || 1) === num && !['cancelled', 'voided'].includes(i.status)
        ) || [];

        const guestSubtotal = guestItems.reduce((sum: any, i: any) => sum + (i.price * i.quantity), 0);
        const guestDiscountRatio = orderSubtotalAll > 0 ? guestSubtotal / orderSubtotalAll : 0;
        const guestDiscount = totalDiscount * guestDiscountRatio;

        const allPaid = guestItems.length > 0 && guestItems.every((i: any) => i.is_paid);

        return {
            number: num,
            items: guestItems,
            subtotal: guestSubtotal,
            discount: guestDiscount,
            total: Math.max(0, guestSubtotal - guestDiscount),
            color: guestColors[(num - 1) % guestColors.length],
            collapsed: false,
            isPaid: allPaid,
        };
    });
});

const paidGuestNumbers = computed(() => {
    return currentGuests.value.filter((g: any) => g.isPaid).map((g: any) => g.number);
});

// Check if current user can cancel items/orders (по правам из auth store)
const canCancelItems = computed(() => authStore.hasPermission('orders.cancel'));
const canCancelOrders = computed(() => authStore.hasPermission('orders.cancel'));

const pendingItemsList = computed(() => {
    return currentOrder.value?.items?.filter((i: any) => i.status === 'pending') || [];
});

const readyItemsList = computed(() => {
    return currentOrder.value?.items?.filter((i: any) => i.status === 'ready') || [];
});

// Counts for GuestPanel (expects Numbers)
const pendingItemsCount = computed(() => pendingItemsList.value.length);
const readyItemsCount = computed(() => readyItemsList.value.length);

// Init discount and bonus (Enterprise: сервер = источник правды)
watch(() => orders.value[currentOrderIndex.value], (order) => {
    if (order) {
        currentDiscount.value = parseFloat(order.discount_amount) || 0;
        currentDiscountReason.value = order.discount_reason || '';
        // Используем поле discount_percent из БД
        currentDiscountPercent.value = parseFloat(order.discount_percent) || 0;
        currentPromoCode.value = order.promo_code || '';
        loyaltyDiscount.value = parseFloat(order.loyalty_discount_amount) || 0;
        loyaltyLevelName.value = order.loyalty_level?.name || '';
        // Enterprise: бонусы для списания хранятся на сервере
        currentBonusToSpend.value = order.pending_bonus_spend || 0;
    } else {
        currentDiscount.value = 0;
        currentDiscountPercent.value = 0;
        currentDiscountReason.value = '';
        currentPromoCode.value = '';
        loyaltyDiscount.value = 0;
        loyaltyLevelName.value = '';
        currentBonusToSpend.value = 0;
    }
}, { immediate: true });

// Methods
const showToast = (message: any, type = 'info') => {
    toast.value = { show: true, message, type };
    setTimeout(() => { toast.value.show = false; }, TOAST_DURATION);
};

const selectGuest = (guestNum: any) => {
    selectedGuest.value = guestNum;
};

const addGuest = () => {
    const maxGuest = Math.max(...createdGuests.value, 0);
    const newGuest = maxGuest + 1;
    createdGuests.value.push(newGuest);
    selectedGuest.value = newGuest;
};

const toggleGuestCollapse = (guest: any) => {
    guest.collapsed = !guest.collapsed;
};

// API methods
const apiCall = async (url: any, method = 'GET', body: any = null) => {
    // Получаем токен из централизованного auth service
    const authHeader = authService.getAuthHeader();

    const options = {
        method,
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            'X-Requested-With': 'XMLHttpRequest',
            ...(authHeader ? { 'Authorization': authHeader } : {}),
        },
    };
    if (body) (options as any).body = JSON.stringify(body);

    log.debug(`API ${method} ${url}`, body);
    const response = await fetch(url, options);
    const data = await response.json();
    log.debug(`API Response ${response.status}:`, data);

    if (!response.ok) {
        // Формируем понятное сообщение об ошибке
        let errorMessage = data.message || 'Ошибка запроса';
        if (data.errors) {
            errorMessage = Object.values(data.errors).flat().join(', ');
        }
        const error = new Error(errorMessage);
        (error as any).response = data;
        (error as any).status = response.status;
        throw error;
    }

    return data;
};

// Helper: формируем URL в зависимости от того, это бар или обычный стол
const isBarOrder = () => table.value?.is_bar || table.value?.id === 'bar';

const getOrderUrl = (orderId: any, suffix = '') => {
    if (isBarOrder()) {
        return `/pos/bar/order/${orderId}${suffix}`;
    }
    return `/pos/table/${table.value.id}/order/${orderId}${suffix}`;
};

const getTableBaseUrl = () => {
    if (isBarOrder()) {
        return '/pos/bar';
    }
    return `/pos/table/${table.value.id}`;
};

// Price lists
const loadPriceLists = async () => {
    try {
        const result = await apiCall('/api/price-lists?active=1');
        const data = result.data || result;
        availablePriceLists.value = Array.isArray(data) ? data : [];
    } catch (e: any) {
        log.warn('Failed to load price lists:', e);
    }
};

const reloadMenu = async (priceListId: any) => {
    try {
        const url = `/pos/menu${priceListId ? '?price_list_id=' + priceListId : ''}`;
        const result = await apiCall(url);
        categories.value = Array.isArray(result) ? result : (result.data || []);
    } catch (e: any) {
        log.error('Failed to reload menu:', e);
    }
};

const changePriceList = async (priceListId: any) => {
    selectedPriceListId.value = priceListId;
    await reloadMenu(priceListId);

    // Update current order's price_list_id if it exists
    if (currentOrder.value?.id) {
        try {
            await apiCall(
                getOrderUrl(currentOrder.value.id, '/price-list'),
                'PATCH',
                { price_list_id: priceListId }
            );
        } catch (e: any) {
            log.warn('Failed to update order price list:', e);
        }
    }
};

const addItem = async (payload: any) => {
    log.debug('addItem called with payload:', payload);

    if (!currentOrder.value) {
        log.debug('No currentOrder, returning');
        return;
    }

    // Support both old format (product) and new format ({ dish, variant, modifiers })
    const dish = payload.dish || payload;
    const variant = payload.variant || null;
    const modifiers = payload.modifiers || [];

    log.debug('dish:', dish?.name, 'id:', dish?.id, 'is_available:', dish?.is_available);

    // Determine the product ID and name
    const dishId = variant ? variant.id : dish.id;
    const productName = variant ? `${dish.name} ${variant.variant_name}` : dish.name;

    log.debug('Adding item:', productName, 'dishId:', dishId);

    try {
        const url = getOrderUrl(currentOrder.value.id, '/item');
        const body = {
            product_id: dishId,
            quantity: 1,
            guest_id: selectedGuest.value,
            modifiers: modifiers,
            price_list_id: selectedPriceListId.value,
        };
        log.debug('Calling apiCall:', url, body);

        const result = await apiCall(url, 'POST', body);

        log.debug('API result:', result);

        if (result.success && result.order) {
            // Replace entire order to get fresh data with modifiers
            orders.value[currentOrderIndex.value] = result.order;
            triggerRef(orders);
            log.debug('Item added, items count:', result.order.items?.length);
            showToast(`${productName} добавлено`, 'success');
            emit('orderUpdated');
        } else if (result.message) {
            log.debug('Server returned message:', result.message);
            showToast(result.message, 'error');
        }
    } catch (e: any) {
        log.error('addItem error:', e);
        showToast('Ошибка добавления', 'error');
    }
};

const updateItemQuantity = async (item: any, delta: any) => {
    const newQty = item.quantity + delta;
    if (newQty < 1) {
        await removeItem(item);
        return;
    }

    try {
        const result = await apiCall(
            getOrderUrl(currentOrder.value.id, `/item/${item.id}`),
            'PATCH',
            { quantity: newQty }
        );

        if (result.success || result.order) {
            // Update locally
            item.quantity = newQty;
            triggerRef(orders);
            emit('orderUpdated');
        }
    } catch (e: any) {
        showToast('Ошибка обновления', 'error');
    }
};

const removeItem = async (item: any) => {
    // Если позиция на кухне - показываем модалку отмены
    if (!['pending', 'saved'].includes(item.status)) {
        openCancelModal(item);
        return;
    }

    // Позиция не на кухне - удаляем сразу
    try {
        const result = await apiCall(
            getOrderUrl(currentOrder.value.id, `/item/${item.id}`),
            'DELETE'
        );

        if (result.success || result.order) {
            if (result.order) {
                orders.value[currentOrderIndex.value] = result.order;
                triggerRef(orders);
            } else {
                const idx = currentOrder.value.items.findIndex((i: any) => i.id === item.id);
                if (idx >= 0) {
                    currentOrder.value.items.splice(idx, 1);
                    triggerRef(orders);
                }
            }
            emit('orderUpdated');

            if (result.order_deleted) {
                emit('close');
            }
        }
    } catch (e: any) {
        showToast('Ошибка удаления', 'error');
    }
};

const openCancelModal = (item: any) => {
    cancelModal.value = { show: true, item };
};

const onItemCancelled = (newStatus: any) => {
    if (cancelModal.value.item) {
        (cancelModal.value.item as any).status = newStatus;
        triggerRef(orders);
    }
    showToast('Позиция отменена', 'success');
    emit('orderUpdated');
};

const onCancelRequestSent = (newStatus: any) => {
    if (cancelModal.value.item && newStatus) {
        (cancelModal.value.item as any).status = newStatus;
        triggerRef(orders);
    }
    showToast('Заявка на отмену отправлена', 'info');
};

const sendItemToKitchen = async (item: any) => {
    try {
        const result = await apiCall(
            getOrderUrl(currentOrder.value.id, '/send-kitchen'),
            'POST',
            { item_ids: [item.id] }
        );

        if (result.success) {
            // Update only the item status locally (without replacing entire order)
            // This prevents the "flying away" animation glitch
            item.status = 'cooking';
            triggerRef(orders);
            showToast('Отправлено на кухню', 'success');
            emit('orderUpdated');
        }
    } catch (e: any) {
        showToast('Ошибка отправки', 'error');
    }
};

const sendAllToKitchen = async () => {
    const pendingItems = pendingItemsList.value;
    const itemIds = pendingItems.map((i: any) => i.id);
    if (!itemIds.length) return;

    try {
        const result = await apiCall(
            getOrderUrl(currentOrder.value.id, '/send-kitchen'),
            'POST',
            { item_ids: itemIds }
        );

        if (result.success) {
            // Update items status locally (without replacing entire order)
            pendingItems.forEach((item: any) => {
                item.status = 'cooking';
            });
            triggerRef(orders);
            emit('orderUpdated');
            showToast(`${itemIds.length} поз. на кухню`, 'success');
        }
    } catch (e: any) {
        showToast('Ошибка отправки', 'error');
    }
};

const markItemServed = async (item: any) => {
    try {
        const result = await apiCall(
            getOrderUrl(currentOrder.value.id, `/item/${item.id}`),
            'PATCH',
            { status: 'served' }
        );

        if (result.success || result.order) {
            // Update only the item status locally
            item.status = 'served';
            triggerRef(orders);
            // Уведомляем бар-панель об изменении (для мгновенного обновления)
            localStorage.setItem('bar_refresh', Date.now().toString());
            emit('orderUpdated');
        }
    } catch (e: any) {
        showToast('Ошибка', 'error');
    }
};

const serveAllReady = async () => {
    for (const item of readyItemsList.value) {
        await markItemServed(item);
    }
};

const openCommentModal = (item: any) => {
    commentModal.value = { show: true, item, text: item.comment || '' };
};

const saveComment = async () => {
    const item = commentModal.value.item;
    const text = (commentModal.value.text || '').replace(/,\s*$/, '').trim();
    try {
        const result = await apiCall(
            getOrderUrl(currentOrder.value.id, `/item/${(item as any)!.id}`),
            'PATCH',
            { comment: text }
        );

        if (result.success || result.order) {
            (item as any)!.comment = text;
            triggerRef(orders);
        }
        commentModal.value.show = false;
        showToast('Комментарий сохранён', 'success');
    } catch (e: any) {
        showToast('Ошибка сохранения', 'error');
    }
};

// Open modifier panel for existing order item
const openModifiersModal = (item: any) => {
    // Find the parent dish to get modifiers list
    const dishId = item.dish_id || item.dish?.id;
    let parentDish = null;

    for (const cat of categories.value) {
        // Check if it's a direct product
        const found = cat.products?.find((p: any) => p.id === dishId);
        if (found) {
            parentDish = found;
            break;
        }

        // Check if it's a variant
        for (const product of (cat.products || [])) {
            if (product.variants?.some((v: any) => v.id === dishId)) {
                parentDish = product;
                break;
            }
        }
        if (parentDish) break;
    }

    if (!parentDish || !parentDish.modifiers?.length) {
        showToast('Нет доступных модификаторов', 'info');
        return;
    }

    // Set the editing item - MenuPanel will react to this
    editingItemForModifiers.value = {
        ...item,
        parentDish: parentDish
    };
};

// Update modifiers for existing order item
const updateItemModifiers = async ({ item, modifiers }: any) => {
    try {
        const result = await apiCall(
            getOrderUrl(currentOrder.value.id, `/item/${item.id}`),
            'PATCH',
            { modifiers: modifiers }
        );

        if (result.success || result.order) {
            // Find the original item in the order and update it
            const originalItem = currentOrder.value.items?.find((i: any) => i.id === item.id);
            if (originalItem) {
                originalItem.modifiers = modifiers;
                // Update price if returned from server
                if (result.item?.price) {
                    originalItem.price = result.item.price;
                }
                triggerRef(orders);
            }
            showToast('Модификаторы обновлены', 'success');
        }
        editingItemForModifiers.value = null;
    } catch (e: any) {
        showToast('Ошибка сохранения', 'error');
    }
};

const openMoveModal = (item: any) => {
    moveModal.value = { show: true, item, fromGuest: item.guest_number || 1 };
};

const moveItem = async ({ item, toGuest, toOrderIndex }: any) => {
    try {
        const result = await apiCall(
            getOrderUrl(currentOrder.value.id, `/item/${item.id}`),
            'PATCH',
            { guest_number: toGuest }
        );

        if (result.success || result.order) {
            // Update locally
            item.guest_number = toGuest;
            triggerRef(orders);
            emit('orderUpdated');
        }
        moveModal.value.show = false;
        showToast('Перенесено', 'success');
    } catch (e: any) {
        showToast('Ошибка перемещения', 'error');
    }
};

// Multi-select
const startSelectMode = (guestNum: any) => {
    selectMode.value = true;
    selectModeGuest.value = guestNum;
    selectedItems.value = [];
};

const cancelSelectMode = () => {
    selectMode.value = false;
    selectModeGuest.value = null;
    selectedItems.value = [];
};

const toggleItemSelection = (item: any) => {
    const idx = selectedItems.value.findIndex((i: any) => i.id === item.id);
    if (idx >= 0) {
        selectedItems.value.splice(idx, 1);
    } else {
        selectedItems.value.push(item);
    }
};

const selectAllGuestItems = (guest: any) => {
    selectedItems.value = [...guest.items];
};

const deselectAllItems = () => {
    selectedItems.value = [];
};

const openBulkMoveModal = () => {
    bulkMoveModal.value.show = true;
};

const bulkMoveItems = async ({ toGuest }: any) => {
    for (const item of selectedItems.value) {
        await moveItem({ item, toGuest });
    }
    cancelSelectMode();
    bulkMoveModal.value.show = false;
};

// Discount
const applyDiscount = async ({ discountAmount, discountPercent, discountMaxAmount, discountReason, promoCode, giftItem, appliedDiscounts, bonusToSpend }: any) => {
    try {
        const result = await apiCall(
            getOrderUrl(currentOrder.value.id, '/discount'),
            'POST',
            {
                discount_amount: discountAmount,
                discount_percent: discountPercent,
                discount_max_amount: discountMaxAmount,
                discount_reason: discountReason,
                promo_code: promoCode,
                gift_item: giftItem, // Передаём информацию о подарке
                applied_discounts: appliedDiscounts, // Детальная информация о скидках
                bonus_to_spend: bonusToSpend || 0 // Enterprise: сохраняем на сервере
            }
        );

        if (result.order) {
            orders.value[currentOrderIndex.value] = result.order;
            triggerRef(orders);
            currentDiscount.value = discountAmount;
            currentDiscountPercent.value = discountPercent;
            currentDiscountReason.value = discountReason;
            currentPromoCode.value = promoCode || '';
            // Enterprise: читаем бонусы с сервера (единый источник правды)
            currentBonusToSpend.value = result.order.pending_bonus_spend || 0;
            emit('orderUpdated');

            // Показываем сообщение с учётом подарка
            if (giftItem) {
                showToast(`Подарок "${giftItem.name}" добавлен`, 'success');
            } else {
                showToast('Скидка применена', 'success');
            }
        }
        showDiscountModal.value = false;
    } catch (e: any) {
        log.error('Apply discount error:', e);
        showToast(e.message || 'Ошибка применения скидки', 'error');
    }
};

const removeDiscount = async () => {
    await applyDiscount({ discountAmount: 0, discountPercent: 0, discountMaxAmount: null, discountReason: '', promoCode: '' });
};

// Payment
const processPayment = async (paymentData: any) => {
    const { _handled, _stayOpen, splitByGuests, guestNumbers } = paymentData;

    // If _handled flag is set, this is a callback after modal animation
    if (_handled) {
        if (_stayOpen) {
            // Modal stays open for next guest payment
            return;
        }
        // Full payment complete - close and return to hall
        showPaymentModal.value = false;
        emit('orderUpdated');
        setTimeout(() => emit('close'), 300);
        return;
    }

    // Transform camelCase to snake_case for backend
    const transformedData = {
        payment_method: paymentData.method,
        amount: paymentData.amount,
        cash_amount: paymentData.cashAmount || 0,
        card_amount: paymentData.cardAmount || 0,
        bonus_used: paymentData.bonusUsed || 0,
        deposit_used: paymentData.depositUsed || 0,
        refund_amount: paymentData.refundAmount || 0,
        fully_paid_by_deposit: paymentData.fullyPaidByDeposit || false,
        split_by_guests: splitByGuests || false,
        guest_numbers: guestNumbers || [],
        tips_percent: paymentData.tipsPercent || 0,
        reservation_id: paymentData.reservationId || reservation.value?.id || null,
    };

    try {
        const result = await apiCall(
            getOrderUrl(currentOrder.value.id, '/payment'),
            'POST',
            transformedData
        );

        if (result.success) {
            emit('orderUpdated');

            // Update order data
            if (result.order) {
                orders.value[currentOrderIndex.value] = result.order;
                triggerRef(orders);
            }

            // Reset bonus after successful payment
            currentBonusToSpend.value = 0;

            // Handle split payment by guests
            if (splitByGuests && guestNumbers && guestNumbers.length > 0) {
                const allPaid = result.remaining === false;

                if (allPaid) {
                    // All guests paid - show success and close
                    paymentModalRef.value?.showSuccessAndClose({ splitByGuests, guestNumbers: null }, false);
                } else {
                    // Partial payment - show success and stay in modal
                    paymentModalRef.value?.showSuccessAndClose({ splitByGuests, guestNumbers }, true);
                }
            } else {
                // Full payment - show success and close
                paymentModalRef.value?.showSuccessAndClose({}, false);
            }
        } else {
            paymentModalRef.value?.showError(result.message || 'Ошибка оплаты');
        }
    } catch (e: any) {
        paymentModalRef.value?.showError('Ошибка оплаты');
    }
};

const processSplitPayment = async (paymentData: any) => {
    await processPayment(paymentData);
    showSplitPayment.value = false;
};

// Order management
const createNewOrder = async () => {
    try {
        const result = await apiCall(
            `${getTableBaseUrl()}/order`,
            'POST',
            {
                linked_table_ids: linkedTableIds.value,
                price_list_id: selectedPriceListId.value,
            }
        );

        if (result.order) {
            orders.value.push(result.order);
            triggerRef(orders);
            currentOrderIndex.value = orders.value.length - 1;
            createdGuests.value = [1];
            selectedGuest.value = 1;
            emit('orderUpdated');
        }
    } catch (e: any) {
        showToast('Ошибка создания заказа', 'error');
    }
};

const confirmDeleteOrder = () => {
    if (!currentOrder.value) return;
    cancelOrderModal.value.show = true;
};

// Обработчик успешной отмены заказа
const onOrderCancelled = () => {
    showToast('Заказ отменён', 'success');
    emit('orderUpdated');
    emit('close');
};

// Обработчик отправки заявки на списание
const onOrderCancelRequestSent = () => {
    showToast('Заявка на списание отправлена', 'success');
};

// Reservation actions
const saveReservationChanges = async (formData: any) => {
    if (!reservation.value) return;

    try {
        const result = await apiCall(
            `/api/reservations/${reservation.value.id}`,
            'PATCH',
            formData
        );

        if (result.success) {
            // Обновляем данные бронирования
            reservation.value = { ...reservation.value, ...formData };
            showToast('Бронирование обновлено', 'success');
        } else {
            showToast(result.message || 'Ошибка сохранения', 'error');
        }
    } catch (e: any) {
        showToast('Ошибка сохранения', 'error');
    }
};

const unlinkReservation = async () => {
    if (!reservation.value) return;

    if (!confirm('Снять бронирование со стола? Гости останутся за столом.')) return;

    try {
        const result = await apiCall(
            `/api/reservations/${reservation.value.id}/unseat`,
            'POST'
        );

        if (result.success || result.ok !== false) {
            reservation.value = null;
            showToast('Бронирование снято со стола', 'success');
        } else {
            showToast(result.message || 'Ошибка', 'error');
        }
    } catch (e: any) {
        showToast('Ошибка снятия бронирования', 'error');
    }
};

// Print precheck
const printPrecheck = async (type = 'all') => {
    if (!currentOrder.value) return;

    try {
        if (type === 'split') {
            // Печатаем раздельные чеки для каждого гостя
            const guestNumbers = [...new Set(currentOrder.value.items?.map((item: any) => item.guest_number || 1) || [1])];

            for (const guestNumber of guestNumbers) {
                const result = await apiCall(
                    `/api/orders/${currentOrder.value.id}/print/precheck`,
                    'POST',
                    { guest_number: guestNumber }
                );

                if (!result.success) {
                    showToast(result.message || `Ошибка печати для гостя ${guestNumber}`, 'error');
                    return;
                }
            }
            showToast(`Напечатано ${guestNumbers.length} счетов по гостям`, 'success');
        } else {
            // Общий счёт
            const result = await apiCall(
                `/api/orders/${currentOrder.value.id}/print/precheck`,
                'POST'
            );

            if (result.success) {
                showToast('Счёт отправлен на печать', 'success');
            } else {
                showToast(result.message || 'Ошибка печати счёта', 'error');
            }
        }
    } catch (e: any) {
        log.error('Print precheck error:', e);
        showToast('Ошибка печати', 'error');
    }
};

// Привязка клиента к заказу
const attachCustomer = async (customer: any) => {
    if (!currentOrder.value) return;

    // Enterprise: проверяем смену клиента
    const previousCustomerId = currentOrder.value.customer_id;
    const isCustomerChange = previousCustomerId && previousCustomerId !== customer.id;

    try {
        const result = await apiCall(
            `/api/table-order/${currentOrder.value.id}/customer`,
            'POST',
            { customer_id: customer.id }
        );

        if (result.success || result.order) {
            // Enterprise: обновляем заказ с данными от сервера (сервер = источник правды)
            if (result.order) {
                orders.value[currentOrderIndex.value] = result.order;
                triggerRef(orders);
                // Синхронизируем локальные состояния с сервером
                currentBonusToSpend.value = result.order.pending_bonus_spend || 0;
            } else {
                currentOrder.value.customer_id = customer.id;
                currentOrder.value.customer = customer;
                triggerRef(orders);
            }

            // Обновляем скидку уровня нового клиента
            loyaltyDiscount.value = parseFloat(result.loyalty_discount) || 0;
            loyaltyLevelName.value = result.loyalty_level || '';

            // Enterprise: при смене клиента сервер сбросил все скидки
            if (isCustomerChange) {
                currentBonusToSpend.value = 0;
                showToast('Скидки сброшены - клиент изменён', 'info');
            }

            const discountInfo = result.loyalty_discount > 0 ? ` (скидка ${result.loyalty_level}: -${result.loyalty_discount}₽)` : '';
            showToast(`Клиент ${customer.name} привязан к заказу${discountInfo}`, 'success');
            emit('orderUpdated');
        } else {
            showToast(result.message || 'Ошибка привязки клиента', 'error');
        }
    } catch (e: any) {
        showToast('Ошибка привязки клиента', 'error');
    }
};

// Отвязка клиента от заказа
// Enterprise: сервер автоматически сбрасывает ВСЕ скидки при отвязке
const detachCustomer = async () => {
    if (!currentOrder.value?.customer_id) return;

    const hadDiscounts = currentBonusToSpend.value > 0 || loyaltyDiscount.value > 0 ||
        currentOrder.value.discount_amount > 0;

    try {
        const result = await apiCall(
            `/api/table-order/${currentOrder.value.id}/customer`,
            'DELETE'
        );

        if (result.success || result.order) {
            // Enterprise: обновляем заказ с данными от сервера (сервер = источник правды)
            if (result.order) {
                orders.value[currentOrderIndex.value] = result.order;
                triggerRef(orders);
                // Синхронизируем локальные состояния
                currentBonusToSpend.value = result.order.pending_bonus_spend || 0;
            } else {
                currentOrder.value.customer_id = null;
                currentOrder.value.customer = null;
                triggerRef(orders);
            }

            // Сбрасываем локальные состояния скидок
            loyaltyDiscount.value = 0;
            loyaltyLevelName.value = '';
            currentBonusToSpend.value = 0;

            if (hadDiscounts) {
                showToast('Клиент отвязан, все скидки сброшены', 'info');
            } else {
                showToast('Клиент отвязан от заказа', 'success');
            }
            emit('orderUpdated');
        } else {
            showToast(result.message || 'Ошибка', 'error');
        }
    } catch (e: any) {
        showToast('Ошибка отвязки клиента', 'error');
    }
};

// ===== Provide order context for GuestPanel / GuestSection =====
provideOrderActions({
    selectGuest,
    addGuest,
    toggleGuestCollapse,
    updateItemQuantity,
    removeItem,
    sendItemToKitchen,
    openCommentModal,
    openMoveModal,
    markItemServed,
    startSelectMode,
    cancelSelectMode,
    toggleItemSelection,
    selectAllGuestItems,
    deselectAllItems,
    openBulkMoveModal: () => { bulkMoveModal.value.show = true; },
    sendAllToKitchen,
    serveAllReady,
    openSplitPayment: () => { showSplitPayment.value = true; },
    openPaymentModal: () => { showPaymentModal.value = true; },
    openDiscountModal: () => { showDiscountModal.value = true; },
    deleteOrder: confirmDeleteOrder,
    saveReservation: saveReservationChanges,
    unlinkReservation,
    printPrecheck: printPrecheck as any,
    attachCustomer,
    detachCustomer,
    openModifiersModal,
});

provideOrderState({
    selectMode: computed(() => selectMode.value),
    selectModeGuest: computed(() => selectModeGuest.value),
    selectedItems: computed(() => selectedItems.value),
    guestColors,
    categories: computed(() => categories.value),
    roundAmounts: computed(() => roundAmounts.value),
});

// Cleanup on unmount
const cleanupEmptyOrders = () => {
    if (table.value?.id && !isBarOrder()) {
        navigator.sendBeacon(`/pos/table/${table.value.id}/cleanup`, JSON.stringify({
            _token: csrfToken
        }));
    }
};

// Load settings
onMounted(async () => {
    window.addEventListener('beforeunload', cleanupEmptyOrders);

    // Load price lists
    loadPriceLists();

    // Load bonus settings
    try {
        // Interceptor бросит исключение при success: false
        const response = await api.loyalty.getBonusSettings();
        bonusSettings.value = response?.data || response || {};
    } catch (e: any) {
        log.warn('Failed to load bonus settings:', e);
    }

    // Load general settings (rounding, timezone)
    try {
        const data = await api.settings.getGeneral();
        if (data) {
            roundAmounts.value = (data as any).round_amounts || false;
            if (data.timezone) {
                setTimezone(data.timezone as any);
            }
        }
    } catch (e: any) {
        log.warn('Failed to load general settings:', e);
    }
});

onBeforeUnmount(() => {
    window.removeEventListener('beforeunload', cleanupEmptyOrders);
});
</script>

