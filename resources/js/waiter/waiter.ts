/**
 * Waiter App - Entry Point (TypeScript)
 */

import { createApp } from 'vue';
import { createPinia } from 'pinia';
import WaiterApp from './WaiterAppNew.vue';
import { registerCanDirective } from '@/shared/directives/can.js';

// Create Vue app
const app = createApp(WaiterApp);

// Install Pinia
const pinia = createPinia();
app.use(pinia);

// Register v-can directive
registerCanDirective(app);

// Mount app
app.mount('#waiter-app');
