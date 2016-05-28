<?php

namespace Venturecraft\Revisionable;

use Illuminate\Support\ServiceProvider;

class RevisionableServiceProvider extends ServiceProvider
{
	public function boot()
	{
		$this->publishes([
			__DIR__ . '/../../../database/migrations/' => database_path('/migrations')
		], 'migrations');
	}

	public function register()
	{
		
	}
}
