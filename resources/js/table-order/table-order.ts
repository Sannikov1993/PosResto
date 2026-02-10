import { createApp } from 'vue';
import TableOrderApp from './TableOrderApp.vue';
import '../../css/table-order.css';

const app = createApp(TableOrderApp);

// Provide initial data from Blade
const dataElement = document.getElementById('table-order-data');
if (dataElement) {
    const data = JSON.parse(dataElement.textContent || '{}');
    app.provide('initialData', data);
}

app.mount('#table-order-app');
