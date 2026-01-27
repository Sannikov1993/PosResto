import { createApp } from 'vue';
import { createPinia } from 'pinia';
import AdminApp from './AdminApp.vue';
import '../../css/app.css';

const app = createApp(AdminApp);
app.use(createPinia());
app.mount('#app');
