<template>
    <div class="live-tracking">
        <!-- Карта -->
        <div ref="mapContainer" class="map-container"></div>

        <!-- Оверлей со статусом -->
        <div class="status-overlay">
            <!-- Карточка статуса -->
            <div class="status-card">
                <div class="status-icon" :style="{ background: statusColor }">
                    <span class="status-emoji">{{ statusIcon }}</span>
                </div>
                <div class="status-info">
                    <h3>{{ statusLabel }}</h3>
                    <p v-if="eta && !isCompleted" class="eta">
                        {{ eta.label }} ({{ eta.distance_km }} км)
                    </p>
                    <p v-if="isCompleted && !isCancelled" class="completed-text">
                        Заказ доставлен
                    </p>
                    <p v-if="isCancelled" class="cancelled-text">
                        Заказ отменён
                    </p>
                </div>
            </div>

            <!-- Карточка курьера -->
            <div v-if="courier && status === 'delivering'" class="courier-card">
                <div class="courier-avatar">
                    <span>{{ courierInitials }}</span>
                </div>
                <div class="courier-info">
                    <span class="courier-name">{{ courier.name }}</span>
                    <a v-if="courier.phone" :href="'tel:' + courier.phone" class="courier-phone">
                        Позвонить
                    </a>
                </div>
            </div>
        </div>

        <!-- Индикатор соединения -->
        <div class="connection-status" :class="{ connected: isConnected, disconnected: !isConnected }">
            <span v-if="isConnected">Онлайн</span>
            <span v-else>Подключение...</span>
        </div>

        <!-- Кнопка обновления (fallback) -->
        <button v-if="!sseSupported" @click="loadData" class="refresh-btn">
            Обновить
        </button>
    </div>
</template>

