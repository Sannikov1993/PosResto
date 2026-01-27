import { createApp } from 'vue';
import { createPinia } from 'pinia';
import FloorEditorApp from './FloorEditorApp.vue';
import '../../css/app.css';

const app = createApp(FloorEditorApp);
app.use(createPinia());
app.mount('#app');
