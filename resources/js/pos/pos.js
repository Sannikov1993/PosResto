/**
 * MenuLab POS - Vue Application Entry Point
 */

import { createApp } from 'vue';
import { createPinia } from 'pinia';
import App from './App.vue';

// Styles
import '../../css/pos.css';

// Create Vue app
const app = createApp(App);

// Install Pinia
const pinia = createPinia();
app.use(pinia);

// Mount app
app.mount('#pos-app');
