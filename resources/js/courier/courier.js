import { createApp } from 'vue';
import { createPinia } from 'pinia';
import CourierApp from './CourierApp.vue';
import '../../css/app.css';

const app = createApp(CourierApp);
app.use(createPinia());
app.mount('#app');
