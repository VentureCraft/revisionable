<?php

$config = ['namespace'  => 'Venturecraft\Revisionable',
           'as'         => Venturecraft\Revisionable\ServiceProvider::SHORT_NAME . '::',
           'prefix'     => config(Venturecraft\Revisionable\ServiceProvider::SHORT_NAME . '.route-prefix'),
           'middleware' => config(Venturecraft\Revisionable\ServiceProvider::SHORT_NAME . '.middleware')];

Route::group($config, function (){
	Route::get('{revisionable}/history', [
		'as'   => 'model-history',
		'uses' => 'Controller@modelHistory'
	]);
});