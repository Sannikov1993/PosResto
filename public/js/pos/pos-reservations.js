/**
 * PosResto POS - Reservations Module
 * Управление бронированиями
 */

const PosReservations = {
    // Конфигурация
    config: {
        slotDurationMinutes: 30,
        minDurationMinutes: 30,
        maxDurationMinutes: 240,
        defaultDurationMinutes: 120,
        soonThresholdMinutes: 30,
        overdueThresholdMinutes: 15
    },

    // ==================== API ВЫЗОВЫ ====================

    /**
     * Получить бронирования
     */
    async getReservations(params = {}) {
        return await PosAPI.getReservations(params);
    },

    /**
     * Получить календарь бронирований
     */
    async getCalendar(month, year) {
        return await PosAPI.getReservationCalendar(month, year);
    },

    /**
     * Получить бронирования стола
     */
    async getTableReservations(tableId, date) {
        return await PosAPI.getTableReservations(tableId, date);
    },

    /**
     * Создать бронирование
     */
    async createReservation(reservationData) {
        return await PosAPI.createReservation(reservationData);
    },

    /**
     * Обновить бронирование
     */
    async updateReservation(reservationId, data) {
        return await PosAPI.updateReservation(reservationId, data);
    },

    /**
     * Отменить бронирование
     */
    async cancelReservation(reservationId) {
        return await PosAPI.cancelReservation(reservationId);
    },

    /**
     * Посадить гостя (подтвердить приход)
     */
    async seatReservation(reservationId) {
        return await PosAPI.seatReservation(reservationId);
    },

    /**
     * Проверить конфликт бронирования
     */
    async checkConflict(tableId, date, timeFrom, timeTo, excludeId = null) {
        return await PosAPI.checkReservationConflict(tableId, date, timeFrom, timeTo, excludeId);
    },

    // ==================== СТАТУСЫ ====================

    /**
     * Получить конфиг статуса
     */
    getStatusConfig(status) {
        const configs = {
            pending: { label: 'Ожидает', color: '#f59e0b', icon: '⏳' },
            confirmed: { label: 'Подтверждён', color: '#3B82F6', icon: '✓' },
            seated: { label: 'Гость сел', color: '#22c55e', icon: '👥' },
            completed: { label: 'Завершён', color: '#6b7280', icon: '✓' },
            cancelled: { label: 'Отменён', color: '#ef4444', icon: '✕' },
            no_show: { label: 'Не пришёл', color: '#ef4444', icon: '?' }
        };
        return configs[status] || configs.pending;
    },

    /**
     * Получить лейбл статуса
     */
    getStatusLabel(status) {
        return this.getStatusConfig(status).label;
    },

    /**
     * Получить цвет статуса
     */
    getStatusColor(status) {
        return this.getStatusConfig(status).color;
    },

    // ==================== СРОЧНОСТЬ ====================

    /**
     * Определить срочность бронирования
     */
    getUrgency(reservation) {
        if (!reservation || !['pending', 'confirmed'].includes(reservation.status)) {
            return 'none';
        }

        const now = new Date();
        const today = now.toISOString().split('T')[0];

        // Не на сегодня
        if (reservation.date !== today) return 'none';

        const [hours, minutes] = reservation.time_from.split(':').map(Number);
        const reservationTime = new Date();
        reservationTime.setHours(hours, minutes, 0, 0);

        const diffMinutes = (reservationTime - now) / (1000 * 60);

        if (diffMinutes < -this.config.overdueThresholdMinutes) return 'overdue';
        if (diffMinutes <= this.config.soonThresholdMinutes) return 'soon';
        return 'normal';
    },

    /**
     * Получить CSS класс срочности
     */
    getUrgencyClass(urgency) {
        const classes = {
            overdue: 'badge-overdue',
            soon: 'badge-soon',
            normal: 'badge-normal',
            none: ''
        };
        return classes[urgency] || '';
    },

    // ==================== ФИЛЬТРАЦИЯ ====================

    /**
     * Фильтровать бронирования по дате
     */
    filterByDate(reservations, date) {
        return reservations.filter(r => r.date === date);
    },

    /**
     * Фильтровать бронирования по столу
     */
    filterByTable(reservations, tableId) {
        return reservations.filter(r => r.table_id === tableId);
    },

    /**
     * Фильтровать активные бронирования
     */
    filterActive(reservations) {
        return reservations.filter(r => ['pending', 'confirmed'].includes(r.status));
    },

    /**
     * Получить предстоящие бронирования на сегодня
     */
    getTodayUpcoming(reservations) {
        const today = new Date().toISOString().split('T')[0];
        return reservations
            .filter(r => r.date === today && ['pending', 'confirmed'].includes(r.status))
            .sort((a, b) => a.time_from.localeCompare(b.time_from));
    },

    // ==================== СВЯЗАННЫЕ СТОЛЫ ====================

    /**
     * Получить связанные бронирования (групповые)
     */
    getLinkedReservations(reservations) {
        return reservations.filter(r => r.linked_table_ids && r.linked_table_ids.length > 1);
    },

    /**
     * Проверить является ли стол частью связанной брони
     */
    isTableInLinkedReservation(tableId, reservations) {
        return reservations.some(r =>
            r.linked_table_ids &&
            r.linked_table_ids.length > 1 &&
            r.linked_table_ids.includes(tableId) &&
            ['pending', 'confirmed'].includes(r.status)
        );
    },

    /**
     * Получить связанную бронь для стола
     */
    getLinkedReservationForTable(tableId, reservations) {
        return reservations.find(r =>
            r.linked_table_ids &&
            r.linked_table_ids.length > 1 &&
            r.linked_table_ids.includes(tableId) &&
            ['pending', 'confirmed'].includes(r.status)
        );
    },

    /**
     * Группировать связанные бронирования
     */
    groupLinkedReservations(reservations) {
        const groups = {};

        reservations.forEach(r => {
            if (r.linked_table_ids && r.linked_table_ids.length > 1) {
                // Создаём уникальный ключ группы
                const key = [...r.linked_table_ids].sort().join('-');
                if (!groups[key]) {
                    groups[key] = r;
                }
            }
        });

        return Object.values(groups);
    },

    // ==================== ВЫЧИСЛЕНИЯ ====================

    /**
     * Добавить минуты ко времени
     */
    addMinutesToTime(time, minutes) {
        const [h, m] = time.split(':').map(Number);
        const totalMinutes = h * 60 + m + minutes;
        const newH = Math.floor(totalMinutes / 60) % 24;
        const newM = totalMinutes % 60;
        return `${String(newH).padStart(2, '0')}:${String(newM).padStart(2, '0')}`;
    },

    /**
     * Рассчитать время окончания
     */
    calculateEndTime(startTime, durationMinutes) {
        return this.addMinutesToTime(startTime, durationMinutes);
    },

    /**
     * Проверить пересечение временных интервалов
     */
    hasTimeOverlap(start1, end1, start2, end2) {
        return !(end1 <= start2 || start1 >= end2);
    },

    /**
     * Генерировать временные слоты
     */
    generateTimeSlots(startHour = 10, endHour = 22, stepMinutes = 30) {
        const slots = [];
        for (let h = startHour; h < endHour; h++) {
            for (let m = 0; m < 60; m += stepMinutes) {
                slots.push(`${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`);
            }
        }
        return slots;
    },

    /**
     * Получить доступные слоты для стола
     */
    getAvailableSlots(tableReservations, date, duration = 120) {
        const allSlots = this.generateTimeSlots();
        const activeReservations = tableReservations.filter(r =>
            r.date === date && ['pending', 'confirmed'].includes(r.status)
        );

        return allSlots.filter(slot => {
            const slotEnd = this.addMinutesToTime(slot, duration);
            return !activeReservations.some(r =>
                this.hasTimeOverlap(slot, slotEnd, r.time_from, r.time_to)
            );
        });
    },

    // ==================== ФОРМАТИРОВАНИЕ ====================

    /**
     * Форматировать время бронирования
     */
    formatReservationTime(reservation) {
        const from = reservation.time_from?.substring(0, 5);
        const to = reservation.time_to?.substring(0, 5);
        return `${from} — ${to}`;
    },

    /**
     * Форматировать информацию о госте
     */
    formatGuestInfo(reservation) {
        const parts = [];
        if (reservation.guest_name) parts.push(reservation.guest_name);
        if (reservation.guests_count) parts.push(`${reservation.guests_count} чел.`);
        return parts.join(', ');
    },

    /**
     * Получить иконку для бейджа
     */
    getBadgeIcon(reservation) {
        const urgency = this.getUrgency(reservation);
        if (urgency === 'overdue') return '⚠️';
        if (urgency === 'soon') return '⏰';
        return '📅';
    },

    // ==================== ВАЛИДАЦИЯ ====================

    /**
     * Валидировать форму бронирования
     */
    validateForm(form) {
        const errors = [];

        if (!form.guest_name?.trim()) {
            errors.push('Укажите имя гостя');
        }

        if (!form.date) {
            errors.push('Выберите дату');
        }

        if (!form.time) {
            errors.push('Выберите время');
        }

        if (!form.guests || form.guests < 1) {
            errors.push('Укажите количество гостей');
        }

        if (form.duration < this.config.minDurationMinutes) {
            errors.push(`Минимальная длительность: ${this.config.minDurationMinutes} минут`);
        }

        return {
            valid: errors.length === 0,
            errors
        };
    },

    /**
     * Получить дефолтную форму бронирования
     */
    getDefaultForm() {
        return {
            date: new Date().toISOString().split('T')[0],
            time: '19:00',
            guests: 2,
            duration: this.config.defaultDurationMinutes,
            guest_name: '',
            guest_phone: '',
            guest_email: '',
            notes: '',
            special_requests: '',
            deposit: 0,
            send_sms: true,
            preorder_enabled: false,
            table_ids: []
        };
    }
};

// Export for global usage
window.PosReservations = PosReservations;
