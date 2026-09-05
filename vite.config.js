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
            refresh: true,
        }),
        tailwindcss(),
>>>>>>> 9019a60 (Baseline before Filament v4 upgrade)
    ],
});
