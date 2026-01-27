/**
 * PosResto POS - Floor Plan Module
 * Управление картой зала, столами и зонами
 */

const PosFloor = {
    // Конфигурация масштаба
    config: {
        defaultScale: 1,
        minScale: 0.5,
        maxScale: 2,
        scaleStep: 0.1,
        defaultWidth: 1200,
        defaultHeight: 800
    },

    // ==================== API ВЫЗОВЫ ====================

    /**
     * Получить столы
     */
    async getTables() {
        return await PosAPI.getTables();
    },

    /**
     * Получить зоны
     */
    async getZones() {
        return await PosAPI.getZones();
    },

    /**
     * Получить план зала (столы + зоны)
     */
    async getFloorPlan() {
        return await PosAPI.getFloorPlan();
    },

    // ==================== СТАТУСЫ СТОЛОВ ====================

    /**
     * Определить статус стола
     */
    getTableStatus(table, activeOrders = [], reservations = []) {
        // Ищем активный заказ на столе
        const activeOrder = activeOrders.find(o =>
            o.table_id === table.id &&
            ['new', 'confirmed', 'cooking', 'ready', 'served'].includes(o.status)
        );

        if (activeOrder) {
            // Если есть заказ и счёт запрошен
            if (activeOrder.bill_requested) return 'bill';
            // Если заказ готов
            if (activeOrder.status === 'ready') return 'ready';
            // Иначе - занят
            return 'occupied';
        }

        // Проверяем бронирование
        const hasReservation = this.hasActiveReservation(table, reservations);
        if (hasReservation) return 'reserved';

        return 'free';
    },

    /**
     * Проверить наличие активного бронирования
     */
    hasActiveReservation(table, reservations) {
        if (!reservations || !reservations.length) return false;

        return reservations.some(res =>
            res.table_id === table.id &&
            ['pending', 'confirmed'].includes(res.status)
        );
    },

    /**
     * Получить конфигурацию статуса стола
     */
    getStatusConfig(status) {
        return PosConfig.tableStatuses[status] || PosConfig.tableStatuses.free;
    },

    /**
     * Получить цвет статуса
     */
    getStatusColor(status) {
        return this.getStatusConfig(status).color;
    },

    /**
     * Получить CSS класс статуса
     */
    getStatusClass(status) {
        return this.getStatusConfig(status).class;
    },

    /**
     * Получить лейбл статуса
     */
    getStatusLabel(status) {
        return this.getStatusConfig(status).label;
    },

    // ==================== ФИЛЬТРАЦИЯ ====================

    /**
     * Отфильтровать столы по зоне
     */
    filterByZone(tables, zoneId) {
        if (!zoneId) return tables;
        return tables.filter(t => t.zone_id === zoneId);
    },

    /**
     * Отфильтровать столы по статусу
     */
    filterByStatus(tables, status, activeOrders = [], reservations = []) {
        if (!status) return tables;
        return tables.filter(t => this.getTableStatus(t, activeOrders, reservations) === status);
    },

    /**
     * Отфильтровать свободные столы
     */
    getFreeTables(tables, activeOrders = [], reservations = []) {
        return this.filterByStatus(tables, 'free', activeOrders, reservations);
    },

    /**
     * Отфильтровать занятые столы
     */
    getOccupiedTables(tables, activeOrders = [], reservations = []) {
        return tables.filter(t => {
            const status = this.getTableStatus(t, activeOrders, reservations);
            return ['occupied', 'bill', 'ready'].includes(status);
        });
    },

    // ==================== ГРУППИРОВКА ====================

    /**
     * Группировать столы по зонам
     */
    groupByZone(tables, zones) {
        const groups = {};

        zones.forEach(zone => {
            groups[zone.id] = {
                zone,
                tables: tables.filter(t => t.zone_id === zone.id)
            };
        });

        // Столы без зоны
        const noZoneTables = tables.filter(t => !t.zone_id);
        if (noZoneTables.length > 0) {
            groups['none'] = {
                zone: { id: 'none', name: 'Без зоны' },
                tables: noZoneTables
            };
        }

        return groups;
    },

    /**
     * Получить связанные столы (для объединённых броней)
     */
    getLinkedTables(tables, linkedTableIds) {
        if (!linkedTableIds || !linkedTableIds.length) return [];
        return tables.filter(t => linkedTableIds.includes(t.id));
    },

    // ==================== ВЫЧИСЛЕНИЯ ====================

    /**
     * Вычислить границы группы связанных столов
     */
    calculateGroupBounds(linkedTables, padding = 20) {
        if (!linkedTables.length) return null;

        let minX = Infinity, minY = Infinity;
        let maxX = -Infinity, maxY = -Infinity;

        linkedTables.forEach(table => {
            const x = table.position_x || table.x || 0;
            const y = table.position_y || table.y || 0;
            const w = table.width || 80;
            const h = table.height || 80;

            minX = Math.min(minX, x - padding);
            minY = Math.min(minY, y - padding);
            maxX = Math.max(maxX, x + w + padding);
            maxY = Math.max(maxY, y + h + padding);
        });

        return {
            x: minX,
            y: minY,
            width: maxX - minX,
            height: maxY - minY
        };
    },

    /**
     * Вычислить центр группы столов
     */
    calculateGroupCenter(linkedTables) {
        if (!linkedTables.length) return { x: 0, y: 0 };

        const sumX = linkedTables.reduce((sum, t) => sum + (t.position_x || t.x || 0) + (t.width || 80) / 2, 0);
        const sumY = linkedTables.reduce((sum, t) => sum + (t.position_y || t.y || 0) + (t.height || 80) / 2, 0);

        return {
            x: sumX / linkedTables.length,
            y: sumY / linkedTables.length
        };
    },

    /**
     * Получить общую вместимость столов
     */
    getTotalCapacity(tables) {
        return tables.reduce((sum, t) => sum + (t.capacity || t.seats || 0), 0);
    },

    // ==================== МАСШТАБИРОВАНИЕ ====================

    /**
     * Увеличить масштаб
     */
    zoomIn(currentScale) {
        return Math.min(currentScale + this.config.scaleStep, this.config.maxScale);
    },

    /**
     * Уменьшить масштаб
     */
    zoomOut(currentScale) {
        return Math.max(currentScale - this.config.scaleStep, this.config.minScale);
    },

    /**
     * Сбросить масштаб
     */
    resetZoom() {
        return this.config.defaultScale;
    },

    // ==================== ТУЛТИПЫ ====================

    /**
     * Получить данные для тултипа стола
     */
    getTableTooltipData(table, activeOrders = [], reservations = []) {
        const status = this.getTableStatus(table, activeOrders, reservations);
        const statusConfig = this.getStatusConfig(status);

        const data = {
            status,
            statusLabel: statusConfig.label,
            statusColor: statusConfig.color,
            tableName: table.name || `Стол ${table.number}`,
            capacity: table.capacity || table.seats || 0
        };

        // Если есть активный заказ
        const activeOrder = activeOrders.find(o => o.table_id === table.id);
        if (activeOrder) {
            data.order = {
                number: activeOrder.order_number || activeOrder.daily_number,
                total: activeOrder.total,
                guestsCount: activeOrder.guests_count || 1,
                items: activeOrder.items?.length || 0,
                createdAt: activeOrder.created_at
            };
        }

        return data;
    },

    /**
     * Получить данные для тултипа бронирования
     */
    getReservationTooltipData(reservation) {
        if (!reservation) return null;

        return {
            guestName: reservation.guest_name,
            guestPhone: reservation.guest_phone,
            time: `${reservation.time_from?.substring(0, 5)} - ${reservation.time_to?.substring(0, 5)}`,
            guests: reservation.guests_count,
            status: reservation.status,
            notes: reservation.notes
        };
    },

    // ==================== МУЛЬТИВЫБОР ====================

    /**
     * Добавить стол в выборку
     */
    addToSelection(selectedTables, table) {
        if (!selectedTables.find(t => t.id === table.id)) {
            selectedTables.push(table);
        }
        return selectedTables;
    },

    /**
     * Убрать стол из выборки
     */
    removeFromSelection(selectedTables, tableId) {
        return selectedTables.filter(t => t.id !== tableId);
    },

    /**
     * Переключить выбор стола
     */
    toggleSelection(selectedTables, table) {
        const index = selectedTables.findIndex(t => t.id === table.id);
        if (index >= 0) {
            selectedTables.splice(index, 1);
        } else {
            selectedTables.push(table);
        }
        return selectedTables;
    },

    /**
     * Очистить выборку
     */
    clearSelection() {
        return [];
    },

    /**
     * Проверить выбран ли стол
     */
    isSelected(selectedTables, tableId) {
        return selectedTables.some(t => t.id === tableId);
    }
};

// Export for global usage
window.PosFloor = PosFloor;
