<?php

namespace Modules\Dashboard\Providers;

use Filament\Panel;
use Modules\Dashboard\DashboardPlugin;
use Nwidart\Modules\Support\ModuleServiceProvider;

class DashboardServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Dashboard';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'dashboard';

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    public function register(): void
    {
        parent::register();

        Panel::configureUsing(function (Panel $panel): void {
            if ($panel->getId() !== 'admin') {
                return;
            }

            $panel->plugin(DashboardPlugin::make());
        });
    }
}
