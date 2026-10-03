<?php

use App\Providers\AppServiceProvider;
use App\Providers\DemoServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\HorizonServiceProvider;

return [
    AppServiceProvider::class,
    AdminPanelProvider::class,
    HorizonServiceProvider::class,
    DemoServiceProvider::class,
];
