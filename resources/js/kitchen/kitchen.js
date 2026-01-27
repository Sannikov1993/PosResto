import { createApp } from 'vue';
import { createPinia } from 'pinia';
import KitchenApp from './KitchenApp.vue';
import '../../css/kitchen.css';

const app = createApp(KitchenApp);
app.use(createPinia());
app.mount('#kitchen-app');
