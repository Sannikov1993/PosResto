import { createApp } from 'vue';
import { createPinia } from 'pinia';
import WaiterApp from './WaiterApp.vue';
import { registerCanDirective } from '@/shared/directives/can.js';
import '../../css/waiter.css';

const app = createApp(WaiterApp);
app.use(createPinia());

// Register v-can directive
registerCanDirective(app);

app.mount('#waiter-app');

if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {});
    });
}
