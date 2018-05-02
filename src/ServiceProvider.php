<?php

namespace Venturecraft\Revisionable;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator as BaseValidator;
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
		$this->publishes([__DIR__ . '/../config/main.php' => config_path(SELF::SHORT_NAME . '.php')]);
		$this->mergeConfigFrom(
			__DIR__ . '/../config/main.php', SELF::SHORT_NAME
		);

		$this->loadViewsFrom(__DIR__ . '/../resources/views', self::SHORT_NAME);
		$this->publishes([__DIR__ . '/../resources/views' => resource_path('views/vendor/' . self::SHORT_NAME)], 'views');

		$this->loadRoutesFrom(__DIR__ . '/../routes/main.php');

		$this->loadTranslationsFrom(__DIR__ . '/../resources/lang', self::SHORT_NAME);
		$this->publishes([__DIR__ . '/../resources/lang' => resource_path('lang/vendor/' . self::SHORT_NAME)], 'lang');

	}

	/**
	 * Register the application services.
	 *
	 * @return void
	 */
	public function register(){

	}

}