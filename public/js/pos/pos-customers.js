/**
 * MenuLab POS - Customers Module
 * Управление клиентами
 */

const PosCustomers = {
    // ==================== API ВЫЗОВЫ ====================

    /**
     * Получить список клиентов
     */
    async getCustomers(params = {}) {
        return await PosAPI.getCustomers(params);
    },

    /**
     * Поиск клиентов
     */
    async searchCustomers(query) {
        return await PosAPI.searchCustomers(query);
    },

    /**
     * Получить клиента по ID
     */
    async getCustomer(customerId) {
        return await PosAPI.getCustomer(customerId);
    },

    /**
     * Создать клиента
     */
    async createCustomer(customerData) {
        return await PosAPI.createCustomer(customerData);
    },

    /**
     * Обновить клиента
     */
    async updateCustomer(customerId, customerData) {
        return await PosAPI.updateCustomer(customerId, customerData);
    },

    /**
     * Получить заказы клиента
     */
    async getCustomerOrders(customerId) {
        return await PosAPI.getCustomerOrders(customerId);
    },

    /**
     * Получить адреса клиента
     */
    async getCustomerAddresses(customerId) {
        return await PosAPI.getCustomerAddresses(customerId);
    },

    /**
     * Переключить статус чёрного списка
     */
    async toggleBlacklist(customerId) {
        return await PosAPI.toggleCustomerBlacklist(customerId);
    },

    // ==================== ФИЛЬТРАЦИЯ ====================

    /**
     * Фильтровать клиентов по статусу
     */
    filterByStatus(customers, status) {
        if (status === 'all') return customers;
        if (status === 'active') return customers.filter(c => !c.is_blacklisted);
        if (status === 'blacklisted') return customers.filter(c => c.is_blacklisted);
        return customers;
    },

    /**
     * Поиск по списку клиентов
     */
    searchInList(customers, query) {
        if (!query) return customers;
        const q = query.toLowerCase();
        return customers.filter(c =>
            c.name?.toLowerCase().includes(q) ||
            c.phone?.includes(q) ||
            c.email?.toLowerCase().includes(q)
        );
    },

    /**
     * Сортировать клиентов
     */
    sortCustomers(customers, sortBy, sortDir = 'desc') {
        return [...customers].sort((a, b) => {
            let valA = a[sortBy];
            let valB = b[sortBy];

            // Обработка null/undefined
            if (valA == null) valA = sortBy === 'name' ? '' : 0;
            if (valB == null) valB = sortBy === 'name' ? '' : 0;

            // Сравнение
            let comparison = 0;
            if (typeof valA === 'string') {
                comparison = valA.localeCompare(valB);
            } else {
                comparison = valA - valB;
            }

            return sortDir === 'desc' ? -comparison : comparison;
        });
    },

    // ==================== ФОРМАТИРОВАНИЕ ====================

    /**
     * Форматировать телефон
     */
    formatPhone(phone) {
        return PosUtils.formatPhone(phone);
    },

    /**
     * Маскировать телефон
     */
    maskPhone(phone) {
        return PosUtils.maskPhone(phone);
    },

    /**
     * Получить инициалы клиента
     */
    getInitials(customer) {
        if (!customer?.name) return '?';
        const parts = customer.name.trim().split(' ');
        if (parts.length >= 2) {
            return (parts[0][0] + parts[1][0]).toUpperCase();
        }
        return customer.name.substring(0, 2).toUpperCase();
    },

    /**
     * Получить цвет аватара
     */
    getAvatarColor(customer) {
        if (!customer) return '#666';
        const colors = ['#EF4444', '#F59E0B', '#10B981', '#3B82F6', '#8B5CF6', '#EC4899'];
        const hash = (customer.id || 0) % colors.length;
        return colors[hash];
    },

    /**
     * Форматировать статистику клиента
     */
    formatCustomerStats(customer) {
        return {
            totalOrders: customer.total_orders || 0,
            totalSpent: customer.total_spent || 0,
            avgCheck: customer.total_orders > 0
                ? Math.round((customer.total_spent || 0) / customer.total_orders)
                : 0,
            lastOrderDate: customer.last_order_at
                ? PosUtils.formatDate(customer.last_order_at)
                : 'Нет заказов'
        };
    },

    // ==================== БОНУСЫ ====================

    /**
     * Получить баланс бонусов
     */
    getBonusBalance(customer) {
        return customer.bonus_balance || 0;
    },

    /**
     * Рассчитать доступные бонусы для списания
     */
    getAvailableBonuses(customer, orderTotal, maxBonusPercent = 50) {
        const balance = this.getBonusBalance(customer);
        const maxByOrder = Math.floor(orderTotal * maxBonusPercent / 100);
        return Math.min(balance, maxByOrder);
    },

    /**
     * Рассчитать бонусы за заказ (начисление)
     */
    calculateBonusEarnings(orderTotal, bonusPercent = 5) {
        return Math.floor(orderTotal * bonusPercent / 100);
    },

    // ==================== АДРЕСА ====================

    /**
     * Форматировать адрес
     */
    formatAddress(address) {
        if (!address) return '';

        let parts = [address.street];
        if (address.apartment) parts.push(`кв. ${address.apartment}`);
        if (address.entrance) parts.push(`подъезд ${address.entrance}`);
        if (address.floor) parts.push(`этаж ${address.floor}`);
        if (address.intercom) parts.push(`домофон ${address.intercom}`);

        return parts.join(', ');
    },

    /**
     * Получить адрес по умолчанию
     */
    getDefaultAddress(addresses) {
        if (!addresses || !addresses.length) return null;
        return addresses.find(a => a.is_default) || addresses[0];
    },

    // ==================== ВАЛИДАЦИЯ ====================

    /**
     * Валидировать форму клиента
     */
    validateForm(form) {
        const errors = [];

        if (!form.name?.trim()) {
            errors.push('Укажите имя клиента');
        }

        if (form.phone && !this.isValidPhone(form.phone)) {
            errors.push('Некорректный номер телефона');
        }

        if (form.email && !this.isValidEmail(form.email)) {
            errors.push('Некорректный email');
        }

        return {
            valid: errors.length === 0,
            errors
        };
    },

    /**
     * Проверить валидность телефона
     */
    isValidPhone(phone) {
        if (!phone) return true; // Пустой - валиден (необязательное поле)
        const cleaned = phone.replace(/\D/g, '');
        return cleaned.length >= 10 && cleaned.length <= 12;
    },

    /**
     * Проверить валидность email
     */
    isValidEmail(email) {
        if (!email) return true; // Пустой - валиден (необязательное поле)
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    },

    /**
     * Получить дефолтную форму клиента
     */
    getDefaultForm() {
        return {
            name: '',
            phone: '',
            email: '',
            birth_date: '',
            notes: ''
        };
    },

    /**
     * Получить дефолтную форму адреса
     */
    getDefaultAddressForm() {
        return {
            title: '',
            street: '',
            apartment: '',
            entrance: '',
            floor: '',
            intercom: '',
            is_default: false
        };
    },

    // ==================== ЧЁРНЫЙ СПИСОК ====================

    /**
     * Проверить в чёрном списке
     */
    isBlacklisted(customer) {
        return customer?.is_blacklisted === true;
    },

    /**
     * Получить CSS класс для статуса
     */
    getStatusClass(customer) {
        if (this.isBlacklisted(customer)) {
            return 'text-red-400';
        }
        return 'text-green-400';
    },

    /**
     * Получить текст статуса
     */
    getStatusText(customer) {
        if (this.isBlacklisted(customer)) {
            return 'В чёрном списке';
        }
        return 'Активен';
    }
};

// Export for global usage
window.PosCustomers = PosCustomers;
