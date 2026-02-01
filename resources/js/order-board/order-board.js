import { createApp } from 'vue';
import { createPinia } from 'pinia';
import OrderBoardApp from './OrderBoardApp.vue';
import '../../css/order-board.css';

const app = createApp(OrderBoardApp);
app.use(createPinia());
app.mount('#order-board-app');
