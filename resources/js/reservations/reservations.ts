import { createApp } from 'vue';
import { createPinia } from 'pinia';
import ReservationsApp from './ReservationsApp.vue';
import '../../css/app.css';

const app = createApp(ReservationsApp);
app.use(createPinia());
app.mount('#app');