<script>
export default {
    name: 'LiveTrackingMap',

    props: {
        trackingToken: {
            type: String,
            required: true,
        },
        initialData: {
            type: Object,
            default: null,
        },
    },

    data() {
        return {
            map: null,
            courierMarker: null,
            destinationMarker: null,
            restaurantMarker: null,
            routeLine: null,

            status: 'new',
            statusLabel: 'Загрузка...',
            courier: null,
            eta: null,
            deliveryAddress: null,
            restaurant: null,
            isCompleted: false,
            isCancelled: false,

            isConnected: false,
            eventSource: null,
            reconnectTimer: null,
            pollInterval: null,
            sseSupported: typeof EventSource !== 'undefined',
        };
    },

    computed: {
        statusColor() {
            const colors = {
                'new': '#3B82F6',
                'confirmed': '#3B82F6',
                'cooking': '#F59E0B',
                'ready': '#10B981',
                'delivering': '#8B5CF6',
                'completed': '#6B7280',
                'cancelled': '#EF4444',
            };
            return colors[this.status] || '#6B7280';
        },

        statusIcon() {
            const icons = {
                'new': '📋',
                'confirmed': '✓',
                'cooking': '👨‍🍳',
                'ready': '✅',
                'delivering': '🚗',
                'completed': '🎉',
                'cancelled': '❌',
            };
            return icons[this.status] || '📦';
        },

        courierInitials() {
            if (!this.courier?.name) return '?';
            return this.courier.name
                .split(' ')
                .map(n => n[0])
                .join('')
                .toUpperCase()
                .substring(0, 2);
        },
    },

    mounted() {
        this.initMap();
        this.loadData();

        if (this.sseSupported) {
            this.connectSSE();
        } else {
            this.startPolling();
        }
    },

    beforeUnmount() {
        this.disconnectSSE();
        this.stopPolling();
        if (this.map) {
            this.map.destroy();
        }
    },

    methods: {
        async initMap() {
            await this.waitForYMaps();

            const defaultCenter = [55.751244, 37.618423]; // Москва

            this.map = new ymaps.Map(this.$refs.mapContainer, {
                center: defaultCenter,
                zoom: 14,
                controls: ['zoomControl'],
            });
        },

        waitForYMaps() {
            return new Promise((resolve) => {
                if (window.ymaps && window.ymaps.ready) {
                    ymaps.ready(resolve);
                } else {
                    const check = setInterval(() => {
                        if (window.ymaps && window.ymaps.ready) {
                            clearInterval(check);
                            ymaps.ready(resolve);
                        }
                    }, 100);

                    // Timeout после 10 секунд
                    setTimeout(() => {
                        clearInterval(check);
                        resolve();
                    }, 10000);
                }
            });
        },

        async loadData() {
            try {
                const response = await fetch(`/api/tracking/${this.trackingToken}/data`);
                const result = await response.json();

                if (result.success) {
                    this.updateState(result.data);
                }
            } catch (error) {
                console.error('Failed to load tracking data:', error);
            }
        },

        connectSSE() {
            if (this.eventSource) {
                this.eventSource.close();
            }

            const url = `/api/tracking/${this.trackingToken}/stream`;
            this.eventSource = new EventSource(url);

            this.eventSource.onopen = () => {
                this.isConnected = true;
                clearTimeout(this.reconnectTimer);
            };

            this.eventSource.addEventListener('connected', () => {
                this.isConnected = true;
            });

            this.eventSource.addEventListener('courier_location', (event) => {
                const data = JSON.parse(event.data);
                this.updateCourierPosition(data.location, data.eta);
            });

            this.eventSource.addEventListener('status_changed', (event) => {
                const data = JSON.parse(event.data);
                this.status = data.status;
                this.statusLabel = data.status_label;
                this.isCompleted = data.is_completed || false;
                this.isCancelled = data.status === 'cancelled';
            });

            this.eventSource.addEventListener('heartbeat', () => {
                this.isConnected = true;
            });

            this.eventSource.addEventListener('reconnect', () => {
                this.isConnected = false;
                this.scheduleReconnect();
            });

            this.eventSource.onerror = () => {
                this.isConnected = false;
                this.scheduleReconnect();
            };
        },

        disconnectSSE() {
            if (this.eventSource) {
                this.eventSource.close();
                this.eventSource = null;
            }
            clearTimeout(this.reconnectTimer);
        },

        scheduleReconnect() {
            clearTimeout(this.reconnectTimer);
            this.reconnectTimer = setTimeout(() => {
                if (!this.isCompleted) {
                    this.connectSSE();
                }
            }, 3000);
        },

        startPolling() {
            this.pollInterval = setInterval(() => {
                if (!this.isCompleted) {
                    this.loadData();
                }
            }, 5000);
        },

        stopPolling() {
            if (this.pollInterval) {
                clearInterval(this.pollInterval);
                this.pollInterval = null;
            }
        },

        updateState(data) {
            this.status = data.status;
            this.statusLabel = data.status_label;
            this.courier = data.courier;
            this.eta = data.eta;
            this.deliveryAddress = data.delivery_address;
            this.restaurant = data.restaurant;
            this.isCompleted = data.is_completed || false;
            this.isCancelled = data.is_cancelled || false;

            this.updateMapMarkers();
        },

        updateMapMarkers() {
            if (!this.map) return;

            // Маркер адреса доставки
            if (this.deliveryAddress?.lat && this.deliveryAddress?.lng) {
                const coords = [this.deliveryAddress.lat, this.deliveryAddress.lng];

                if (!this.destinationMarker) {
                    this.destinationMarker = new ymaps.Placemark(coords, {
                        hintContent: 'Адрес доставки',
                        balloonContent: this.deliveryAddress.formatted || 'Адрес доставки',
                    }, {
                        preset: 'islands#redHomeIcon',
                    });
                    this.map.geoObjects.add(this.destinationMarker);
                } else {
                    this.destinationMarker.geometry.setCoordinates(coords);
                }
            }

            // Маркер ресторана
            if (this.restaurant?.lat && this.restaurant?.lng) {
                const coords = [this.restaurant.lat, this.restaurant.lng];

                if (!this.restaurantMarker) {
                    this.restaurantMarker = new ymaps.Placemark(coords, {
                        hintContent: 'Ресторан',
                    }, {
                        preset: 'islands#blueFoodIcon',
                    });
                    this.map.geoObjects.add(this.restaurantMarker);
                }
            }

            // Маркер курьера
            if (this.courier?.location) {
                this.updateCourierPosition(this.courier.location, this.eta);
            }

            this.fitMapBounds();
        },

        updateCourierPosition(location, eta) {
            if (!this.map || !location) return;

            const coords = [location.lat, location.lng];

            if (!this.courierMarker) {
                this.courierMarker = new ymaps.Placemark(coords, {
                    hintContent: 'Курьер',
                }, {
                    preset: 'islands#violetCircleDotIcon',
                });
                this.map.geoObjects.add(this.courierMarker);
            } else {
                // Плавная анимация перемещения
                this.animateMarker(this.courierMarker, coords);
            }

            if (eta) {
                this.eta = eta;
            }

            // Обновляем линию маршрута
            this.updateRouteLine(coords);
        },

        animateMarker(marker, newCoords) {
            const currentCoords = marker.geometry.getCoordinates();
            const steps = 20;
            const duration = 500; // ms
            const stepTime = duration / steps;

            const latStep = (newCoords[0] - currentCoords[0]) / steps;
            const lngStep = (newCoords[1] - currentCoords[1]) / steps;

            let step = 0;
            const animate = () => {
                if (step < steps) {
                    step++;
                    marker.geometry.setCoordinates([
                        currentCoords[0] + latStep * step,
                        currentCoords[1] + lngStep * step,
                    ]);
                    setTimeout(animate, stepTime);
                }
            };
            animate();
        },

        updateRouteLine(courierCoords) {
            if (!this.destinationMarker) return;

            const destCoords = this.destinationMarker.geometry.getCoordinates();

            if (this.routeLine) {
                this.map.geoObjects.remove(this.routeLine);
            }

            this.routeLine = new ymaps.Polyline(
                [courierCoords, destCoords],
                {},
                {
                    strokeColor: '#8B5CF6',
                    strokeWidth: 4,
                    strokeStyle: 'shortdash',
                    strokeOpacity: 0.7,
                }
            );
            this.map.geoObjects.add(this.routeLine);
        },

        fitMapBounds() {
            const points = [];

            if (this.courierMarker) {
                points.push(this.courierMarker.geometry.getCoordinates());
            }
            if (this.destinationMarker) {
                points.push(this.destinationMarker.geometry.getCoordinates());
            }
            if (this.restaurantMarker && !this.courierMarker) {
                points.push(this.restaurantMarker.geometry.getCoordinates());
            }

            if (points.length > 1) {
                this.map.setBounds(ymaps.util.bounds.fromPoints(points), {
                    checkZoomRange: true,
                    zoomMargin: 60,
                });
            } else if (points.length === 1) {
                this.map.setCenter(points[0], 15);
            }
        },
    },
};
</script>

