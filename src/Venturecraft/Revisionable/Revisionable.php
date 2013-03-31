<?php namespace Venturecraft\Revisionable;

/*
 * This file is part of the Revisionable package by Venture Craft
 *
 * (c) Venture Craft <http://www.venturecraft.com.au>
 *
 */

use Illuminate\Support\ServiceProvider;

class Revisionable extends \Eloquent {

	private $original_data;
    private $updated_data;
    private $updating;

    protected $revisionEnabled = true;

    public function __construct(array $attributes = array())
    {
        $this->createEventListener();
    }

    public function revisionHistory()
    {
        return $this->morphMany('\Venturecraft\Revisionable\Revision', 'revisionable');
    }


    /**
     * Create the event listeners for the saving and saved events
     * This lets us save revisions whenever a save is made, no matter the
     * http method.
     *
     */
    private function createEventListener()
    {

        \Event::listen('eloquent.saving: '.get_called_class(), function()
        {
            return $this->beforeSave();
        });
        \Event::listen('eloquent.saved: '.get_called_class(), function()
        {
            return $this->afterSave();
        });

    }


    /**
     * Invoked before a model is saved. Return false to abort the operation.
     *
     * @param bool    $forced Indicates whether the model is being saved forcefully
     * @return bool
     */
    protected function beforeSave()
    {

        if ($this->revisionEnabled) {
        	$this->original_data 	= $this->original;
        	$this->updated_data 	= $this->attributes;

            // we can only safely compare basic items,
            // so for now we drop any object based items, like DateTime
            foreach ($this->updated_data as $key => $val) {
                if (gettype($val) == 'object') {
                    unset($this->original_data[$key]);
                    unset($this->updated_data[$key]);
                }
            }

            $this->updating         = $this->exists;
        }

    }




    /**
     * Called after a model is successfully saved.
     *
     * @param bool    $success Indicates whether the database save operation succeeded
     * @param bool    $forced  Indicates whether the model is being saved forcefully
     * @return void
     */
    protected function afterSave()
    {
    	// check if the model already exists
		if($this->revisionEnabled AND $this->updating) {
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

    }


}
