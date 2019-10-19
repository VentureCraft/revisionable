<?php

namespace Venturecraft\Revisionable;

use App\Http\Controllers\Controller as BaseController;
use Illuminate\Support\Str;

class Controller extends BaseController{
	//
	public function modelHistory($revisionable){
		$parts = explode('-', $revisionable);
		$parts = array_map(function ($item){ return Str::studly($item); }, $parts);
		$key   = array_pop($parts);
		$model = implode('\\', $parts);

		$model = $model::findOrFail($key);

		return view(ServiceProvider::SHORT_NAME . '::history')->withModel($model);
	}

}
