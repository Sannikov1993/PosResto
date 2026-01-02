/**
 * PosLab Real-time Client
 * Библиотека для получения событий в реальном времени
 * 
 * Использование:
 * const rt = new PosLabRealtime({ channels: ['orders', 'kitchen'] });
 * rt.on('new_order', (data) => { console.log('Новый заказ!', data); });
 * rt.connect();
 */

class PosLabRealtime {
    constructor(options = {}) {
        this.baseUrl = options.baseUrl || 'http://127.0.0.1:8000/api';
        this.channels = options.channels || ['orders', 'kitchen', 'delivery', 'reservations'];
        this.restaurantId = options.restaurantId || 1;
        this.useSSE = options.useSSE !== false; // по умолчанию SSE
        this.reconnectDelay = options.reconnectDelay || 3000;
        this.maxReconnectDelay = options.maxReconnectDelay || 30000;
        this.debug = options.debug || false;
        
        this.eventSource = null;
        this.lastEventId = 0;
        this.listeners = {};
        this.connected = false;
        this.reconnectAttempts = 0;
        this.reconnectTimer = null;
        
        // Звуки
        this.sounds = {};
        this.soundEnabled = options.soundEnabled !== false;
        this.soundVolume = options.soundVolume || 0.5;
        
        this._initSounds();
    }

    /**
     * Инициализация звуков
     */
    _initSounds() {
        // Создаём звуки через Web Audio API
        this.audioContext = null;
        
        // Частоты для разных событий
        this.soundFrequencies = {
            new_order: [523, 659, 784],      // C5, E5, G5 - мажорный аккорд
            order_ready: [784, 988, 1175],   // G5, B5, D6 - высокий
            kitchen_new: [440, 554, 659],    // A4, C#5, E5
            delivery_new: [392, 494, 587],   // G4, B4, D5
            reservation: [349, 440, 523],    // F4, A4, C5
            payment: [523, 659],             // C5, E5
            alert: [880, 698, 880],          // A5, F5, A5 - тревога
            notification: [659, 784],        // E5, G5
        };
    }

    /**
     * Воспроизвести звук
     */
    playSound(soundName) {
        if (!this.soundEnabled) return;
        
        try {
            if (!this.audioContext) {
                this.audioContext = new (window.AudioContext || window.webkitAudioContext)();
            }
            
            const frequencies = this.soundFrequencies[soundName] || this.soundFrequencies.notification;
            const duration = 0.15;
            
            frequencies.forEach((freq, index) => {
                const oscillator = this.audioContext.createOscillator();
                const gainNode = this.audioContext.createGain();
                
                oscillator.connect(gainNode);
                gainNode.connect(this.audioContext.destination);
                
                oscillator.frequency.value = freq;
                oscillator.type = 'sine';
                
                const startTime = this.audioContext.currentTime + (index * duration);
                gainNode.gain.setValueAtTime(this.soundVolume, startTime);
                gainNode.gain.exponentialRampToValueAtTime(0.01, startTime + duration);
                
                oscillator.start(startTime);
                oscillator.stop(startTime + duration);
            });
        } catch (e) {
            this._log('Sound error:', e);
        }
    }

    /**
     * Подключиться к серверу событий
     */
    connect() {
        if (this.connected) {
            this._log('Already connected');
            return;
        }

        if (this.useSSE && typeof EventSource !== 'undefined') {
            this._connectSSE();
        } else {
            this._connectPolling();
        }
    }

    /**
     * Подключение через Server-Sent Events
     */
    _connectSSE() {
        const url = new URL(`${this.baseUrl}/realtime/stream`);
        url.searchParams.set('channels', this.channels.join(','));
        url.searchParams.set('restaurant_id', this.restaurantId);
        if (this.lastEventId) {
            url.searchParams.set('last_id', this.lastEventId);
        }

        this._log('Connecting SSE:', url.toString());

        this.eventSource = new EventSource(url.toString());

        this.eventSource.onopen = () => {
            this._log('SSE Connected');
            this.connected = true;
            this.reconnectAttempts = 0;
            this._emit('connected', { method: 'sse' });
        };

        this.eventSource.onerror = (error) => {
            this._log('SSE Error:', error);
            this.connected = false;
            this.eventSource.close();
            this._emit('disconnected', { error });
            this._scheduleReconnect();
        };

        // Обработка heartbeat
        this.eventSource.addEventListener('heartbeat', (e) => {
            this._log('Heartbeat received');
        });

        // Обработка reconnect
        this.eventSource.addEventListener('reconnect', (e) => {
            this._log('Server requested reconnect');
            this.eventSource.close();
            this._scheduleReconnect(100); // быстрый реконнект
        });

        // Обработка всех событий
        this.eventSource.onmessage = (e) => {
            this._handleEvent(e);
        };

        // Слушаем конкретные типы событий
        const eventTypes = [
            'new_order', 'order_updated', 'order_status', 'order_paid', 'order_cancelled',
            'kitchen_new', 'kitchen_ready',
            'delivery_new', 'delivery_status', 'delivery_assigned',
            'reservation_new', 'reservation_confirmed', 'reservation_cancelled',
            'table_status'
        ];

        eventTypes.forEach(eventType => {
            this.eventSource.addEventListener(eventType, (e) => {
                this._handleEvent(e);
            });
        });
    }

