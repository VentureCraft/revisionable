<?php namespace Venturecraft\Revisionable;

use Illuminate\Support\ServiceProvider;

class RevisionableServiceProvider extends ServiceProvider
{
    protected $defer = false;

    public function boot()
    {
        $this->publishes([
            __DIR__ . '/../../config/revisionable.php' => config_path('revisionable.php'),
        ], 'config');

        $this->publishes([
            __DIR__ . '/../../migrations/' => database_path('migrations'),
        ], 'migrations');
    }

    public function register()
    {

    }
}
