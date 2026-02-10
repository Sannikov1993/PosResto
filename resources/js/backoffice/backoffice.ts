import { createApp } from 'vue';
import { createPinia } from 'pinia';
import BackOfficeApp from './BackOfficeApp.vue';
import { registerCanDirective } from '@/shared/directives/can.js';
import { migrateFromLegacy } from '@/shared/services/auth.js';
import '../../css/backoffice.css';

// Migrate legacy auth to unified storage (SSO support)
migrateFromLegacy();

const app = createApp(BackOfficeApp);
const pinia = createPinia();

app.use(pinia);

// Register v-can directive
registerCanDirective(app);

app.mount('#backoffice-app');
