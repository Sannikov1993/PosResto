import { createApp } from 'vue';
import { createPinia } from 'pinia';
import BackOfficeApp from './BackOfficeApp.vue';
import '../../css/backoffice.css';

const app = createApp(BackOfficeApp);
const pinia = createPinia();

app.use(pinia);
app.mount('#backoffice-app');
