import { createApp } from 'vue';
import { createPinia } from 'pinia';
import WaiterApp from './WaiterApp.vue';
import '../../css/waiter.css';

const app = createApp(WaiterApp);
app.use(createPinia());
app.mount('#waiter-app');

if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {});
    });
}
