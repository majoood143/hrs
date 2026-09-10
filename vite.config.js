<<<<<<< HEAD
import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";
=======
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
>>>>>>> 9019a60 (Baseline before Filament v4 upgrade)

export default defineConfig({
    plugins: [
        laravel({
<<<<<<< HEAD
<<<<<<< HEAD
            input: [
                "resources/css/app.css",
                "resources/css/admin.css",
                "resources/js/app.js",
                "resources/js/admin/login.js",
            ],
            refresh: true,
        }),
=======
            input: ['resources/css/app.css', 'resources/js/app.js'],
=======
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/css/site.css',
                'resources/js/site.js',
            ],
>>>>>>> bbd33618 (Add transfer board creation and listing views)
            refresh: true,
        }),
        tailwindcss(),
>>>>>>> 9019a60 (Baseline before Filament v4 upgrade)
    ],
});
