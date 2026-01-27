/**
 * Live Tracking Vue Application
 *
 * Приложение для отслеживания курьера в реальном времени
 */

import { createApp } from 'vue';
import LiveTrackingMap from './components/LiveTrackingMap.vue';

// Создаём приложение когда DOM готов
document.addEventListener('DOMContentLoaded', () => {
    const mountPoint = document.getElementById('tracking-app');

    if (mountPoint) {
        const app = createApp(LiveTrackingMap, {
            trackingToken: mountPoint.dataset.token || '',
            initialData: JSON.parse(mountPoint.dataset.initial || 'null'),
        });

        app.mount('#tracking-app');
    }
});
