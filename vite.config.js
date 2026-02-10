import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import compression from 'vite-plugin-compression';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/pos/pos.ts',
                'resources/js/kitchen/kitchen.ts',
                'resources/js/waiter/waiter.js',
                'resources/js/table-order/table-order.ts',
                'resources/js/backoffice/backoffice.ts',
                'resources/js/floor-editor/floor-editor.ts',
                'resources/js/courier/courier.ts',
                'resources/js/reservations/reservations.ts',
                'resources/js/admin/admin.ts',
                'resources/js/guest-admin/guest-admin.ts',
                'resources/js/realtime-monitor/realtime-monitor.ts',
                'resources/js/guest-menu/guest-menu.ts',
                'resources/js/home/home.ts',
                'resources/js/tracking/app.ts',
                'resources/js/cabinet/cabinet.ts',
                'resources/js/register/register.ts',
                'resources/js/register-tenant/register-tenant.ts',
                'resources/js/order-board/order-board.ts'
            ],
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        compression({
            algorithm: 'gzip',
            threshold: 1024,
        }),
    ],
    build: {
        chunkSizeWarningLimit: 500,
        rollupOptions: {
            output: {
                manualChunks: {
                    'vue-vendor': ['vue', 'pinia'],
                    'axios': ['axios'],
                    'echo-vendor': ['laravel-echo', 'pusher-js'],
                },
            },
        },
    },
    resolve: {
        alias: {
            '@': '/resources/js',
            '@pos': '/resources/js/pos',
        },
    },
    server: {
        host: '0.0.0.0',
        hmr: {
            host: 'localhost',
        },
        cors: true,
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
