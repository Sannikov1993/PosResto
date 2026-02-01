/**
 * MenuLab POS - Utility Functions
 * Вспомогательные функции форматирования и работы с данными
 */

const PosUtils = {
    // ==================== ФОРМАТИРОВАНИЕ ДАТА/ВРЕМЯ ====================

    formatDate(dt) {
        if (!dt) return '';
        const d = new Date(dt);
        return d.toLocaleDateString('ru-RU', { day: '2-digit', month: '2-digit' });
    },

    formatTime(dt) {
        if (!dt) return '';
        return new Date(dt).toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' });
    },

    formatDateTime(dateStr) {
        if (!dateStr) return '';
        const d = new Date(dateStr);
        return d.toLocaleDateString('ru-RU') + ' ' + d.toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' });
    },

    formatLocalDate(d) {
        return d.toISOString().split('T')[0];
    },

    getTodayDate() {
        return this.formatLocalDate(new Date());
    },

    getYesterdayDate() {
        const d = new Date();
        d.setDate(d.getDate() - 1);
        return this.formatLocalDate(d);
    },

    getTomorrowDate() {
        const d = new Date();
        d.setDate(d.getDate() + 1);
        return this.formatLocalDate(d);
    },

    // ==================== ФОРМАТИРОВАНИЕ ДЕНЕГ ====================

    formatMoney(n) {
        if (!n) return '0';
        return Math.floor(n).toLocaleString('ru-RU');
    },

    formatPrice(n) {
        if (!n && n !== 0) return '0';
        return Math.floor(n).toLocaleString('ru-RU');
    },

    // ==================== ДНИ НЕДЕЛИ ====================

    getDayName(dt) {
        if (!dt) return '';
        const days = ['вс', 'пн', 'вт', 'ср', 'чт', 'пт', 'сб'];
        return days[new Date(dt).getDay()];
    },

    getDayClass(dt) {
        if (!dt) return 'bg-gray-700 text-gray-400';
        const day = new Date(dt).getDay();
        if (day === 0) return 'bg-red-900/50 text-red-400';
        if (day === 6) return 'bg-blue-900/50 text-blue-400';
        return 'bg-gray-700 text-gray-400';
    },

    getDayNameByKey(dateKey) {
        const [day, month] = dateKey.split('.');
        const year = new Date().getFullYear();
        const date = new Date(year, parseInt(month) - 1, parseInt(day));
        const days = ['вс', 'пн', 'вт', 'ср', 'чт', 'пт', 'сб'];
        return days[date.getDay()];
    },

    getDayClassByKey(dateKey) {
        const [day, month] = dateKey.split('.');
        const year = new Date().getFullYear();
        const date = new Date(year, parseInt(month) - 1, parseInt(day));
        const dayOfWeek = date.getDay();
        if (dayOfWeek === 0) return 'bg-red-900/50 text-red-400';
        if (dayOfWeek === 6) return 'bg-blue-900/50 text-blue-400';
        return 'bg-gray-700 text-gray-400';
    },

    // ==================== ТЕЛЕФОН ====================

    formatPhone(phone) {
        if (!phone) return '';
        const cleaned = phone.replace(/\D/g, '');
        if (cleaned.length === 11) {
            return `+${cleaned[0]}(${cleaned.slice(1, 4)})${cleaned.slice(4, 7)}-${cleaned.slice(7, 9)}-${cleaned.slice(9)}`;
        }
        return phone;
    },

    maskPhone(phone) {
        if (!phone) return '';
        const cleaned = phone.replace(/\D/g, '');
        if (cleaned.length >= 10) {
            return `+7 *** ${cleaned.slice(-4)}`;
        }
        return phone;
    },

    // ==================== СТАТУСЫ ДОСТАВКИ ====================

    getDeliveryStatusClass(status) {
        const classes = {
            'new': 'bg-blue-900/50 text-blue-400',
            'cooking': 'bg-yellow-900/50 text-yellow-400',
            'ready': 'bg-purple-900/50 text-purple-400',
            'delivering': 'bg-orange-900/50 text-orange-400',
            'delivered': 'bg-green-900/50 text-green-400',
            'cancelled': 'bg-red-900/50 text-red-400'
        };
        return classes[status] || 'bg-gray-700 text-gray-400';
    },

    getDeliveryStatusText(status, orderType = 'delivery') {
        const texts = {
            'new': 'Новый',
            'cooking': 'Готовится',
            'ready': orderType === 'pickup' ? 'Готов к выдаче' : 'Готов',
            'delivering': 'В пути',
            'delivered': orderType === 'pickup' ? 'Выдан' : 'Доставлен',
            'cancelled': 'Отменён'
        };
        return texts[status] || status;
    },

    getCourierStatusClass(status) {
        const classes = {
            'free': 'bg-green-600',
            'busy': 'bg-yellow-600',
            'offline': 'bg-gray-600'
        };
        return classes[status] || 'bg-gray-600';
    },

    getCourierStatusText(status) {
        const texts = {
            'free': 'Свободен',
            'busy': 'Занят',
            'offline': 'Офлайн'
        };
        return texts[status] || status;
    },

    getPaymentMethodText(method) {
        const texts = {
            'cash': 'Наличные',
            'card': 'Картой курьеру',
            'online': 'Онлайн',
            'qr': 'QR-код'
        };
        return texts[method] || method;
    },

    // ==================== КУРЬЕРЫ ====================

    getCourierInitials(courier) {
        if (!courier || !courier.name) return '?';
        const parts = courier.name.split(' ');
        if (parts.length >= 2) {
            return parts[0][0] + parts[1][0];
        }
        return courier.name.substring(0, 2).toUpperCase();
    },

    getCourierColor(courier) {
        if (!courier) return '#666';
        const colors = ['#EF4444', '#F59E0B', '#10B981', '#3B82F6', '#8B5CF6', '#EC4899'];
        const hash = (courier.id || 0) % colors.length;
        return colors[hash];
    },

    // ==================== ВРЕМЯ ДОСТАВКИ ====================

    getDeliveryTimeText(order) {
        if (!order.expected_delivery_at) return '';
        const expected = new Date(order.expected_delivery_at);
        const now = new Date();
        const diffMs = expected - now;
        const diffMin = Math.round(diffMs / 60000);

        if (diffMin < 0) {
            return `Опоздание ${Math.abs(diffMin)} мин`;
        } else if (diffMin < 60) {
            return `${diffMin} мин`;
        } else {
            return this.formatTime(order.expected_delivery_at);
        }
    },

    getDeliveryTimeClass(order) {
        if (!order.expected_delivery_at) return '';
        const expected = new Date(order.expected_delivery_at);
        const now = new Date();
        const diffMs = expected - now;
        const diffMin = Math.round(diffMs / 60000);

        if (diffMin < 0) return 'text-red-400';
        if (diffMin < 15) return 'text-yellow-400';
        return 'text-green-400';
    },

    // ==================== SHIFT DURATION ====================

    getShiftDuration(shift) {
        if (!shift.opened_at) return '';
        const start = new Date(shift.opened_at);
        const end = shift.closed_at ? new Date(shift.closed_at) : new Date();
        const diffMs = end - start;
        const hours = Math.floor(diffMs / 3600000);
        const minutes = Math.floor((diffMs % 3600000) / 60000);
        return `${hours}ч ${minutes}м`;
    }
};

// Export for global usage
window.PosUtils = PosUtils;
