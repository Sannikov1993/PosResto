<template>
    <div class="delivery-map h-full flex">
        <!-- Карта -->
        <div ref="mapContainer" class="flex-1 relative">
            <!-- Загрузка -->
            <div v-if="loading" class="absolute inset-0 bg-[rgba(0,0,0,0.5)] flex items-center justify-center z-10">
                <div class="text-white text-lg">Загрузка карты...</div>
            </div>
        </div>

        <!-- Боковая панель -->
        <div class="w-80 bg-[rgba(255,255,255,0.03)] border-l border-[rgba(255,255,255,0.08)] flex flex-col overflow-hidden">
            <!-- Заголовок -->
            <div class="p-4 border-b border-[rgba(255,255,255,0.08)]">
                <h3 class="text-white font-semibold text-lg">Курьеры на карте</h3>
                <p class="text-[rgba(255,255,255,0.5)] text-sm mt-1">
                    {{ couriers.length }} активных
                </p>
            </div>

            <!-- Список курьеров -->
            <div class="flex-1 overflow-y-auto">
                <div
                    v-for="courier in couriers"
                    :key="courier.id"
                    @click="focusCourier(courier)"
                    class="p-4 border-b border-[rgba(255,255,255,0.06)] cursor-pointer transition-colors"
                    :class="selectedCourier?.id === courier.id ? 'bg-[rgba(59,130,246,0.15)]' : 'hover:bg-[rgba(255,255,255,0.04)]'"
                >
                    <div class="flex items-center gap-3">
                        <!-- Аватар со статусом -->
                        <div
                            class="w-10 h-10 rounded-full flex items-center justify-center text-white font-bold text-sm"
                            :class="getCourierStatusClass(courier.status)"
                        >
                            {{ getInitials(courier.name) }}
                        </div>

                        <!-- Информация -->
                        <div class="flex-1 min-w-0">
                            <p class="text-white font-medium truncate">{{ courier.name }}</p>
                            <p class="text-[rgba(255,255,255,0.5)] text-xs">
                                {{ getCourierStatusLabel(courier.status) }}
                                <span v-if="courier.active_orders > 0">
                                    · {{ courier.active_orders }} заказ(а)
                                </span>
                            </p>
                        </div>

                        <!-- Транспорт -->
                        <div class="text-lg" :title="courier.transport">
                            {{ getTransportIcon(courier.transport) }}
                        </div>
                    </div>
                </div>

                <!-- Пусто -->
                <div v-if="couriers.length === 0" class="p-8 text-center">
                    <div class="text-4xl mb-2">🚗</div>
                    <p class="text-[rgba(255,255,255,0.5)]">Нет активных курьеров</p>
                </div>
            </div>

            <!-- Легенда -->
            <div class="p-4 border-t border-[rgba(255,255,255,0.08)]">
                <p class="text-[rgba(255,255,255,0.5)] text-xs mb-2">Легенда:</p>
                <div class="flex flex-wrap gap-3 text-xs">
                    <div class="flex items-center gap-1">
                        <div class="w-3 h-3 rounded-full bg-green-500"></div>
                        <span class="text-[rgba(255,255,255,0.7)]">Свободен</span>
                    </div>
                    <div class="flex items-center gap-1">
                        <div class="w-3 h-3 rounded-full bg-yellow-500"></div>
                        <span class="text-[rgba(255,255,255,0.7)]">Занят</span>
                    </div>
                    <div class="flex items-center gap-1">
                        <div class="w-3 h-3 rounded-full bg-red-500"></div>
                        <span class="text-[rgba(255,255,255,0.7)]">Заказ</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import api from '../../api';

const props = defineProps({
    // Deprecated: используем api.delivery.getMapData()
    apiUrl: {
        type: String,
        default: '/api/delivery/map-data'
    }
});

const emit = defineEmits(['select-order', 'select-courier']);

// Refs
const mapContainer = ref(null);
const map = ref(null);
const loading = ref(true);

// Data
const couriers = ref([]);
const orders = ref([]);
const zones = ref([]);
const restaurant = ref(null);
const selectedCourier = ref(null);