    /**
     * Подключение через Long Polling
     */
    _connectPolling() {
        this.connected = true;
        this._emit('connected', { method: 'polling' });
        this._poll();
    }

    /**
     * Long polling цикл
     */
    async _poll() {
        if (!this.connected) return;

        try {
            const url = new URL(`${this.baseUrl}/realtime/poll`);
            url.searchParams.set('channels', this.channels.join(','));
            url.searchParams.set('restaurant_id', this.restaurantId);
            url.searchParams.set('last_id', this.lastEventId);
            url.searchParams.set('timeout', '20');

            const response = await fetch(url.toString());
            const result = await response.json();

            if (result.success && result.data.events) {
                result.data.events.forEach(event => {
                    this._processEvent(event);
                });
                this.lastEventId = result.data.last_id;
            }

            this.reconnectAttempts = 0;
            
            // Продолжаем polling
            if (this.connected) {
                setTimeout(() => this._poll(), 100);
            }
        } catch (error) {
            this._log('Polling error:', error);
            this._emit('error', { error });
            this._scheduleReconnect();
        }
    }

    /**
     * Обработка SSE события
     */
    _handleEvent(e) {
        try {
            const data = JSON.parse(e.data);
            this.lastEventId = data.id || this.lastEventId;
            this._processEvent(data);
        } catch (error) {
            this._log('Event parse error:', error, e.data);
        }
    }

    /**
     * Обработка события
     */
    _processEvent(event) {
        this._log('Event received:', event);

        // Воспроизводим звук если указан
        if (event.data?.sound) {
            this.playSound(event.data.sound);
        }

        // Отправляем в общий обработчик
        this._emit('event', event);

        // Отправляем в обработчик канала
        this._emit(`channel:${event.channel}`, event);

        // Отправляем в обработчик конкретного типа события
        this._emit(event.event, event.data || {});
    }

    /**
     * Запланировать переподключение
     */
    _scheduleReconnect(delay = null) {
        if (this.reconnectTimer) {
            clearTimeout(this.reconnectTimer);
        }

        const reconnectDelay = delay || Math.min(
            this.reconnectDelay * Math.pow(2, this.reconnectAttempts),
            this.maxReconnectDelay
        );

        this._log(`Reconnecting in ${reconnectDelay}ms (attempt ${this.reconnectAttempts + 1})`);

        this.reconnectTimer = setTimeout(() => {
            this.reconnectAttempts++;
            this.connect();
        }, reconnectDelay);
    }

    /**
     * Отключиться
     */
    disconnect() {
        this.connected = false;
        
        if (this.eventSource) {
            this.eventSource.close();
            this.eventSource = null;
        }
        
        if (this.reconnectTimer) {
            clearTimeout(this.reconnectTimer);
            this.reconnectTimer = null;
        }

        this._emit('disconnected', { manual: true });
        this._log('Disconnected');
    }

    /**
     * Подписаться на событие
     */
    on(event, callback) {
        if (!this.listeners[event]) {
            this.listeners[event] = [];
        }
        this.listeners[event].push(callback);
        return this;
    }

    /**
     * Отписаться от события
     */
    off(event, callback) {
        if (this.listeners[event]) {
            this.listeners[event] = this.listeners[event].filter(cb => cb !== callback);
        }
        return this;
    }

    /**
     * Одноразовая подписка
     */
    once(event, callback) {
        const wrapper = (...args) => {
            this.off(event, wrapper);
            callback(...args);
        };
        return this.on(event, wrapper);
    }

    /**
     * Вызвать обработчики события
     */
    _emit(event, data) {
        if (this.listeners[event]) {
            this.listeners[event].forEach(callback => {
                try {
                    callback(data);
                } catch (error) {
                    console.error('Event handler error:', error);
                }
            });
        }
    }

    /**
     * Отправить событие на сервер
     */
    async send(channel, event, data = {}) {
        try {
            const response = await fetch(`${this.baseUrl}/realtime/send`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ channel, event, data }),
            });
            return await response.json();
        } catch (error) {
            this._log('Send error:', error);
            throw error;
        }
    }

    /**
     * Получить статус подключения
     */
    getStatus() {
        return {
            connected: this.connected,
            lastEventId: this.lastEventId,
            reconnectAttempts: this.reconnectAttempts,
            channels: this.channels,
        };
    }

    /**
     * Включить/выключить звук
     */
    setSoundEnabled(enabled) {
        this.soundEnabled = enabled;
    }

    /**
     * Логирование
     */
    _log(...args) {
        if (this.debug) {
            console.log('[PosLabRT]', ...args);
        }
    }
}

// Экспорт для использования как модуль или глобально
if (typeof module !== 'undefined' && module.exports) {
    module.exports = PosLabRealtime;
} else {
    window.PosLabRealtime = PosLabRealtime;
}
