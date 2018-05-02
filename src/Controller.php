<?php

namespace Venturecraft\Revisionable;

use App\Http\Controllers\Controller as BaseController;

class Controller extends BaseController{
	//
	public function modelHistory($revisionable){
		$parts = explode('-', $revisionable);
		$parts = array_map(function ($item){ return studly_case($item); }, $parts);
		$key   = array_pop($parts);
		$model = implode('\\', $parts);

		$model = $model::find($key);

		return view(ServiceProvider::SHORT_NAME . '::history')->withModel($model);
	}

}