// Markers
const courierMarkers = ref({});
const orderMarkers = ref({});
const restaurantMarker = ref(null);
const zonePolygons = ref([]);
const routeLines = ref([]);

// Polling
let pollInterval = null;

// Init
onMounted(() => {
    initMap();
    loadData();
    startPolling();
});

onUnmounted(() => {
    stopPolling();
    if (map.value) {
        map.value.destroy();
    }
});

// Инициализация карты
function initMap() {
    if (!window.ymaps) {
        console.error('Yandex Maps API not loaded');
        loading.value = false;
        return;
    }

    window.ymaps.ready(() => {
        const defaultCenter = [55.7558, 37.6173]; // Москва по умолчанию

        map.value = new window.ymaps.Map(mapContainer.value, {
            center: defaultCenter,
            zoom: 12,
            controls: ['zoomControl', 'fullscreenControl'],
        });

        loading.value = false;
    });
}

// Загрузка данных
async function loadData() {
    try {
        const result = await api.delivery.getMapData();
        couriers.value = result?.couriers || [];
        orders.value = result?.orders || [];
        zones.value = result?.zones || [];
        restaurant.value = result?.restaurant;
        updateMapObjects();
    } catch (error) {
        console.error('Error loading map data:', error);
    }
}

// Обновление объектов на карте
function updateMapObjects() {
    if (!map.value) return;

    // Ресторан
    updateRestaurantMarker();

    // Зоны
    updateZones();

    // Курьеры
    updateCourierMarkers();

    // Заказы
    updateOrderMarkers();

    // Маршруты
    updateRouteLines();
}

// Маркер ресторана
function updateRestaurantMarker() {
    if (!restaurant.value) return;

    if (restaurantMarker.value) {
        map.value.geoObjects.remove(restaurantMarker.value);
    }

    restaurantMarker.value = new window.ymaps.Placemark(
        [restaurant.value.lat, restaurant.value.lng],
        {
            balloonContent: `<strong>${restaurant.value.name}</strong><br>Ресторан`,
            hintContent: restaurant.value.name,
        },
        {
            preset: 'islands#blueFoodIcon',
        }
    );

    map.value.geoObjects.add(restaurantMarker.value);
}

// Зоны доставки
function updateZones() {
    // Удаляем старые
    zonePolygons.value.forEach(p => map.value.geoObjects.remove(p));
    zonePolygons.value = [];

    zones.value.forEach(zone => {
        if (!zone.polygon || !Array.isArray(zone.polygon)) return;

        const polygon = new window.ymaps.Polygon(
            [zone.polygon],
            {
                balloonContent: `${zone.name}<br>От ${zone.min_distance} до ${zone.max_distance} км`,
                hintContent: zone.name,
            },
            {
                fillColor: zone.color + '20', // 20 = ~12% opacity
                strokeColor: zone.color,
                strokeWidth: 2,
                strokeOpacity: 0.5,
            }
        );

        map.value.geoObjects.add(polygon);
        zonePolygons.value.push(polygon);
    });
}

// Маркеры курьеров
function updateCourierMarkers() {
    // Обновляем существующие или создаём новые
    const currentIds = new Set(couriers.value.map(c => c.id));

    // Удаляем устаревшие
    Object.keys(courierMarkers.value).forEach(id => {
        if (!currentIds.has(parseInt(id))) {
            map.value.geoObjects.remove(courierMarkers.value[id]);
            delete courierMarkers.value[id];
        }
    });

    // Добавляем/обновляем
    couriers.value.forEach(courier => {
        const coords = [courier.lat, courier.lng];
        const color = courier.status === 'available' ? '#10B981' : '#F59E0B';
        const icon = getTransportIcon(courier.transport);

        if (courierMarkers.value[courier.id]) {
            // Обновляем позицию с анимацией
            courierMarkers.value[courier.id].geometry.setCoordinates(coords);
        } else {
            // Создаём новый
            const marker = new window.ymaps.Placemark(
                coords,
                {
                    balloonContent: `
                        <strong>${courier.name}</strong><br>
                        ${getCourierStatusLabel(courier.status)}<br>
                        Заказов: ${courier.active_orders}
                    `,
                    hintContent: `${courier.name} ${icon}`,
                    iconContent: icon,
                },
                {
                    preset: courier.status === 'available'
                        ? 'islands#greenCircleDotIcon'
                        : 'islands#yellowCircleDotIcon',
                }
            );

            marker.events.add('click', () => {
                selectedCourier.value = courier;
                emit('select-courier', courier);
            });

            map.value.geoObjects.add(marker);
            courierMarkers.value[courier.id] = marker;
        }
    });
}

