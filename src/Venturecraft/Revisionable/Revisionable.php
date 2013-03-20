<?php namespace Venturecraft\Revisionable;

/*
 * This file is part of the Revisionable package by Venture Craft
 *
 * (c) Venture Craft <http://www.venturecraft.com.au>
 *
 */

use Illuminate\Support\ServiceProvider;
use LaravelBook\Ardent\Ardent;

class Revisionable extends Ardent {

	private $original_data;
    private $updated_data;
    private $updating;



    public function revisionHistory()
    {

        return $this->morphMany('\Venturecraft\Revisionable\Revision', 'revisionable');

    }



    /**
     * Invoked before a model is saved. Return false to abort the operation.
     *
     * Overriding Ardent method, Ardent version called once this is finished
     *
     * @param bool    $forced Indicates whether the model is being saved forcefully
     * @return bool
     */
    protected function beforeSave( $forced = false ) {

    	$this->original_data 	= $this->original;
    	$this->updated_data 	= $this->attributes;

        $this->updating         = $this->exists;

    	// call parent beforeSave from Ardent
        return parent::beforeSave( $forced );

    }




    /**
     * Called after a model is successfully saved.
	 *
     * Overriding Ardent method, Ardent version called once this is finished
     *
     * @param bool    $success Indicates whether the database save operation succeeded
     * @param bool    $forced  Indicates whether the model is being saved forcefully
     * @return void
     */
    protected function afterSave( $success, $forced = false ) {

    	// check if the model already exists
		if($success AND $this->updating) {
			// if it does, it means we're updating

			$changes = array_diff($this->updated_data, $this->original_data);

			foreach( $changes as $key => $change ) {

				$revision = new Revision();
				$revision->revisionable_type 	= get_class($this);
				$revision->revisionable_id 		= $this->id;
				$revision->key 					= $key;
				$revision->old_value			= $this->original_data[$key];
				$revision->new_value 			= $this->updated_data[$key];
				$revision->user_id 				= \Auth::user()->id;
				$revision->save();

			}

		}

    	// call parent beforeSave from Ardent
        return parent::afterSave( $success, $forced );

    }



}
