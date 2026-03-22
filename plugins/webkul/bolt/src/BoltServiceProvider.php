<?php

namespace Webkul\Bolt;

use Filament\Panel;
use Webkul\PluginManager\Console\Commands\InstallCommand;
use Webkul\PluginManager\Console\Commands\UninstallCommand;
use Webkul\PluginManager\Package;
use Webkul\PluginManager\PackageServiceProvider;

class BoltServiceProvider extends PackageServiceProvider
{
    public static string $name = 'bolt';

    public static string $viewNamespace = 'bolt';

    public function configureCustomPackage(Package $package): void
    {
        $package->name(static::$name)
            ->hasViews()
            ->hasTranslations()
            ->hasMigrations([
                '2026_03_22_000001_create_bolt_pages_table',
                '2026_03_22_000002_create_bolt_forms_table',
                '2026_03_22_000003_create_bolt_form_submissions_table',
                '2026_03_22_000004_create_bolt_content_blocks_table',
                '2026_03_22_000005_create_bolt_media_table',
            ])
            ->runsMigrations()
            ->hasSettings([
            ])
            ->runsSettings()
            ->hasInstallCommand(function (InstallCommand $command) {
                $command
                    ->runsMigrations();
            })
            ->hasUninstallCommand(function (UninstallCommand $command) {})
            ->icon('heroicon-o-document-text');
    }

    public function packageRegistered(): void
    {
        Panel::configureUsing(function (Panel $panel): void {
            $panel->plugin(BoltPlugin::make());
        });
    }
}
