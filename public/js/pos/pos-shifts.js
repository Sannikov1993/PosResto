/**
 * PosResto POS - Cash Shifts Module
 * Управление кассовыми сменами
 */

const PosShifts = {
    // ==================== API ВЫЗОВЫ ====================

    /**
     * Получить список смен
     */
    async getShifts() {
        return await PosAPI.getShifts();
    },

    /**
     * Получить текущую открытую смену
     */
    async getCurrentShift() {
        return await PosAPI.getCurrentShift();
    },

    /**
     * Получить детали смены
     */
    async getShiftDetails(shiftId) {
        return await PosAPI.getShiftDetails(shiftId);
    },

    /**
     * Получить заказы смены
     */
    async getShiftOrders(shiftId) {
        return await PosAPI.getShiftOrders(shiftId);
    },

    /**
     * Открыть смену
     */
    async openShift(openingAmount, cashierId = null) {
        const { data } = await axios.post(`${PosAPI.baseUrl}/finance/shifts/open`, {
            opening_cash: Number(openingAmount) || 0,
            restaurant_id: 1,
            cashier_id: cashierId
        });
        return data;
    },

    /**
     * Закрыть смену
     */
    async closeShift(shiftId, closingAmount) {
        const { data } = await axios.post(`${PosAPI.baseUrl}/finance/shifts/${shiftId}/close`, {
            closing_amount: Number(closingAmount) || 0
        });
        return data;
    },

    /**
     * Загрузить события смены
     */
    async loadShiftEvents(shift) {
        if (!shift.events) {
            const details = await this.getShiftDetails(shift.id);
            shift.events = details.events || [];
        }
        return shift.events;
    },

    // ==================== ВЫЧИСЛЕНИЯ ====================

    /**
     * Рассчитать ожидаемую сумму наличных в кассе
     */
    calculateExpectedCash(shift) {
        if (!shift) return 0;
        const opening = Number(shift.opening_amount) || 0;
        const totalCash = Number(shift.total_cash) || 0;
        return Math.round(opening + totalCash);
    },

    /**
     * Получить длительность смены
     */
    getShiftDuration(shift) {
        return PosUtils.getShiftDuration(shift);
    },

    /**
     * Получить общую выручку смены
     */
    getTotalRevenue(shift) {
        if (!shift) return 0;
        return (
            (Number(shift.total_cash) || 0) +
            (Number(shift.total_card) || 0) +
            (Number(shift.total_online) || 0)
        );
    },

    /**
     * Получить средний чек
     */
    getAverageCheck(shift) {
        if (!shift || !shift.orders_count || shift.orders_count <= 0) return 0;
        return Math.round(this.getTotalRevenue(shift) / shift.orders_count);
    },

    /**
     * Рассчитать расхождение при закрытии
     */
    calculateDifference(shift, actualClosingAmount) {
        const expected = this.calculateExpectedCash(shift);
        return actualClosingAmount - expected;
    },

    // ==================== ГРУППИРОВКА ====================

    /**
     * Группировать смены по датам
     */
    groupShiftsByDate(shifts) {
        const groups = {};

        shifts.forEach(shift => {
            const dateKey = this.getShiftDateKey(shift);
            if (!groups[dateKey]) {
                groups[dateKey] = {
                    date: dateKey,
                    shifts: [],
                    totalRevenue: 0,
                    ordersCount: 0
                };
            }
            groups[dateKey].shifts.push(shift);
            groups[dateKey].totalRevenue += this.getTotalRevenue(shift);
            groups[dateKey].ordersCount += shift.orders_count || 0;
        });

        // Сортируем по дате (новые сверху)
        return Object.values(groups).sort((a, b) => {
            const dateA = this.parseDateKey(a.date);
            const dateB = this.parseDateKey(b.date);
            return dateB - dateA;
        });
    },

    /**
     * Получить ключ даты смены (ДД.ММ)
     */
    getShiftDateKey(shift) {
        if (!shift.opened_at) return '';
        const d = new Date(shift.opened_at);
        return `${String(d.getDate()).padStart(2, '0')}.${String(d.getMonth() + 1).padStart(2, '0')}`;
    },

    /**
     * Парсить ключ даты в Date
     */
    parseDateKey(dateKey) {
        const [day, month] = dateKey.split('.');
        const year = new Date().getFullYear();
        return new Date(year, parseInt(month) - 1, parseInt(day));
    },

    // ==================== ФОРМАТИРОВАНИЕ ====================

    /**
     * Получить статус смены
     */
    getShiftStatus(shift) {
        return shift.status === 'open' ? 'Открыта' : 'Закрыта';
    },

    /**
     * Получить цвет статуса смены
     */
    getShiftStatusColor(shift) {
        return shift.status === 'open' ? 'text-green-400' : 'text-gray-400';
    },

    /**
     * Форматировать время открытия/закрытия
     */
    formatShiftTime(shift) {
        const openTime = PosUtils.formatTime(shift.opened_at);
        const closeTime = shift.closed_at ? PosUtils.formatTime(shift.closed_at) : 'сейчас';
        return `${openTime} — ${closeTime}`;
    },

    /**
     * Получить иконку типа события
     */
    getEventIcon(eventType) {
        const icons = {
            open: '🔓',
            close: '🔒',
            income: '💰',
            expense: '💸',
            deposit: '📥',
            withdrawal: '📤',
            refund: '↩️',
            correction: '✏️'
        };
        return icons[eventType] || '📋';
    },

    /**
     * Получить название типа события
     */
    getEventTypeName(eventType) {
        const names = {
            open: 'Открытие смены',
            close: 'Закрытие смены',
            income: 'Приход',
            expense: 'Расход',
            deposit: 'Внесение',
            withdrawal: 'Выдача',
            refund: 'Возврат',
            correction: 'Корректировка'
        };
        return names[eventType] || eventType;
    },

    // ==================== ВАЛИДАЦИЯ ====================

    /**
     * Проверить можно ли открыть новую смену
     */
    canOpenShift(currentShift) {
        return !currentShift;
    },

    /**
     * Проверить можно ли закрыть смену
     */
    canCloseShift(currentShift) {
        return currentShift && currentShift.status === 'open';
    },

    /**
     * Проверить есть ли незакрытые заказы
     */
    hasUnclosedOrders(shiftOrders) {
        if (!shiftOrders || !Array.isArray(shiftOrders)) return false;
        return shiftOrders.some(order =>
            order.status !== 'paid' &&
            order.status !== 'cancelled'
        );
    }
};

// Export for global usage
window.PosShifts = PosShifts;