// Маркеры заказов
function updateOrderMarkers() {
    const currentIds = new Set(orders.value.map(o => o.id));

    // Удаляем устаревшие
    Object.keys(orderMarkers.value).forEach(id => {
        if (!currentIds.has(parseInt(id))) {
            map.value.geoObjects.remove(orderMarkers.value[id]);
            delete orderMarkers.value[id];
        }
    });

    // Добавляем/обновляем
    orders.value.forEach(order => {
        const coords = [order.lat, order.lng];

        if (!orderMarkers.value[order.id]) {
            const marker = new window.ymaps.Placemark(
                coords,
                {
                    balloonContent: `
                        <strong>${order.order_number}</strong><br>
                        ${order.address}<br>
                        ${order.status_label}<br>
                        Сумма: ${order.total} ₽
                    `,
                    hintContent: order.order_number,
                },
                {
                    preset: 'islands#redDotIcon',
                }
            );

            marker.events.add('click', () => {
                emit('select-order', order);
            });

            map.value.geoObjects.add(marker);
            orderMarkers.value[order.id] = marker;
        }
    });
}

// Линии маршрутов
function updateRouteLines() {
    // Удаляем старые
    routeLines.value.forEach(line => map.value.geoObjects.remove(line));
    routeLines.value = [];

    // Рисуем линии от курьеров к их заказам
    couriers.value.forEach(courier => {
        if (courier.status !== 'busy') return;

        const courierOrders = orders.value.filter(o =>
            o.courier_id === courier.user_id &&
            ['picked_up', 'in_transit'].includes(o.status)
        );

        courierOrders.forEach(order => {
            const line = new window.ymaps.Polyline(
                [
                    [courier.lat, courier.lng],
                    [order.lat, order.lng]
                ],
                {},
                {
                    strokeColor: '#8B5CF6',
                    strokeWidth: 3,
                    strokeStyle: 'shortdash',
                    strokeOpacity: 0.7,
                }
            );

            map.value.geoObjects.add(line);
            routeLines.value.push(line);
        });
    });
}

// Фокус на курьере
function focusCourier(courier) {
    selectedCourier.value = courier;

    if (map.value && courier.lat && courier.lng) {
        map.value.setCenter([courier.lat, courier.lng], 15, {
            duration: 300,
        });
    }

    emit('select-courier', courier);
}

// Polling
function startPolling() {
    pollInterval = setInterval(loadData, 10000); // Каждые 10 сек
}

function stopPolling() {
    if (pollInterval) {
        clearInterval(pollInterval);
        pollInterval = null;
    }
}

// Helpers
function getInitials(name) {
    if (!name) return '?';
    return name.split(' ').map(n => n[0]).join('').toUpperCase().substring(0, 2);
}

function getCourierStatusClass(status) {
    const classes = {
        'available': 'bg-green-500',
        'busy': 'bg-yellow-500',
        'offline': 'bg-gray-500',
    };
    return classes[status] || 'bg-gray-500';
}

function getCourierStatusLabel(status) {
    const labels = {
        'available': 'Свободен',
        'busy': 'Занят',
        'offline': 'Оффлайн',
    };
    return labels[status] || status;
}

function getTransportIcon(transport) {
    const icons = {
        'car': '🚗',
        'bike': '🚴',
        'scooter': '🛵',
        'walk': '🚶',
    };
    return icons[transport] || '🚗';
}
</script>

<style scoped>
.delivery-map {
    background: #1a1a2e;
}
</style>
