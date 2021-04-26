<?php

namespace Fakhrani\Visitor;

use Illuminate\Support\ServiceProvider;

/**
 * Class VisitorServiceProvider.
 */
class VisitorServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            realpath(__DIR__.'/migrations') => base_path('/database/migrations'),
        ],
            'migrations');

        $this->publishes([
            __DIR__.'/config/visitor.php' => config_path('visitor.php'),
        ]);
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->registerBindings();

        $this->RegisterIp();

        $this->RegisterVisitor();

        $this->RegisterBooting();
    }

    public function RegisterVisitor()
    {
        $this->app->singleton('visitor', function ($app) {
            return new Visitor(
                $app['Fakhrani\Visitor\Storage\VisitorInterface'],
                $app['Fakhrani\Visitor\Services\Geo\GeoInterface'],
                $app['ip'],
                $app['Fakhrani\Visitor\Services\Cache\CacheInterface']

            );
        });

        $this->app->bind('Fakhrani\Visitor\Visitor', function ($app) {
            return $app['visitor'];
        });
    }

    public function RegisterIp()
    {
        $this->app->singleton('ip', function ($app) {
            return new Ip(
                $app->make('request'),
                [
                    $app->make('Fakhrani\Visitor\Services\Validation\Validator'),
                    $app->make('Fakhrani\Visitor\Services\Validation\Checker'),
                ]

            );
        });
    }

    public function registerBooting()
    {
        $this->app->booting(function () {
            $loader = \Illuminate\Foundation\AliasLoader::getInstance();
            $loader->alias('Visitor', 'Fakhrani\Visitor\Facades\VisitorFacade');
        });
    }

    protected function registerBindings()
    {
        $this->app->singleton(
            'Fakhrani\Visitor\Storage\VisitorInterface',
            'Fakhrani\Visitor\Storage\QbVisitorRepository'
        );

        $this->app->singleton(
            'Fakhrani\Visitor\Services\Geo\GeoInterface',
            'Fakhrani\Visitor\Services\Geo\MaxMind'
        );

        $this->app->singleton(
            'Fakhrani\Visitor\Services\Cache\CacheInterface',
            'Fakhrani\Visitor\Services\Cache\CacheClass'
        );
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return ['visitor'];
    }
}
