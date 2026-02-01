/**
 * MenuLab POS - Stop List Module
 * Управление стоп-листом
 */

const PosStopList = {
    // ==================== API ВЫЗОВЫ ====================

    /**
     * Получить стоп-лист
     */
    async getStopList() {
        return await PosAPI.getStopList();
    },

    /**
     * Поиск блюд для добавления
     */
    async searchDishes(query) {
        return await PosAPI.searchDishesForStopList(query);
    },

    /**
     * Добавить в стоп-лист
     */
    async addToStopList(dishId, reason, availableAt = null) {
        return await PosAPI.addToStopList(dishId, reason, availableAt);
    },

    /**
     * Удалить из стоп-листа
     */
    async removeFromStopList(dishId) {
        return await PosAPI.removeFromStopList(dishId);
    },

    /**
     * Обновить запись в стоп-листе
     */
    async updateStopListItem(dishId, reason, availableAt) {
        return await PosAPI.updateStopListItem(dishId, reason, availableAt);
    },

    // ==================== РАБОТА С ДАННЫМИ ====================

    /**
     * Создать Set из ID блюд для быстрой проверки
     */
    createDishIdsSet(stopList) {
        return new Set(stopList.map(item => item.dish_id));
    },

    /**
     * Проверить находится ли блюдо в стоп-листе
     */
    isInStopList(dishId, dishIdsSet) {
        return dishIdsSet.has(dishId);
    },

    /**
     * Получить запись стоп-листа для блюда
     */
    getStopListItem(dishId, stopList) {
        return stopList.find(item => item.dish_id === dishId);
    },

    // ==================== ФИЛЬТРАЦИЯ ====================

    /**
     * Поиск по стоп-листу
     */
    searchInList(stopList, query) {
        if (!query) return stopList;
        const q = query.toLowerCase();
        return stopList.filter(item =>
            item.dish?.name?.toLowerCase().includes(q) ||
            item.reason?.toLowerCase().includes(q)
        );
    },

    /**
     * Фильтровать по категории
     */
    filterByCategory(stopList, categoryId) {
        if (!categoryId) return stopList;
        return stopList.filter(item => item.dish?.category_id === categoryId);
    },

    /**
     * Фильтровать по времени возврата
     */
    filterByAvailability(stopList, type) {
        if (type === 'all') return stopList;

        const now = new Date();

        if (type === 'indefinite') {
            return stopList.filter(item => !item.resume_at);
        }

        if (type === 'timed') {
            return stopList.filter(item => item.resume_at);
        }

        if (type === 'expiring_soon') {
            // В течение часа
            const hourLater = new Date(now.getTime() + 60 * 60 * 1000);
            return stopList.filter(item =>
                item.resume_at && new Date(item.resume_at) <= hourLater
            );
        }

        return stopList;
    },

    // ==================== ФОРМАТИРОВАНИЕ ====================

    /**
     * Форматировать причину
     */
    formatReason(item) {
        return item.reason || 'Причина не указана';
    },

    /**
     * Форматировать время возврата
     */
    formatResumeAt(item) {
        if (!item.resume_at) return 'Бессрочно';
        return PosUtils.formatDateTime(item.resume_at);
    },

    /**
     * Получить время до возврата
     */
    getTimeUntilResume(item) {
        if (!item.resume_at) return null;

        const now = new Date();
        const resumeAt = new Date(item.resume_at);
        const diffMs = resumeAt - now;

        if (diffMs <= 0) return 'Истекло';

        const diffMinutes = Math.floor(diffMs / (1000 * 60));
        const diffHours = Math.floor(diffMinutes / 60);

        if (diffHours > 24) {
            return `${Math.floor(diffHours / 24)} дн.`;
        }
        if (diffHours > 0) {
            return `${diffHours} ч. ${diffMinutes % 60} мин.`;
        }
        return `${diffMinutes} мин.`;
    },

    /**
     * Получить CSS класс для времени
     */
    getTimeClass(item) {
        if (!item.resume_at) return 'text-gray-400';

        const now = new Date();
        const resumeAt = new Date(item.resume_at);
        const diffMs = resumeAt - now;
        const diffMinutes = diffMs / (1000 * 60);

        if (diffMinutes <= 0) return 'text-red-400';
        if (diffMinutes <= 30) return 'text-yellow-400';
        return 'text-green-400';
    },

    // ==================== СТАТИСТИКА ====================

    /**
     * Получить статистику стоп-листа
     */
    getStats(stopList) {
        return {
            total: stopList.length,
            indefinite: stopList.filter(item => !item.resume_at).length,
            timed: stopList.filter(item => item.resume_at).length,
            expiringSoon: stopList.filter(item => {
                if (!item.resume_at) return false;
                const diffMs = new Date(item.resume_at) - new Date();
                return diffMs > 0 && diffMs <= 60 * 60 * 1000;
            }).length
        };
    },

    /**
     * Группировать по категориям
     */
    groupByCategory(stopList) {
        const groups = {};

        stopList.forEach(item => {
            const categoryName = item.dish?.category?.name || 'Без категории';
            if (!groups[categoryName]) {
                groups[categoryName] = [];
            }
            groups[categoryName].push(item);
        });

        return groups;
    },

    // ==================== ВАЛИДАЦИЯ ====================

    /**
     * Валидировать форму
     */
    validateForm(form) {
        const errors = [];

        if (!form.dish_id) {
            errors.push('Выберите блюдо');
        }

        if (form.resume_at) {
            const resumeDate = new Date(form.resume_at);
            if (resumeDate <= new Date()) {
                errors.push('Время возврата должно быть в будущем');
            }
        }

        return {
            valid: errors.length === 0,
            errors
        };
    },

    /**
     * Получить дефолтную форму
     */
    getDefaultForm() {
        return {
            dish_id: null,
            dish: null,
            reason: '',
            resume_at: ''
        };
    },

    // ==================== ПРИЧИНЫ ПО УМОЛЧАНИЮ ====================

    /**
     * Получить список предустановленных причин
     */
    getDefaultReasons() {
        return [
            'Закончились продукты',
            'Отсутствует ингредиент',
            'Оборудование не работает',
            'Блюдо временно недоступно',
            'По решению шеф-повара',
            'Сезонное ограничение'
        ];
    }
};

// Export for global usage
window.PosStopList = PosStopList;
