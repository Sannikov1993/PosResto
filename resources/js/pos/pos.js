/**
 * MenuLab POS - Vue Application Entry Point
 */

import { createApp } from 'vue';
import { createPinia } from 'pinia';
import App from './App.vue';
import { registerCanDirective } from '@/shared/directives/can.js';
import { migrateFromLegacy } from '@/shared/services/auth.js';

// Styles
import '../../css/pos.css';

// Migrate legacy auth to unified storage (SSO support)
migrateFromLegacy();

// Create Vue app
const app = createApp(App);

// Install Pinia
const pinia = createPinia();
app.use(pinia);

// Register v-can directive
registerCanDirective(app);

// Mount app
app.mount('#pos-app');
