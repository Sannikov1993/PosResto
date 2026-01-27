/**
 * PosResto POS - Configuration
 * Константы и конфигурация приложения
 */

const PosConfig = {
    // ==================== API ENDPOINTS ====================
    API_BASE: '/api',

    endpoints: {
        // Auth
        login: '/api/staff/login',
        logout: '/api/staff/logout',

        // Tables & Zones
        tables: (restaurantId) => `/api/restaurants/${restaurantId}/tables`,
        zones: (restaurantId) => `/api/restaurants/${restaurantId}/zones`,
        floorPlan: (restaurantId) => `/api/restaurants/${restaurantId}/floor-plan`,

        // Orders
        orders: (restaurantId) => `/api/restaurants/${restaurantId}/orders`,
        order: (orderId) => `/api/orders/${orderId}`,

        // Reservations
        reservations: (restaurantId) => `/api/restaurants/${restaurantId}/reservations`,
        tableReservations: (restaurantId, tableId) => `/api/restaurants/${restaurantId}/tables/${tableId}/reservations`,

        // Shifts
        shifts: (restaurantId) => `/api/finance/${restaurantId}/shifts`,
        currentShift: (restaurantId) => `/api/finance/${restaurantId}/shifts/current`,

        // Menu
        menu: (restaurantId) => `/api/restaurants/${restaurantId}/menu`,
        categories: (restaurantId) => `/api/restaurants/${restaurantId}/categories`,
        dishes: (restaurantId) => `/api/restaurants/${restaurantId}/dishes`,

        // Delivery
        delivery: (restaurantId) => `/api/restaurants/${restaurantId}/delivery`,
        couriers: (restaurantId) => `/api/restaurants/${restaurantId}/couriers`,

        // Customers
        customers: (restaurantId) => `/api/restaurants/${restaurantId}/customers`,

        // Stoplist
        stoplist: (restaurantId) => `/api/restaurants/${restaurantId}/stoplist`,

        // Write-offs
        writeoffs: (restaurantId) => `/api/restaurants/${restaurantId}/writeoffs`,
        cancellations: (restaurantId) => `/api/restaurants/${restaurantId}/cancellations`,

        // Inventory
        inventory: (restaurantId) => `/api/restaurants/${restaurantId}/inventory`,

        // Settings
        settings: (restaurantId) => `/api/restaurants/${restaurantId}/settings`,
        printers: (restaurantId) => `/api/restaurants/${restaurantId}/printers`
    },

    // ==================== TAB CONFIGURATION ====================
    tabs: [
        { id: 'cash', label: 'Касса', icon: '💰' },
        { id: 'orders', label: 'Заказы', icon: '🍽️' },
        { id: 'delivery', label: 'Доставка', icon: '🚗' },
        { id: 'catalog', label: 'Каталог', icon: '📋' },
        { id: 'kitchen', label: 'Кухня', icon: '👨‍🍳' },
        { id: 'stoplist', label: 'Стоп-лист', icon: '🚫' },
        { id: 'customers', label: 'Клиенты', icon: '👥' },
        { id: 'writeoffs', label: 'Списания', icon: '📝' },
        { id: 'settings', label: 'Настройки', icon: '⚙️' }
    ],

    // ==================== TABLE STATUSES ====================
    tableStatuses: {
        free: { label: 'Свободен', color: '#22c55e', class: 'table-free' },
        occupied: { label: 'Занят', color: '#f59e0b', class: 'table-occupied' },
        reserved: { label: 'Бронь', color: '#3B82F6', class: 'table-reserved' },
        bill: { label: 'Счёт', color: '#8b5cf6', class: 'table-bill' },
        ready: { label: 'Готов', color: '#4A7C59', class: 'table-ready' }
    },

    // ==================== ORDER STATUSES ====================
    orderStatuses: {
        new: { label: 'Новый', color: '#3B82F6' },
        cooking: { label: 'Готовится', color: '#f59e0b' },
        ready: { label: 'Готов', color: '#22c55e' },
        served: { label: 'Подан', color: '#8b5cf6' },
        paid: { label: 'Оплачен', color: '#22c55e' },
        cancelled: { label: 'Отменён', color: '#ef4444' }
    },

    // ==================== DELIVERY STATUSES ====================
    deliveryStatuses: {
        new: { label: 'Новый', color: '#3B82F6', icon: '📋' },
        cooking: { label: 'Готовится', color: '#f59e0b', icon: '🍳' },
        ready: { label: 'Готов', color: '#8b5cf6', icon: '✅' },
        delivering: { label: 'В пути', color: '#f97316', icon: '🚗' },
        delivered: { label: 'Доставлен', color: '#22c55e', icon: '✓' },
        cancelled: { label: 'Отменён', color: '#ef4444', icon: '✕' }
    },

    // ==================== PAYMENT METHODS ====================
    paymentMethods: {
        cash: { label: 'Наличные', icon: '💵' },
        card: { label: 'Карта', icon: '💳' },
        online: { label: 'Онлайн', icon: '📱' },
        qr: { label: 'QR-код', icon: '📷' }
    },

    // ==================== CANCELLATION REASONS ====================
    cancellationReasons: [
        { value: 'guest_refused', label: 'Гость отказался' },
        { value: 'wrong_order', label: 'Ошибка в заказе' },
        { value: 'quality', label: 'Проблема с качеством' },
        { value: 'long_wait', label: 'Долгое ожидание' },
        { value: 'other', label: 'Другое' }
    ],

    // ==================== WRITE-OFF REASONS ====================
    writeOffReasons: [
        { value: 'expired', label: 'Истёк срок годности' },
        { value: 'spoiled', label: 'Испортилось' },
        { value: 'cooking_loss', label: 'Потери при готовке' },
        { value: 'staff_meal', label: 'Питание персонала' },
        { value: 'other', label: 'Другое' }
    ],

    // ==================== SESSION CONFIG ====================
    session: {
        storageKey: 'pos_session',
        expiryHours: 12,
        activityTimeoutMinutes: 30
    },

    // ==================== FLOOR PLAN CONFIG ====================
    floor: {
        defaultScale: 1,
        minScale: 0.5,
        maxScale: 2,
        scaleStep: 0.1,
        defaultWidth: 1200,
        defaultHeight: 800
    },

    // ==================== RESERVATION CONFIG ====================
    reservation: {
        slotDurationMinutes: 30,
        minDurationMinutes: 30,
        maxDurationMinutes: 240,
        defaultDurationMinutes: 120,
        soonThresholdMinutes: 30,
        overdueThresholdMinutes: 15
    }
};

// Export for global usage
window.PosConfig = PosConfig;
