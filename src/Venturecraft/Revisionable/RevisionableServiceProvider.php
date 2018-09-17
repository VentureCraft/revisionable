<?php

namespace Venturecraft\Revisionable;
use Illuminate\Support\ServiceProvider;

class RevisionableServiceProvider extends ServiceProvider
{

    /**
     * Bootstrap the application events.
     */
    public function boot()
    {
        $this->publishes([
            __DIR__.'/../../../config/revisionable.php' => config_path('revisionable.php'),
        ], 'config');
    }

    /**
     * Register the service provider.
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../../../config/revisionable.php', 'revisionable');
    }
}