/**
 * MenuLab POS - Delivery Module
 * Управление доставкой и курьерами
 */

const PosDelivery = {
    // ==================== API ВЫЗОВЫ ====================

    /**
     * Получить заказы на доставку/самовывоз
     */
    async getDeliveryOrders() {
        return await PosAPI.getDeliveryOrders();
    },

    /**
     * Получить список курьеров
     */
    async getCouriers() {
        return await PosAPI.getCouriers();
    },

    /**
     * Назначить курьера на заказ
     */
    async assignCourier(orderId, courierId) {
        const { data } = await axios.post(`${PosAPI.baseUrl}/delivery/orders/${orderId}/assign-courier`, {
            courier_id: courierId
        });
        return data;
    },

    /**
     * Изменить статус доставки
     */
    async updateDeliveryStatus(orderId, status) {
        // Маппинг order status -> delivery_status для API
        const statusMap = {
            'new': 'pending',
            'cooking': 'preparing',
            'ready': 'ready',
            'delivering': 'in_transit',
            'completed': 'delivered',
            'cancelled': 'cancelled'
        };
        const deliveryStatus = statusMap[status] || status;

        const { data } = await axios.patch(`${PosAPI.baseUrl}/delivery/orders/${orderId}/status`, {
            delivery_status: deliveryStatus
        });
        return data;
    },

    /**
     * Отменить заказ доставки
     */
    async cancelDeliveryOrder(orderId) {
        const { data } = await axios.patch(`${PosAPI.baseUrl}/delivery/orders/${orderId}/status`, {
            delivery_status: 'cancelled'
        });
        return data;
    },

    /**
     * Получить рекомендацию курьера
     */
    async suggestCourier(orderId) {
        const { data } = await axios.get(`${PosAPI.baseUrl}/delivery/orders/${orderId}/suggest-courier`);
        return data;
    },

    /**
     * Автоматически назначить курьера
     */
    async autoAssignCourier(orderId) {
        const { data } = await axios.post(`${PosAPI.baseUrl}/delivery/orders/${orderId}/auto-assign`);
        return data;
    },

    // ==================== ФИЛЬТРАЦИЯ ====================

    /**
     * Фильтровать заказы по статусу
     */
    filterOrdersByStatus(orders, status) {
        if (status === 'all') return orders;
        return orders.filter(order => order.status === status);
    },

    /**
     * Фильтровать заказы по типу (доставка/самовывоз)
     */
    filterOrdersByType(orders, type) {
        if (type === 'all') return orders;
        return orders.filter(order => order.type === type);
    },

    /**
     * Поиск по заказам
     */
    searchOrders(orders, query) {
        if (!query) return orders;
        const q = query.toLowerCase();
        return orders.filter(order =>
            order.order_number?.toString().includes(q) ||
            order.customer_name?.toLowerCase().includes(q) ||
            order.phone?.includes(q) ||
            order.delivery_address?.toLowerCase().includes(q)
        );
    },

    /**
     * Сортировать заказы по приоритету
     */
    sortOrdersByPriority(orders) {
        const statusPriority = {
            'new': 1,
            'cooking': 2,
            'ready': 3,
            'delivering': 4,
            'completed': 5,
            'cancelled': 6
        };

        return [...orders].sort((a, b) => {
            const priorityA = statusPriority[a.status] || 99;
            const priorityB = statusPriority[b.status] || 99;
            if (priorityA !== priorityB) return priorityA - priorityB;
            // При одинаковом приоритете - по времени создания
            return new Date(a.created_at) - new Date(b.created_at);
        });
    },

    // ==================== СТАТИСТИКА ====================

    /**
     * Получить статистику по заказам
     */
    getOrdersStats(orders) {
        const stats = {
            total: orders.length,
            new: 0,
            cooking: 0,
            ready: 0,
            delivering: 0,
            completed: 0,
            cancelled: 0,
            delivery: 0,
            pickup: 0
        };

        orders.forEach(order => {
            if (stats[order.status] !== undefined) stats[order.status]++;
            if (order.type === 'delivery') stats.delivery++;
            if (order.type === 'pickup') stats.pickup++;
        });

        return stats;
    },

    /**
     * Получить статистику по курьерам
     */
    getCouriersStats(couriers) {
        const stats = {
            total: couriers.length,
            available: 0,
            busy: 0,
            offline: 0
        };

        couriers.forEach(courier => {
            if (courier.status === 'available') stats.available++;
            else if (courier.status === 'busy') stats.busy++;
            else stats.offline++;
        });

        return stats;
    },

    // ==================== ФОРМАТИРОВАНИЕ ====================

    /**
     * Получить CSS класс статуса доставки
     */
    getStatusClass(status) {
        const classes = {
            'new': 'bg-blue-600 text-white',
            'cooking': 'bg-amber-600 text-white',
            'ready': 'bg-purple-600 text-white',
            'delivering': 'bg-orange-600 text-white',
            'completed': 'bg-green-600 text-white',
            'cancelled': 'bg-red-600 text-white'
        };
        return classes[status] || 'bg-gray-600 text-white';
    },

    /**
     * Получить текст статуса доставки
     */
    getStatusText(status, orderType = 'delivery') {
        const texts = {
            'new': 'Новый',
            'cooking': 'Готовится',
            'ready': orderType === 'pickup' ? 'Готов к выдаче' : 'Готов к доставке',
            'delivering': 'В пути',
            'completed': orderType === 'pickup' ? 'Выдан' : 'Доставлен',
            'cancelled': 'Отменён'
        };
        return texts[status] || status;
    },

    /**
     * Получить иконку статуса
     */
    getStatusIcon(status) {
        const icons = {
            'new': '📋',
            'cooking': '🍳',
            'ready': '✅',
            'delivering': '🚗',
            'completed': '✓',
            'cancelled': '✕'
        };
        return icons[status] || '📋';
    },

    /**
     * Получить CSS класс статуса курьера
     */
    getCourierStatusClass(status) {
        const classes = {
            'available': 'text-green-400',
            'free': 'text-green-400',
            'busy': 'text-yellow-400',
            'offline': 'text-gray-500'
        };
        return classes[status] || 'text-gray-500';
    },

    /**
     * Получить текст статуса курьера
     */
    getCourierStatusText(status) {
        const texts = {
            'available': 'Свободен',
            'free': 'Свободен',
            'busy': 'Занят',
            'offline': 'Не на линии'
        };
        return texts[status] || status;
    },

    /**
     * Получить инициалы курьера
     */
    getCourierInitials(courier) {
        return PosUtils.getCourierInitials(courier);
    },

    /**
     * Получить цвет курьера (для аватара)
     */
    getCourierColor(courier) {
        return PosUtils.getCourierColor(courier);
    },

    /**
     * Получить текст времени доставки
     */
    getDeliveryTimeText(order) {
        return PosUtils.getDeliveryTimeText(order);
    },

    /**
     * Получить CSS класс времени доставки
     */
    getDeliveryTimeClass(order) {
        return PosUtils.getDeliveryTimeClass(order);
    },

    // ==================== ДОСТУПНЫЕ ДЕЙСТВИЯ ====================

    /**
     * Получить доступные действия для заказа
     */
    getAvailableActions(order) {
        const actions = [];

        switch (order.status) {
            case 'new':
                actions.push({ action: 'cooking', label: 'В готовку', icon: '🍳' });
                actions.push({ action: 'cancel', label: 'Отменить', icon: '✕' });
                break;
            case 'cooking':
                actions.push({ action: 'ready', label: 'Готово', icon: '✅' });
                actions.push({ action: 'cancel', label: 'Отменить', icon: '✕' });
                break;
            case 'ready':
                if (order.type === 'delivery') {
                    if (order.courier_id) {
                        actions.push({ action: 'delivering', label: 'Отправить', icon: '🚗' });
                    } else {
                        actions.push({ action: 'assign_courier', label: 'Назначить курьера', icon: '👤' });
                    }
                } else {
                    actions.push({ action: 'completed', label: 'Выдать', icon: '✓' });
                }
                actions.push({ action: 'cancel', label: 'Отменить', icon: '✕' });
                break;
            case 'delivering':
                actions.push({ action: 'completed', label: 'Доставлен', icon: '✓' });
                break;
        }

        return actions;
    },

    /**
     * Получить следующий статус
     */
    getNextStatus(currentStatus, orderType = 'delivery') {
        const flow = {
            'new': 'cooking',
            'cooking': 'ready',
            'ready': orderType === 'delivery' ? 'delivering' : 'completed',
            'delivering': 'completed'
        };
        return flow[currentStatus] || null;
    },

    // ==================== ЗОНЫ ДОСТАВКИ ====================

    /**
     * Определить зону доставки по адресу
     */
    async detectDeliveryZone(address, lat, lng) {
        try {
            const { data } = await axios.post(`${PosAPI.baseUrl}/delivery/detect-zone`, {
                address,
                latitude: lat,
                longitude: lng
            });
            return data;
        } catch (e) {
            console.error('Ошибка определения зоны доставки:', e);
            return null;
        }
    },

    /**
     * Рассчитать стоимость доставки
     */
    calculateDeliveryCost(zoneInfo, orderTotal) {
        if (!zoneInfo) return 0;

        // Если есть минимальная сумма для бесплатной доставки
        if (zoneInfo.free_delivery_from && orderTotal >= zoneInfo.free_delivery_from) {
            return 0;
        }

        return zoneInfo.delivery_cost || 0;
    }
};

// Export for global usage
window.PosDelivery = PosDelivery;
