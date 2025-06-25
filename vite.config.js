import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
            ],
            refresh: true,
        }),
    ],
    server: {
        watch: {
            // Ignore these directories to reduce file watchers
            ignored: [
                '**/vendor/**',
                '**/node_modules/**',
                '**/public/**',
                '**/storage/**',
                '**/.git/**',
                '**/bootstrap/cache/**',
                '**/database/**',
                '**/tests/**',
                '**/*.php',  // Ignore PHP files since Vite only needs to watch frontend assets
            ]
        }
    }
});