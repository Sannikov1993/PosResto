import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/pos/pos.js',
                'resources/js/kitchen/kitchen.js',
                'resources/js/waiter/waiter.js',
                'resources/js/table-order/table-order.js',
                'resources/js/backoffice/backoffice.js',
                'resources/js/floor-editor/floor-editor.js',
                'resources/js/courier/courier.js',
                'resources/js/reservations/reservations.js',
                'resources/js/admin/admin.js',
                'resources/js/guest-admin/guest-admin.js',
                'resources/js/realtime-monitor/realtime-monitor.js',
                'resources/js/guest-menu/guest-menu.js',
                'resources/js/home/home.js',
                'resources/js/tracking/app.js',
                'resources/js/cabinet/cabinet.js',
                'resources/js/register/register.js',
                'resources/js/register-tenant/register-tenant.js',
                'resources/js/order-board/order-board.js'
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
    ],
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
