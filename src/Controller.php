<?php

namespace Venturecraft\Revisionable;

use App\Http\Controllers\Controller as BaseController;
use Illuminate\Support\Str;

class Controller extends BaseController{
	//
	public function modelHistory($revisionable){
		return view(ServiceProvider::SHORT_NAME . '::history')->withModel($revisionable);
	}

}
