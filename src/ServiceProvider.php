<?php
namespace Venturecraft\Revisionable;

use Illuminate\Support\ServiceProvider as BaseServiceProvider;

/**
 * Class ServiceProvider
 * @package FuquIo\LaravelCors
 */
class ServiceProvider extends BaseServiceProvider{
	CONST VENDOR_PATH = 'venturecraft/revisionable';
	CONST SHORT_NAME = 'revisionable';

	/**
	 * Bootstrap the application services.
	 *
	 * @return void
	 */
	public function boot(){
		$this->loadMigrationsFrom(__DIR__ . '/../migrations');
	}

	/**
	 * Register the application services.
	 *
	 * @return void
	 */
	public function register(){

	}

}