/**
 * PosResto POS - Orders Module
 * Управление заказами, оплата, отмена
 */

const PosOrders = {
    // ==================== API ВЫЗОВЫ ====================

    /**
     * Получить заказы с фильтрами
     */
    async getOrders(params = {}) {
        return await PosAPI.getOrders(params);
    },

    /**
     * Получить активные заказы (для столов)
     */
    async getActiveOrders() {
        return await PosAPI.getActiveOrders();
    },

    /**
     * Получить оплаченные заказы за сегодня
     */
    async getPaidTodayOrders() {
        return await PosAPI.getPaidTodayOrders();
    },

    /**
     * Получить заказ по ID
     */
    async getOrder(orderId) {
        return await PosAPI.getOrder(orderId);
    },

    /**
     * Создать заказ
     */
    async createOrder(orderData) {
        return await PosAPI.createOrder(orderData);
    },

    /**
     * Обновить заказ
     */
    async updateOrder(orderId, orderData) {
        return await PosAPI.updateOrder(orderId, orderData);
    },

    /**
     * Оплатить заказ
     */
    async payOrder(orderId, method, amount, fiscalize = false) {
        const { data } = await axios.post(`${PosAPI.baseUrl}/orders/${orderId}/pay`, {
            method,
            amount,
            fiscalize
        });
        return data;
    },

    /**
     * Отменить заказ со списанием
     */
    async cancelOrderWithWriteOff(orderId, reason, managerId, isWriteOff = false) {
        const { data } = await axios.post(`${PosAPI.baseUrl}/orders/${orderId}/cancel-with-writeoff`, {
            reason: reason.trim(),
            manager_id: managerId,
            is_write_off: isWriteOff
        });
        return data;
    },

    /**
     * Изменить статус заказа
     */
    async updateOrderStatus(orderId, status) {
        const { data } = await axios.post(`${PosAPI.baseUrl}/orders/${orderId}/status`, { status });
        return data;
    },

    // ==================== КОРЗИНА ====================

    /**
     * Добавить блюдо в корзину
     */
    addToCart(cart, dish, quantity = 1, modifiers = [], notes = '', guestIndex = 0) {
        const existingIndex = cart.findIndex(item =>
            item.dish_id === dish.id &&
            item.guest_index === guestIndex &&
            JSON.stringify(item.modifiers) === JSON.stringify(modifiers) &&
            item.notes === notes
        );

        if (existingIndex >= 0) {
            cart[existingIndex].quantity += quantity;
        } else {
            cart.push({
                dish_id: dish.id,
                name: dish.name,
                price: dish.price,
                quantity,
                modifiers,
                notes,
                guest_index: guestIndex,
                image: dish.image
            });
        }

        return cart;
    },

    /**
     * Удалить позицию из корзины
     */
    removeFromCart(cart, index) {
        cart.splice(index, 1);
        return cart;
    },

    /**
     * Обновить количество в корзине
     */
    updateCartItemQuantity(cart, index, quantity) {
        if (quantity <= 0) {
            return this.removeFromCart(cart, index);
        }
        cart[index].quantity = quantity;
        return cart;
    },

    /**
     * Очистить корзину
     */
    clearCart() {
        return [];
    },

    // ==================== РАСЧЁТЫ ====================

    /**
     * Подсчитать сумму корзины
     */
    calculateCartTotal(cart) {
        return cart.reduce((sum, item) => {
            let itemTotal = item.price * item.quantity;
            // Добавляем стоимость модификаторов
            if (item.modifiers && item.modifiers.length > 0) {
                const modifiersSum = item.modifiers.reduce((mSum, mod) => mSum + (mod.price || 0), 0);
                itemTotal += modifiersSum * item.quantity;
            }
            return sum + itemTotal;
        }, 0);
    },

    /**
     * Применить скидку
     */
    applyDiscount(total, discount) {
        if (!discount || discount.value <= 0) return total;

        if (discount.type === 'percent') {
            return Math.round(total * (1 - discount.value / 100));
        } else if (discount.type === 'fixed') {
            return Math.max(0, total - discount.value);
        }

        return total;
    },

    /**
     * Получить итоговую сумму с учётом скидки и доставки
     */
    calculateFinalTotal(cart, discount = null, deliveryCost = 0) {
        let total = this.calculateCartTotal(cart);
        total = this.applyDiscount(total, discount);
        return total + deliveryCost;
    },

    /**
     * Количество позиций в корзине
     */
    getCartItemsCount(cart) {
        return cart.reduce((sum, item) => sum + item.quantity, 0);
    },

    // ==================== ФОРМИРОВАНИЕ ДАННЫХ ====================

    /**
     * Сформировать полный адрес доставки
     */
    buildFullAddress(customer) {
        if (!customer.address) return '';

        let fullAddress = customer.address;
        if (customer.apartment) fullAddress += ', кв. ' + customer.apartment;
        if (customer.entrance) fullAddress += ', подъезд ' + customer.entrance;
        if (customer.floor) fullAddress += ', этаж ' + customer.floor;
        if (customer.intercom) fullAddress += ', домофон ' + customer.intercom;

        return fullAddress;
    },

    /**
     * Подготовить данные заказа для отправки
     */
    prepareOrderData(options) {
        const {
            type,
            table,
            customer,
            cart,
            discount,
            deliveryZoneInfo,
            deliveryCost,
            comment
        } = options;

        return {
            type,
            table_id: table?.id || null,
            table: table || null,
            customer,
            customer_name: customer.name,
            phone: customer.phone,
            delivery_address: this.buildFullAddress(customer),
            items: cart,
            total: this.calculateFinalTotal(cart, discount, deliveryCost),
            discount: discount,
            comment,
            // Delivery zone info
            delivery_zone_id: deliveryZoneInfo?.zone_id || null,
            delivery_fee: deliveryCost,
            delivery_latitude: deliveryZoneInfo?.coordinates?.lat || null,
            delivery_longitude: deliveryZoneInfo?.coordinates?.lng || null
        };
    },

    // ==================== СТАТУСЫ ====================

    /**
     * Получить конфиг статуса заказа
     */
    getOrderStatusConfig(status) {
        return PosConfig.orderStatuses[status] || { label: status, color: '#6b7280' };
    },

    /**
     * Получить лейбл статуса заказа
     */
    getOrderStatusLabel(status) {
        return this.getOrderStatusConfig(status).label;
    },

    /**
     * Получить цвет статуса заказа
     */
    getOrderStatusColor(status) {
        return this.getOrderStatusConfig(status).color;
    },

    /**
     * Получить CSS класс для статуса
     */
    getOrderStatusClass(status) {
        const classes = {
            'new': 'bg-blue-900/50 text-blue-400',
            'confirmed': 'bg-blue-900/50 text-blue-400',
            'cooking': 'bg-yellow-900/50 text-yellow-400',
            'ready': 'bg-green-900/50 text-green-400',
            'served': 'bg-purple-900/50 text-purple-400',
            'paid': 'bg-green-900/50 text-green-400',
            'cancelled': 'bg-red-900/50 text-red-400'
        };
        return classes[status] || 'bg-gray-700 text-gray-400';
    },

    /**
     * Проверить является ли заказ активным
     */
    isOrderActive(order) {
        const activeStatuses = ['new', 'confirmed', 'cooking', 'ready', 'served'];
        return activeStatuses.includes(order.status);
    },

    /**
     * Проверить можно ли отменить заказ
     */
    canCancelOrder(order) {
        const cancellableStatuses = ['new', 'confirmed', 'cooking', 'ready'];
        return cancellableStatuses.includes(order.status);
    },

    /**
     * Проверить можно ли оплатить заказ
     */
    canPayOrder(order) {
        const payableStatuses = ['ready', 'served'];
        return payableStatuses.includes(order.status);
    },

    // ==================== СПОСОБЫ ОПЛАТЫ ====================

    /**
     * Получить доступные способы оплаты
     */
    getPaymentMethods() {
        return PosConfig.paymentMethods;
    },

    /**
     * Получить лейбл способа оплаты
     */
    getPaymentMethodLabel(method) {
        return PosConfig.paymentMethods[method]?.label || method;
    },

    /**
     * Получить иконку способа оплаты
     */
    getPaymentMethodIcon(method) {
        return PosConfig.paymentMethods[method]?.icon || '';
    },

    // ==================== ПРИЧИНЫ ОТМЕНЫ ====================

    /**
     * Получить список причин отмены
     */
    getCancellationReasons() {
        return PosConfig.cancellationReasons;
    },

    /**
     * Получить лейбл причины отмены
     */
    getCancellationReasonLabel(value) {
        const reason = PosConfig.cancellationReasons.find(r => r.value === value);
        return reason?.label || value;
    },

    // ==================== РАЗДЕЛЕНИЕ СЧЁТА ====================

    /**
     * Разделить счёт поровну
     */
    splitBillEqual(total, parts) {
        const perPerson = Math.floor(total / parts);
        const remainder = total - (perPerson * parts);

        const bills = [];
        for (let i = 0; i < parts; i++) {
            bills.push({
                guest: i + 1,
                amount: perPerson + (i === 0 ? remainder : 0),
                items: []
            });
        }

        return bills;
    },

    /**
     * Разделить счёт по позициям
     */
    splitBillByItems(cart, guestsCount) {
        const bills = [];
        for (let i = 0; i < guestsCount; i++) {
            const guestItems = cart.filter(item => item.guest_index === i);
            const guestTotal = this.calculateCartTotal(guestItems);
            bills.push({
                guest: i + 1,
                amount: guestTotal,
                items: guestItems
            });
        }
        return bills;
    }
};

// Export for global usage
window.PosOrders = PosOrders;