<style scoped>
.live-tracking {
    position: relative;
    height: 100vh;
    width: 100%;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.map-container {
    height: 100%;
    width: 100%;
}

.status-overlay {
    position: absolute;
    bottom: 20px;
    left: 16px;
    right: 16px;
    display: flex;
    flex-direction: column;
    gap: 12px;
    z-index: 100;
}

.status-card,
.courier-card {
    background: white;
    border-radius: 16px;
    padding: 16px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
    display: flex;
    align-items: center;
    gap: 16px;
}

.status-icon {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.status-emoji {
    font-size: 24px;
}

.status-info {
    flex: 1;
    min-width: 0;
}

.status-info h3 {
    font-size: 18px;
    font-weight: 600;
    margin: 0;
    color: #1f2937;
}

.eta {
    margin: 4px 0 0;
    color: #059669;
    font-size: 14px;
    font-weight: 500;
}

.completed-text {
    margin: 4px 0 0;
    color: #6B7280;
    font-size: 14px;
}

.cancelled-text {
    margin: 4px 0 0;
    color: #EF4444;
    font-size: 14px;
}

.courier-avatar {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: linear-gradient(135deg, #8B5CF6, #6366F1);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 16px;
    flex-shrink: 0;
}

.courier-info {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.courier-name {
    font-weight: 600;
    color: #1f2937;
    font-size: 15px;
}

.courier-phone {
    color: #8B5CF6;
    text-decoration: none;
    font-size: 14px;
    font-weight: 500;
}

.courier-phone:hover {
    text-decoration: underline;
}

.connection-status {
    position: absolute;
    top: 16px;
    right: 16px;
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
    z-index: 100;
}

.connection-status.connected {
    background: rgba(209, 250, 229, 0.95);
    color: #059669;
}

.connection-status.disconnected {
    background: rgba(254, 243, 199, 0.95);
    color: #D97706;
}

.refresh-btn {
    position: absolute;
    top: 16px;
    left: 16px;
    padding: 10px 20px;
    background: white;
    border: none;
    border-radius: 20px;
    font-size: 14px;
    font-weight: 500;
    color: #374151;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    cursor: pointer;
    z-index: 100;
}

.refresh-btn:hover {
    background: #F3F4F6;
}

.refresh-btn:active {
    transform: scale(0.98);
}

@media (max-width: 480px) {
    .status-overlay {
        bottom: 16px;
        left: 12px;
        right: 12px;
    }

    .status-card,
    .courier-card {
        padding: 14px;
        border-radius: 14px;
    }

    .status-icon {
        width: 44px;
        height: 44px;
    }

    .status-emoji {
        font-size: 20px;
    }

    .status-info h3 {
        font-size: 16px;
    }
}
</style>
