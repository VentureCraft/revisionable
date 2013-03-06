<?php namespace Venturecraft\Revisionable;

class Revision extends \Eloquent {


	public $table = 'revisions';


    public function revisionable()
    {
        return $this->morphTo();
    }

}