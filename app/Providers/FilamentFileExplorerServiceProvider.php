<?php

namespace App\Providers;

use Ardavan\FilamentFileExplorer\FilamentFileExplorerServiceProvider as BaseServiceProvider;
use Ardavan\FilamentFileExplorer\Livewire\FileExplorer;
use Livewire\Livewire;

class FilamentFileExplorerServiceProvider extends BaseServiceProvider
{
    /**
     * spatie/laravel-package-tools resolves the package's base directory (for its
     * config/migrations/translations/views) via ReflectionClass(get_class($this)).
     * Subclassing moves that to app/Providers, so it must be pinned back to the
     * vendor package's own directory.
     */
    protected function getPackageBaseDir(): string
    {
        return dirname((new \ReflectionClass(BaseServiceProvider::class))->getFileName());
    }

    /**
     * Vendor's packageBooted() calls Livewire::addNamespace(), a Livewire 4-only
     * method that doesn't exist on the Livewire 3 manager this app runs (Filament 4
     * pins livewire/livewire ^3.5). That call fatals on boot, so this override
     * replays the same registration minus that one line; the explicit
     * Livewire::component() calls below already register the component under both
     * name forms without needing the namespace API.
     */
    public function packageBooted(): void
    {
        $this->registerFilamentAssets();

        Livewire::component('filament-file-explorer::file-explorer', FileExplorer::class);
        Livewire::component('filament-file-explorer.file-explorer', FileExplorer::class);

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../../vendor/ardavan/filament-file-explorer/stubs' => base_path('stubs/filament-file-explorer'),
            ], 'filament-file-explorer-stubs');

            $this->publishes([
                __DIR__.'/../../vendor/ardavan/filament-file-explorer/resources/images' => public_path('vendor/filament-file-explorer'),
            ], 'filament-file-explorer-assets');
        }

        $this->registerRoutes();
    }
}
