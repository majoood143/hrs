<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\Filament\StablePanelProvider;
use App\Providers\FilamentFileExplorerServiceProvider;
use App\Providers\MailConfigServiceProvider;

return [
    AppServiceProvider::class,
    FilamentFileExplorerServiceProvider::class,
    AdminPanelProvider::class,
    StablePanelProvider::class,
    MailConfigServiceProvider::class,
];
