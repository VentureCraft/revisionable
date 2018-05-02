<?php

return [
	/*
	|--------------------------------------------------------------------------
	| Revision Model
	|--------------------------------------------------------------------------
	*/
	'model'        => Venturecraft\Revisionable\Revision::class,
	'route-prefix' => 'log',
	'middleware'   => ['web', 'auth'],
];
