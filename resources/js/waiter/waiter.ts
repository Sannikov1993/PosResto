/**
 * Waiter App - Entry Point (TypeScript)
 */

import { createApp } from 'vue';
import { createPinia } from 'pinia';
import WaiterApp from './WaiterAppNew.vue';

// Create Vue app
const app = createApp(WaiterApp);

// Install Pinia
const pinia = createPinia();
app.use(pinia);

// Mount app
app.mount('#waiter-app');
